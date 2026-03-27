**Access Requirements Checklist**

Innova Fulfillment Integration Project

Prepared by Ian Bruce  •  March 2026

The items below cover everything needed to get the integration built and tested. They are split into two groups: what is needed at or right after the discovery call, and what can wait until later phases. Nothing requires action before we have had a chance to talk through the project together.

Note on Amazon SP-API: Amazon's developer registration process can take several business days and requires approval from Amazon's side. It is worth starting that process as early as possible so it does not become a bottleneck once development is underway.

|  | Item | When Needed |
| ----- | :---- | :---- |
|  | **Database (SQL Server on AWS)** |  |
| ☐ | **Read access to the SQL database** Needed to review the Order, OrderLineItem, and OrderShipping table schemas before any code is written. Confirm whether access is via a connection string, VPN, or whitelisted IP. | **Needed at discovery** |
| ☐ | **Database connection details** Host/server address, port, database name, and a read-only login credential to start. Write access will be needed once development begins. | **Needed at discovery** |
|  | **Shopify** |  |
| ☐ | **Custom app credentials (Client ID and Client Secret)** Required scopes: read\_orders, write\_orders, read\_fulfillments, write\_fulfillments. Can be created under Settings \> Apps and Sales Channels \> Develop Apps in the Shopify admin. | **Needed at discovery** |
| ☐ | **Shopify admin access (view only)** Needed during discovery to inspect real order records, confirm the "rejected by Amazon" tag structure, and review a partially fulfilled order's line item data. | **Needed at discovery** |
|  | **Amazon SP-API** |  |
| ☐ | **SP-API application Client ID and Client Secret** Requires a registered developer app in the Amazon Solution Provider Portal. If one already exists, credentials can be shared. If not, registration will need to be initiated and can take several business days. | **Needed at discovery** |
| ☐ | **Seller Refresh Token** Generated when the seller authorizes the SP-API application. This is what allows the integration to act on behalf of the Amazon seller account. | **Needed at discovery** |
| ☐ | **Marketplace ID** The Amazon marketplace the seller operates in (e.g., ATVPDKIKX0DER for US). Usually found in Seller Central under Account Info. | **Needed at discovery** |
|  | **TikTok Shop API** |  |
| ☐ | **TikTok Shop developer app credentials** App Key and App Secret from a registered TikTok Shop developer application. If one does not already exist, TikTok's app approval process can take a few business days. | Needed before build |
| ☐ | **Seller authorization token** Generated when the TikTok Shop seller authorizes the developer app. Needed before the inventory and sales reconciliation phase can begin. | Needed before build |
|  | **WebBee** |  |
| ☐ | **Admin access to the WebBee account** Needed to review how WebBee is currently configured and confirm whether it is doing any order routing that would need to be accounted for or modified. | Needed before build |
| ☐ | **Contact for the current WebBee consultant** Angeline mentioned a consultant is already working on the Shopify side. A brief introduction would help avoid any overlap or conflicts. | Needed before build |
|  | **Sage X3** |  |
| ☐ | **Clarification on X3 and the database relationship** The key question is whether Sage X3 writes to the same SQL database the order workflow uses, or whether it has a separate API. If it shares the same DB, no additional credentials may be needed. This will be confirmed during discovery. | Needed before build |

Once the discovery call is complete, I will confirm exactly which items are needed and when, and can provide step-by-step instructions for generating any credentials that need to be created from scratch.

**Ian Bruce**  
ianbruce.me@gmail.com