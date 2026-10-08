---
title: Uploading new supplier products
summary: Fill in the supplier products template, upload it on the supplier's page, check every row in the preview, decide what needs a decision and import. Aiku creates the trade units, SKOs, supplier products and the draft purchase orders in one go, and nothing is created until you press Import.
date: 2026-10-07
tags: procurement, supply chain, products, upload
category: procurement
help_routes: grp.supply-chain.suppliers.supplier_products.index, grp.supply-chain.suppliers.supplier_products.uploads.show, grp.supply-chain.suppliers.supplier_products.create
---

<aside class="tldr">
Download the template from the supplier's <b>Products</b> page, fill one row per product, and upload it with <b>Attach file</b>. Aiku reads the sheet, checks every row and opens a <b>preview</b>. Rows marked <b>Fix in the sheet</b> must be corrected in the file. Rows marked <b>Needs a decision</b> need you to tick <b>This is OK, I accept responsibility</b> (or skip the row). When nothing is left to fix or decide, press <b>Import</b>: aiku creates the families, trade units, barcodes, SKOs, supplier products and the draft purchase orders. Until then nothing is created, and <b>Cancel upload</b> throws it all away. For a single product, <b>New Supplier Product</b> does the same from a form, without a sheet.
</aside>

## What one row becomes

Each row of the sheet describes one product the supplier sells us, and Import turns it into:

| Created | From the columns |
| --- | --- |
| **SKO family** and **trade unit family** | Family |
| **Trade unit** (the single item a shopper buys) | Part reference, Unit recommended description, Unit label, Unit weight, Unit dimensions, Materials, Tariff code, Unit barcode |
| **SKO** (what the warehouse picks) | Part reference, Units per SKO, SKO weight, SKO dimensions |
| **Supplier product** (what we buy from this supplier) | Supplier's product code, Unit cost, Unit expense, Extra costs %, SKOs per carton, Minimum order, Average delivery time, Carton CBM, Carton Weight |
| **Recommended prices** for the future master product | Unit recommended price and RRP in £ and € and Recommended SKOs per selling outer |
| **Draft purchase orders** | Order Cartons UK / SK / ES / Aroma |

If the Part reference already exists, nothing new is made: the supplier is added to that trade unit as another source, and only its empty fields are filled.

## The template

Download it from the supplier's **Products** page. The upload reads columns by the **heading** in the heading row, so you can move columns around or add your own working columns; anything aiku does not know is ignored. Notes above the heading row are fine.

The row above the headings says **Required** or **Opt**. If a Required heading is missing, the whole file is refused straight away and the message names the missing column.

A few rules that save most mistakes:

- **One row is one single unit.** "Unit recommended description" is the name of one item, never "Pack of 6 …". The pack is described by Units per SKO.
- **Supplier's product code** can be left empty: the Part reference is used.
- **Money columns are in their own currency**: Unit cost and Unit expense in the supplier's currency, the recommended prices in £ and €. A cell formatted or typed in another currency is refused.
- **Weights in kg, sizes in cm** written as 20x10x5, Carton CBM in m³, Extra costs as 40% or 0.4.
- **Unit barcode**: a real EAN, or `auto` to take the next free barcode from the pool at Import. Empty means no barcode and needs a decision.
- **Units per SKO, SKOs per carton and Minimum order** are whole numbers.

## Compliance data (v7 template)

The template has three tabs. **Product data** keeps every column above in place. After them come optional columns for GPSR, the product's regulatory category and EUDR. The two other tabs hold what one row per product cannot. All of it is optional: a file without these columns or tabs uploads exactly as before.

| Where | What it becomes |
| --- | --- |
| **Product data**: Manufacturer, EU responsible person, Warnings and safety information, Instructions for use, Languages of warnings and instructions | The trade unit's GPSR fields, which the products selling it show and translate |
| **Product data**: Brand, Batch traceability, Regulatory category, Toy status, Batteries / magnets, SVHC above 0.1%, SVHC substance, CLP signal word, Material composition (% by weight) | The trade unit's **Compliance** tab |
| **Product data**: EUDR status, commodity, species (scientific name), country of production, region of production, plot geolocation, certification, legality evidence | The EUDR block on the trade unit's **Compliance** tab |
| **Packaging components**: one row per component, per packaging level, per part | The trade unit's packaging family (PPWR, EPR returns) |
| **Supplier declarations**: company, signed by, position, date and one answer per statement | A signed declaration on the supplier's **Declarations** tab |

**Packaging components** columns: Part reference, Packaging level (Primary, Secondary, Tertiary, Pallet or Service), Component, Material, Material code (PAP 20, PE-LD 4 …), Weight (g), Quantity at this level, Recycled content %, Recycled content evidence, Recyclability, Separable, Marks on the packaging, National marks, Artwork owner, Notes. Quantity is how many of the component there are at its level: 2 labels on one bottle is 2. Aiku works out how much of it one sales unit carries. A Secondary component is shared by the units in the SKO, and a Tertiary carton by every unit in it. Pallet aids are not counted per unit.

Packaging is entered once. Parts packed exactly the same way share one packaging family, and a component already known, such as the same bottle or carton, is shared rather than copied.

As with the other columns, compliance data only fills what is empty. A trade unit that already has GPSR text, a compliance answer or a packaging family keeps it.

The preview warns, without stopping the import, when:

- EUDR status says Yes but the commodity, country of production, plot geolocation or legality evidence is missing. EUDR applies to AW from 30 Dec 2026, and AW files the due diligence statement, so it needs these.
- Material composition does not add up to 100%.
- An SVHC above 0.1% is declared without naming the substance.
- A packaging row has no weight, or a level aiku does not know (the row is then left out).

The preview also lists packaging rows with no Part reference, or one that is not on Product data, and any declaration statement not answered Yes, unanswered ones included.

**Supplier declarations** reads Company, Signed by, Position and Date (each as a label with its value next to it), then a **Statement | Answer** heading with one statement per row. Dates are read day first: 01/10/2026 is 1 October. The declaration is kept when at least one row is imported, once per upload: uploading the file again keeps it again, dated by that upload.

## Uploading

Open the supplier, go to **Products**, press **Attach file** and choose the file. A few seconds later the preview opens.

## Adding one product without a sheet

For a single product, press **New Supplier Product** on the supplier's **Products** page. The form has the same fields as the template, with the same headings, grouped into Product, Packing and ordering, Cost and prices, and Weights and sizes. The same rules apply: one unit per product, money in the column's currency, weights in kg, sizes as 20x10x5, `auto` for a pool barcode.

Saving takes two steps:

1. **Save.** As you type, the field checks of the upload already show under each field. Pressing **Save** runs all of them again plus the same AI checks an upload gets: the per-product questions and the AI review. This takes up to a minute. If nothing comes up, the product is created straight away.
2. **Check before saving.** If anything comes up, a window opens with the AI's suggested fix, every finding, and the fields concerned, so you can correct them there. Tick what you accept, then press **Submit**. Submit is final: the AI is not asked again. If you change a field the AI warned about, the warning goes. A decision the AI asked for stays, marked as about the earlier value, and still needs a tick. Changing the Part reference needs a new Save.

The findings use the same colours as the preview:

- **red**: must be fixed;
- **orange**: needs a decision. Tick **This is OK, I accept responsibility** if it is right. If you change the field and the message changes, tick it again;
- **blue**: the Part reference already exists. Tick **Add this supplier to it** to add the supplier to that trade unit as another source;
- **amber**: worth a look, nothing to tick.

If the AI checks cannot run, the window asks for **I accept responsibility** instead, as an upload does.

When an SKO holds more than one unit, **SKO name** appears under Packing, pre-filled as "Pack of N …". Change it if the wording is wrong.

Submit creates the families, trade unit, barcode, SKO and supplier product in one go, exactly as Import does for a row, and opens the new supplier product. Every organisation that buys from the supplier gets the supplier product straight away, and its SKO the first time it orders the product.

The form does not order cartons. Add the product to a purchase order afterwards, or use the sheet when you also want draft purchase orders.

## The preview

The preview lists every row with what aiku found, in three kinds:

| Mark | Meaning | What to do |
| --- | --- | --- |
| **Fix in the sheet** | The row cannot be imported as it is: a required cell is empty, a number is not a number, the barcode is wrong, a price is in the wrong currency, the same Part reference appears twice | Fix the file and upload it again, or skip the row |
| **Needs a decision** | The row can be imported, but something looks risky: a name that reads like a pack, a margin below target, an existing product that will be updated, no barcode, a carton that does not split into outers | Tick **This is OK, I accept responsibility** if it is right; aiku records who ticked it and when. Otherwise fix the file |
| **Check** | Worth a look, does not stop anything: a new family, a weight or size that looks odd, values far from the supplier's other products | Read it; nothing to tick |

**Skip row** leaves a row out of this import. **SKO name** appears when an SKO holds more than one unit: it is pre-filled as "Pack of N …" and you can change it.

**Import** stays disabled while any row still has something to fix or decide; the list at the bottom says exactly what it is waiting for.

### What aiku checks

- **Margins**: our margin on the recommended price must be at least 60% after the landed cost (unit cost plus unit expense plus extra costs, converted at aiku's exchange rate). The retailer's margin (RRP against our price) is usually about 58%; below 50% needs a decision.
- **£ and € prices** must agree with each other within 25% after conversion.
- **Packing**: a carton must split into whole selling outers; units must fit in the SKO and SKOs in the carton; weights must add up and the density must be believable.
- **Families**: a new family is flagged, and so is one that looks like an existing one (a typo). A Part reference whose prefix does not match the family's other products is refused.
- **Existing products**: an existing Part reference links to that trade unit; an existing supplier code updates that supplier product, and a cost change above 20% needs a decision.
- **Minimum order**: the cartons ordered across organisations must reach the supplier's minimum.

### AI checks

While you read the preview, two AI checks run in the background and the page updates by itself:

- every row is checked for spelling mistakes, materials that do not fit the item, numbers that look wrong for the item, a product in the wrong family and rows that look shifted;
- the whole sheet is then reviewed: a short **AI review** appears at the top and an **AI suggests** note under the rows that need a change, with the exact fix.

Import waits for them. If the AI cannot run, each row asks for **I accept responsibility** instead, so an upload is never stuck.

## Draft purchase orders

The **Order Cartons UK / SK / ES / Aroma** columns order cartons for each organisation. The preview shows, per organisation, how many cartons and lines will be ordered and on which order:

- if the organisation already has an **open draft** for this supplier (sent through the agent when the supplier has one), the lines are added to it and the sheet sets the quantity;
- otherwise a new draft is made. Tick **New draft instead** to always get a new one.

Lines already on the draft that are not in the sheet are left alone. The orders stay drafts until someone submits them.

## Importing

Press **Import**. Each row is created on its own, so one failing row does not stop the others; the page then shows which rows were created, skipped or failed, and the draft purchase orders that were filled.

## Uploading the same sheet again

You can upload a corrected file as often as you need. Rows whose supplier code already exists are offered as an **update** of that supplier product, and rows whose Part reference exists are linked, never duplicated.

## After the import

When someone creates the **master product** from one of these trade units, its £ and € price and RRP are pre-filled from the recommended prices in the sheet, and the form shows the recommended number of units per outer. On purchase orders, the supplier's unit expense estimate is added as **Estimated total incl. supplier expenses** under the real total, to help with budgets.
