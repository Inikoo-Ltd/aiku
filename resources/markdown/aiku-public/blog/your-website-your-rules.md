---
title: Your website, your rules
summary: Why a trading company wrote its own CMS instead of bolting a shop onto a page builder, and how two front‑end engineers turned "we need a product page" into seventy‑odd web blocks, a workshop that edits the live site, snapshots you can publish and roll back, and a storefront that knows its own catalogue.
date: 2026-06-12
tags: cms, iris, vue, storefront
---

<aside class="tldr"><strong>TL;DR</strong>Two front-end engineers built aiku's own storefront engine, Iris, instead of bolting a shop onto a page builder, because off-the-shelf builders don't understand a family, trade pricing, or discontinued products. Pages are ordered lists of about seventy web blocks — some generic, some (`Products`, `Family`, `Department`) that read the live catalogue directly. The workshop edits the real renderer, publishing takes a rollback-able snapshot, and layout templates share header/footer bones across shops.</aside>

Every off‑the‑shelf website platform we looked at had the same shape: a very good page builder with a shop stapled to the side. The builder did not know what a *family* of products was, or that a trade customer sees different prices, or that a product can be discontinued, replaced, or sold in cartons of six. We would have spent our lives syncing our real catalogue into somebody else's idea of one.

So we wrote our own. Two front‑end engineers on our team built what we now call Iris — the storefront engine and its editing workshop — over the course of a year, block by block, on top of the same data the warehouse and the sales team use. This note is about the ideas that made it work, because the ideas are reusable even if the code is ours.

## The page is a list of blocks that know things

An Iris webpage is an ordered list of **web blocks**. A block is a Vue component plus a JSON shape, and there are about seventy of them: hero carousels, image‑and‑text columns, galleries, call‑to‑action strips, FAQ disclosures, a video, a newsletter box, a blog list.

The interesting ones are the blocks that are *not* generic. `Products`, `Family`, `Department`, `SubDepartment`, `Product` — these blocks know the catalogue. Drop a `Families` block on a department page and it renders that department's families, in the order merchandising decided, with the images and descriptions the product team maintains in the ERP. Change the family in the ERP and the page changes, because the page never had a copy.

That is the whole difference from a page builder: our blocks are views over the business, not boxes of pasted content.

## Webpages are data, not files

A website in aiku has a tree of webpages, each with a URL, a type (storefront, department, family, product, blog, a plain content page), SEO fields, and its blocks. Product and category pages are created automatically from the catalogue and follow it — a discontinued product's page closes itself and redirects; a renamed family keeps its old URL as a redirect. The content team edits what the page says around the catalogue; they never have to create a product page by hand, and they cannot create one that disagrees with stock.

## The workshop edits the real thing

The editing surface — the **workshop** — is not a separate preview stack. It renders the same Vue blocks the storefront renders, in the staff app, with editing controls around them. You drag blocks to reorder, click into a block to change its text or pick an image from the shared media library, and see exactly what a visitor will see, including the bits that come from the catalogue.

Each block type ships with sensible default properties, so a new block is presentable the moment it lands, and the block list has been curated by people who had to build pages with it every day — which is why the most‑used blocks have three or four variants and the rarely‑used ones have one.

## Publish is a snapshot; rollback is free

Saving in the workshop does not change the live site. Publishing takes a **snapshot** of the page's blocks and makes it live; the previous snapshot is kept. If the new hero looks wrong on Monday, the previous version is one click away. Snapshots also give the obvious audit answers: who published what, when, and what it looked like.

<aside class="technical"><strong>Technical box</strong>
<ul>
<li>Web blocks are modelled in [app/Models/Web/WebBlock.php](https://github.com/Inikoo-Ltd/aiku/blob/main/app/Models/Web/WebBlock.php) and [WebBlockType.php](https://github.com/Inikoo-Ltd/aiku/blob/main/app/Models/Web/WebBlockType.php), attached to pages via [ModelHasWebBlocks.php](https://github.com/Inikoo-Ltd/aiku/blob/main/app/Models/Web/ModelHasWebBlocks.php), with edit history in [WebBlockHistory.php](https://github.com/Inikoo-Ltd/aiku/blob/main/app/Models/Web/WebBlockHistory.php).</li>
<li>Publishing takes a snapshot via [StoreWebpageSnapshot.php](https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Helpers/Snapshot/StoreWebpageSnapshot.php).</li>
<li>Layout templates live under [app/Models/Web/WebLayoutTemplate.php](https://github.com/Inikoo-Ltd/aiku/blob/main/app/Models/Web/WebLayoutTemplate.php), applied to a shop's site by [ApplyWebLayoutTemplate.php](https://github.com/Inikoo-Ltd/aiku/blob/main/app/Actions/Web/WebLayoutTemplate/ApplyWebLayoutTemplate.php).</li>
</ul></aside>

## Layouts are templates, shared across shops

A group runs many shops, and their sites share bones — header, footer, menu structure, the way a product card looks — while differing in logo, colour and copy. Layout templates capture the bones once. Apply a template to a new shop's website and you have a working storefront in an afternoon; the rest is content. When the header changes for everyone, it changes in one place and cascades to the shops that use it.

## Server‑rendered, cached, and still personal

Every Iris page is rendered on the server and hydrated as a single‑page app, so search engines and slow phones get full HTML. The rendered pages sit behind Varnish; the bits that depend on *you* — your prices, your basket — come in afterwards. How we made a personalised storefront cacheable is its own [note](/blog/varnish-in-front-of-a-storefront-that-knows-who-you-are).

## Things the CMS does because the business needed them

- **Announcements and banners** with scheduling, per shop or per group, that do not need a deploy.
- **GDPR, unsubscribe and disclosure pages** generated from the same data the email system uses, so they are always right.
- **Merchandised search** with trending and "last seen" blocks fed by the search engine.
- **Redirects** as first‑class records with history, because URLs are promises.
- **Multi‑language** text on every block, with the same translation pipeline the rest of the system uses.

## What we would say to someone tempted to do the same

Do it only if your catalogue is the point of the site. If it is, the block model — components that render *your* entities, not pasted copies of them — is the idea to steal. Give the editors the real renderer, not a preview that lies. Make publish a snapshot. And let the two people who will live inside it shape the block list; they will make a better one than a committee would.

<aside class="tldr bottom"><strong>In one paragraph</strong>Iris works because its blocks are live views over the real catalogue rather than pasted content, the workshop edits the real renderer, and publishing is a rollback-able snapshot — the idea worth stealing even if the code stays aiku's.</aside>
