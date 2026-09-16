---
title: Designing and printing labels
summary: Build an A4 label sheet on an artefact: upload the artwork, set the grid, drop on the batch code, expiry date and a barcode that scans, then publish it so the floor can print it.
date: 2026-09-16
tags: production, crafts, labels, printing
category: production
help_routes: grp.org.productions.show.crafts.labels.index
---

<aside class="tldr">
A <b>label</b> belongs to one artefact and describes a whole A4 sheet: the artwork behind it, how many labels fit on the page, and the handful of texts that change with every run — the batch code, the expiry date, a barcode. You design it once in the editor, <b>publish</b> it, and from then on anyone making that artefact prints it straight from the <b>To produce</b> board. Until it is published it is a draft and only you will see it.
</aside>

## Where labels live

Open an artefact and pick the **Labels** tab. Everything about that artefact's labels happens there: the list of what has been designed, and the editor you design in.

Two other places are worth knowing. **Crafts → Labels** lists every label in the factory with the artefact each one belongs to, so you can find a label when you remember the product but not which artefact it hangs off. And the crafts dashboard has a **Labels** box showing how many exist and how many are published — the second number is the one that matters, because an unpublished label is invisible to the floor.

## Draft, designed, published

A label is in one of three states, and the chip on each row tells you which.

| State | What it means |
| --- | --- |
| **Raw** | Not touched in the editor yet. Usually a label that arrived from an import. |
| **Processed** | Opened and edited. Yours to work on, invisible to the floor. |
| **Published** | Live. It can be printed from the To produce board. |

**Raw almost always means imported.** Most labels in aiku were not designed here at all — they were brought over in bulk from folders of artwork PDFs held elsewhere, one file per label, matched to artefacts by code. An imported label arrives as the whole A4 being one label, carrying its artwork and nothing else: no grid, no batch code, no expiry date, no barcode. That is a faithful record of what the old file was, not a half-finished design.

So a Raw label is usually a perfectly good artwork waiting for someone to decide whether it needs anything printed on it. Open it, add whatever the run needs, and saving moves it to Processed. A label you create yourself is also Raw until you open and save it a second time, so do not read Raw as "broken".

Only a published label can be printed outside the editor. That is the whole point of publishing, and it is why the **Publish** button carries a small red dot until you press it.

Editing a published label does **not** knock it back to a draft. Save your changes and it stays published, which means the change is live the moment you save it — useful when you are correcting a typo, worth pausing over when you are redesigning. If you want a published label out of circulation, press **Unpublish**: it drops back to Processed and stops being printable, and nothing else about it is lost.

## Starting a label

**New label** opens the editor. Give it a name first — the name is how you find it again, and **Save** stays disabled until there is one.

**Save as new** takes the design you have open and saves it as a separate label, which is the quick way to make a variant without disturbing the original.

## The artwork behind it

**Background artwork** takes an image or a PDF, up to 8 MB. JPG, PNG, GIF and WebP all work.

A **PDF is the better choice** if you have one. It is placed as vector artwork, so the text inside it stays sharp at any print size and stays selectable in the finished sheet — an image gets stretched to fit and cannot do either. If your PDF was saved in a newer format than aiku's reader handles, it is converted automatically; you only hear about it if the conversion fails, and then the fix is to save it again as PDF 1.4.

The artwork is kept with the label. Open the label in six months and it still prints against the artwork it was designed with, without you uploading anything again.

**Canvas rotation** turns the artwork on the label in quarter turns. Use it when the file was drawn sideways — it turns the picture, not the page.

## The grid

**Columns** and **Rows** decide how many labels fit on the A4 sheet, up to 20 across and 30 down. **Page margin** is the blank border around the whole sheet and **Gap** the space between labels. Under the boxes a line tells you how many labels that makes and how big each one is in millimetres, and turns red if the grid no longer fits on the page.

**Show cutting guides** prints a dashed line around each label so they can be cut apart.

If your artwork file is *already* a full sheet of labels — a design that has the grid drawn into it — tick **Artwork already contains the grid**. The whole A4 becomes one label, the grid boxes switch off, and you drop the texts straight onto the picture. In that mode you duplicate each text and place a copy on every label the image has, because aiku is no longer repeating them for you.

## The texts that change every run

Under **Texts** there are three buttons, and each adds one line to the sheet:

- **Batch code** — arrives filled in as the artefact code and today's date. Artefacts do not carry a batch code of their own yet, so this is a sensible stand-in you are meant to edit.
- **Expiry date** — arrives as a year from today, on the same basis.
- **Barcode** — arrives as the barcode held on the artefact's SKU, falling back to the unit barcode if the outer one is blank. If the SKU has no barcode at all you get an empty line to type into.

Every one of them is just text you can overwrite. Select a line and you can change its content, size, colour, boldness and rotation. The eyedropper picks a colour off the screen, which is the easy way to match a colour already in the artwork.

## Barcodes that actually scan

A barcode prints as real bars with the digits underneath, not as a number, and the bars are drawn as vectors so they stay crisp however the sheet is printed.

**Symbology** is picked for you when you add the barcode: thirteen digits become an **EAN13**, anything else a **CODE 128**. You can switch it, and it is worth knowing why the two differ. EAN13 is a strict standard — exactly thirteen digits ending in the correct check digit — and aiku refuses anything else rather than printing bars that scan as a different number than the digits below them. CODE 128 takes letters as well, which is why an outer barcode ending in a letter lands there.

If the text cannot be drawn as the chosen symbology the editor says so in red and the sheet will not generate until it is fixed. A yellow note appears when the barcode is narrower than 20 mm: it will still print, but hand scanners struggle below that, so widen it if the label has room.

**Digits below** can be switched off if the artwork already prints the number.

## Placing everything

The preview shows the whole sheet. The first label is outlined and it is the only one you drag things on — everything you do there is copied to every other label on the sheet, which is what makes a sheet of fifty worth designing once.

Drag a text to move it. The small square on a selected text resizes it: font size for a text, the size of the bars for a barcode. **Snap to** puts a text exactly in a corner, an edge or the middle without fiddling. Zoom with the buttons above the preview, or jump with **Whole page** and **Edited label**.

**Highlight texts** fades the artwork and puts the texts on a contrasting patch. It only affects what you see here — nothing about it reaches the PDF — and it is the fastest way to find a small line of dark text sitting on a dark background.

## Printing

**Download PDF** generates the sheet as it stands, saved or not, so you can hold a test print against the real labels before committing to anything.

Once the label is published, the floor prints it from the **Preparing** lane of the To produce board, and the header of the editor also carries a **Published PDF** link.

## Things worth knowing

- **The list tells you a lot at a glance.** Each row carries a thumbnail of the artwork, the grid, and small icons for whichever of batch code, expiry date and barcode that label prints. A label with no icons prints no changing text at all — which is fine for a plain wrapper, and a warning sign for anything that needs a batch number.
- **PDF artwork shows its real size in centimetres.** If a label looks wrong on the page, the size on the row usually explains it: the artwork is not the size you thought.
- **Editing a published label goes live on save.** There is no draft copy sitting in front of it.
- **Deleting a label cannot be undone.** The layout and its link to the artwork go with it. The artwork file itself stays on the artefact.
- **An imported label has no variable text by design.** It prints exactly the artwork it came with. If a run needs a batch number on it, that is a decision someone makes in the editor, not something the import got wrong.
- **The batch code and expiry date are stand-ins, not live data.** They do not update themselves between runs. Check them before each print until artefacts carry real ones.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Design a label:</b> your organisation → <b>Factory</b> → <b>Crafts</b> → <b>Artefacts</b> → open the artefact → <b>Labels</b> tab → <b>New label</b>.</li>
<li><b>Every label in the factory:</b> <b>Crafts</b> → <b>Labels</b>, or the <b>Labels</b> box on the crafts dashboard.</li>
<li><b>Only the published ones:</b> the <b>Published</b> count on that box, or the <b>State</b> chips above the list.</li>
<li><b>Test print:</b> open the label → <b>Download PDF</b>.</li>
<li><b>Print for real:</b> <b>Operations</b> → <b>To produce</b> → the <b>Preparing</b> lane.</li>
<li><b>Take one out of circulation:</b> open it → <b>Unpublish</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permissions you need</strong>
<ul>
<li>Positions are set on the employee record under Human Resources and carry the rights with them.</li>
<li>Seeing labels and downloading a sheet: a production position for that factory, or organisation supervisor.</li>
<li>Designing, publishing and unpublishing: the factory's <b>research and development</b> right, or organisation supervisor.</li>
<li>The barcode comes from the SKU, which is edited in the warehouse under <b>Inventory</b>, not here.</li>
</ul>
</aside>
