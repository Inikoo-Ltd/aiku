---
title: Two renderers, one label, and a barcode that lied
summary: A label sheet is designed in a browser and printed by mPDF — two renderers that share no code and must agree to the millimetre. Where they disagreed, what happened when a barcode library quietly accepted an invalid EAN13 and encoded a different number than the digits printed under it, why the PDF thumbnails are rendered on the client, and the one Blade character that cost an afternoon.
date: 2026-09-16
tags: production, pdf, barcodes, vue, labels
---

<aside class="tldr"><strong>TL;DR</strong>A label is an A4 sheet designed by dragging texts in a browser and printed by mPDF on the server — two renderers sharing no code, which must produce the same page. Every bug in the feature was a disagreement between them. The worst: <code>picqer</code> accepts an invalid EAN13, pads and re-checksums it, and draws bars that scan as a <em>different number</em> than the digits printed underneath — so we validate the code ourselves, on both sides, with the same rule, and store the chosen symbology rather than deriving it twice. Barcodes are vector, verified by reading the PDF content stream. Thumbnails are rendered in the browser because nothing on the server can rasterise a PDF, pulled over HTTP range requests because the artwork averages 626 KB and peaks at 6.7 MB.</aside>

## What a label is here

A factory makes an artefact; the artefact needs labels stuck on it. The label design is an A4 sheet: a background artwork, a grid of *n* × *m* copies on the page, and a handful of short texts that change with every production run — a batch code, an expiry date, a barcode.

You design it by dragging those texts around a picture of the sheet in the browser. Then the server prints it. `945` labels exist across `394` artefacts, `942` of them with artwork, and almost all of that artwork is PDF.

## Two renderers that share nothing

The preview is a Vue component: DOM nodes positioned in pixels, artwork drawn by [pdf.js](https://mozilla.github.io/pdf.js/), barcodes by JsBarcode, a zoom factor sitting between the design's millimetres and the screen.

The print is mPDF: a Blade template of absolutely positioned blocks in millimetres, artwork imported by FPDI, barcodes drawn by picqer.

Not one line is shared. They have different coordinate systems, different text metrics, different rotation semantics, different ideas of what a "block" is. And their output must match, because the whole promise of the editor is that the thing you dragged is the thing that prints — on a sheet you are about to run forty copies of.

Every real bug in this feature was one of these two disagreeing with the other. Like [rendering the storefront twice](/blog/rendering-the-storefront-twice), the interesting work is not in either renderer; it is in the contract between them.

## Only the browser knows how wide the text is

mPDF lays a rotated block out and then turns it, and which corner stays put depends on the angle. Drop a text at a spot, turn it ninety degrees, and mPDF anchors it somewhere the designer never clicked. To put the top-left corner back where it was dropped, the origin has to be moved back by the size of the box the text was laid out in — and that size depends on how wide the text actually draws, in the font, at that size.

The server cannot know that before it draws. The browser already does. So the browser measures the rendered chip and ships the length along with the field, and the server uses it to work the origin back to the point the designer chose. It is not elegant. It is the only side of the wire that has the measurement.

## The barcode that lied

The requirement was ordinary: print bars, not a number, with the digits underneath.

The danger was not. Handed `5056422987998N` — the real outer barcode of a real SKU, thirteen digits and a letter — picqer's EAN13 generator does not complain. It strips what it cannot use, pads to twelve digits, computes a check digit, and returns a perfectly valid barcode. We print the digits underneath ourselves, from the field's text. So the bars say one number and the line below them says another, and nobody finds out until a scanner disagrees with a human somewhere down the line.

It is worse with a legitimate-looking input. Give it the twelve digits `505642298799` and it appends the check digit and encodes `5056422987998` — correct EAN13 behaviour, and still a label whose bars and printed digits differ by one character.

There is no exception to catch. The library is not wrong; it is doing what its API promises. The fix is to stop asking it questions we are not prepared to have answered loosely:

- **EAN13 must be exactly thirteen digits with a correct check digit**, verified before the generator is called. Anything else is refused with a message naming the text, rather than printed.
- **The same rule is implemented in the browser**, so the preview refuses exactly what the PDF refuses. A designer finds out while designing, not while printing.
- **The symbology is stored on the field, not re-derived.** It is guessed once when the text is added — thirteen digits become EAN13, anything else CODE 128 — and after that it is data. Two renderers deriving the same decision from the same input is a coin flip that lands the same way until one of them changes.

That last point is the general one. Anything both sides need to agree on is a stored decision, not a computation performed twice.

The `N` suffix, incidentally, is not noise: `1,270` of our org stocks carry an outer barcode ending in a letter and `1,161` of those are exactly the unit EAN13 with `N` appended — a convention inherited from the old system. It is precisely the kind of code EAN13 cannot hold and CODE 128 can, which is why the guess exists at all.

## Verifying the bars are actually bars

A barcode is only a barcode if it survives printing. A rasterised one at the wrong scale is a smudge.

picqer emits SVG; mPDF accepts SVG as a data URI. Whether it *embeds* that as vector or quietly rasterises it is not documented anywhere we trusted, so we generated a sheet and read the PDF's content stream. The bars came through as a Form XObject full of path fills — real vectors — and a sheet of twenty-four cells carrying two barcodes contained `2` XObjects invoked by `48` draw calls. One definition per distinct code, reused per cell, scaled into whatever box the designer drew.

Checking took ten minutes. Trusting would have shipped a feature whose entire purpose is to be machine-readable, on a hope.

## One character of Blade

The template grew a branch: barcode fields render differently from text fields. It exploded with `syntax error, unexpected token "else"`.

Blade compiles a directive only where `@` is not preceded by a word character. Inside a style attribute the template had `...;@endif@if ($field['rotation'])...` — `@if` sitting flush against the `f` of `@endif`. The `@endif` compiled, the `@if` did not, and the template was left with an unbalanced `endif`. Adding one space fixed it.

Nothing to design around; just a thing worth knowing before spending an afternoon reading a correct-looking branch.

## Nothing on the server can turn a PDF into a picture

The label list wants a thumbnail, and the artwork is overwhelmingly PDF. There is no Imagick on the box, and our image proxy handles images, not documents. Ghostscript is installed — we already use it to rewrite PDFs the FPDI parser cannot read — but shelling out per row on a page load is not a thumbnail strategy.

So the browser does it: pdf.js renders page one to a canvas, and reads the page box in the same pass, which is also where the artwork's real size in centimetres on each row comes from.

That leaves the download. Artwork averages `626 KB`, the largest is `6.7 MB`, and one artefact can hold ten labels — fetching every file whole to draw a 48-pixel square is indefensible. The media download route returns a `BinaryFileResponse`, which honours HTTP `Range`; we confirmed it answers `206` with a correct `Content-Range` rather than assuming, then let pdf.js fetch with `disableAutoFetch` so it pulls only the chunks page one needs. If range support ever disappears, pdf.js falls back to the whole file — slower, not broken.

## Published is a state, not a snapshot

Only a published label can be printed from the production board. The first implementation knocked a label back to *processed* whenever it was edited, which is the obvious behaviour and the wrong one: correcting a typo on a live label silently pulled it out of circulation, and nobody noticed until the floor could not print.

Editing a published label now leaves it published — the change is live on save — and there is an explicit **Unpublish** for taking one out. Making the demotion a decision someone takes, rather than a side effect of saving, is the whole of the fix.

## What we would keep

When two renderers have to agree, the temptation is to make them clever in the same way. Better to make them dumb in the same way: store every decision that both need instead of deriving it twice, put the measurement on the side that can actually measure, and verify the output format by reading it rather than believing the library's README.

And when a library accepts input it should have rejected, that is not generosity. A barcode that scans as something other than the number printed beneath it is worse than no barcode at all, because it is believed.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>PDF generation, barcode SVG and the rotated-origin arithmetic: <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Production/Artefact/Label/PdfArtefactLabelSheet.php">PdfArtefactLabelSheet.php</a>; the sheet template is <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/resources/views/labels/templates/pdf/artefact_sheet.blade.php">artefact_sheet.blade.php</a>.</li>
<li>Layout validation and the stored field shape, symbology included: <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Production/Artefact/Label/WithArtefactLabelLayout.php">WithArtefactLabelLayout.php</a>.</li>
<li>The editor, preview barcodes and measured text length: <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/resources/js/Components/Production/Artefact/ArtefactLabelSheetModal.vue">ArtefactLabelSheetModal.vue</a>.</li>
<li>PDFs newer than the free FPDI parser reads are rewritten as PDF 1.4 by Ghostscript in <a href="https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Production/Artefact/Label/MakeArtworkPdfReadable.php">MakeArtworkPdfReadable.php</a>.</li>
</ul></aside>

<aside class="tldr bottom"><strong>In one paragraph</strong>A WYSIWYG editor whose two renderers share no code is a contract problem, not a rendering problem: store every decision both sides need rather than deriving it twice, take measurements on the side that can measure, and read the output format to confirm it rather than trusting a library. The barcode was the sharp edge — a generator that silently pads and re-checksums invalid input will happily print bars that scan as a different number than the digits beneath them, and that is a defect no one discovers until a scanner contradicts a person.</aside>
