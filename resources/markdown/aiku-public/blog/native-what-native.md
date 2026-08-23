---
title: Native? What native. Barcode scanning without a mobile app
summary: Our pickers and packers work on cheap tablets with a Bluetooth scanner, on the same web application the office uses — same URL, same codebase, no app store. How a browser tells a barcode from a person typing, what the server does with a scan (match → location → pick, in one round‑trip), and why "it feels like an app" is a design discipline, not a framework.
date: 2026-08-14
tags: warehouse, frontend, vue, picking, ux
---

There is a reflex, when someone says *handheld warehouse app*, to reach for React Native, two app stores, a release pipeline with signing keys, and a second codebase that drifts from the first. We have that reflex too — there is a half‑built native project in our repository to prove it. What the warehouse actually uses is the web application, on a tablet, with a scanner. Same URL as the office. This note is how that works and why it is enough.

## The hardware

A mid‑range Android tablet in a rugged case on the trolley or the packing bench, and a Bluetooth barcode scanner paired as a keyboard. That last detail is the whole trick: a "keyboard wedge" scanner types the barcode, fast, and (usually) presses Enter. To the browser it is a person who types 13 digits in 40 milliseconds. No camera permissions, no SDK, no app.

## Telling a scanner from a person

The picking and packing screens listen for keystrokes at the window level, not in a text box — a picker with gloves should not have to tap a field first. A small composable buffers keys and decides what it is looking at:

- **Machine input** arrives in a burst: the inter‑key gap is a few milliseconds. Human typing is tens to hundreds. Timing alone separates them.
- **Enter terminates** a scan on most scanners. Some are configured without it, so a quiet gap after a long enough burst is treated as the end of a code — long enough that it never submits half of a manual entry.
- **Dialogs win.** If a modal is open, the listener stands down; a scan into a confirmation dialog would be a bug.
- **A queue, not a race.** Scans are processed one at a time in order. A picker scanning four boxes in two seconds gets four results, in order, not three and a lost one.

When a code is recognised, the screen posts it — with the current picking session, the tab the picker is on, and optionally the location they are standing at — to one endpoint.

## What the server does with a scan

One action, one round‑trip, and it answers a question that sounds simple and is not: *what did you just scan, and what should happen?*

1. **Match.** The code might be a product barcode, a trade‑unit barcode, an internal SKU, or a location label. Find the items in this session that could mean this code.
2. **Choose.** If several items match — the same product on two orders in the session — pick the one that is next in the route, unless the picker asked for a specific one.
3. **Locate.** Which location to pick from: the one the picker scanned, or the route's suggested one, or any that holds stock, in that order.
4. **Pick.** Record the pick in stock units, handling the case where the scanned barcode is a *carton* of six rather than a single unit.
5. **Answer.** A status (`picked`, `already_picked`, `no_stock`, `not_found`, `wrong_state`), a one‑line message in the picker's language — *"Picked 6 × lavender candle from B‑11‑01 for 000071, 2 still to pick"* — and just the row that changed, so the screen patches one line instead of reloading.

The same shape serves packing: scan an item at the bench, the server says which box it goes in and what is left.

## Feeling like an app is a discipline

The tablet shows the same Vue application as a desk in the office; it feels like an app because of a handful of rules we hold to on warehouse screens and nowhere else:

- **Full‑row targets.** The whole row is the button. Nothing smaller than a thumb.
- **Fewest taps to the common action**, with the default already selected so one tap fires the label.
- **Sound.** A different tone for picked, already picked, not found. Nobody is looking at the screen.
- **The screen never loses its place.** A scan patches one row; the route and the tabs only change when the state changes.
- **Pretty is allowed if it costs no taps.** Morale is real; so is a queue of forty orders.

None of that needed a native framework. It needed people who stood at the bench for a day.

## What we gave up, honestly

A web app cannot wake the device, cannot scan with the camera as smoothly as a native SDK (we do not try; the Bluetooth scanner is better anyway), and depends on Wi‑Fi coverage in the racks. We extended coverage. Offline picking is the one thing a native app would genuinely buy us, and so far the warehouse has never asked for it.

## The payoff

One codebase, one deploy, one release number. A fix for the picking screen at ten in the morning is on every tablet at half past, [with the rest of that day's releases](/blog/369-production-releases-in-five-months). No store review, no "please update the app", no second team. When a new warehouse opens, the tablet setup is: open the browser, bookmark the URL, pair the scanner.
