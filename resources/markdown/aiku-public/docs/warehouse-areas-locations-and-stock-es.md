---
title: Áreas, ubicaciones y stock del almacén
summary: Entiende cómo se divide un almacén en áreas y ubicaciones, cómo crear una ubicación nueva, y cómo se coloca y mueve el stock entre ubicaciones.
date: 2026-09-01
source_date: 2026-09-01
tags: warehouse, locations, stock
category: warehouse
---

<aside class="tldr">
Un almacén se divide en <b>areas</b> — secciones como Goods In o una zona de picking — y cada área contiene un conjunto de <b>locations</b>, las estanterías o casillas concretas donde realmente está el stock. Cada SKO (stock keeping object) se coloca en una o varias ubicaciones, y lo mueves entre ellas, o corriges su cantidad, desde la propia pantalla de la ubicación.
</aside>

## Áreas del almacén

Abre tu almacén y ve a **Locations → Areas** en la navegación izquierda. La lista muestra el nombre de cada área, su orden de picking, cuánto stock tiene en valor, cuántas ubicaciones contiene y cuántas de esas ubicaciones están vacías.

Pulsa **Areas** en la parte superior de la lista para crear una nueva. El formulario pide:

- **Code** — una referencia interna, hasta 16 caracteres.
- **Name** — cómo se llama el área.
- **Picking position** — un número opcional que fija dónde cae esta área en el orden de picking.

Al abrir un área llegas a su pestaña **Overview**, con las pestañas **Locations** e **History** al lado. La cabecera de la página muestra cuántas ubicaciones contiene el área, y desde aquí puedes crear una ubicación nueva directamente dentro de esa área.

## Ubicaciones

Las ubicaciones se listan en **Locations → Locations**, en la navegación izquierda del almacén. La lista se puede filtrar a **All**, **Empty** o **Partially empty**, y cada fila muestra el código de la ubicación, su peso máximo, su volumen máximo (en metros cúbicos), cuántas plazas de stock tiene y cuántas de esas plazas están vacías.

### Crear una ubicación

Pulsa el botón de crear y rellena:

- **Code** — la referencia de la ubicación.
- **Max weight (kg)** — lo máximo que debería contener esta ubicación, por peso.
- **Max volume (m³)** — lo máximo que debería contener, por volumen.

Una ubicación se puede crear directamente bajo el almacén, o dentro de un área concreta — en ambos casos acaba en la lista de ubicaciones del almacén.

### Qué muestra la página de una ubicación

Al abrir una ubicación llegas a su pestaña **Overview**, que muestra su peso y volumen máximos, cuántas plazas de stock tiene en total, y cuántas están vacías. Según cómo esté configurada la ubicación, aparecen más pestañas:

- **SKOs** — el stock que hay actualmente en esta ubicación, cuando la ubicación puede contener stock.
- **Pallets** — cualquier palé que esté en la ubicación, cuando la ubicación se usa para fulfilment.
- **Stock movements** — cada cambio de cantidad registrado contra esta ubicación, cuando la ubicación puede contener stock.
- **History** — un registro de las ediciones hechas sobre la propia ubicación.

Una ubicación se puede activar o desactivar para contener stock ordinario, palés de fulfilment o dropshipping — las pestañas de arriba solo aparecen cuando el ajuste correspondiente está activado.

## Stock (SKOs)

Cada producto que hay en el almacén se controla como un SKO. La lista de todo el almacén está en **Inventory → SKOs**, y muestra la referencia de cada SKO, su familia, su nombre, cuántos hay en stock, su valor en stock, las ventas potenciales, el stock en camino de proveedores, y cuántos días de cobertura da el stock actual.

El stock propio de un SKO se reparte entre una o varias ubicaciones — el mismo SKO puede estar en varias casillas a la vez, cada una con su propia cantidad.

## Mover stock entre ubicaciones

Desde la pestaña **SKOs** de una ubicación puedes sacar stock de esa ubicación de dos formas:

- **Move All SKO** — mueve todos los SKO que hay actualmente en la ubicación a otra ubicación que elijas, todos a la vez.
- **Partialy Move SKO** — selecciona primero SKOs concretos de la lista, y mueve solo esos a otra ubicación; también eliges si quitar cada SKO de la ubicación original una vez movido.

Ambos formularios te piden elegir la **destination location** y un **transfer reason** (por ejemplo, un traslado de almacén, una corrección de error de picking o una corrección de casilla equivocada), con una nota opcional. Mover stock así mantiene exacta la cantidad del SKO en cada ubicación y registra el movimiento, que aparece en la pestaña **Stock movements** de ambas ubicaciones.

## Recuentos y auditorías de stock

Cuando el stock de un SKO en todo el almacén cae por debajo del umbral de stock bajo del almacén, aparece en la pantalla **Low Stock Audits**, a la que se llega desde el bloque **Low Stock Audits** del panel **Inventory** del almacén. Esta lista muestra la referencia del SKO, su familia, su nombre, su stock actual y las ubicaciones en las que está — es la lista que trabaja el personal para recontar y confirmar el stock.

Auditar el stock de una ubicación registra la diferencia entre lo que el sistema espera y lo que realmente has contado: introduces la cantidad contada y un motivo (recuento, ganancia de recuento, falta de recuento, dañado, caducado, y otros), y aiku registra el ajuste como un movimiento de stock contra esa ubicación y marca el stock como comprobado.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Ver o añadir áreas del almacén:</b> tu almacén → <b>Locations → Areas</b> → botón <b>Areas</b> para crear una.</li>
<li><b>Ver o añadir ubicaciones:</b> tu almacén → <b>Locations → Locations</b>, o desde la pestaña <b>Locations</b> de un área — usa el botón de crear, o <b>New location</b> en la página de un área.</li>
<li><b>Ver el stock del almacén:</b> tu almacén → <b>Inventory → SKOs</b>.</li>
<li><b>Mover stock entre ubicaciones:</b> abre una ubicación → pestaña <b>SKOs</b> → <b>Move All SKO</b> o selecciona filas y usa <b>Partialy Move SKO</b>.</li>
<li><b>Comprobar stock:</b> tu almacén → panel <b>Inventory</b> → bloque <b>Low Stock Audits</b>.</li>
</ul>
</aside>
<aside class="permissions"><strong>Permisos que necesitas</strong>
<p>Ver áreas, ubicaciones y stock requiere acceso de visualización de inventory o locations para el almacén. Crear o editar áreas y ubicaciones, y mover stock entre ubicaciones, requiere acceso de edición de locations (o el rol de supervisor de locations) para ese almacén.</p>
</aside>
