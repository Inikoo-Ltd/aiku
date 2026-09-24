---
title: Repacking a finalised delivery note
summary: What happens when a finalised delivery note is unpacked and packed again, why it goes straight back to finalised, and what to do when aiku refuses because the picks no longer match the invoice.
date: 2026-09-15
tags: dispatch, packing, invoices, accounting
category: dispatch
---

<aside class="tldr">
<b>Warehouse:</b> you can still <b>Unpack</b> a finalised note to fix something. When you pack it again it goes straight back to <b>Finalised</b>, and so does its order. Then press <b>Dispatch</b> as normal.<br>
<b>If aiku refuses to pack:</b> the quantities picked are no longer the ones on the invoice. Either put the picks back to what was invoiced, or ask accounts to refund the difference, then pack again.
</aside>

## Why a finalised note is special

Finalising a delivery note does two things at once: it marks the note ready to leave, and it creates the order's **invoice** from what was picked. From that moment the invoice is the record of what the customer is paying for.

A finalised note can still be taken back with **Unpack**, for example to open a box and check or correct what is inside. The note and its order go back to **Packing**. The invoice stays exactly as it was.

## Packing it again

When the note is packed again, with **Set as packed** or by scanning the last item, it does not stop at **Packed**. Because its order already has an invoice, the note and the order go straight back to **Finalised**. The invoice, the order amounts and any shipment already recorded are left untouched.

From there the note shows its **Dispatch** button, and you carry on as with any finalised note.

## When aiku refuses to pack

Before sending the note back to finalised, aiku checks every line: the quantity picked now must be the quantity on the invoice. If a line was picked differently while the note was unpacked, packing is refused with a message naming the invoice and linking to this page. The note stays in **Packing**; nothing else changes.

Going back to finalised in that case would send the customer something different from what they were invoiced for, so one of these has to happen first:

- **The picks were a mistake.** Step the note back (**Undo packing**, then **Undo set as picked**), pick the lines again to the invoiced quantities, and pack.
- **Less is really going out.** Ask accounts to raise a **refund** on the invoice for the items that will not be sent. Once the refund is in, pack the note again. See [Invoices, payments and refunds](/docs/invoices-payments-and-refunds).
- **More is really going out.** Do not add it to this note. Pick the invoiced quantities here, and ask customer services to put the extra goods on a new order.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Find the note:</b> your warehouse → <b>Dispatching → Delivery notes</b> → <b>Packing</b> tab (after unpacking) or <b>Finalised</b> tab (after packing again).</li>
<li><b>Unpack and pack again:</b> open the note → <b>Unpack</b>, then <b>Set as packed</b> when the box is ready. Then <b>Dispatch</b>.</li>
<li><b>Step back to re-pick:</b> on a <b>Packing</b> note, <b>Undo packing</b>; on a <b>Picked</b> note, <b>Undo set as picked</b>.</li>
<li><b>Check the invoice:</b> your organisation → <b>Accounting → Invoices</b> → open the invoice named in the message → <b>Transactions</b> and <b>Refunds</b> tabs.</li>
</ul>
</aside>

<aside class="permissions">
<strong>Permissions you need</strong>
Unpacking and packing need dispatching access for the warehouse. Raising a refund is done by accounts staff with access to the organisation's invoices.
</aside>
