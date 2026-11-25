---
title: AIKU-SYNC-002 · Location has no stock
summary: An offline pick or move took goods from a location where Aiku shows no stock.
date: 2026-11-25
tags: staff app, errors, warehouse
category: warehouse
series: Staff app error codes
order: 14
---

<aside class="tldr">
Aiku shows nothing in the location you took the goods from, so it could not record the pick or move. Aiku has asked for a stock check of that location. Keep the goods in the tote until the check is done, then try again.
</aside>

## What happened

When your action reached Aiku, the location's stock was 0 in Aiku. The shelf and Aiku disagree, which a count of the location will settle.

## What to do

1. Keep the goods in the tote; do not put them on another shelf.
2. Wait for the stock check (a task is created for the warehouse).
3. When the location has been counted, open the conflict and press **Try again**.

## When to ask a supervisor

A supervisor or stock controller counts the location.
