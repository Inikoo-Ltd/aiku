---
title: Seventeen kinds of PDF, two renderers
summary: Almost every piece of paper the business touches is a PDF we generated — invoices, delivery notes, picking sheets, pallet returns, timesheets, barcode labels in five widths, carrier labels from three APIs. How one rule decides between mpdf and headless Chrome, how a single invoice template serves shops in different countries, languages and tax regimes without forking, and the gotchas: a bakery called "& Fils" that broke shipping, labels that must scale from 55 mm to 125 mm, and why we merge PDFs with a renderer that never renders them.
date: 2026-08-30
tags: pdf, invoicing, laravel, printing, architecture
---

<aside class="tldr"><strong>TL;DR</strong>Seventeen distinct PDF pathways, one rule: our own Blade templates render through mpdf; HTML we did not write (carrier labels, print payloads) renders through headless Chrome via Browsershot, because you only trust a real browser with someone else's CSS. One invoice template serves every shop — language, timezone, tax-field labels, footer and legal blocks all come from shop and organisation settings, not template forks. Everything is generated on demand and streamed, nothing stored: <a href="/blog/an-invoice-is-a-snapshot">the data is the document</a>. The gotchas live at the edges — a customer named "&amp; Fils" once broke a carrier's SOAP API, and label templates branch on five physical widths.</aside>

A warehouse business runs on paper, even when the paper is a PDF. An order becomes a picking sheet, then a delivery note, then a carrier label on a box, then an invoice, sometimes a credit note, occasionally a pallet return form; behind the scenes there are goods-in sheets, stock labels, top-up receipts and employee timesheets. Count the distinct documents aiku can produce and you get **seventeen kinds of PDF**, generated in a steady stream every working day across [thirty-four shops](/blog/62000-products-no-problem) in several countries.

Every benchmark of "the best way to generate PDFs in Laravel" assumes there is one answer. We ended up with two, and the interesting part is the rule that decides — plus what one shared invoice template has to absorb when it serves shops in different countries, currencies and tax regimes.

## The rule: whose HTML is it?

**If we wrote the template, mpdf renders it.** Invoices, delivery notes, packing and picking lists, pallet forms, stock-delivery sheets, labels, timesheets — all Blade views rendered server-side and handed to [mpdf](https://mpdf.github.io/). mpdf's HTML engine is limited, but limited is fine when you control both sides: you write the template to what the engine supports, you get precise page formats and margins per document type, and rendering is a pure PHP function call — no browser process, no queue hop, fast enough to generate and stream in the same request.

**If someone else wrote the HTML, headless Chrome renders it.** Two pathways start from markup we do not control: a carrier API that returns its shipping label as HTML, and print jobs that arrive as raw HTML payloads. Those go through [Browsershot](https://spatie.be/docs/browsershot) — a real Chromium, because a third party's label was written against a real browser's CSS and mpdf's engine is not something you want interpreting a barcode's exact millimetres. The Chrome calls run defensively: a five-second timeout and <code>no-stop-slow-scripts</code>, because foreign HTML sometimes brings foreign JavaScript.

That is the whole decision tree. No benchmark required: correctness picks the renderer, not speed.

## One invoice template, every jurisdiction

The temptation, with shops in several countries, is a template per shop. We have exactly one invoice Blade view, and it has never forked. Everything that differs between shops is **data, not template**:

- **Language** — before rendering, the locale switches to the shop's language, so the same template produces an English, Slovak, Czech, or Spanish invoice from the same translation keys. (Customer-facing labels come from the shop's language, not the viewer's — the invoice is the shop's document.)
- **Timezone** — every date on the document is converted to the shop's timezone at render time; [the servers run seven hours ahead of the warehouse](/blog/seven-hours-ahead-of-the-warehouse) and an invoice dated tomorrow is a customer-service ticket.
- **Tax identity fields** — what one country calls a VAT number, another splits into two identity documents with local names. The *labels* for those fields are per-shop settings, so a Spanish shop prints "CIF" where a UK shop prints nothing at all.
- **Toggles from organisation settings** — whether to print a tax-liability date, for example, is a setting checked by the template, because one country's accountants require the line and another's are confused by it.
- **Footer and legal text** — free HTML per shop, carried on the invoice itself.
- **National e-invoicing** — Czech invoices get an ISDOC XML attachment merged into the PDF after rendering, a post-processing step other shops never trigger.

The pattern generalises: a template forks when behaviour differs, and settings absorb when only *values* differ. In five years the values have differed constantly and the behaviour almost never — which is why one person can change the invoice layout for the whole group in one file, and why [the invoice remains a snapshot](/blog/an-invoice-is-a-snapshot): generated on demand from immutable data, streamed to the browser, never stored.

## Labels: where physics enters

Product and carton labels are the least glamorous pathway and the most constrained: the output must match a physical roll of label stock. The templates branch on five widths — 55, 63, 63.5, 70 and 125 mm — scaling fonts and barcode sizes for each, because a Code128 barcode that renders beautifully at 125 mm is an unscannable smudge at 55. Barcodes themselves are generated as PNGs and embedded base64 into the HTML; internal shipping labels skip the browser entirely and go straight from mpdf to the warehouse printers via PrintNode.

Carrier labels are the mirror image: PDFs we *receive* rather than produce. One carrier returns label HTML (rendered through the Chrome path); another returns one raw PDF per parcel, which we merge into a single document using mpdf's page-import mode — mpdf as a PDF stapler, never rendering a single tag. The merge needs real temp files on disk because the import API refuses strings; they are created, used and unlinked in a <code>finally</code>.

## The gotchas, as always, at the edges

The bugs in five years of PDF generation have almost never been in rendering. They were:

- **A customer called "Boulangerie & Fils"** — the unescaped ampersand broke a carrier's SOAP XML and shipments silently failed until the payload was XML-escaped. Renderers forgive; parsers do not.
- **Memory, not speed** — a packing list for a large delivery eager-loads everything up front, because the N+1 pattern that costs milliseconds on a webpage costs hundreds of megabytes when a thousand lines each lazy-load their relations inside one long-lived render.
- **Bulk documents** — rendering a whole department's timesheets as one giant DOM is how PHP learns about memory limits; they render as per-employee chunks that mpdf concatenates.

None of which a benchmark measures — and all of which is where the actual engineering lives.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>Renderers: <a href="https://github.com/mpdf/mpdf">mpdf</a> via the LaravelMpdf facade for internal Blade templates; <a href="https://github.com/spatie/browsershot">spatie/browsershot</a> (headless Chromium) only for third-party HTML, with <code>timeout(5000)</code> and <code>no-stop-slow-scripts</code>.</li>
<li>Barcodes: <a href="https://github.com/picqer/php-barcode-generator">picqer/php-barcode-generator</a>, Code128 → PNG → base64 <code>&lt;img&gt;</code>.</li>
<li>Per-shop invoice variation: locale switch before render, shop-timezone date formatting, settings-driven field labels and toggles, per-shop footer HTML — one Blade view, zero forks. ISDOC merge for Czech e-invoicing as a post-step.</li>
<li>PDF merging: mpdf's <code>setSourceFile</code>/<code>importPage</code>/<code>useTemplate</code> over temp files (the API requires paths, not strings).</li>
<li>Everything renders synchronously and streams — no stored PDFs, no queue; the document is reproducible from data at any time.</li>
<li>Bulk rendering: <code>chunkLoadView</code> with an HTML separator to keep per-chunk memory flat.</li>
</ul></aside>
