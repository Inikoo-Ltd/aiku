---
title: Lotes y fechas de consumo preferente
summary: Cómo sabe aiku qué lote de cada producto está en cada estantería y cuándo caduca, qué hace cada equipo para que sea correcto, y dónde ver lo que está a punto de caducar.
date: 2026-10-08
source_date: 2026-10-08
tags: warehouse, batches, best-before, goods in, picking, production, reports
category: warehouse
---

<aside class="tldr">
Cada lote que llega a una estantería lleva su código y su fecha de consumo preferente, y cada pick lo vuelve a sacar, así que aiku siempre sabe qué lotes hay en cada estantería. Casi todo es automático. La recepción escribe el código y la fecha de la etiqueta en las entregas de proveedores, producción registra la vida útil de lo que fabrica, y el almacén etiqueta una vez el stock que ya estaba en las estanterías. Después <b>Inventory → Batch Codes</b> y la exportación de SKO muestran lo que está a punto de caducar.
</aside>

## Cómo viaja un lote

Un lote es un código y una fecha de consumo preferente para un SKO. El stock lleva su lote desde que llega hasta que sale:

- **Entregas de proveedores:** la recepción registra el lote al comprobar, y al ubicar se pone en la estantería. Ver [Cursar una orden de compra y recibir la mercancía](/docs/raising-a-purchase-order-and-receiving-the-goods-es).
- **Nuestra producción:** una orden de trabajo terminada recibe un lote al guardarse, con la fecha escrita para la tanda o calculada con la vida útil del artefacto. Ver [Guardar la producción terminada](/docs/putting-away-finished-production-es).
- **Entregas de una organización socia:** los lotes que el socio recogió llegan ya rellenados en tu entrega. Ver [Comprar a un socio](/docs/buying-from-a-partner-es).
- **Picking:** cada pick toma el lote con la fecha de consumo preferente más próxima en esa ubicación, y se divide entre lotes cuando hace falta. Ver [Picking y packing de un albarán](/docs/picking-and-packing-a-delivery-note-es).
- **Las devoluciones y los picks cancelados** vuelven a la estantería como los lotes que se recogieron.

El stock sin lote registrado se trata como el más antiguo de la estantería, así que se usa primero.

## Qué hace cada equipo

| Quién | Qué hacer | Cada cuánto |
|---|---|---|
| Recepción | Al **comprobar**, pulsa **+ Batch** bajo la cantidad comprobada y escribe el código de lote y la fecha de la etiqueta. Divide con **Add batch** cuando una línea llegó en varios lotes. | Cada entrega de proveedor |
| Recepción, entregas de socios | Confirma los lotes ya rellenados, corrígelos si la mercancía dice otra cosa. | Cada entrega de socio |
| Pickers | Nada, salvo que hayas cogido otro lote distinto del que aparece: entonces cámbialo en el pick. | Cuando pase |
| Producción | Rellena la vida útil de cada artefacto (o de una familia entera). Sin ella, lo fabricado recibe lote pero no fecha de consumo preferente. | Una vez, y para artefactos nuevos |
| Almacén | Etiqueta el stock que ya está en las estanterías: en la página **Batch Codes** del SKO, **Count batches**, cuenta cada ubicación tal como viene impreso y guarda. Empieza por alimentación, aromas y cosmética. | Una vez por SKO |
| Responsable de la familia de stock | Activa **Batch tracked** (página de edición de la familia) donde la fecha importa. Sus líneas de recepción avisan hasta que cada SKO tenga lote. | Una vez por familia |

Nada bloquea nunca: la mercancía sin código impreso se puede recibir y recoger igualmente. Simplemente aparece como *sin lote* en los informes, y esa es la señal para corregirla.

## Ver lo que está a punto de caducar

- **Inventory → Batch Codes** muestra los lotes en las estanterías, primero los de fecha más próxima, con los SKO que quedan, el número de ubicaciones y los días que faltan: en ámbar a menos de 90 días y en rojo cuando ya ha caducado. Un lote sin fecha se señala.
- La página **Batch Codes** de un SKO muestra lo mismo para ese SKO, y es donde lo cuentas lote a lote.
- La exportación de SKO (**Inventory → SKOs**, exportar) añade para cada SKO la fecha más próxima en las estanterías, los SKO ya caducados, los que caducan en 30 y en 90 días, y los SKO sin lote.
- El PDF del albarán muestra lote, fecha y cantidad debajo de cada artículo.

Más sobre ubicaciones y recuentos: [Áreas, ubicaciones y stock del almacén](/docs/warehouse-areas-locations-and-stock-es).

<aside class="wayfinder"><strong>Dónde hacer clic en aiku</strong>
<ul>
<li><b>Lotes a punto de caducar:</b> tu almacén → <b>Inventory → Batch Codes</b>.</li>
<li><b>Escribir lotes en la recepción:</b> la entrega → <b>Items</b> → <b>+ Batch</b> bajo la cantidad comprobada.</li>
<li><b>Etiquetar el stock que ya está en las estanterías:</b> <b>Inventory → SKOs</b> → el SKO → <b>Batch Codes</b> → <b>Count batches</b>.</li>
<li><b>Activar el seguimiento por lotes:</b> <b>Goods → Families</b> → la familia → editar → <b>Batch tracked</b>.</li>
<li><b>Exportar la fecha por SKO:</b> <b>Inventory → SKOs</b> → exportar.</li>
</ul>
</aside>
