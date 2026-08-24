---
title: One image, six URLs
summary: 226,000 images — product photos, family banners, logos, pallet photos, chat attachments — stored once and never resized by us. Every image on every page is a signed URL to an image proxy that resizes, crops and converts on the fly: AVIF and WebP with a fallback, a retina variant of each, the right width for the slot, cached at the edge. Why a proxy beat pre‑generated thumbnails, how the signature stops the proxy being a free resizer for the internet, and the "picture" element every card uses.
date: 2026-07-08
tags: images, storefront, performance, cdn
---

A product has a photo. That photo appears as a 64‑pixel thumbnail in a staff list, a 320‑pixel card on a family page, a 1200‑pixel hero on the product page, a 2× version of each for a retina screen, in AVIF where the browser can, in WebP where it cannot, in JPEG as the last resort, and cropped square for the marketplace listing. One file in storage; a dozen renditions on screen. We do not generate those renditions. We ask for them.

## The proxy

Every image URL on a storefront or in the staff app points at an **image proxy** (we run [imgproxy](https://imgproxy.net), a small Go service, next to the application). The URL encodes the *processing options* — resize mode, width, height, gravity, format, enlargement allowed or not — and the location of the original. The proxy fetches the original once, applies the options, returns the result, and the CDN in front of it caches the answer by URL. The second request for the same rendition never reaches the proxy; the first request for a new one costs a few milliseconds of Go.

The win over pre‑generating thumbnails is not just disk. It is that a design change — "cards are now 360 wide", "hero images go 16:9" — is a change to the URL builder, not a batch job over 226,000 files. The old renditions simply stop being asked for and fall out of the cache.

## The signature

An open resizer is a gift to the internet: anyone can point it at any image and use your CPU. So every URL is **signed** — an HMAC over the processing path with a key and salt only the application and the proxy know — and the proxy refuses anything whose signature does not match. The helper that builds URLs is the only place the key lives; the size of the signature is configurable, and the allowed resize modes, gravities and maximum source dimension are constrained in config so a mistyped option cannot ask for something absurd.

## One image, six URLs

A storefront card does not receive an image URL. It receives a small map:

```
avif, avif_2x, webp, webp_2x, original, original_2x, alt
```

and renders a `<picture>` element with AVIF and WebP sources, a `srcset` for device pixel ratio, and the original as fallback, with width and height attributes set so the layout does not shift while the image loads. A gallery is a list of those maps. The resource layer builds the maps; the Vue component only arranges them. Nothing on the front end knows the proxy's URL format.

## What is stored

The originals live in object‑style storage under the application's media library — one row per file with its dimensions, type, checksum, the model it belongs to and the collection it is in (product images, family banner, logo, pallet photo, chat attachment, invoice PDF). Animated images are flagged so the proxy is told to keep the animation. Uploads are checked, and the image *alt* text lives on the same row and is editable by the catalogue team, because an image without a description is half an image to a screen reader and to a search engine.

## The small print

- Because proxy output is cached at the edge by URL, a replaced image should be a new file with a new URL, not an overwrite of the old one.
- Staff‑app images go through the same proxy; the staff app just asks for smaller sizes.
- The maximum source dimension (8,192 px a side) and the allowed resize modes and gravities are constrained in config, so the proxy is never asked for something absurd by a typo.

## What we would tell a team

Store one original; never store renditions. Sign every URL. Give the front end a map of formats, not a URL, and let a `<picture>` do the choosing. Put a CDN in front, and make the URL change when the image does. The result is that nobody in the company has resized an image by hand in three years.
