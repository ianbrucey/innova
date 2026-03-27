# Innova: Pre-Discovery Technical Blueprint

---

## 1. Core Operational Problem

> **Correction from 03-26-26 transcript:** The original problem statement attributed this to Sage X3 breaking integrations. Kim confirmed this is incorrect — Sage X3 is unrelated to e-commerce operations entirely. The real cause: Amazon began handling split fulfillments (shipping some items on an order while Innova ships the rest), and the original automation was never built for partial fulfillments.

Innova's e-commerce staff (Kim) is acting as human middleware between three systems: Shopify, Amazon Seller Central, and a local SQL Server database hosted on AWS. The trigger was Amazon's introduction of promotional split fulfillments — where Amazon ships some items and the Irvine warehouse ships the rest — which the existing automation could not handle.

**WebBee** is middleware already in production that pushes Shopify orders to Amazon for fulfillment. When Amazon doesn't carry a SKU or is out of stock, WebBee rejects that import and tags the Shopify order `"rejected by Amazon"`. WebBee is working and does not need to be replaced — the automation system we build inherits its output.

The daily manual process has two phases:

**Morning routing:** Kim identifies unfulfilled Shopify orders — those tagged "rejected by Amazon" or partially fulfilled with remaining items not covered by Amazon — and manually enters order numbers into a SQL UPDATE script to set `AdminOrderStatus = 5` (ready to ship), routing them to the Irvine warehouse.

**Evening sync (5:30 PM PST):** A process queries for orders with `AdminOrderStatus = 5` and a non-null FedEx tracking number, pushes those tracking numbers to Shopify, then updates status to `4` (complete).

The central technical flaw: **the status field lives on the order header, not the line item.** For split shipments — where Amazon handles some items and Irvine handles others — marking the entire order header as "ready to ship" gives the warehouse no instruction on *which specific items* to pick and pack.

**Volume:** ~25 warehouse shipments per day. Low volume — no high-performance infrastructure required. Simple, readable code over over-engineered solutions.

---

## 2. Proposed System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        External APIs                        │
│      Shopify Admin API          │        Amazon SP-API            │
└──────────────────┬──────────────────┴──────────────────┘
                   │
                   ▼
┌──────────────────────────────────────────────────────────────┐
│               Integration / Automation Layer                 │
│                                                              │
│  ┌─────────────────────┐   ┌──────────────────────────────┐  │
│  │ Split Fulfillment   │   │   Nightly Fulfillment Sync   │  │
│  │ Engine (scheduled)  │   │   Cron — 8:00 PM Pacific     │  │
│  │                     │   │                              │  │
│  │ - Polls Shopify     │   │ - Query: status=5 + tracking │  │
│  │ - Filters locally   │   │ - Push tracking → Shopify    │  │
│  │ - Queries SP-API    │   │ - Update status → 4          │  │
│  │ - Routes line items │   └──────────────────────────────┘  │
│  └──────────┬──────────┘                                     │
│             │                                                │
└─────────────┼────────────────────────────────────────────────┘
              │
              ▼
┌──────────────────────────────────────────────────────────────┐
│              AWS SQL Server (existing DB)                    │
│                                                              │
│  Order (header)  │  OrderLineItem (needs new status field)  │
│  OrderShipping   │  FedEx tracking (auto-populated by WMS)  │
└──────────────────────────────────────────────────────────────┘
              │
              ▼
┌──────────────────────────────────────────────────────────────┐
│              Warehouse Release UI (new)                      │
│                                                              │
│  - Replaces MS Access + manual SQL                          │
│  - Shows line items routed to Irvine (status = 5)           │
│  - Secure, role-based web interface                         │
└──────────────────────────────────────────────────────────────┘
```

**Data flow in plain terms:**
1. Fulfillment Engine runs on a schedule → fetches Shopify orders → cross-references Amazon SP-API → writes routing decisions to SQL at the **line-item level**
2. Warehouse staff opens the web UI → sees their pick list → WMS generates FedEx labels → tracking numbers auto-populate back to the DB (existing behavior, unchanged)
3. Nightly cron at 8 PM → finds status=5 + tracking → fulfills in Shopify → marks status=4

---

## 3. Recommended Tech Stack

Two viable options are presented below. **Option A is recommended** given primary Laravel expertise. Option B is the more technically principled split if the team is comfortable managing two runtimes.

---

### Option A: Pure Laravel (Recommended)

**Best for:** Laravel-primary developer, single codebase, fastest time to delivery.

#### Backend + Automation: **Laravel (PHP)**

Laravel natively handles all three requirements in a single framework: scheduled cron jobs (Artisan scheduler), queue-based async processing, REST API integration, and SQL Server via Eloquent ORM. No need to stitch together separate tools. Avoids tech sprawl for what is fundamentally a multi-API glue job with business logic and a CRUD UI.

- **Artisan Scheduler**: replaces fragile manual batch processes with managed cron entries (`php artisan schedule:run` from a single crontab)
- **Laravel Queues + Horizon**: handles Amazon SP-API calls asynchronously, rate-limit aware, with built-in retry logic and a real-time monitoring dashboard
- **Eloquent + SQL Server**: connects to existing AWS SQL Server via `sqlsrv` driver — no migration of underlying DB required

#### Queue Backend: **Redis** (or DB driver for v1 simplicity)

Amazon SP-API calls must be processed via queue workers to handle rate limiting gracefully. Redis is the standard Laravel queue backend. For v1, the database queue driver works with zero additional infrastructure and can be swapped for Redis later.

#### API Clients:
- **Shopify**: direct REST/GraphQL via Guzzle — mature, well-documented, no heavy package dependency needed
- **Amazon SP-API**: `jlevers/selling-partner-api` (PHP) — actively maintained, covers Orders and Fulfillment endpoints
- **TikTok**: Guzzle HTTP client — scope undefined, no library recommendation yet

#### Frontend (Warehouse UI): **Laravel Blade + Livewire**

For an internal warehouse pick-list UI, Livewire gives reactive interactions without a full SPA. Low overhead, ships fast. If the client wants a more polished feel, Inertia.js + Vue is the next step up without requiring a separate API layer.

#### Honest trade-off:
PHP's async story is weaker than Python or Node. Laravel's queue workers solve most of it, but if SP-API call concurrency ever becomes a bottleneck, you'll feel it. At Innova's order volumes (hundreds to low thousands per day), this will not be a problem in practice.

---

### Option B: Python Workers + Laravel UI (Split Stack)

**Best for:** Teams comfortable managing two runtimes. More principled architecture; higher operational overhead.

#### Automation Layer: **Python + Celery + Celery Beat**

| Component | Tool | Why |
|---|---|---|
| Worker runtime | Python 3.11+ | Native async I/O, best-in-class for API-heavy workers |
| Task queue | Celery | Battle-tested distributed task queue, purpose-built for this pattern |
| Scheduler | Celery Beat | Manages all cron schedules in code; more observable than crontab |
| HTTP client | `httpx` (async) | Concurrent SP-API + Shopify calls without threading complexity |
| Shopify SDK | `ShopifyAPI` (Python) | Official Python library |
| Amazon SP-API | `python-amazon-sp-api` | Well-maintained, covers all needed endpoints |
| DB access | SQLAlchemy + `pyodbc` | Connects to SQL Server; familiar ORM pattern |

The fulfillment engine and nightly sync run as Celery tasks. Celery Beat fires them on schedule. Workers process SP-API lookups concurrently without blocking.

#### Warehouse UI: **Laravel (PHP)**

Keep Laravel for the web application. It's the right tool for authenticated CRUD interfaces, and it's your strongest skill. The UI reads from the same SQL Server the Python workers write to — they share only the database, no API contract needed between them.

#### Shared Infrastructure:
- **Redis**: serves as Celery's broker AND Laravel's queue/cache backend
- **AWS SQL Server**: single shared database — both services read/write it
- **AWS**: Python workers on ECS or EC2; Laravel app on separate EC2/Beanstalk instance, same VPC

#### Honest trade-off:
Two runtimes, two deployments, two dependency trees to maintain. For a solo developer or small team, this is real overhead. The async advantage of Python is genuine but irrelevant at Innova's current order volumes. Only choose this path if you expect significant scale-up or if the TikTok/reconciliation work grows into heavy data processing.

---

### Stack Decision Summary

| Factor | Option A (Pure Laravel) | Option B (Python + Laravel) |
|---|---|---|
| Developer fit | Strong match | Requires Python comfort |
| Time to v1 | Faster | Slower (two environments) |
| Async I/O | Adequate (queues) | Superior (native async) |
| Operational complexity | Low | Moderate |
| Future analytics/TikTok | Manageable | Better positioned |
| Recommended for this project | **Yes** | If scale demands it |

---

### Database: **Existing AWS SQL Server** (both options)

The existing database is already live, connected to warehouse tooling, and has FedEx tracking auto-population working. Don't replace it — extend it. The critical change is adding line-item level fulfillment routing fields (see Section 4).

### Hosting: **AWS — inside the VPC (required)**

The SQL Server is in a private VPC, accessible only via VPN. It is not publicly reachable. The Laravel application and all cron workers **must be deployed inside the same VPC** — on EC2 or ECS within that network. A public-facing EC2 instance sitting outside the VPC will not work. Outbound connections from the VPC are permitted; inbound connections to the DB from outside are not.

---

## 4. Key Components to Build

### Component 1: Split Fulfillment Engine
The core logic service. Runs every 15 minutes via Artisan Scheduler.

**Logic:**
1. Pull Shopify orders: unfulfilled + partially fulfilled
2. For each order, identify line items where `requires_shipping = true` and routing is not yet determined
3. **Fast path**: if order is tagged `"rejected by Amazon"` (set by WebBee) → all line items route to Irvine, no SP-API call needed
4. **Split path**: for orders without the rejection tag, query Amazon SP-API to check if Amazon has the order
5. If Amazon returns order data → mark those line items `SOURCE_AMAZON`
6. If Amazon returns empty → mark those line items `SOURCE_IRVINE`, set `LineItemShipStatus = 5`
7. Write all routing decisions at **line-item level** in SQL

**Simplified by transcript:** Amazon's fulfillment decision is binary per line item — they either take the entire line or reject it. There is no partial quantity split within a single line item. The routing check is always: does Amazon have this line? Yes → Amazon. No → Irvine.

**Rate limit mitigation:** WebBee pre-tags the clear-cut cases, so SP-API calls are only needed for ambiguous split orders. Cache SP-API responses per order within a run cycle.

### Component 2: Line-Item Level Status Schema (Database Migration)
**This is the most critical architectural fix.**

Add a `FulfillmentSource` column to `OrderLineItem`:
- `NULL` = unprocessed
- `AMAZON` = Amazon handling
- `IRVINE` = route to Irvine warehouse
- Add `LineItemShipStatus` (int): mirrors the order-level `AdminOrderStatus` logic but per-line (5 = ready to ship, 4 = complete)

The warehouse UI and nightly sync both key off line-item level status, not the order header. The order header status can remain for backward compatibility with existing Access views during transition.

### Component 3: Warehouse Release UI (Phase 2)
Web application replacing the MS Access + manual SQL workflow. **This is Phase 2 — Phase 1 is automation only.**

**Access:** IP-whitelisted to the Innova office. Their IT handles the network-level restriction; the app just needs to be deployed inside the VPC.

**Features:**
- Shows all orders for the day — both Amazon-handled and Irvine-routed — with their routing status
- Approval flow: manager reviews pending orders and clicks approve → sets `LineItemShipStatus = 5` → releases to warehouse
- **Fraud flags from Shopify:** surface Shopify's built-in fraud indicators on each order — billing/shipping zip mismatch, high-risk score, etc. — so approvers can investigate before releasing
- View of pending approval, in-progress (status 5), and completed (status 4) orders
- No editing of order data — read and approve only

### Component 4: Nightly Fulfillment Sync (Cron Job)
Scheduled at **5:30 PM Pacific** via Artisan Scheduler. (Confirm: original video said 8:00 PM, transcript references 5:30 PM PST — verify with Kim.)

**Logic:**
1. Query: `OrderLineItem WHERE LineItemShipStatus = 5 AND tracking_number IS NOT NULL AND tracking_number != ''`
2. Group by order
3. For each order, call Shopify Fulfillment API to create fulfillment for those specific line items with the tracking number
4. On success: update `LineItemShipStatus = 4` for those line items
5. Log results, alert on failures

**Key constraint:** Must only fulfill the specific Irvine-handled line items — **not** all open line items on an order. This is the bug in the current automation.

### Component 5: Shopify Order Sync (Supporting)
A recurring sync (every 15 minutes) that pulls Shopify orders into the local DB, keeping the local system as the source of truth for order state. This reduces SP-API call volume by enabling local-first filtering.

---

## 5. Assumptions, Risks, and Unknowns

*Updated after 03-26-26 transcript. Many prior unknowns are now resolved.*

### Resolved (no longer unknowns)
| Item | Resolution |
|---|---|
| Sage X3 ↔ SQL Server relationship | **Not related.** Sage X3 has no e-commerce integration. Schema changes are safe. |
| WebBee's role | **Confirmed working.** Pushes Shopify orders to Amazon; generates "rejected by Amazon" tag on rejection. Do not replace. |
| TikTok integration | **Out of scope.** Different brand, future phase (sales reporting only). |
| Amazon SP-API credentials | **Already exist.** Developer accounts set up for both Shopify and Amazon. |
| Quantity splitting within a line item | **Never happens.** Amazon takes the whole line or rejects it entirely. Logic is binary. |
| Approval workflow in scope? | **Yes, Phase 2.** Confirmed — manager approves orders in UI before status is set to 5. |
| Database network access | **VPC-only via VPN.** App must be deployed inside the VPC. |

### Remaining Unknowns (still need answers)
| Item | Impact |
|---|---|
| Exact SQL Server schema — table names, column names | Can't finalize migration or Eloquent models without it |
| FedEx tracking column location in DB | Nightly sync query depends on this |
| Total daily order volume (not just warehouse shipments) | Informs polling frequency; Kim said ~25 warehouse orders, total order count TBD |
| Nightly sync time — 5:30 PM PST or 8:00 PM PST? | Minor — confirm before deploying scheduler |
| "Order ceiling" approval emails — continuing or being replaced by UI? | Affects Phase 1 triggering logic |
| Shopify API plan/tier | Determines rate limit bucket size |

### Remaining Risks
- **SP-API rate limits**: Still a consideration for the split-order path. WebBee's tags mitigate most of it, but SP-API calls must have retry/backoff logic.
- **Shopify fulfillment ID mapping**: The two-step fulfillment flow (FulfillmentOrder → Fulfillment) requires careful ID mapping. Already accounted for in the scaffold.
- **Schema migration on live DB**: Adding columns to a production table requires a maintenance window or careful online migration. Low risk given volume, but needs coordination.

---

## 6. Confident Now vs. Must Wait for Discovery

### Can Propose Now (High Confidence)
- Full Phase 1 architecture: fulfillment engine + nightly sync cron, no UI required
- Phase 2 architecture: VPC-internal web dashboard with order approval + fraud flags
- The line-item level schema fix — correct and safe to implement (Sage X3 confirmed unrelated)
- Laravel + SQL Server — appropriate stack, deploy inside the VPC
- The "rejected by Amazon" tag path — fully understood, WebBee generates it, we consume it
- Routing logic is binary per line item — no quantity math needed
- Nightly sync logic fully specified — status=5 + tracking → Shopify → status=4, Irvine lines only
- Rate limit strategy — WebBee pre-filters the easy cases, SP-API only for ambiguous split orders
- Shopify fulfillment API flow — researched, two-step FulfillmentOrder approach ready in scaffold

### Still Needs Confirmation Before Code Starts
- SQL Server schema dump — needed to finalize model column names and migration
- FedEx tracking column name and table location
- Total daily order count (beyond the ~25 warehouse shipments)
- Nightly sync time — 5:30 PM or 8:00 PM PST
- Shopify API plan tier

---

## 7. Meeting-Ready Solution Outline

Use this as your framing in the meeting.

---

**"Here's how I understand your problem and where I'd take this."**

> When Amazon started doing split fulfillments — shipping some items while you ship the rest — your existing automation couldn't handle it. It was built to close out an entire order, not individual line items. So right now Kim is manually figuring out which items Amazon is taking, entering order numbers into a SQL script, and waiting on a nightly sync to close the loop. WebBee is already doing the right thing tagging rejected orders — the gap is everything after that tag gets written.
>
> The fix isn't complicated in concept. The hard part is that your current system makes routing decisions at the **order level** when it needs to make them at the **line item level**. I need to add routing fields to your line item table and rebuild the automation to operate at that granularity.

**What I'd deliver — two phases:**

**Phase 1: Full automation (no UI)**
- Fulfillment engine runs every 15 minutes. Pulls open Shopify orders. If WebBee already tagged it "rejected by Amazon," all line items go straight to Irvine — no Amazon API call. For everything else, checks Amazon SP-API to see what they're handling. Writes the routing decision per line item to your SQL database. Kim never looks at Amazon Seller Central for this again.
- Nightly cron at 5:30 PM PST finds every Irvine-routed line item with a FedEx tracking number, pushes it to Shopify, marks it complete. Irvine lines only — not the Amazon lines on the same order. That's the current automation bug fixed.

**Phase 2: Approval dashboard**
- IP-whitelisted web UI (your IT handles the whitelist). Shows all orders for the day with their routing status. Manager approves pending orders — approval sets the line items to "ready to ship" and releases them to the warehouse. Surfaces Shopify's fraud flags (billing/shipping mismatch, risk score) so approvers can investigate before releasing.

**Stack:** Laravel on AWS, deployed inside your existing VPC so it can reach the SQL Server. Same database you already have, with a few new columns on the line item table. Simple — no Lambda, no scaling infrastructure, no new databases.

**What I still need from you:**
1. A schema dump of the relevant tables — or just VPN access to look at the structure
2. Which column holds the FedEx tracking number and which table it's on
3. Confirmation on the nightly sync time — 5:30 PM PST or later?
4. Total daily order volume coming into Shopify (not just the ~25 warehouse shipments)

---

*The architecture is fully designed. The code scaffold is already started. The only things blocking a first working build are the schema details.*
