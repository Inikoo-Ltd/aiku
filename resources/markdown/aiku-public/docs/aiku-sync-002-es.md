---
title: AIKU-SYNC-002 · La ubicación no tiene stock
summary: Una recogida o un movimiento sin conexión sacó mercancía de una ubicación en la que Aiku no muestra stock.
date: 2026-11-25
source_date: 2026-11-25
tags: staff app, errors, warehouse
category: warehouse
series: Staff app error codes
order: 14
---

<aside class="tldr">
Aiku no muestra nada en la ubicación de la que sacaste la mercancía, así que no pudo registrar la recogida o el movimiento. Aiku ha pedido un recuento de esa ubicación. Deja la mercancía en la cubeta hasta que se haga el recuento y vuelve a intentarlo.
</aside>

## Qué ha pasado

Cuando tu acción llegó a Aiku, el stock de la ubicación era 0 en Aiku. La estantería y Aiku no coinciden, y un recuento de la ubicación lo aclarará.

## Qué hacer

1. Deja la mercancía en la cubeta; no la pongas en otra estantería.
2. Espera al recuento (se crea una tarea para el almacén).
3. Cuando se haya contado la ubicación, abre el conflicto y pulsa **Reintentar**.

## Cuándo avisar a un supervisor

Un supervisor o un controlador de stock cuenta la ubicación.
