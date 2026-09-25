---
title: Making compliance labels
summary: For compliance staff — how a SKO gets its compliance label: who decides what it must show, how to place names, ingredients, warnings in every language, symbols and free text, where each piece of information comes from, and how a label is published so agents can print it.
date: 2026-09-25
tags: warehouse, inventory, labels, compliance, printing
category: warehouse
help_routes: grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show.labels, grp.org.warehouses.show.inventory.org_stocks.active_org_stocks.show.labels, grp.org.warehouses.show.inventory.org_stocks.abnormality_org_stocks.show.labels, grp.org.warehouses.show.inventory.org_stock_families.show.org_stocks.show.labels
series: Compliance labels
order: 1
---

<aside class="tldr">
Every SKO can carry its own compliance label: the product name, weight, ingredients, warnings and directions in each language, the responsible person, the recycling and safety symbols, and the batch code and expiry date. Three people share the work. The <b>Compliance Manager</b> decides what every label of a SKO must show, the <b>Compliance Worker</b> designs the label, and the <b>Compliance Supervisor</b> publishes it. Once published, the agents who buy that product for us print it with the batch code and expiry date of the goods, as explained in <a href="/docs/printing-labels-as-an-agent">printing labels as an agent</a>.
</aside>

## Where compliance labels live

Open a SKO and pick **Labels**, next to **Batch codes**. The page has two tabs.

- **Labels** — what every label must show, the labels already made for this SKO, and the button to make a new one.
- **Compliance** — the list of certificates, safety tests, tariff codes and other documents the product needs, each with its reference, the dates it is valid between and whether it is compliant.

A label belongs to the SKO of the organisation that buys the product. The same product bought in the UK and in Slovakia has two SKOs, so each can carry the right responsible person and languages for its own market.

The factory reaches the same labels from its artefacts. How the label editor itself works — the artwork, the grid of labels on the A4 sheet, the barcode — is explained in <a href="/docs/designing-and-printing-labels">designing and printing labels</a>. This guide covers what a compliance label adds.

## Who does what

| Position | What they do |
| --- | --- |
| **Compliance Manager** | Ticks the information every label of a SKO must show. |
| **Compliance Worker** | Designs labels and keeps them up to date. |
| **Compliance Supervisor** | Checks labels and publishes them, so agents can print them. |

Agents never design or change a label. They only see labels once they are published.

## Deciding what a label must show

At the top of the **Labels** tab is the **Mandatory information** box. The Compliance Manager ticks what every label of this SKO must show — for example the ingredients, the EU responsible person, the warnings in German and the recycling symbols — and presses **Save**.

Each label in the list then shows every mandatory item as a small chip: green with a tick when the label has it, red with a warning sign when it is missing. **A label with a red chip cannot be published.**

Sometimes the information is already printed on the supplier's box. In that case tick **on artwork** on the chip: the label counts it as present without printing it twice.

## Placing the information

Press **New label**, give it a name and, if you have it, upload the supplier's artwork as the background. Then add what the label must show.

- **Batch code**, **Expiry date** and **Barcode** have their own buttons. The batch code and expiry date on the design are only examples: the real ones are typed in each time the labels are printed.
- Everything else is in the **+ Product information** menu. Pick an item and it lands on the label with the text or the symbols taken from the product record. Drag it into place.

An item the product record does not have yet is shown in the menu as *not on the product record* and cannot be picked. Fill in the product record first, then come back to the label.

**The text is copied when you place it.** If the product record changes later, remove the item from the label and place it again, so the label reads the new text.

### Texts in several languages

The product name, the warnings and the directions for use are offered once per language: *Warnings (German)*, *Warnings (French)* and so on. Place one block for each language the label needs.

The languages offered are the ones the product record asks for, plus every language the product texts are already translated into. A language with no translation yet is shown as *type it in*: place it and type the text on the label.

Long texts such as ingredients, warnings or an address should wrap. Select the item, tick **Wrap in a box** and set the width in millimetres; the text then breaks into lines inside the box and keeps its own line breaks.

### Symbols

These are placed as pictures, and each one only when the product record says the product carries it.

| Symbol | Shown when the product record has |
| --- | --- |
| Hazard pictograms | The hazards ticked on the product. |
| Packaging material marks, such as PET 1 or PAP 21 | Its packaging material codes. |
| CE, UKCA and WEEE marks | The matching mark ticked. |
| Open jar with the months, such as 12M | A period after opening (PAO) chosen as its best before. |
| French sorting logo and instruction ("FR", "Cet emballage se trie") | **Sorting / Recycling Information** ticked. |

Select a symbol to change its height in millimetres.

### Free text

**Free text** is for anything that is not product information: fixed headings such as "Weight / Peso / váha / Waga / Poids / Gewicht", or "Ingredients / Ingrédients / Inhaltsstoffe". Place it and type what the label should say. Free text is never taken from the product record, so it cannot be made mandatory.

## Where the information comes from

| On the label | Filled in on |
| --- | --- |
| Product name, net weight, ingredients, country of origin, manufacturer, CPNP, UFI, SCPN | The trade unit. |
| Warnings and directions for use | The trade unit, in the **GPSR** section (**Warnings**, **How To Use**). |
| Product name, warnings and directions in other languages | The product in each shop, in its **Name** and **GPSR** translations. |
| Languages the label must carry | The trade unit, **Labeling & Compliance Marks** → **Languages**. |
| UK and EU responsible person | Our own UK and EU companies' details, offered when the trade unit lists that market under **Markets**. |
| Importer | The details of the organisation that buys the product. |
| Symbols | The trade unit, **Labeling & Compliance Marks** and its hazards. |
| Barcode | The SKO's barcode. |
| Batch code and expiry date | Typed in when the labels are printed. |

A label can only be as good as the product record behind it. If a warning is missing in one language, add the translation to the product rather than typing it on one label: the next label, and the website, will then have it too.

## Publishing

When every mandatory chip is green or marked on artwork, the Compliance Supervisor opens the label and presses **Publish**. From that moment the agents who buy the product for us see the label and can print it.

A published label can still be improved. Change it and press **Publish again**: the changes are live and the label stays published. **Unpublish** takes it away from the agents until it is published again.

<aside class="wayfinder">
<b>Where to click in aiku</b><br>
Your warehouse → <b>Inventory</b> → <b>SKOs</b> → open the SKO → <b>Labels</b>. The <b>Labels</b> tab has <b>Mandatory information</b> and <b>New label</b>; the <b>Compliance</b> tab has the certificates and tests. The product record is at <b>Trade Units</b> → open the trade unit → <b>Edit</b>, and the translations at the product in each shop → <b>Edit</b>.
</aside>

<aside class="wayfinder">
<b>Permissions you need</b><br>
One of the job positions <b>Compliance Manager</b>, <b>Compliance Worker</b> or <b>Compliance Supervisor</b>, in the <b>Compliance</b> row of the group permissions. Group administrators can do all three.
</aside>
