---
title: How fast is my page?
summary: The PageSpeed Insights panel on a webpage's Performance tab scores the live page out of 100 on desktop and mobile, shows what real visitors experienced over the last month, and keeps a history so you can see whether a change made the page faster.
date: 2026-09-16
tags: website, performance, seo, shop
category: marketing
---

<aside class="tldr">
Open a webpage in aiku, click <b>Performance</b> and scroll to <b>PageSpeed Insights</b>. Four dials score the live page out of 100 for <b>Performance</b>, <b>Accessibility</b>, <b>Best practices</b> and <b>SEO</b>, separately for desktop and mobile. Green is 90–100, amber 50–89, red below 50. Underneath, <b>Core Web Vitals</b> is what real visitors actually experienced in the last 28 days, <b>Lab metrics</b> is where the score came from, and <b>Score history</b> draws the scores over the period you picked at the top of the tab. The measurement is Google's, not ours, and it always measures the published page, never your unsaved draft.
</aside>

## What is being measured

Google measures the page on its own servers, which means it fetches the **live, published address** of the page. Two things follow from that:

- A page that is not published, or that has no public address yet, cannot be measured. The panel says so instead of showing scores.
- What you are looking at is the page as a visitor gets it today, not the draft you are editing in the workshop. Publish first, then measure.

Every page is measured twice, once as a phone and once as a desktop computer, and the two results are kept apart. Use the **Desktop** / **Mobile** buttons at the top of the panel to swap between them. Mobile scores are almost always lower than desktop ones: Google simulates a mid-range phone on a slow connection, and that is the harsher, more honest test, because most shoppers arrive on a phone.

## The four scores

Each dial is a mark out of 100:

- **Performance** — how quickly the page loads and becomes usable.
- **Accessibility** — how well the page works for someone using a screen reader or other assistive technology.
- **Best practices** — security and modern web checks, such as HTTPS and errors in the browser.
- **SEO** — the basic checks that let search engines find, crawl and understand the page.

The colours are Google's own bands: **0–49 poor**, **50–89 needs improvement**, **90–100 good**. Hover or focus a dial and a card explains that score in one line, puts the desktop and mobile numbers side by side so you can see which device is dragging, and repeats the bands.

Treat the number as a direction, not a target. The same page measured twice an hour apart can move a few points, because Google's simulated network is not identical every run. A drop from 88 to 84 is noise; a drop from 88 to 40 is something you changed.

## Core Web Vitals: your real visitors

The **Core Web Vitals** block only appears when Google has enough real traffic to this page. It is not a simulation: it is what people who actually visited in the **last 28 days** experienced, gathered from Chrome.

Five measurements are shown, each with a bar splitting your visits into good, needs improvement and poor:

- **Largest Contentful Paint** — how long until the main thing on the page, usually the big image or the heading, is on screen.
- **Interaction to Next Paint** — how long the page takes to answer a tap or a click.
- **Cumulative Layout Shift** — how much the page jumps around while it loads. This is the one shoppers describe as "it moved and I pressed the wrong thing".
- **First Contentful Paint** — how long until anything at all appears.
- **Time to First Byte** — how long our server took to start answering.

The bars matter more than the headline figure. A page can have an acceptable average while a fifth of visits are poor, and that fifth is usually one country, one phone or one slow image.

A quiet page — a new family, a rarely visited content page — will not have this block at all. That is not a fault; Google simply will not report on a handful of visits.

## Lab metrics: where the score came from

**Lab metrics** are the timings from Google's own single simulated run, the one that produced the Performance dial: First Contentful Paint, Largest Contentful Paint, Total Blocking Time, Cumulative Layout Shift and Speed Index. They are the working behind the mark.

Use them to tell *what kind* of slow a page is. A bad Largest Contentful Paint usually means a heavy hero image. A bad Total Blocking Time means scripts. A bad Cumulative Layout Shift usually means an image or a banner without a reserved space. If the lab numbers look fine and the real-visitor bars look bad, the problem is not the page itself but who is visiting it and from where.

## Score history

At the bottom, **Score history** draws one score at a time over the period set by the **From** and **To** dates at the top of the Performance tab. Pick which score with the dropdown. The solid line is desktop, the dashed line is mobile.

Points are daily when the range is up to about three months, and a weekly average beyond that, so a long look back stays readable. A point only exists for a day the page was actually measured, so expect gaps rather than an unbroken line.

This is the part to look at after a redesign, after adding a video, or after swapping the images on a department page. Put the period around the change and see whether the line stepped.

## Measuring and re-measuring

Next to the panel title is the time the current result was measured. A result is kept for a day: the first time anyone opens the tab the page is measured, and for the next 25 hours everyone sees that same measurement rather than triggering a new one. This is deliberate — Google limits how often we may ask.

When you want a fresh number straight away, press **Re-measure**. The dials go grey, the panel says it is measuring, and results usually arrive within a minute; the panel refreshes itself, so there is no need to reload. If Google is being slow the panel keeps checking for about three minutes, then asks you to press **Re-measure** again.

Every completed measurement, whether it came from you opening the tab or from a batch run, is written into the score history. So the more often the page is looked at, the denser its history line.

## When there are no scores

- **"This webpage has no publicly reachable URL to analyse"** — the page is not live, or the website has no public domain yet. Publish the page, or ask whoever set up the website to finish pointing the domain.
- **"Google could not measure this page"** with a message underneath — Google reached the page and refused or failed. The usual causes are a page that returns an error to a visitor who is not logged in, a redirect loop, or a page too slow to finish loading at all. Open the page in a private browser window first; if it does not work there, it does not work for Google either.
- **No Core Web Vitals block** — not enough real visitors in the last 28 days. Everything else on the panel still applies.

## What to do with a bad score

Most of what moves these numbers is not in the page text:

- **Images** are the usual culprit. An enormous product photo scaled down in the browser costs the visitor the whole original file.
- **Anything embedded** — a video, a map, a chat or review widget — is somebody else's code running on your page, and it is counted against you.
- **Accessibility and SEO** scores often lose points on things that are quick to fix from the workshop: missing image descriptions, a missing page title or description, headings used for their size rather than their meaning, or text too pale against its background.

Performance problems that are the same on every page of a website are ours, not yours; raise a ticket with the page address and a screenshot of the panel. Problems on one page are usually that page's own content.

<aside class="wayfinder"><strong>Where to click in aiku</strong>
<ul>
<li><b>Open the panel:</b> your organisation → your shop → <b>Website → Webpages</b> → the page → <b>Performance</b>, then scroll to <b>PageSpeed Insights</b>.</li>
<li><b>Phone or computer:</b> the <b>Desktop</b> / <b>Mobile</b> buttons in the panel header.</li>
<li><b>Explain a score:</b> hover one of the four dials.</li>
<li><b>Fresh measurement:</b> the <b>Re-measure</b> button on the right of the panel header.</li>
<li><b>Change the history period:</b> the <b>From</b> and <b>To</b> dates at the top of the Performance tab.</li>
<li><b>Pick the history line:</b> the dropdown next to <b>Score history</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Good to know</strong>
<ul>
<li><b>Scores are Google's.</b> aiku asks PageSpeed Insights and shows the answer; we do not calculate the marks.</li>
<li><b>Only the live page is measured</b>, never a draft, and never a page behind a login.</li>
<li><b>One measurement a day per page</b>, per device, unless you press <b>Re-measure</b>.</li>
<li><b>Traffic and sales for the same page</b> are on the chart above this panel — see <a href="/docs/did-my-webpage-change-work">Did my webpage change work?</a></li>
</ul>
</aside>
