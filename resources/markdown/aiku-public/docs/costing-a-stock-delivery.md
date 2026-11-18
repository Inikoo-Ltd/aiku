---
title: Costing a stock delivery
summary: Enter what a delivery really cost - the agent or supplier invoice and the bills paid locally for freight, customs and transport - so the stock is valued at its landed cost.
date: 2026-11-18
tags: procurement, stock deliveries, costing, landed cost, service invoices, customs
category: procurement
help_routes: grp.org.procurement.stock_deliveries, grp.org.procurement.service_invoices, grp.org.procurement.dashboard
---

<aside class="tldr">
The cost of a delivery has two parts. The first is the <b>invoice of the agent or supplier</b>: the goods and whatever they charged on top. The second is the <b>landed costs</b>: every bill you pay to somebody else to bring the goods in, such as freight, customs clearance, duty and the lorry to the warehouse. aiku adds the two, shares the landed costs over the items, and values the stock at that total. Enter the costs before the goods arrive: from 12 October 2026 goods can not be put away until they are in.
</aside>

## Where the costing is

Open the delivery under **Procurement → Stock Deliveries**. Near the top of the page there is a table with three rows:

- **Agent invoice** or **Supplier invoice** - what the agent or supplier billed.
- **Landed costs** - what was paid locally to bring the goods in.
- **Landed total** - the two together.

Each row shows its **Status**, its **Amount** in the currency of your organisation and its **Balance due**. When the invoice is in another currency, that amount is shown small underneath. The rows arrive closed; click one to open it and see its lines and its buttons.

Costs can be entered as soon as the delivery is confirmed. You do not have to wait for the goods.

## The invoice of the agent or supplier

Open the invoice row and press **Invoice**. Enter the **Invoice number**, the **Invoice date**, the amount for the **Goods** and each of the agent's or supplier's own charges, marked **Freight** or **Other**. Attach the PDF under **Invoice file** and press **Save invoice**.

Until a real invoice is entered, a delivery that came from the old system shows an **Estimated** invoice built from what was recorded there. Saving the real invoice replaces the estimate, and the estimate is kept for comparison.

The same window holds the **Invoice exchange rate**, the **Settlement** with the deposits applied and the balance due, and the costs read from an attached invoice.

A container bought through an agent needs its invoice before it can be marked as dispatched, so its **Invoice** button is there while it is still in process.

### Reading the supplier invoice from its file

Attach the supplier's invoice to the delivery as **Invoice** or **Proforma**, then under **Costs from the supplier invoice** press **Read invoice** and, once it is read, **Review invoice**. It is read twice, by two different AI models, and aiku checks the reading the same way as a bill of the landed costs: the two readings must agree on every line, code, quantity and amount, every amount must be printed in the document, the lines and charges must add up to the total, and it must name the delivery or one of its purchase orders and no other delivery.

Each invoice line is matched to a product of the delivery by the supplier's code, then by our SKO code, then by its barcode (unit, SKO or carton). A line that matches none of them is suggested from its description when one product fits, and you confirm it. The amounts cannot be changed in the review. Each product's goods cost is the invoice price times what was received. You only say where an invoice line that matches no product goes (to a product, or **Invoiced but not in the delivery**) and what kind of expense each charge is, then press **Apply to costing**. A charge marked **Freight** becomes the shipping, shared by weight, and the forwarder's freight bills add to it; if the shipping was typed by hand, put the freight there instead. It can be applied once the delivery is checked, so the costs are in before the goods are put away.

When fewer were received than invoiced, the review says how much the invoice charges for goods that did not arrive: that money stays out of the goods cost, so ask the supplier for a credit. When more were received than invoiced, the extra goods are costed at the invoice price.

The reading is refused, and you enter the invoice by hand, when a check fails, when a product of the delivery is missing from the invoice, when an invoice price is far from the order price (the invoice may count packs or cartons), when the invoice has a discount or credit, or when the invoice of the delivery is already entered. An invoice that prints no total, and a container bought through an agent, are always entered by hand.

For a delivery bought through an agent, press **Agent payments** to approve the agent's charges and to record what you have paid the agent. The agent's charges only count in the costing once you approve them.

## The landed costs: one line per bill

Open the **Landed costs** row and press **Add** next to **Service invoices**. Enter one service invoice for each bill you received:

- the kind of bill (see the list below),
- who sent it and its reference,
- the date,
- the **currency** and the amount - the freight can be in dollars and the customs agent's bill in euros,
- the PDF of the bill.

One bill that covers several deliveries can be shared between them, by the value of their goods or by amounts you type.

The kinds of bill are:

| Kind | How it is shared over the items |
| --- | --- |
| Freight | by weight |
| Local transport (port to warehouse) | by weight |
| Customs duty | by customs line, or by value when no declaration is entered |
| Anti-dumping duty | by value, only over the products of the tariff codes you pick for it |
| Other import taxes (plastic, excise) | the same as customs duty |
| Import VAT | not costed: it is recovered in the VAT return |
| Customs clearance | by value |
| Port and terminal charges | by value |
| Storage and demurrage | by value |
| Inspection and certificates | by value |
| Documentation fees | by value |
| Insurance | by value |
| Fines | by value |
| Other | by value |

Freight is shared by value instead of by weight when a product in the delivery has no weight recorded; the window tells you which products.

Each bill fills the costing by itself, and a cost filled by a bill can only be changed by changing the bill. All the bills of your organisation are listed under **Service invoices**, reached from the heading of that list, where you can filter them by kind, sender, paid or unpaid, and period.

If a cost was already typed by hand, aiku warns you before you save a bill of the same kind, whether you type the bill or have it read from its file. A bill for shipping or duty replaces the amount typed by hand, and the warning shows both amounts when they are not the same. A bill for any other kind of cost counts next to the one typed by hand, so remove the typed one if it is the same cost. An amount typed as an estimate is simply replaced by the bill.

## Reading a bill from its file

Instead of typing a bill, aiku can read it. Open the **Landed costs** row and, under **Read from a document**, press **Read an invoice** and choose the PDF, the spreadsheet or a photo of the bill. It shows **Reading…** for up to a minute or two, then press **Review**.

The review shows who issued the bill, its reference, date and currency, and each line with its amount and the kind of cost aiku thinks it is. Check them against the bill and change the kind of a line if it is wrong. The amounts can not be changed: they are the ones printed on the bill. Press **Add to the service invoices**. Lines of the same kind become one service invoice, so a forwarder's bill with sea freight and customs clearance on it becomes two, each shared over the items its own way, and each keeps the file.

Nothing is costed until you press that button. The bill is read twice, by two different AI models, and aiku then checks the reading itself. It refuses the reading, and says why, when:

- the two readings do not agree on an amount, the reference, the date or the currency,
- an amount, the reference or the currency that was read is not printed in the document,
- the document does not mention the delivery, or mentions another delivery too, even one that is already costed or belongs to another organisation,
- the lines do not add up to the total of the bill,
- the bill has no reference, date or currency,
- a bill with the same reference is already entered, or one of the same amount is already on the delivery,
- the bill is larger than the value of the goods,
- the delivery is costed, cancelled or a partner delivery.

A refused bill shows **Enter it by hand**: add it with **Add**, as in the section above. A scan or a photo has no text for aiku to check, so it asks you to tick that you have checked every amount yourself.

VAT on the bill is left out of the cost, and is written in the notes of the service invoice.

## Typing a cost without a bill

Press **Edit landed costs** to open the costs as a table. Each cost has:

- **N/A** - tick it when the delivery has no such cost, for example no duty on a purchase inside the country.
- **Amount** and **Currency** - typing an amount marks the cost as received. The currency starts as your organisation's.
- **Rate** - only when the currency is neither the delivery's nor your organisation's.
- **Waiting for invoice** - see below.
- **Invoice** - **Upload** the PDF behind that cost.
- **History** - every change to that cost, with who made it and when.

A note can be added under each cost. **Add extra expense** adds a cost of one of the kinds in the list above; for **Other**, say what it is.

### Anti-dumping duty

Anti-dumping duty is charged only on some tariff codes of a container, so it has a row of its own and is never shared over the whole delivery. Under the row, pick the tariff codes it applies to: the list shows each tariff code of the delivery with its products. The duty is then shared by value only over the products with those codes, and counts as duty in their cost. The row says how many products it is on.

Until the tariff codes are picked the delivery can not be costed, and the row says so in red. A product with no tariff code can not be picked; the row lists those products so you can give their trade unit a tariff code first. Until the delivery is costed for the first time, the duty follows the products that carry the picked codes, so a tariff code corrected later is taken into account. After that it stays on the products it was costed on.

An anti-dumping bill entered under service invoices gets its own row in the same way: its amount comes from the bill, and you pick its tariff codes on the delivery.

## When a bill has not arrived yet

Tick **Waiting for invoice** on that cost and, if you know, type in how many days you expect it. The delivery page then shows that the total will increase.

If you type an amount while the cost is waiting, it is an **estimate**. An estimate counts in the costing, so the delivery can be costed and the stock valued, and the delivery stays marked **Costed with an estimate** until the real bill replaces it.

## When the costing is finished

A delivery is costed when its invoice is in and its shipping, duty and extra costs each have an amount, an estimate or **N/A**. At that moment the stock put away from the delivery takes its landed cost. The **Landed total** row then lists every cost with its percentage of the goods, and says how much extra cost there is for every euro of goods.

After that the costs are locked. To correct them, or to replace an estimate with the real bill, an accounting manager presses **Update costing**, makes the changes and presses **Finish costing**. The stock is valued again and the change is recorded with their name and reason.

## Costs come before the goods

For deliveries created from 12 October 2026:

- **Putting away is blocked** until the invoice, the shipping and the duty each have an amount, an estimate or **N/A**. The goods can be received and checked, but they can not go onto a shelf, so nothing is sold at the wrong cost.
- **Marking a delivery as received** while costs are missing asks for a reason. The reason is written in the delivery's history and the procurement supervisors are told.

Partner deliveries are not affected: they are costed from the partner's order prices.

## Seeing what still needs costing

The **Procurement** dashboard opens with a bar showing how many deliveries are still to cost, and how many of them are held up. Each figure opens the stock deliveries list on its filter. In the list itself, the **Costing** filter offers **To cost**, **Costs missing, put-away blocked** and **Costed with an estimate**.

## Through your AI assistant

People who cost deliveries can ask their AI assistant to show the costs of a delivery or to save its shipping, duty and extra costs. It shows what will change first and saves only after you confirm. It refuses a delivery that is already costed, and a total of costs higher than the value of the goods unless you confirm, which usually means an amount in the wrong currency. Every save is kept in the AI changes log and can be reverted there. Import VAT, the agent or supplier invoice and the PDF files are entered on the page, not through the assistant.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Open a delivery's costing:</b> your organisation → <b>Procurement → Stock Deliveries</b> → the delivery → the table with <b>Agent invoice</b>, <b>Landed costs</b> and <b>Landed total</b>.</li>
<li><b>Enter the invoice:</b> open the invoice row → <b>Invoice</b> → <b>Save invoice</b>.</li>
<li><b>Approve an agent's charges or record a payment:</b> open the invoice row → <b>Agent payments</b>.</li>
<li><b>Add a bill:</b> open <b>Landed costs</b> → <b>Add</b> next to <b>Service invoices</b>.</li>
<li><b>Have a bill read from its file:</b> open <b>Landed costs</b> → <b>Read an invoice</b> under <b>Read from a document</b> → <b>Review</b> → <b>Add to the service invoices</b>.</li>
<li><b>Type a cost, mark it N/A or waiting:</b> open <b>Landed costs</b> → <b>Edit landed costs</b>.</li>
<li><b>Correct a costed delivery (accounting managers):</b> <b>Update costing</b> at the top of the delivery, then <b>Finish costing</b>.</li>
<li><b>See what is waiting:</b> <b>Procurement</b> dashboard, the bar at the top; or the <b>Costing</b> filter of the stock deliveries list.</li>
<li><b>Let someone save costs through their AI assistant (administrators):</b> <b>Sysadmin → Users</b> → open the user → <b>Edit</b> → switch on <b>Can save stock delivery costs through their AI assistant</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permissions you need</strong>
You need permission to view procurement or accounting for the organisation to see the costing, and permission to edit procurement or accounting to enter costs and bills. Changing a delivery that is already costed needs the accounting manager position. Putting goods away and marking a delivery as received follow the warehouse goods-in permissions.
</aside>
