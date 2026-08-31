---
title: Only flush the cache you actually changed
summary: Every deploy was emptying the storefront cache and restarting server-side rendering, even when nothing on the front end had changed. The cause was one string baked into one JavaScript chunk. The fix was a checksum and a meta tag.
date: 2026-07-29
tags: deploy, varnish, ssr, vite
---

Our storefronts are served through a caching proxy (Varnish) in front of a server‑side rendering process. A cache flush plus an SSR restart costs real money: for a few minutes every page is rendered cold, and the warmers that re‑fill the cache compete with customers for the same workers.

So the deploy script had a rule: *only flush and restart if the front end changed.* The rule was implemented as "did anything under `resources/` change since the last release" — and in practice that was true on almost every deploy, because almost every deploy touches a Vue file somewhere in the staff app that the storefront never loads. We were paying the cold‑cache cost for changes customers could not see.

## First attempt: hash the build output instead

The obvious improvement is to stop looking at source files and look at what was actually built for the storefront: the SSR bundle, its manifest, and the public asset manifest. Hash those three; if the hash matches the previous release, skip the flush.

It did not match. Ever. Every deploy produced a different hash of the storefront bundle even when no storefront file had changed.

## The string that changed every time

Vite builds a graph of chunks, and the hash of a chunk depends on the hash of what it imports. Our entry chunk imported the error‑tracking client, initialised with:

```js
Sentry.init({ release: import.meta.env.VITE_RELEASE })
```

`VITE_RELEASE` is set by the deploy to the new release number. So the entry chunk's content changed on every deploy by a few bytes, its hash changed, and because roughly a hundred and thirteen other chunks are reachable from it, *their* filenames changed too. Non‑deterministic builds, by design, for a string nobody reads at build time.

## The fix

Read the release at runtime instead of baking it in:

```html
<meta name="app-release" content="{{ config('app.release') }}">
```

```js
Sentry.init({
  release: document.querySelector('meta[name=app-release]')?.content,
})
```

The bundle no longer knows what release it is. Chunk hashes stabilised immediately, and the checksum of the SSR output became a true answer to "did the storefront change".

The deploy now writes that checksum once, and two later steps — flush the proxy, restart SSR — read it and compare to the previous release. If any component of the checksum cannot be computed, the checksum is *not* written, and the downstream steps fail loudly rather than guess. Silent skipping is how you ship a stale storefront.

## A debugging note

Production asset directories on our hosts are a union of every release (old files are carried forward so that already‑loaded pages can still fetch their chunks). That means you cannot diff two releases by listing files. Compare manifests, and normalise the eight‑character hashes out before diffing — otherwise every line differs and you learn nothing.

The same release‑in‑bundle pattern still exists in our other apps. It is harmless there because nothing caches them at the proxy. It is the template we will reach for the day one of them does.
