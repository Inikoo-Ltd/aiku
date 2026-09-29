---
title: Changing GPSR and labels on many trade units at once
summary: Tick the trade units, press Bulk Edit, fill in the GPSR or Labeling & Compliance Marks once, and give it to all of them in one go instead of opening each trade unit.
date: 2026-09-29
tags: trade units, gpsr, labels, compliance, catalogue
category: shop
help_routes: grp.trade_units.units.index, grp.trade_units.units.active, grp.trade_units.units.in_process, grp.trade_units.units.discontinuing, grp.trade_units.units.discontinued, grp.trade_units.units.anomality, grp.trade_units.families.show
---

<aside class="tldr">
For whoever keeps the product safety and label details up to date. When a group of trade units share the same details, for example a whole range of candles from one supplier, you no longer have to open each one. Tick them in the list, press <b>Bulk Edit</b>, and fill in the <b>GPSR</b> or the <b>Labeling & Compliance Marks</b> once. Save a single field with the save icon next to it, or the whole section with the button at the bottom.
</aside>

## Finding it

Bulk edit lives on two lists:

| Where | What you see |
| --- | --- |
| **Trade Units** in the left menu | every trade unit in the group, with the Active, In process, Discontinued and All tabs across the top |
| A trade unit family, **Trade units** tab | only the trade units in that family |

Both lists have a tick box at the start of every row. Tick the trade units you want to change. The box in the table header ticks every trade unit on the page.

You can move to the next page of the list and keep ticking. The ones you ticked on earlier pages stay ticked.

Then press **Bulk Edit** at the top right of the page. The button shows how many trade units you have picked, for example **Bulk Edit (12)**. It stays greyed out until you tick at least one.

## The bulk edit window

The window has three parts.

**On the left, the sections.** There are two: **GPSR** and **Labeling & Compliance Marks**. Click one to show its form. You can fill in both, one after the other.

**In the middle, the form.** It has the same fields you know from a trade unit's edit page, but it always starts empty. It does not show what the trade units have today, because each of them might have something different.

**On the right, the trade units you picked.** Each one shows its picture, code and name. If you spot one that should not be there, press the **×** next to it. It comes off the list and is unticked in the table behind the window too.

## What goes in each section

**GPSR** is the product safety information the law asks for:

- **Manufacturer Details** and **EU Responsible Person**: name and postal address of each.
- **Warnings & Precautions** and **Directions for Use**: the words printed on the label and shown on the product page.
- **Hazard Class & Category**: the CLP / GHS classification, if the product has one.
- The **hazard pictograms**, such as Flammable or Acute Toxicity. Switch on each one that applies.

**Labeling & Compliance Marks** is what is printed on the label itself:

- **Publish Regulatory & Label Information**: switch this on only once everything below has been checked. While it is off, the Regulatory & Label Information tab stays hidden on the website.
- **Markets**: UK, EU and Other International Markets. Tick every market the label is made for.
- **Languages** the label is written in.
- **PAO / Expiry Date / Best Before**.
- **Packaging Material Codes**, with a switch to show or hide them.
- The marks that are present on the label: **Batch Number**, **CE**, **UKCA**, **WEEE**, **IP Rating**, **Sorting / Recycling Information** and **Safety Icons** (candles only).
- **Show Net Quantity**: switch off to hide the net quantity on the product page.

## Saving

There are two ways to save. Pick the one that matches what you want to change.

**Just one field: the save icon next to it.** Each field has a small save icon on its right. It lights up green once you change that field. Press it and only that field is applied to the trade units you picked. Everything else they have stays exactly as it was. This is the one to use for small jobs, like setting the markets on a range without touching its warnings or languages.

**The whole section: the button at the bottom.** It says what it is about to do, for example **Replace GPSR on 12 trade units**. It applies every field of the section you are looking at, all in one go. Use it when you want a group of trade units to end up with exactly the same details.

Either way, only the section on screen is saved. If you want to change something in both sections, save one, click the other on the left, and save that too. The window stays open after you save, so you can carry on. Press **Close**, or click anywhere outside the window, when you are done.

Small icons tell you where you are, and you can hover over any of them to see what it means:

- Next to a field, a **green save icon** means you changed it and have not applied it yet. A **green tick** means you applied it and have not changed it since.
- Next to a section name, an **amber triangle** means something in that section is not applied yet. A **green tick** means everything you changed there has been applied.

A field or section you have not touched shows nothing.

## The button at the bottom replaces the whole section

This is the part to be careful with. When you use **Replace**, every trade unit you picked ends up with exactly what is in the form:

- **Every field is saved, including the ones you left empty.** An empty Manufacturer Details box clears the manufacturer on all of them. A switch you left off turns that mark off on all of them.
- **Anything the trade units had before in that section is replaced.** It does not add to it. If you tick only UK under Markets, trade units that were also sold in the EU lose the EU tick.
- **The other section is left alone.** Replacing GPSR does not touch the labels, and the other way round.

If you only meant to change one or two fields, use their own save icons instead. The yellow box above the buttons reminds you of this each time.

**Publish Regulatory & Label Information starts switched off.** If you replace the Labeling section without switching it on, the Regulatory & Label Information tab is hidden on the website for every trade unit you picked, even the ones where it was showing before.

**A single field still replaces that field.** Saving Markets with only UK ticked leaves every picked trade unit with just UK, even the ones that also had EU before. It does not add UK to what they had.

## What happens after you save

**The products follow.** Products and master products made from these trade units pick up the new details straight away, in every shop that sells them.

**Products made of several trade units combine them.** A few of the details are worked out from all the trade units in a product:

- The Regulatory & Label Information tab only shows on the website once **every** trade unit in the product is published.
- A market only shows when **every** trade unit in the product has it.
- Languages and the marks on the label (CE, UKCA and the rest) show when **any** of the trade units has them.

**Every change is written down.** Open a trade unit and click the **History** tab, the clock at the right of the tabs. You see who changed what and when, with the old value struck through next to the new one. Bulk edits show up there the same way as changes made one at a time.

## Things worth knowing

- **Check the list on the right before you save.** There is no undo. To go back, you would have to set the old details again.
- **Big selections take longer.** Every product that uses the trade units is updated too, so saving a few hundred trade units at once can take a while. Wait for the green message before closing the window.
- **Only the details in these two sections change.** Names, weights, barcodes, prices and stock are not touched.
- **One trade unit on its own** is still easier from its own page. Open it, press the pencil, and use the GPSR or Labeling & Compliance Marks section on the left. That page shows what the trade unit has today.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Every trade unit:</b> <b>Trade Units</b> in the left menu.</li>
<li><b>One family:</b> <b>Trade Unit Families</b> at the top → the family → <b>Trade units</b> tab.</li>
<li><b>Start:</b> tick the rows → <b>Bulk Edit</b> at the top right.</li>
<li><b>Switch section:</b> <b>GPSR</b> or <b>Labeling & Compliance Marks</b> on the left of the window.</li>
<li><b>Save one field:</b> the save icon on the right of that field.</li>
<li><b>Save the whole section:</b> <b>Replace</b> at the bottom of the window.</li>
<li><b>See what changed:</b> open a trade unit → <b>History</b> tab.</li>
<li><b>One trade unit only:</b> open it and use the pencil, the same fields are there.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Seeing the trade unit lists: goods viewing rights.</li>
<li>Using bulk edit: goods editing rights, which come with the <b>Goods manager</b> role. Without it the tick boxes and the Bulk Edit button do not appear at all.</li>
<li>If you need it and do not have it, ask an admin to give you the Goods manager role.</li>
</ul>
</aside>
