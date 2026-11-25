---
title: AIKU-SYNC-008 · Waiting already cleared
summary: An offline 'waiting' on a line was sent after the waiting had already been cleared.
date: 2026-11-25
tags: staff app, errors, warehouse
category: warehouse
series: Staff app error codes
order: 20
---

<aside class="tldr">
Someone cleared the waiting on this line after you set it offline. Pick the line again.
</aside>

## What happened

You marked a line as waiting while offline. Before it synced, the waiting was resolved, so your waiting would have reopened it by mistake.

## What to do

1. Pick the line again.
2. Press **Done** on the conflict.
