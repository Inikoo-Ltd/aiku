---
title: AIKU-SYNC-009 · El albarán está en una sesión de picking
summary: Una acción sin conexión era para un albarán que forma parte de una sesión de picking, que solo funciona en línea.
date: 2026-11-25
source_date: 2026-11-25
tags: staff app, errors, warehouse
category: warehouse
series: Staff app error codes
order: 21
---

<aside class="tldr">
El albarán está en una sesión de picking, y las sesiones de picking solo funcionan en línea. Vuelve a hacer esta línea cuando tengas conexión.
</aside>

## Qué ha pasado

Las sesiones de picking agrupan varios albaranes y Aiku las mantiene sincronizadas, así que sus líneas no se pueden cambiar sin conexión.

## Qué hacer

1. Vuelve a estar en línea.
2. Vuelve a hacer la línea desde la sesión de picking.
3. Pulsa **Hecho** en el conflicto.
