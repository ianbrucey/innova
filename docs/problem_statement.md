Here is a granular Problem Definition Document designed specifically to orient your AI agent or development team. It breaks down the business context, the "As-Is" messy reality of their manual operations, the technical gaps we've identified, and the specific objectives for the "To-Be" automated state.

---

## Innova E-Commerce API Integration: Problem Definition Document

#### 1. High-Level Business Problem

* **The Catalyst:** Innova recently implemented Sage X3 as their ERP, which subsequently broke their existing e-commerce API integrations^^.
* **The Resource Gap:** Innova currently has a consultant assisting with Shopify, but they lack internal resources with Amazon API (SP-API) expertise^^^^^^^^^^.
* **The Operational Impact:** Because the API is broken, operations staff (specifically Kim) are acting as "human middleware," manually querying local databases, cross-referencing Amazon Seller Central, and hand-typing order statuses to route shipments to their Irvine warehouse.
* **Core Mandate:** The project must enhance Shopify, WebBee, and Amazon APIs to automate split fulfillments, create a warehouse order release UI, and integrate TikTok/Amazon for inventory and sales reconciliation^^^^^^^^^^^^^^.

#### 2. The Current Manual Workflow (The "As-Is" State)

The current process requires manual intervention to bridge Shopify, Amazon, and their local SQL database hosted on AWS.

* **Routing "Rejected" Orders:**
  * Staff reviews the Shopify queue for orders tagged with `"rejected by Amazon"`.
  * These orders must be fulfilled by the local Irvine warehouse.
  * Staff manually executes a SQL `UPDATE` query to set `Admin_order_status = 5` (internally enumerated as "ready to ship") and updates the datetime to `GetDate()` for future inventory reconciliation.
* **Handling Split / Partial Fulfillments:**
  * For Shopify orders marked "Partially Fulfilled," staff must manually search the order number inside the Amazon Seller Central UI.
  * If Amazon returns the order details, Amazon is handling the remaining items, so the order is ignored.
  * If Amazon returns a blank result, it means Amazon is *not* fulfilling the item, and it must be manually added to the SQL `UPDATE` script to be routed to the Irvine warehouse.
* **Warehouse Operations:**
  * An MS Access database connects to the local SQL database to create a view of all orders with `Admin_order_status = 5`.
  * The warehouse prints pick tickets, picks the items, and generates FedEx shipping labels.
  * FedEx tracking numbers automatically populate back into the local database.
* **The Nightly Sync:**
  * Currently, they need a scheduled task (batch process) to run daily at 8:00 PM Pacific.
  * This task must find orders where `Admin_order_status = 5` AND the tracking number is not null/empty.
  * It must push these tracking numbers to Shopify to fulfill the line items, and finally update the local DB to `Admin Order Status = 4` (Order Complete).

#### 3. Technical Bottlenecks & Architectural Flaws

The agent must be aware of the following logical flaws in the client's current setup:

* **The Order Header vs. Line Item Disconnect:** The current SQL query updates `AdminOrderStatus = 5` on the `[Order]` header table, using an `INNER JOIN` to the `OrderLineItem` table merely to check if `Requires_shipping = 1`. This is a severe architectural flaw for split shipments. If an order has two items (one Amazon, one Irvine), marking the entire order header as "Ready to Ship" (5) fails to instruct the warehouse on *which specific line item* to put in the box.
* **Line Item Filtering:** The solution must evaluate fulfillment logic at the line-item level. **It must strictly "Only update line items that need to be fulfilled"**^^.
* **API Rate Limiting:** The client is explicitly concerned about hitting API rate limits if the script utilizes a "brute force" method to check Amazon for every single unfulfilled Shopify order. The logic must be optimized to filter locally before querying the Amazon SP-API.

#### 4. Missing Context (The "Unknowns" for Discovery)

The agent should flag these areas as requiring further technical scoping from the client:

* **WebBee's Role:** WebBee is listed as a "Must-have" API integration^^, but it is entirely absent from the operations video walkthrough. Its exact role in the data pipeline is unknown.
* **TikTok Integration:** The project scope requires integrating the TikTok API for "automated data retrieval for inventory and sales reconciliation"^^. The specific endpoints, data payloads, and destination (e.g., local DB vs. Sage X3) are currently undefined.
* **Sage X3 Interaction:** The documentation does not specify if the new automation scripts will write directly to the Sage X3 database, or if Sage X3 relies entirely on the intermediary local AWS database shown in the video.

#### 5. Target Automation Objectives

To successfully deliver the project, the following systems must be built:

1. **The Split Fulfillment Engine:** A backend service that ingests Shopify orders, checks line-item tags, queries the Amazon SP-API to verify fulfillment source, and automatically routes Irvine-bound items without manual Seller Central lookups.
2. **Warehouse UI:** A web-based application (proposed: Laravel) replacing the manual SQL/Access workflow, allowing staff to securely view parsed orders and release them to the warehouse.
3. **Nightly Sync Cron Job:** An automated script running at 8:00 PM to capture local FedEx tracking numbers, push them to Shopify, and close the loop by updating the order status to `4`.

---

Would you like me to generate a checklist of specific API endpoints (Shopify and Amazon SP-API) your agent will need to research to execute these target objectives?2
