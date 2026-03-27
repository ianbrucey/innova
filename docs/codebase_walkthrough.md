# Codebase Walkthrough — Innova Fulfillment Automation

This document walks through every file in the project, explains what it does, why it was built the way it was, and tells you which phase it belongs to.

---

## Current Status

> **We are in Phase 1 — Automation Engine.**
>
> No UI has been built yet. Phase 1 is two Artisan commands that run on a cron schedule and replace Kim's entire manual morning and evening workflow. Phase 2 (the warehouse dashboard) comes after Phase 1 is live and verified.

---

## Project Map

```
app/
├── Console/Commands/
│   ├── RunFulfillmentEngine.php   ← Phase 1 — morning routing command
│   └── RunNightlySync.php         ← Phase 1 — evening sync command
├── Models/
│   ├── Order.php                  ← Phase 1
│   ├── OrderLineItem.php          ← Phase 1 (+ Phase 2 reads from it)
│   └── OrderShipping.php          ← Phase 1 stub (used in Phase 2 UI)
└── Services/
    ├── ShopifyService.php         ← Phase 1
    ├── AmazonSpApiService.php     ← Phase 1
    └── FulfillmentRouterService.php ← Phase 1

database/migrations/
└── 2026_03_27_..._add_fulfillment_routing_to_order_line_items.php  ← Phase 1

routes/
└── console.php   ← Phase 1 — scheduler config

config/
└── services.php  ← Phase 1 — API credentials + schema config

docs/             ← Reference only
```

---

## The Big Picture Before Diving In

The entire Phase 1 automation is driven by **two facts** confirmed in the client discovery sessions:

1. **WebBee** (existing middleware) already pushes Shopify orders to Amazon and tags any Amazon-rejected order with `"rejected by Amazon"` in Shopify. We consume that tag. We never touch WebBee.

2. **Amazon's fulfillment decision is binary per line item.** They take the entire line or reject it entirely. There is no partial quantity fulfillment within a single line item. This means our routing logic is always a yes/no check per line, never a quantity calculation.

These two facts shape every decision in the code below.

---

## `config/services.php`

**Phase: 1**

```php
'shopify' => [
    'domain'       => env('SHOPIFY_DOMAIN'),
    'access_token' => env('SHOPIFY_ACCESS_TOKEN'),
    'api_version'  => '2025-01',
],

'amazon' => [
    'client_id'      => env('AMAZON_SP_CLIENT_ID'),
    ...
],

'innova_schema' => [
    'tables'  => [ 'order' => env('DB_TABLE_ORDER', 'Order'), ... ],
    'columns' => [ 'requires_shipping' => env('DB_COL_REQUIRES_SHIPPING', 'RequiresShipping'), ... ],
],
```

**Why this exists:** We don't have Innova's schema dump yet. Rather than hardcoding table and column names that might be wrong, every name the app touches is an env variable with a sensible default inferred from Kim's walkthrough video. When the schema dump arrives, you update `.env` — you don't touch any code.

The Shopify API version is pinned to `2025-01` explicitly. Never use `latest` in a production integration — Shopify ships quarterly breaking changes and a version bump should be a conscious decision.

---

## `app/Models/Order.php`

**Phase: 1**

```php
const STATUS_READY_TO_SHIP = 5;
const STATUS_COMPLETE      = 4;

public function __construct(array $attributes = [])
{
    parent::__construct($attributes);
    $this->table = config('services.innova_schema.tables.order');
}
```

**Why the table name is set in the constructor:** Eloquent normally reads `protected $table` at class definition time, before the service container is available. Setting it in the constructor ensures `config()` is available. This is the pattern required when table names come from config.

**Why the status constants:** `5` and `4` are Innova's internal enumeration values confirmed in the video — Kim says them explicitly ("5 is ready to ship," "4 is complete"). Having them as named constants means you never have a magic number sitting raw in a query. If they ever change, you update one place.

**`isRejectedByAmazon()`** — this is the WebBee fast path check. It reads the tags field (a comma-separated string Shopify stores on orders) and looks for "rejected by amazon". This one check eliminates the SP-API call for a significant portion of daily orders.

---

## `app/Models/OrderLineItem.php`

**Phase: 1 (and read by Phase 2)**

This is the most important model in the project. The entire architectural fix lives here.

```php
const SOURCE_AMAZON = 'AMAZON';
const SOURCE_IRVINE = 'IRVINE';

const LINE_STATUS_PENDING       = 0;
const LINE_STATUS_READY_TO_SHIP = 5;
const LINE_STATUS_COMPLETE      = 4;
```

**Why these fields exist at all:** The client's existing system stores `AdminOrderStatus` on the *Order header*. That works fine when one party fulfills an entire order. It breaks completely for split orders — you can't mark the whole order "ready to ship" when only some of its items are going to the Irvine warehouse. These new columns (`FulfillmentSource`, `LineItemShipStatus`) give each line item its own independent routing state.

---

### The Split Order Problem — A Concrete Scenario

This is the scenario you can walk the client through directly.

**The order:**
A customer places Order #16384 containing two items:
- **Line Item A:** Wireless Headphones (SKU: `HDPH-BLK`) — Amazon stocks and ships this
- **Line Item B:** Foam Ear Cushions (SKU: `CUSH-SM`) — Amazon does not carry this, Irvine ships it

WebBee pushes the order to Amazon. Amazon accepts the headphones but rejects the ear cushions. The order now shows as **"Partially Fulfilled"** in Shopify — one line is Amazon's, one is not.

---

**What happens today (the broken flow):**

Kim opens Shopify, sees Order #16384 is partially fulfilled, and checks Amazon Seller Central manually. She confirms Amazon is handling the headphones. She now needs to route only the ear cushions to Irvine.

She runs her SQL script:

```sql
UPDATE [Order]
SET AdminOrderStatus = 5, UpdateDate = GETDATE()
WHERE OrderID = 16384
```

This sets `AdminOrderStatus = 5` on the **Order row** — a single row that represents the entire order. There is no instruction here about *which items* need to ship. The value `5` just means "this order is ready to ship" with no further detail.

The warehouse's MS Access view queries for orders where `AdminOrderStatus = 5`. It returns Order #16384. The pick ticket prints. It shows both line items — the headphones and the ear cushions — because the system has no way to say "only the cushions." The warehouse has to rely on memory or a verbal instruction from Kim to know to skip the headphones.

That night, the nightly sync runs:

```sql
SELECT * FROM [Order]
WHERE AdminOrderStatus = 5
AND TrackingNumber IS NOT NULL AND TrackingNumber != ''
```

It finds Order #16384. The FedEx tracking number is there (the warehouse shipped the ear cushions). The sync pushes the tracking number to Shopify and marks the order fulfilled — **closing both line items**, including the headphones line that Amazon is still in the middle of shipping.

Now Shopify believes Irvine shipped the headphones. Amazon also ships the headphones. **The customer receives the headphones twice.**

Even in the best case — where the warehouse correctly skips the headphones and Amazon doesn't double-ship — Shopify's fulfillment record is wrong. It says Irvine fulfilled everything. Reports, reconciliation, and any downstream process that reads Shopify fulfillment data are now corrupted for this order.

---

**What happens with our fix:**

The Order header is left alone. Instead, we write routing decisions to each line item individually:

| Line Item | SKU | FulfillmentSource | LineItemShipStatus |
|---|---|---|---|
| Line Item A (Headphones) | `HDPH-BLK` | `AMAZON` | `0` (pending — Amazon's problem) |
| Line Item B (Ear Cushions) | `CUSH-SM` | `IRVINE` | `5` (ready to ship) |

The warehouse sees only Line Item B in their queue. The nightly sync queries for line items where `FulfillmentSource = IRVINE AND LineItemShipStatus = 5 AND TrackingNumber IS NOT NULL`. It finds only the ear cushions. It calls Shopify's Fulfillment API for **that specific line item only**, with the FedEx tracking number. Line Item A (headphones) is never touched by our code. Amazon closes it on their side when they ship.

Shopify ends up with an accurate record: Irvine fulfilled the ear cushions, Amazon fulfilled the headphones. No double-shipments. No corrupted data.

---

**The scopes** are the primary query interface for the rest of the app:

```php
->irvineRouted()    // FulfillmentSource = 'IRVINE'
->readyToShip()     // LineItemShipStatus = 5
->hasTracking()     // TrackingNumber is not null/empty
->unresolved()      // FulfillmentSource is null (not yet decided)
->requiresShipping()// RequiresShipping = 1 (Shopify flag)
```

Rather than writing `->where('FulfillmentSource', 'IRVINE')->where('LineItemShipStatus', 5)` everywhere, scopes give you readable, chainable query logic. The nightly sync query reads like a sentence: "give me all line items that are Irvine-routed, ready to ship, and have a tracking number."

**`markAsIrvine()`, `markAsAmazon()`, `markComplete()`** — these are the three state transitions a line item goes through. Putting them on the model means the routing logic doesn't need to know the column names; it just calls the right method. It also means if you ever need to add a side-effect to one of these transitions (e.g., log to a separate audit table), you add it in one place.

**`getTrackingNumber()`** — the tracking column name is an env variable because we don't have the schema yet. This method centralizes that config lookup so nothing in the business logic has to care.

---

## `app/Models/OrderShipping.php`

**Phase: 1 stub / Phase 2**

A thin model right now. It exists so the `Order` relationship is properly declared and Eloquent doesn't complain. In Phase 2 it will provide the shipping address data shown in the warehouse UI.

---

## `app/Services/ShopifyService.php`

**Phase: 1**

This is the Shopify API client. It has three distinct responsibilities:

### 1. Order fetching (`getOpenOrders`, `paginateOrders`)

```php
public function getOpenOrders(): Collection
{
    return collect()
        ->merge($this->paginateOrders(['fulfillment_status' => 'unfulfilled', ...]))
        ->merge($this->paginateOrders(['fulfillment_status' => 'partial', ...]));
}
```

**Why two separate calls:** Shopify's `fulfillment_status` filter doesn't accept multiple values in one request. `unfulfilled` means nothing has shipped at all. `partial` means some items shipped, some haven't. Both are relevant to us — we need to route both types.

**Why `paginateOrders` follows cursor pagination:** Shopify uses a link-header cursor system, not page numbers. The `Link` response header contains a URL with a `page_info` token for the next page. When using that token, Shopify requires that you pass *only* `limit` (no other filters) — the filters are baked into the cursor. The pagination loop handles this correctly by using the full next URL directly rather than re-appending filter params.

### 2. Rate limit handling (`requestWithRetry`, `throttleIfNeeded`)

```php
private function requestWithRetry(string $method, string $url, ...): Response
{
    do {
        $response = Http::withHeaders($this->headers)->{$method}($url, ...);
        if ($response->status() === 429) {
            $wait = (float) ($response->header('Retry-After') ?? 2);
            sleep((int) ceil($wait));
            $attempts++;
            continue;
        }
        $this->throttleIfNeeded($response);
        return $response;
    } while ($attempts < 3);
}
```

**Why this wraps every request:** At ~25 orders/day we're nowhere near Shopify's rate limits, but this costs nothing to have and prevents a bad day (backlog, weekend catch-up) from producing failed API calls. The `X-Shopify-Shop-Api-Call-Limit` header gives us a `used/max` ratio — we sleep proactively at 80% full before Shopify forces a 429.

### 3. Fulfillment creation (`getFulfillmentOrders`, `mapToFulfillmentLineItems`, `createFulfillment`)

This is the most nuanced part of the codebase. Shopify's fulfillment API changed significantly in 2022-2023.

**The old way (deprecated):** `POST /orders/{id}/fulfillments` with a list of order line item IDs.

**The current way (required):** A two-step process:
1. `GET /orders/{id}/fulfillment_orders` — fetches FulfillmentOrder objects
2. `POST /fulfillments` — creates a fulfillment using FulfillmentOrder line item IDs

**Why this matters:** Order line item IDs and FulfillmentOrder line item IDs are **two different ID spaces**. The same item has a different numeric ID in each context. `mapToFulfillmentLineItems()` bridges this by fetching the FulfillmentOrders and cross-referencing `line_item_id` (the order-space ID) against the FulfillmentOrder's line items to find the correct FulfillmentOrder-space ID.

```php
foreach ($this->getFulfillmentOrders($shopifyOrderId) as $fo) {
    foreach ($fo['line_items'] as $foItem) {
        if (in_array($foItem['line_item_id'], $shopifyLineItemIds, strict: false)) {
            $mapped[$fo['id']][] = [
                'id'       => $foItem['id'],  // this is the ID Shopify actually wants
                'quantity' => $foItem['fulfillable_quantity'],
            ];
        }
    }
}
```

**Why line items are grouped by `fulfillment_order_id` before posting:** Shopify made a breaking change in April 2024 — if your POST payload contains duplicate `fulfillment_order_id` entries, the request is rejected. The grouping step ensures all line items sharing the same fulfillment order are consolidated into one entry.

**Why we only fulfill the Irvine lines:** This is the fix for the bug in Innova's current automation. Their existing script closes *all open line items* on an order. For a split order that means it also closes the Amazon-handled lines — Shopify then believes everything shipped from Irvine, which is wrong. We only pass `irvineLineItems` to `createFulfillment`. Amazon's lines are never touched.

---

## `app/Services/AmazonSpApiService.php`

**Phase: 1**

```php
private function connector()
{
    return SellingPartnerApi::make(
        clientId:     config('services.amazon.client_id'),
        clientSecret: config('services.amazon.client_secret'),
        refreshToken: config('services.amazon.refresh_token'),
        endpoint:     Endpoint::NA,
    );
}
```

**Why the connector is a method, not a property:** The `jlevers/selling-partner-api` SDK performs an LWA (Login With Amazon) token refresh on initialization. Building the connector fresh per call ensures we always have a valid token without having to manage token state ourselves. At this volume, the overhead is negligible.

**`getAmazonFulfilledSkus()`** does two SP-API calls sequentially:
1. `getOrders()` — find Amazon's order ID matching the Shopify order number
2. `getOrderItems()` — get the line items on that Amazon order, extract `SellerSKU` values

**Why it's cached for 30 minutes:**

```php
return Cache::remember($cacheKey, now()->addMinutes(30), function () { ... });
```

The engine runs every 15 minutes. Without caching, the same order could be looked up twice in back-to-back runs before its status changes. SP-API's Orders endpoint has a burst limit of 20 requests, restoring at 1 request/second. Caching means we make at most one SP-API call per order per 30-minute window.

**Why we re-throw on API failure instead of defaulting to Irvine:**

```php
} catch (\Exception $e) {
    Log::error(...);
    throw $e;  // intentional
}
```

If we silently defaulted a failed SP-API lookup to "Irvine," we could route an Amazon-handled order to the warehouse, and it would ship twice. Re-throwing causes the order to be skipped this cycle and retried on the next 15-minute run. A transient API failure is recoverable; a double-shipment is not.

---

## `app/Services/FulfillmentRouterService.php`

**Phase: 1**

This is the brain of the operation. It orchestrates the two services above and owns the routing decision logic.

```php
public function __construct(
    private readonly ShopifyService     $shopify,
    private readonly AmazonSpApiService $amazon,
) {}
```

**Why constructor injection:** Laravel's service container resolves this automatically. `RunFulfillmentEngine` type-hints `FulfillmentRouterService` in its `handle()` method, and the container wires everything together. No manual `new` calls anywhere.

### The routing logic

```php
// Fast path
if ($localOrder->isRejectedByAmazon()) {
    $this->markAllIrvine($unresolved);
    return;
}

// Split path
$amazonSkus = $this->amazon->getAmazonFulfilledSkus($orderNumber);
foreach ($unresolved as $item) {
    if (in_array($item->SKU, $amazonSkus, strict: true)) {
        $item->markAsAmazon();
    } else {
        $item->markAsIrvine();
    }
}
```

**The fast path exists for a reason:** WebBee's "rejected by Amazon" tag covers all orders where Amazon simply won't fulfill anything — out of stock, SKU not in their system, etc. This is the majority of daily routable orders. For every one of these we skip the SP-API call entirely. At Innova's volume this isn't about performance — it's about not burning SP-API rate limit on calls we already know the answer to.

**Why `strict: true` on `in_array`:** PHP's loose comparison (`==`) does unexpected things with numeric strings. SKUs may be numeric. `strict: true` ensures we're comparing string to string, not accidentally matching `"0"` to `false`.

**`markAllIrvine()` uses a batch UPDATE, not a loop:**

```php
OrderLineItem::whereIn($items->first()->getKeyName(), $ids)->update([...]);
```

For the rejected-by-Amazon case, all lines on an order go to Irvine. A single UPDATE statement is cleaner and faster than iterating and calling `markAsIrvine()` on each item individually. For split orders we do iterate, because each line needs its own decision.

**Why errors per-order are caught and logged rather than aborting:**

```php
try {
    $this->routeOrder($shopifyOrder);
} catch (\Exception $e) {
    Log::error('FulfillmentRouter: error routing order', [...]);
}
```

If one order fails (bad data, transient API error), we log it and move on. The alternative — letting the exception bubble up and kill the whole run — would mean one bad order blocks the routing of every other order in the batch. The error will appear in the logs; the order will be retried on the next cycle.

---

## `app/Console/Commands/RunFulfillmentEngine.php`

**Phase: 1**

```php
protected $signature = 'innova:route-orders
                            {--dry-run : Log routing decisions without writing to the database}';
```

This command is intentionally thin. It calls `$router->run()` and returns a success/failure exit code. Business logic lives in `FulfillmentRouterService`, not here.

**The `--dry-run` flag** is important for the first time you run this against the production database. You can run `php artisan innova:route-orders --dry-run` and inspect the logs to see what routing decisions *would* be made, without writing anything to the DB or calling any APIs.

Note: dry-run support needs to be threaded through to `FulfillmentRouterService` — right now the flag is accepted by the command but not passed down. That's a TODO before first live run.

---

## `app/Console/Commands/RunNightlySync.php`

**Phase: 1**

This command is the evening half of the automation. It is more self-contained than the routing engine — it doesn't need `FulfillmentRouterService`, just `ShopifyService` and direct model queries.

```php
$lineItems = OrderLineItem::with('order')
    ->irvineRouted()
    ->readyToShip()
    ->hasTracking()
    ->get();
```

**Why this query is the exact condition Kim described:** In the video she says: *"look for any order that has admin order status 5 and has a tracking number."* We've translated this to the line-item level using the scopes on `OrderLineItem`. The `with('order')` eager-loads the parent order so we can get the Shopify order ID without N+1 queries.

**Why we group by order before calling Shopify:**

```php
$grouped = $lineItems->groupBy(fn($item) => $item->order->shopify_order_id);
```

We make one Shopify API call per order, not per line item. An order with three Irvine-routed line items produces one `createFulfillment` call that covers all three, not three separate calls.

**The two-step ID mapping inside the loop:**

```php
$shopifyLineItemIds = $items->pluck('ShopifyLineItemId')->filter()->toArray();
$fulfillmentItems   = $shopify->mapToFulfillmentLineItems($shopifyOrderId, $shopifyLineItemIds);
```

We store `ShopifyLineItemId` on each line item (populated when we sync orders from Shopify). We then use `ShopifyService::mapToFulfillmentLineItems()` to convert those to FulfillmentOrder line item IDs. See the `ShopifyService` section above for why these are different.

**Why the command returns `Command::FAILURE` if any order failed:**

```php
return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
```

The scheduler can be configured to alert on non-zero exit codes. Returning `FAILURE` when even one order didn't sync means a failed order won't silently pass unnoticed overnight.

---

## `routes/console.php`

**Phase: 1**

```php
Schedule::command('innova:route-orders')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

$syncTime = env('NIGHTLY_SYNC_TIME', '17:30');

Schedule::command('innova:sync-fulfillments')
    ->dailyAt($syncTime)
    ->timezone('America/Los_Angeles')
    ->withoutOverlapping();
```

**Why `withoutOverlapping()`:** If a routing run takes longer than 15 minutes (unlikely at this volume, but possible if SP-API is slow), a second run should not start on top of it. Without this guard, you could have two instances simultaneously trying to route the same orders.

**Why `runInBackground()` on the routing engine but not the nightly sync:** The engine runs every 15 minutes and should not block the scheduler process. The nightly sync runs once a day and we want it to complete before the scheduler moves on, so it can report its exit code accurately.

**Why the sync time is an env variable:** The client gave two different times across two sessions (5:30 PM and 8:00 PM PST). Rather than hardcoding either, it's configurable in `.env` as `NIGHTLY_SYNC_TIME`. Default is `17:30`. Change it without touching code.

**How to activate the scheduler on the server — one crontab entry:**

```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

That single line drives both scheduled tasks.

---

## `database/migrations/2026_03_27_..._add_fulfillment_routing_to_order_line_items.php`

**Phase: 1 — must run before anything else**

```php
$table->string('FulfillmentSource', 10)->nullable()->default(null);
$table->smallInteger('LineItemShipStatus')->default(0);
$table->dateTime('FulfillmentResolvedAt')->nullable();
$table->string('ShopifyLineItemId', 50)->nullable();
```

**Why nullable with a null default on `FulfillmentSource`:** Existing rows in `OrderLineItem` have no routing decision yet. They need to start as `NULL` so the `->unresolved()` scope correctly identifies them as needing routing. Setting a non-null default would incorrectly mark every existing line item as already decided.

**Why `LineItemShipStatus` defaults to `0` not `null`:** It's an integer status enum, not a tri-state. Defaulting to `0` (pending) is unambiguous — null integers in SQL comparisons behave unexpectedly.

**The composite index:**

```php
$table->index(['FulfillmentSource', 'LineItemShipStatus'], 'idx_fulfillment_routing');
```

The nightly sync query hits `WHERE FulfillmentSource = 'IRVINE' AND LineItemShipStatus = 5` on every run. This index makes that query fast regardless of how many rows are in the table.

**The `table()` method reads from config:**

```php
private function table(): string
{
    return config('services.innova_schema.tables.order_line_item');
}
```

Same reason as the models — the table name is an env variable until confirmed by the schema dump.

---

## What's Not Built Yet (Phase 2)

These do not exist yet and are not needed for Phase 1 to go live:

- **Warehouse dashboard** — the web UI showing orders, routing status, fraud flags, and the approval button
- **`Http/Controllers/WarehouseController.php`** — the controller for the dashboard views
- **Blade views** — the actual HTML
- **Auth / IP whitelisting** — access control for the dashboard
- **Shopify order sync command** — a dedicated command to pull Shopify orders into the local DB on a schedule. Currently `FulfillmentRouterService` pulls from Shopify live. Phase 2 may benefit from a local cache of order data, but Phase 1 doesn't require it.

---

## The One Remaining TODO That Blocks a First Live Run

Every file has `TODO` comments where a schema detail is unknown. They all trace back to the same root cause: **we need Innova's SQL Server schema dump.** Specifically:

| Unknown | Where it's used |
|---|---|
| Column name for Shopify order number on `Order` table | `FulfillmentRouterService::routeOrder()` |
| Column name for Shopify order ID on `Order` table | `RunNightlySync::handle()` |
| Exact `OrderLineItem` table name | `OrderLineItem` model constructor |
| Exact `Order` table name | `Order` model constructor |
| FedEx tracking column name | `OrderLineItem::scopeHasTracking()`, `getTrackingNumber()` |

Once those are confirmed, update `.env` and run `php artisan innova:route-orders --dry-run` to verify routing decisions look correct before writing anything to the database.
