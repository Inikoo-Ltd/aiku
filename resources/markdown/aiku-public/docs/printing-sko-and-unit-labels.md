---
title: Printing SKO and unit labels
summary: Print the label that goes on the product and the one that goes on the box, straight from the SKO page or from a line on a delivery note, on the roll you already buy or 27 up on an A4 sheet.
date: 2026-09-23
tags: warehouse, inventory, labels, barcodes, printing
category: warehouse
help_routes: grp.org.warehouses.show.inventory.org_stocks, grp.org.warehouses.show.dispatching.delivery_notes
---

<aside class="tldr">
A SKO can print <b>two different labels</b>. The <b>unit label</b> goes on the product a customer ends up holding, so it carries the origin, the manufacturer, the weight and your company's address, and it is built around the unit EAN13. The <b>SKO label</b> goes on the outer box, so it says what the box is and how many are in it, in type you can read from a few metres down an aisle. Open either from the SKO page or from a line on a delivery note, tick what you want on it, pick your label size, and print one or a sheet of 27.
</aside>

## The two labels

They are not the same label at two sizes. They are for two different readers.

| | Unit label | SKO label |
| --- | --- | --- |
| Goes on | the product | the outer box |
| Read by | whoever ends up holding it | anyone walking the aisle |
| Barcode | the unit EAN13 | the SKO barcode, run full width |
| Can carry | image, made in, manufactured by, weight, custom text, account signature | image, custom text |
| Sizes | seven | four |

The unit label is the one with the small print. It names where the goods came from and who made them, states the weight and signs the organisation underneath, because that is what a label on a product is for. The SKO label throws all of that away: the code is set reversed out of black, the count and the name sit under it, and the barcode runs the full width of the label.

## Printing one

Open a SKO and find the barcodes. Click either barcode — the SKO one or the unit EAN — and the label panel opens on that one. If only one of the two can be printed, the panel opens on whichever it is; if both can, a small **SKO / Unit** switch appears at the top and you can move between them without closing the panel.

From a delivery note it is quicker. Every line in the **Items** tab has a small PDF icon next to the code. Press it and the same panel opens for that SKO, without leaving the delivery note.

## Choosing what goes on it

Every element is a tick box, and what is ticked by default is what Aurora printed by default: everything the SKO has a value for, except the account signature and the custom text, which stay off until you ask for them.

**A tick box you cannot press means the SKO has nothing to print there.** Hover it and it tells you which — *This item has no image*, *This item has no country of origin*, *This item has no manufacturer*, *This item has no weight*. That is the SKO's data talking, not the label: fill the field in on the trade unit and the box comes alive. It works this way so nobody prints a label with a hole in it.

**Custom text** is free text for this print run only. It is not saved against the SKO. Use it for the things that change between runs and belong to nobody's record.

## Sizes and sheets

The sizes are the label stocks Aurora offered, to the millimetre, so a roll bought for the old system still prints straight out of this one.

| | Sizes offered |
| --- | --- |
| Unit label | 63 × 29.6, 63.5 × 29.6, 70 × 29.7, 70 × 30, 125 × 37, 130 × 60, 140 × 90 |
| SKO label | 63 × 29.6, 63.5 × 29.6, 70 × 29.7, 130 × 60 |

The SKO label is offered on four because it carries far less, and the four are the ones that suit a box.

**Layout** is either a single label, cut to its own size, or **A4 27 labels (EU30161)** — three across and nine down on a sheet of ordinary A4. The sheet is die cut to 63.5 × 29.6, so when you choose it the size picker steps aside and says so. Every one of the 27 is the same label; a sheet is for printing a run of one SKO, not a mixed page.

Type, barcode and picture all scale with the label you choose rather than being fixed, so a 140 × 90 fills its space and a 63 × 29.6 stays readable.

## When a label will not print

The unit label is built around its barcode, so a SKO with no unit EAN13 cannot print one. The panel says so and the PDF button greys out. Put the EAN on the SKO first — the unit EAN has its own editor on the SKO page — and the label prints.

The SKO label is more forgiving. It prints for a box that has not been given a barcode yet; it simply prints without one. See [Checking SKO barcodes with a scanner](/docs/checking-sko-barcodes-with-a-scanner) for putting the outer barcode on the right SKO in the first place.

## What the wording comes from

Nothing on the label is typed by hand except the custom text. Each line is read off the record, so correcting a label means correcting the record.

| On the label | Comes from |
| --- | --- |
| Code and name | the SKO's code, and its trade unit's name |
| **6x** before the name | how many units are packed in the box |
| *Imported from … by …* | the trade unit's country of origin, and your organisation |
| *Manufactured by …* | the trade unit's GPSR manufacturer |
| Weight | the trade unit's marketing weight |
| The address block | your organisation's address and phone |
| Picture | the first trade unit that has a main image |

Where a SKO holds several trade units that disagree on one of these, the line is left off rather than guessed: a label naming one country for a box holding goods from two would be wrong rather than merely short.
