# Innova Fulfillment Automation — Technical Plan

---

## The Problem

Innova runs a split-fulfillment model: Amazon FBA ships some items on an order, the Irvine warehouse ships the rest. The existing automation was built before this model existed. It cannot handle per-line-item routing decisions, so a staff member (Kim) does it manually every day.

**The morning process:**
- Review Shopify for unfulfilled and partially-fulfilled orders
- Identify which ones Amazon rejected (tagged by WebBee middleware) or only partially handled
- Manually enter order numbers into a SQL UPDATE script: `AdminOrderStatus = 5`

**The evening process:**
- Run a query: find orders where `AdminOrderStatus = 5` and a FedEx tracking number exists
- Push tracking numbers to Shopify
- Update status to `4` (complete)

**Why this breaks for split orders:**
The status field sits on the order header, not the line item. If a 3-item order has 2 items going to Amazon and 1 going to Irvine, marking the entire order header as "ready to ship" gives the warehouse no instruction on which specific item to pick. The automation also closes the entire order at night — which incorrectly closes Amazon's lines on split orders.

**Root cause:** The data model was built for single-source fulfillment. It needs to operate at the line-item level.

---

## How the Existing System Works

These components are live and are **not being replaced**:

| Component | Role |
|---|---|
| **Shopify** | Customer-facing store, order management |
| **WebBee** | Middleware — pushes Shopify orders to Amazon FBA; tags orders as `"rejected by Amazon"` when Amazon can't fulfill |
| **Amazon Seller Central / FBA** | Fulfills items that Amazon stocks |
| **AWS SQL Server** | Internal order database; the warehouse works from an MS Access view into this DB |
| **Irvine Warehouse WMS** | Picks, packs, generates FedEx labels; writes tracking numbers back to SQL automatically |
| **MS Access + SQL scripts** | Kim's current manual routing and sync tools — being replaced |

We are building automation around these systems. The only structural change is a schema extension to the SQL Server database.

---

## What We're Building

Two phases, cleanly separated. Phase 1 is automation only — no UI. Phase 2 adds the web dashboard.

---

## Phase 1: Automation Engine

### 1A — Split Fulfillment Router

A scheduled background service running every 15 minutes. Replaces Kim's morning manual process entirely.

**Decision logic:**

```
For each open Shopify order:
  └── Tagged "rejected by Amazon" (by WebBee)?
        YES → Route all line items to Irvine. Done. (no API call needed)
        NO  → Query Amazon SP-API: is Amazon fulfilling this order?
                YES → Mark those line items SOURCE = AMAZON
                NO  → Mark those line items SOURCE = IRVINE, status = 5
```

**One confirmed rule from Innova:** Amazon's fulfillment decision is binary per line item. They take the entire line or reject it entirely — there is no quantity-level splitting within a single line item. The routing check is always a yes/no per line.

**Output:** Every open order's line items have a routing decision written to SQL. The warehouse DB is always current. Kim never looks at Amazon Seller Central for this workflow again.

### 1B — Nightly Fulfillment Sync

A cron job running at 5:30 PM PST. Replaces Kim's evening manual process.

**Logic:**
1. Query SQL: line items where `FulfillmentSource = IRVINE` AND `LineItemShipStatus = 5` AND FedEx tracking number is not null
2. Group by Shopify order
3. Call Shopify Fulfillment API for **only the Irvine-handled line items** on each order — not all open items
4. On success: update `LineItemShipStatus = 4` (complete)

**The critical fix:** Current automation closes the entire order. This closes only the Irvine-handled lines, leaving Amazon's lines intact. That is the bug this replaces.

---

## Phase 2: Warehouse Dashboard

A web application replacing the MS Access + SQL workflow. Deployed inside Innova's AWS VPC — IP-whitelisted, not public-facing.

### Views

**Today's Orders**
All orders for the day with routing status — which items Amazon is handling, which are Irvine-bound, which are complete. Replaces the current Access query.

**Pending Approval**
Irvine-routed orders awaiting manager sign-off before they reach the warehouse floor. One-click approve releases the order (sets `LineItemShipStatus = 5`).

**Order Detail**
Line-item breakdown with routing decisions, Shopify fraud indicators, and shipping address. Fraud flags surfaced: billing/shipping zip mismatch, Shopify risk score, any other Shopify-provided risk signals.

**History**
Completed orders with tracking numbers for reconciliation.

### Approval Flow

```
Fulfillment Router writes routing decisions
        ↓
Orders appear in Pending Approval queue
        ↓
Manager reviews + clicks Approve
        ↓
LineItemShipStatus set to 5 → warehouse picks it up
        ↓
Nightly sync closes the loop at 5:30 PM
```

---

## The Database Change

The single most important technical change in this project: a migration that adds line-item level routing fields to the `OrderLineItem` table.

| New Column | Type | Values |
|---|---|---|
| `FulfillmentSource` | VARCHAR(10) | `NULL` = unresolved, `AMAZON`, `IRVINE` |
| `LineItemShipStatus` | SMALLINT | `0` = pending, `5` = ready to ship, `4` = complete |
| `FulfillmentResolvedAt` | DATETIME | Timestamp when routing decision was made |
| `ShopifyLineItemId` | VARCHAR(50) | Shopify's internal line item ID — required for Fulfillment API calls |

**This is safe to run.** Sage X3 (the ERP) confirmed to have no integration with the e-commerce database. Adding columns will not interfere with any existing processes.

The order header's `AdminOrderStatus` field remains in place for backward compatibility with existing Access views during the transition.

---

## Tech Stack

### Backend & Automation: Laravel (PHP)

Single framework covering all requirements: scheduled cron jobs via Artisan Scheduler, queue-based async API calls, SQL Server access via Eloquent ORM, and the Phase 2 web UI. One codebase, one deployment, one team context.

The alternative (Python for automation + Laravel for UI) has better native async I/O but introduces two runtimes, two deployment pipelines, and two dependency trees. At ~25 warehouse shipments per day, async performance is irrelevant. Laravel is the right call.

### Database: Existing AWS SQL Server

Already live and connected to all existing tooling. Add columns — don't replace or migrate.

### Queue Backend: Database driver (v1) → Redis (if needed)

The database queue driver requires zero additional infrastructure and is appropriate for v1 at this volume. Redis is a straight swap if volume ever demands it.

### Warehouse UI: Laravel Blade + Livewire

Reactive, server-rendered UI without the complexity of a full SPA. Appropriate for an internal approval dashboard. No separate API layer required.

### Shopify Integration: Guzzle HTTP (direct REST)

Current Shopify REST API (`2025-01`) is appropriate for custom/internal apps. The new two-step fulfillment flow (fetch FulfillmentOrder → create Fulfillment) is already researched and scaffolded. Key detail: Shopify uses two separate line item ID spaces — the mapping between them is implemented.

### Amazon SP-API: `jlevers/selling-partner-api` (PHP)

Actively maintained SDK. Handles LWA token refresh automatically. Credentials already exist on the Innova side.

### Hosting: AWS EC2 or ECS — inside the existing VPC

The SQL Server is in a private VPC with no inbound public access. The Laravel application must be deployed inside the same VPC. Outbound internet (for Shopify and Amazon API calls) is permitted. No new networking infrastructure required.

---

## What's Ready vs. What's Still Needed

### Ready to build now
- Full system architecture and data flow
- Phase 1 routing logic — fully specified, no unknowns
- Database migration design — column names and types defined
- Shopify API integration — current API shape confirmed via documentation research, code scaffolded
- Amazon SP-API — SDK selected, credential setup confirmed to exist
- VPC deployment constraint — accounted for in architecture
- WebBee's role — confirmed working, we consume its output only

### Still needed before code starts
| Item | Why it matters |
|---|---|
| SQL Server schema dump | Confirm actual table and column names for models and migration |
| FedEx tracking column | Nightly sync query depends on knowing exactly where tracking lives |
| Nightly sync time | 5:30 PM PST or 8:00 PM PST — minor, needs confirmation |
| Total daily Shopify order volume | Informs polling frequency for the router |
| Shopify API plan tier | Determines rate limit bucket size (40 vs. 400 requests) |

---

## Code Scaffold (Already Started)

The following have been written as real, annotated code — not pseudocode. Each file is ready to drop into a Laravel project once the schema is confirmed:

| File | Description |
|---|---|
| `Models/Order.php` | Order model with status constants, scopes, and relationships |
| `Models/OrderLineItem.php` | Line item model with routing fields, scopes (`irvineRouted`, `hasTracking`, etc.), and `markAsIrvine/Amazon/Complete` helpers |
| `Services/ShopifyService.php` | Full Shopify API client — cursor pagination, rate limit handling, FulfillmentOrder two-step flow, ID space mapping |
| `Services/AmazonSpApiService.php` | SP-API wrapper with per-order response caching and safe error handling |
| `Services/FulfillmentRouterService.php` | Core routing logic — fast path for WebBee-tagged orders, SP-API split path, batch DB writes |
| `Jobs/NightlyFulfillmentSync.php` | Queue job for the 5:30 PM sync — groups by order, maps IDs, calls Shopify for Irvine lines only |
| `Console/Commands/RunFulfillmentEngine.php` | Artisan command wrapping the router service |
| `Console/Kernel.php` | Scheduler: engine every 15 minutes, nightly sync at 5:30 PM PST |
| `Http/Controllers/WarehouseController.php` | Warehouse UI — index, show, release, history |
| `routes/web.php` | Auth-protected warehouse routes |
| `database/migrations/add_line_item_fulfillment_fields.php` | The schema migration with composite index |

---

## Delivery

Client expectation (stated explicitly): weekly visible progress. No long silent stretches.

| Week | Deliverable |
|---|---|
| **1** | Schema migration applied to dev environment. "Rejected by Amazon" fast path working end-to-end. Shopify order polling live. |
| **2** | Amazon SP-API integration live. Split order routing working. Nightly sync cron operational. Phase 1 complete. |
| **3** | Phase 1 hardened — error handling, logging, alerting. Phase 2 UI: order list and routing status display. First client walkthrough. |
| **4+** | Phase 2 approval workflow. Fraud flag display. Edge cases from Phase 1 testing addressed. Handoff documentation. |

Weekly check-in at the start of each week to review what shipped and align on next priorities.

---

## Open Questions for This Meeting

1. Can you provide a schema dump of the relevant tables, or grant read access to the dev database?
2. Which table and column holds the FedEx tracking number that the WMS writes?
3. What is the exact nightly sync time — 5:30 PM or 8:00 PM PST?
4. How many total orders come into Shopify daily (not just the ~25 warehouse shipments)?
5. What is your Shopify plan tier?
