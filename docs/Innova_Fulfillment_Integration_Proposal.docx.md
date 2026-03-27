**E-Commerce Order Fulfillment Integration**

Proposed Approach for Innova

Prepared by Ian Bruce  •  March 2026

# **Overview**

Following a review of the project requirements, this document outlines how I would approach building the integration between Shopify, the Amazon API, and the Irvine warehouse operations. The goal is to replace the current manual daily process with reliable, automated logic that runs in the background without requiring hands-on intervention.

Based on the materials reviewed, the current process requires someone to manually check Shopify for rejected and partially fulfilled orders, query Amazon Seller Central by hand to determine fulfillment responsibility for individual line items, run SQL updates to flag orders for the warehouse, and trigger a nightly sync to push tracking data back to Shopify. Each of those steps is a candidate for automation. A discovery session would confirm the exact configuration before development begins.

# **Understanding the Current Workflow**

The table below summarizes the current manual process as described by the team. Each step maps directly to a component of the proposed automated solution.

| 1 | Releasing Rejected Orders | Shopify orders tagged "rejected by Amazon" are identified. A SQL query updates AdminOrderStatus \= 5 (ready to ship) and stamps AdminUpdatedDateTimeUTC \= today. The query joins on OrderLineItem where Requires\_shipping \= 1 to confirm shippable items, and filters to orders where AdminTrackingNumber IS NULL to avoid duplicates. |
| :---: | :---- | :---- |
| **2** | **Resolving Split Fulfillments** | For Partially Fulfilled Shopify orders, each unfulfilled line item is checked by entering the order number into Amazon Seller Central manually. If Amazon returns no data for those items (a blank response), the Irvine warehouse is responsible. This is currently the most time-consuming step in the daily process. |
| **3** | **Generating Warehouse Pick Tickets** | Orders with AdminOrderStatus \= 5 surface in an MS Access database view. The warehouse uses this view to print pick tickets. Once items are packed and processed through FedEx, the tracking number is written back to AdminTrackingNumber in the local database automatically. |
| **4** | **Nightly Fulfillment Sync** | At 5:30 PM EST, a team member queries for records where AdminOrderStatus \= 5 AND AdminTrackingNumber IS NOT NULL AND NOT empty string. For each match, it pushes tracking data to Shopify to mark the order fulfilled, then sets AdminOrderStatus \= 4 (fulfillment complete). |

# **A Key Technical Consideration**

One issue worth calling out before any development begins: the current nightly sync marks all open line items on a qualifying order as fulfilled in Shopify, regardless of which fulfillment source is responsible for each item. For split shipment orders where Amazon is handling some items and Irvine is handling others, this causes Amazon-assigned line items to be incorrectly closed out, resulting in short shipments.

The proposed solution must operate at the line-item level, not the order level, for the fulfillment push to Shopify. That means the system needs to know exactly which line items belong to Irvine before the nightly job runs. Two approaches are possible, and the right one depends on the existing database schema:

* Option A: The split fulfillment engine (step 2 above) writes line-item-level records to the database when it routes items. The nightly job then queries those specific records rather than all items on the order.

* Option B: The nightly job queries Shopify's fulfillment state at runtime and only touches line items that have no existing Amazon fulfillment attached.

The discovery session will determine which approach fits the current schema. This is the most important design decision in the entire project and will be prioritized in the first conversation.

# **Key Solution Components**

The proposed solution replaces each manual step with automated logic. Final scope will be confirmed after discovery.

**1\.  Order Routing Rules Engine**

The system monitors Shopify for orders carrying the "rejected by Amazon" tag. For each qualifying order, it evaluates line items individually, checking the Requires\_shipping flag to confirm which items need warehouse fulfillment. A shortcut condition applies when an order contains a non-Amazon SKU and a single line item: that order can be routed to Irvine immediately without an API call to Amazon, reducing unnecessary requests.

Qualifying orders trigger the following database update, replicating Kim's current manual SQL:

UPDATE \[Order\]

SET AdminOrderStatus \= 5, AdminUpdatedDateTimeUTC \= GetDate()

WHERE Order\_Number IN (...)

AND AdminTrackingNumber IS NULL

**2\.  Split Fulfillment Engine**

For orders marked Partially Fulfilled in Shopify, the system queries the Amazon SP-API using the Shopify order number. The routing rule is straightforward: if the SP-API returns a null or empty response for a given line item, Amazon is not responsible for it, and the system routes it to Irvine automatically. Items confirmed as Amazon-handled are recorded so the nightly sync does not touch them.

This replaces the current manual process of copying order numbers into Amazon Seller Central one by one. All Shopify orders will also be pulled into the local database on a rolling basis (approximately every 15 minutes) to ensure the routing engine always has current data to work with, while keeping Amazon SP-API calls targeted and within rate limits.

**3\.  Nightly Fulfillment Sync**

A scheduled job runs at 8:00 PM EST each evening. It queries the local database for:

AdminOrderStatus \= 5

AND AdminTrackingNumber IS NOT NULL

AND AdminTrackingNumber \!= ''

For each matching record, the job pushes only the Irvine-responsible line items as fulfilled to Shopify, attaching the tracking number from AdminTrackingNumber. Once the Shopify update is confirmed, it sets AdminOrderStatus \= 4 on those records to close the loop. Amazon-assigned line items on the same order are not touched.

**4\.  Inventory and Sales Reconciliation**

Amazon and TikTok data feeds are connected to automate the inventory and sales figures currently pulled and reconciled manually, providing the team with an accurate, up-to-date picture of stock levels and channel performance.

# **Estimated Timeline**

The following is a preliminary estimate based on the workflow as currently understood. Actual timelines will be refined after the discovery session.

| Phase | Scope | Estimated Duration |
| :---- | :---- | :---- |
| 1\. Nightly Sync Automation | Automate the 8 PM cron, Shopify fulfillment push, and AdminOrderStatus updates | 1 to 2 weeks |
| 2\. Order Routing Engine | Automate rejected tag detection, Requires\_shipping evaluation, and DB status update | 1 to 2 weeks |
| 3\. Split Fulfillment Engine | Amazon SP-API integration, line-item routing logic, and DB design for item-level tracking | 2 to 3 weeks |
| 4\. Inventory Reconciliation | Amazon and TikTok data feeds, reporting pipeline | 1 to 2 weeks |
| Testing and Handoff | End-to-end testing across all phases, edge cases, documentation | 1 week |
| Total |  | 6 to 9 weeks |

Note: Phase 1 can begin immediately once DB and Shopify API access is confirmed. Phases 2 and 3 can run in parallel once the discovery session resolves the line-item tracking design question.

# **Open Questions for Discovery**

The following questions need to be answered before development begins. They are listed in order of priority, as the answers directly affect the technical design.

| Question | Why It Matters |
| :---- | :---- |
| Does the database have a line-item status table, or only order-level status via AdminOrderStatus? | Determines whether Option A or Option B is used for the nightly fulfillment push to Shopify. |
| What does the Amazon SP-API return for an order it is not handling? Null, empty array, or a 404? | The split fulfillment routing logic depends on the exact shape of a blank response. |
| Is the Shopify order polling process (pulling orders into the local DB on a rolling basis) already built, or does it need to be built as part of this project? | Affects overall scope and timeline. |
| What role does WebBee currently play? Is it handling any Amazon order routing today, or is it purely a product/inventory sync layer? | Determines whether WebBee needs to be modified or worked around. |
| How is Sage X3 connected to the order workflow? Does the DB schema need to account for X3 data structures? | Could affect how the Order and OrderLineItem tables are queried or updated. |
| Is the warehouse UI work an enhancement to the existing MS Access view, or is a replacement interface expected? | Significantly changes the scope of the UI deliverable. |

# **Systems Involved**

This project coordinates across the following systems. The specifics of each connection will be confirmed during discovery.

| Platform / System | Role in the Project |
| :---- | :---- |
| Shopify | Order source; receives fulfillment status updates and tracking numbers per line item |
| Amazon SP-API | Queried to determine per-line-item fulfillment responsibility and retrieve tracking data |
| Local SQL / MS Access Database | Stores AdminOrderStatus, AdminTrackingNumber, and AdminUpdatedDateTimeUTC; drives warehouse pick ticket generation |
| WebBee | Middleware between Shopify and Amazon; to be updated for split fulfillment support |
| TikTok Shop API | Data source for sales reconciliation and inventory reporting |
| Sage X3 | ERP system; integrations will be built to work with its data structure, confirmed during discovery |

# **My Approach**

I believe in keeping things practical and low-risk. Here is how I would structure the engagement:

**Discovery Session**

Before writing any code, I would walk through the current order flow with your team, review the local database schema, and get answers to the open questions listed above. This session is the foundation that keeps everything else on track.

**Deliver Phase 1 First**

The nightly sync automation is the most self-contained piece and addresses the most immediate operational risk. Delivering it first gets the team off the manual end-of-day process quickly, while the more complex split fulfillment engine is being built in parallel.

**Test Against Real Order Scenarios**

Every phase will be tested against real order scenarios before going live, including split shipments, partially fulfilled orders, rejected-by-Amazon tags, and the single-line-item shortcut condition, to make sure every edge case is handled correctly.

# **Key Deliverables**

| Deliverable | Description |
| :---- | :---- |
| Order Routing Rules Engine | Automated detection of "rejected by Amazon" orders and line-item routing based on the Requires\_shipping flag, with single-line non-Amazon SKU shortcut |
| Split Fulfillment Handler | SP-API integration that determines fulfillment responsibility per line item and routes Irvine orders automatically |
| Nightly Automation Job | Cron at 8:00 PM EST querying AdminOrderStatus \= 5 with AdminTrackingNumber, pushing only Irvine line items to Shopify, then closing to status 4 |
| Warehouse UI / Pick Ticket View | Scope clarified in discovery: enhancement to existing MS Access view or lightweight replacement |
| Inventory and Sales Reconciliation Feed | Automated data pipeline from Amazon and TikTok for reporting |

Note: I am open to discussing interim manual support for Shopify order releases as a bridge during the build period if there is an immediate operational need.

# **Next Steps**

* A 30-minute discovery call to walk through the database schema, confirm the line-item tracking design, and work through the open questions listed above

* A follow-up to confirm API access credentials for Amazon, Shopify, and TikTok, and to review the Sage X3 setup

* A finalized scope and timeline document based on what comes out of those conversations

I am comfortable working within your existing tech stack and can move quickly once access is confirmed. Happy to answer any questions or adjust this proposal based on your priorities.

**Ian Bruce**  
ianbruce.me@gmail.com