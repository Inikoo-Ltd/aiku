---
title: Picking and packing a delivery note
summary: Follow a delivery note from the moment it lands in the warehouse through picking, packing and dispatch, and see what each button on the note actually does.
date: 2026-09-01
tags: dispatch, picking, packing
category: dispatch
help_routes: grp.org.warehouses.show.dispatching.delivery-notes, grp.org.warehouses.show.dispatching.picking_sessions
---

<aside class="tldr">
A <em>delivery note</em> is the warehouse's copy of an order: the list of what has to go out, and the record of what actually did. It moves through a fixed set of stages — to do, picking, picked, packing, packed, finalised, dispatched — and each stage change is a button you press once the work behind it is done. This article follows a note through the whole journey and shows where to find it at each stage.
</aside>

## Where delivery notes live

Inside your warehouse, **Dispatching → Delivery notes** lists every note, with a **Stats** tab and a **History** tab alongside the main list. The list itself is split into stage tabs down the side: **Dispatched**, **All**, **To do**, **Queued**, **Handling**, **Waiting**, **Picked**, **Packing**, **Packed** and **Finalised**. Each tab shows only the notes currently in that stage, with a count next to its name, so you can see at a glance how much is sitting where.

A note's stage is called its state. In order, a note passes through:

- **To do** — nothing has started yet.
- **Queued** — sitting inside a picking session, waiting for that session to start.
- **Handling** — being picked.
- **Waiting** — picking has stopped because something on the note needs a decision.
- **Picked** — every line has been picked.
- **Packing** — being packed.
- **Packed** — every line has been packed.
- **Finalised** — ready to leave, shipment details set.
- **Dispatched** — gone.
- **Cancelled** — the note was cancelled.

## Picking

A note becomes pickable either on its own, from the **To do** tab, or as part of a **picking session** — a batch of notes picked together. Picking sessions have their own screen, reached from **Dispatching → Picking sessions**, with the same kind of stage tabs: **In Process**, **Picking**, **Waiting**, **Picked**, **Packed**, and **All**.

Pressing **Start picking** on a note moves it to **Handling** and records who is picking it. If the note was queued inside a session, starting the session's own pick moves every note in it to **Handling** the same way, and whoever started the session becomes the picker on each note. A note already assigned to somebody else shows a locked padlock instead of the picking button — press **Unlock to pick** to take it over.

While a note is being picked, a line can turn out to need a decision the picker cannot make on the bench — for example a replacement or a release from the warehouse. When that happens the whole note moves to **Waiting** rather than letting picking continue around the problem. Once nothing is genuinely waiting any more, an **Auto Finish Waiting** button appears, and pressing it checks the note over and, if every line really is resolved, carries it on to **Picked**.

## From picked to packing

Once every line on a note is picked, it sits in **Picked** with a **Start packing** button. For most shops this is a separate step: pressing it moves the note into **Packing**, records who is packing it, and unassigns any picking bay that was holding it. For dropshipping shops, packing is skipped — from **Picked** the button reads **Set as packed** instead, taking the note straight to **Packed** in one step.

During packing, the note cannot be marked **Set as packed** if it still has lines waiting on a replacement decision or a warehouse release — that block has to clear first.

Pressing **Set as packed** records who packed the note, sweeps in any lines that were not confirmed line-by-line at the bench, and sets a default parcel if none has been recorded yet.

If a note needs to go back a step, editable notes carry undo buttons: **Undo set as picked** returns a **Picked** note to picking, **Undo packing** returns a **Packing** note to picked, and **Unpack** takes a **Packed** or **Finalised** note back to **Packing**.

## Finalising and dispatching with a shipper

Once a **Packed** note has its parcels recorded, it carries a single **Finalise and Dispatch** button (the label changes to **Dispatch** or **Finalise and set as Collected** for a replacement note or one going by collection instead of a shipper). Pressing it finalises the note — which is refused if no shipment has been recorded — and dispatches it in the same step: marking the note dispatched, recording the dispatch time on every line, and, for notes tied to a customer order, carrying the order itself on to dispatched.

A dispatched note can be pulled back with **Undispatch**, which returns it to packed.

## Cancelling a note

A note can be cancelled from any stage before it is finalised or dispatched — cancelling a finalised or dispatched note is refused. Cancelling releases anything already picked or packed back into stock, marks every line on the note as cancelled, and detaches it from any trolley or picking bay it was using. Where the note belongs to a customer order, the order itself is rolled back too, unless it has already been cancelled, finalised or dispatched.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>See delivery notes by stage:</b> your warehouse → <b>Dispatching → Delivery notes</b>, then pick a stage tab — <b>To do</b>, <b>Queued</b>, <b>Handling</b>, <b>Waiting</b>, <b>Picked</b>, <b>Packing</b>, <b>Packed</b>, <b>Finalised</b>, <b>Dispatched</b> or <b>All</b>.</li>
<li><b>Work through picking sessions:</b> your warehouse → <b>Dispatching → Picking sessions</b> → stage tabs <b>In Process</b>, <b>Picking</b>, <b>Waiting</b>, <b>Picked</b>, <b>Packed</b>.</li>
<li><b>Move a note along:</b> open the note and use its stage button — <b>Start picking</b>, <b>Auto Finish Waiting</b>, <b>Start packing</b> / <b>Set as packed</b>, <b>Finalise and Dispatch</b>, <b>Dispatch</b>. Undo buttons (<b>Undo set as picked</b>, <b>Undo packing</b>, <b>Unpack</b>, <b>Undispatch</b>) step it back.</li>
</ul>
</aside>

<aside class="permissions">
<strong>Permissions you need</strong>
To view a warehouse's delivery notes you need dispatching or fulfilment view access for that warehouse. Cancelling a delivery note additionally needs a dispatching supervisor, an organisation admin, or edit access to the shop's orders or CRM.
</aside>
