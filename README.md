# Innova Fulfillment Automation

A Laravel application that automates Innova's order fulfillment routing and nightly Shopify sync. Replaces a daily manual process where staff had to cross-reference Shopify, Amazon Seller Central, and a local SQL Server database by hand.

---

## The Problem

Innova sells products through Shopify. 

Some orders are fulfilled entirely by Amazon FBA. Some are fulfilled entirely by the Irvine warehouse. 

The hard case is split orders, where Amazon ships some items and Irvine ships the rest.

The original workflow tracks order status at the order level, not the line item level. This design assumption can cause a real operational failure.

### What Goes Wrong With a Split Order

FOR EXAMPLE: Take an Order `#16384`. The customer ordered two items:

- Wireless Headphones (SKU: `HDPH-BLK`) - Amazon stocks and ships this
- Foam Ear Cushions (SKU: `CUSH-SM`) - Amazon does not carry this, Irvine ships it

WebBee (existing middleware) pushes the order to Amazon. Amazon accepts the headphones, rejects the ear cushions. The order shows as Partially Fulfilled in Shopify.

A staff member (Kim N.) manually identifies that the ear cushions need to go to Irvine. He runs a SQL UPDATE that sets `AdminOrderStatus = 5` on the Order row. That single row represents the entire order. There is no field on the row that says which items need to ship.

**The warehouse sees the order in their Access view and gets a pick ticket showing >both items<. They have to know from context to only pack the ear cushions. The system gives them no instruction.**

That night, the nightly sync script finds the order: `AdminOrderStatus = 5` with a FedEx tracking number. It closes every open line item on the order, including the headphones line that Amazon is still in the process of shipping. Shopify now believes Irvine fulfilled the headphones. Amazon ships the headphones too. At minimum, this is an inefficency in the record keeping, and at worse, a communication failure and the headphones are shipped twice .

### The Fix

Instead of one status flag on the order header, we write a routing decision to each line item individually.

| Line Item    | SKU          | FulfillmentSource | LineItemShipStatus      |
| ------------ | ------------ | ----------------- | ----------------------- |
| Headphones   | `HDPH-BLK` | `AMAZON`        | `0` (not our concern) |
| Ear Cushions | `CUSH-SM`  | `IRVINE`        | `5` (ready to ship)   |

The warehouse queue shows only the ear cushions. The nightly sync closes only the ear cushions line in Shopify. Amazon's line is never touched by this application. Clear deliniation and no potential for double-shipments.

---

## How It Works

Two Artisan commands run on a cron schedule and handle everything.

### 1) `innova:route-orders`

Runs every 15 minutes. Fetches open and partially fulfilled Shopify orders, determines which line items belong to Irvine, and writes that decision to the database.

Decision logic per order:

1. If the order is tagged `"rejected by Amazon"` (set by WebBee) THEN all line items go to Irvine. No Amazon API call needed.
2. Otherwise ***(for partially fulfilled orders)***, query the Amazon SP-API to get the SKUs Amazon is fulfilling.
3. Line items matching an Amazon SKU are marked `SOURCE = AMAZON`.
4. Remaining line items are marked `SOURCE = IRVINE` and `LineItemShipStatus = 5`.

Amazon's fulfillment decision is binary per line item. They either take the entire line or reject it entirely. There is no quantity splitting within a line.

### 2)  `innova:sync-fulfillments`

Runs once daily at 5:30 PM Pacific (configurable via `NIGHTLY_SYNC_TIME` in `.env`). Handles the Shopify fulfillment close-out.

1. Query for all Irvine-routed line items where `LineItemShipStatus = 5` and a FedEx tracking number exists.
2. Group by Shopify order.
3. Call Shopify's Fulfillment API for those specific line items only, passing the tracking number.
4. On success, update `LineItemShipStatus = 4` (complete).

Both commands support `--dry-run` for safe testing before writing anything to the database or calling any external APIs.

```bash
php artisan innova:route-orders --dry-run
php artisan innova:sync-fulfillments --dry-run
```

---

## Scheduler

One crontab entry drives both commands:

```
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

The schedule is defined in `routes/console.php`.

---

## Setup

### 1. Install dependencies

```bash
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Configure `.env`

Fill in the required values:

```env
# Database (SQL Server inside the client VPC)
DB_HOST=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

# Shopify
SHOPIFY_DOMAIN=your-store.myshopify.com
SHOPIFY_ACCESS_TOKEN=shpat_...

# Amazon SP-API
AMAZON_SP_CLIENT_ID=
AMAZON_SP_CLIENT_SECRET=
AMAZON_SP_REFRESH_TOKEN=
```

### 3. Set schema overrides

The client's table and column names are not confirmed yet. Defaults are inferred from the discovery sessions. Update these once the schema dump is received:

```env
DB_TABLE_ORDER=Order
DB_TABLE_ORDER_LINE_ITEM=OrderLineItem
DB_COL_REQUIRES_SHIPPING=RequiresShipping
DB_COL_TRACKING_NUMBER=TrackingNumber
```

### 4. Run the migration

```bash
php artisan migrate
```

This adds `FulfillmentSource`, `LineItemShipStatus`, `FulfillmentResolvedAt`, and `ShopifyLineItemId` to the `OrderLineItem` table. The existing `AdminOrderStatus` field on the `Order` table is not touched.

---

## Deployment

The application must be deployed inside the client's AWS VPC. The SQL Server database is not publicly accessible -- it sits behind a VPN on a private network. Any server outside the VPC cannot reach it.

Outbound internet access from the VPC is available, which is what the Shopify and Amazon API calls use.

---

## Remaining TODOs Before First Live Run

These are the only things blocking a `--dry-run` test against the real database:

| Item                                           | Where it matters                                                                |
| ---------------------------------------------- | ------------------------------------------------------------------------------- |
| Shopify order number column on `Order` table | `FulfillmentRouterService` - used to match Shopify orders to local DB records |
| Shopify order ID column on `Order` table     | `RunNightlySync` - used to group line items before calling Shopify            |
| Confirm `OrderLineItem` table name           | `OrderLineItem` model                                                         |
| Confirm `Order` table name                   | `Order` model                                                                 |
| FedEx tracking column name                     | `OrderLineItem::scopeHasTracking()` and `getTrackingNumber()`               |

All of these are env variables. Once confirmed, update `.env` and run with `--dry-run` to verify before going live.

---

## Phase Status

**Phase 1 (current): Automation Engine**
Both Artisan commands and all supporting services are built. Blocked only on schema confirmation.

**Phase 2 (next): Warehouse Dashboard**
A web UI showing all orders for the day with routing status, a manager approval flow, and Shopify fraud flags surfaced on each order. Not yet started.
