Here is the comprehensive list of nuanced discovery questions we need to ask, categorized by system, along with the strategic rationale for each.

## Category 1: The Database & Split Fulfillment Logic (The Structural Flaw)

As we identified earlier, Kim’s manual SQL query updates the `AdminOrderStatus` to `5` at the **Order** header level, not the **Line Item** level.

* **Question 1 (IDENTIFYING CORRECT ITEMS ON SPLIT SHIPMENTS):** For split shipments, how does the warehouse currently know *which specific line items* to pick? Is there an existing flag on the `OrderLineItem` table (like `Fulfilled_By_Amazon`), or will we need to create a new line-item status column to track this?
  * **Rationale:** If an order has Item A (Amazon) and Item B (Irvine Warehouse), setting the entire order status to 5 ("Ready to Ship") is dangerous. The MS Access pick-ticket system might instruct the warehouse to ship both items. You need to know if you are modifying their database schema to support line-item level routing.
* **Question 2 (HANDLING QUANTITY-BASED, not item-based, PARTIAL FULFILLMENTS):** How do we handle partial fulfillments of a  *single line item quantity* ? For example, if a customer orders 5 units of SKU A, but Amazon only fulfills 3 of them, how does the system expect us to split that single row?
  * **Rationale:** Kim’s video only showed him looking at line items as a whole. Quantity-based splits are notoriously complex in e-commerce APIs. You need to know if this edge case exists, as it drastically increases the complexity of your Python/Node script.

## Category 2: The "Missing" Systems (WebBee & TikTok)

Kim's video walkthrough focused entirely on Shopify, Amazon, and the local SQL database. **However, Angeline's email explicitly mentions WebBee and TikTok as core project requirements**^^^^^^^^.

* **Question 3 (WEB BEE'S ROLE?):** Angeline’s project scope explicitly lists the WebBee API as a "Must-have," but Kim didn’t mention it in his manual workflow. **What exact role is WebBee playing in the current architecture?**
  * **Rationale:** WebBee is typically used as a middleware between Shopify and Amazon FBA. You need to know if your new scripts are replacing WebBee, sending data *to* WebBee, or if WebBee is the reason the split fulfillments are failing in the first place.
* **Question 4 (TIK TOK DATA SPECS):** The requirements mention integrating the TikTok API for inventory and sales reconciliation. **Can you define what data points need to be pulled from TikTok, and where exactly that data needs to be deposited (e.g., into the local SQL database, or directly into Sage X3)?**
  * **Rationale:** You have zero context on the TikTok requirement. You need to scope whether this is a simple daily cron job pulling a CSV report, or a real-time webhook integration.

## Category 3: Sage X3 ERP Interaction

**The business driver states that "The recent implementation of Sage X3 has caused issues with the e-commerce API"**^^.

* **Question 5 (INTERACTING WITH SAGE X3):** Are we expected to read and write directly to the Sage X3 SQL database tables, or does Sage X3 have an API layer (REST, SOAP, or GraphQL) that we must interact with?
  * **Rationale:** Writing raw SQL updates directly into an ERP’s database (like Kim is doing) is highly risky and often voids vendor support warranties. If they want you to use the Sage X3 API instead of raw SQL for the automated sync, that changes your technical approach and timeline completely.
* **Question 6 (NIGHTLY SALES RECONCILIATION SAGE X3):** How exactly does Sage X3 consume the "sales reconciliation" data? **Does the nightly 8:00 PM sync script need to push financial data into Sage X3, or just update the `AdminOrderStatus` to 4?**
  * **Rationale:** Closing the loop in Kim's database (`Status = 4`) might not be enough if Sage X3 requires financial ledgers to be updated simultaneously.

## Category 4: Infrastructure, Access & Security

To build the Decoupled AWS stack (Laravel EC2 + Python Lambda), you need to know their security posture. Kim mentioned the database is on their "AWS cloud".

* **Question 7 (SQL SERVER SECURITY):** **How is the SQL Server database secured within your AWS environment? Will my AWS Lambda functions and EC2 instance need to be deployed within a specific VPC (Virtual Private Cloud), or will you provide a secure connection string with IP whitelisting?**
  * **Rationale:** You cannot write the automation scripts if you cannot securely access the database. Identifying AWS networking constraints early prevents a massive deployment roadblock later.
* **Question 8 (WHAT DEV ACCOUNTS DO YOU HAVE?):** Do you already have developer accounts and API keys provisioned for the Amazon SP-API, Shopify Custom Apps, and TikTok Shop?
  * **Rationale:** Getting approval for an Amazon SP-API developer account can sometimes take weeks due to Amazon's strict data privacy audits (PII restrictions). If they don't have this set up, the project timeline will be immediately delayed.

## Summary

By asking these questions, we are demonstrating that we have audited their process deeply enough to find the disconnects between the recruiter's high-level requirements and the ground-floor reality of their database.
