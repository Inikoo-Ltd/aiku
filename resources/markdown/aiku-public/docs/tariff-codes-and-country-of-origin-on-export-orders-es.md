---
title: Códigos arancelarios y país de origen en pedidos de exportación
summary: Descubre dónde vive la información aduanera de un albarán, por qué el peso y el valor se reparten entre las partes de un producto multiparte, y por qué la pestaña puede diferir legítimamente de la factura.
date: 2026-09-12
source_date: 2026-09-12
tags: dispatch, customs, tariff codes
category: dispatch
---

<aside class="tldr">
Todo albarán tiene una pestaña <b>Tariff codes / Origin</b> [Códigos arancelarios / Origen], junto a <b>Items</b> [Artículos]. Lista lo que hay físicamente en las cajas, una fila por código arancelario y país de origen, con las unidades, el peso y el valor correspondientes. Puedes exportarla a Excel para el papeleo de aduanas. Si a una fila le falta algún dato, queda fijada arriba del todo para que la corrijas rápido. Aquí tienes cómo leer la pestaña, por qué un producto multiparte puede verse raro en ella, y por qué no tiene por qué coincidir con la factura.
</aside>

## Dónde encontrarla

Abre un albarán — desde la lista de tu almacén en **Dispatching → Delivery notes** [Expedición → Albaranes], o desde la propia pestaña **Delivery notes** [Albaranes] de un pedido — y verás **Tariff codes / Origin** [Códigos arancelarios / Origen] junto a **Items** [Artículos]. Cada fila agrupa todo lo del albarán que comparte el mismo código arancelario y el mismo país de origen: el código, su descripción, la bandera de origen, si está marcado como mercancía peligrosa (DG), las partes que cubre, los números ONU si los hay, y el total de unidades, peso y valor.

## Por qué cuenta por parte, no por producto

Un producto puede estar compuesto de más de una parte. Un rodillo facial vendido con su propia bolsa, por ejemplo, son en realidad dos SKO (referencias de stock) distintas por debajo, cada una con su propio código arancelario. La pestaña lista cada parte bajo su propio código, con su propia parte de las unidades y el peso. Una bolsa vendida por separado, y esa misma bolsa vendida dentro del set con el rodillo, acaban ambas bajo el código arancelario de la bolsa — porque eso es lo que le importa a aduanas: lo que hay realmente en la caja, no cómo se vendió.

La misma lógica se aplica al peso y a las unidades: se calculan por parte, no por producto completo.

## De dónde sale el importe

El valor es más complicado, porque una línea de pedido tiene un precio como producto completo, no parte por parte. Para repartirlo, aiku usa la mejor base que todas las partes de esa línea tengan en común: el precio de venta propio de cada parte si todas lo tienen, si no el coste de proveedor, si no el valor de stock, y solo como último recurso un reparto igual entre las partes. Cada parte recibe su parte proporcional, y las partes suman el total de la línea. Si los totales de una línea cambian sin motivo aparente, suele ser porque ha cambiado el precio o el coste de alguna parte.

## Cuando falta algo

Si a alguna parte le falta el código arancelario o el país de origen, aiku no puede situarla, así que fija una fila arriba del todo con un aviso de código arancelario u origen faltante, listando exactamente qué trade units son el problema. Ambos datos viven en el trade unit, así que ahí es donde se corrigen: **Goods → Trade units** [Mercancías → Trade units], abre el trade unit, rellénalos y guarda. Cualquier albarán que lo referencie recogerá el cambio.

Una organización también puede afinar un código arancelario sin tocar el código HS de 6 dígitos compartido que usan todas las organizaciones: puede añadir o cambiar los últimos dígitos nacionales sobre ese código, para sus propias normas de aduanas, sin afectar al código compartido de las demás. La pestaña muestra esa sobrescritura en cuanto existe.

## Sacarla como hoja de cálculo

Pulsa **Export** [Exportar] y obtienes un archivo Excel con la misma agrupación que ves en pantalla. Antes de descargarlo puedes elegir qué columnas incluir: tariff code [código arancelario], description [descripción], origin [origen], UN numbers [números ONU], references [las referencias de las partes], weight (kg) [peso en kg], units [unidades] y amount [importe]. Elige solo lo que necesite el papeleo que estás rellenando.

## Por qué puede diferir de la factura

La factura tiene su propio diseño y exportación **Group by Tariff Code** [Agrupar por código arancelario], que agrupa por producto completo: un producto multiparte va bajo el código de su parte principal, arrastrando todo su precio. La pestaña del albarán, en cambio, reparte ese mismo producto entre cada una de sus partes y sus propios códigos.

Ambas cosas son correctas — responden a preguntas distintas. La pestaña del albarán responde "qué hay físicamente en estas cajas, bajo qué código aduanero", que es lo que necesita el papeleo de exportación. La factura responde "qué compró el cliente, por cuánto", un documento comercial. El valor o el peso de un producto multiparte puede diferir legítimamente entre ambos sitios; eso no es un desajuste que haya que arreglar.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Ver códigos arancelarios y origen de un envío:</b> abre el albarán (desde <b>Dispatching → Delivery notes</b> de tu almacén, o desde la propia pestaña <b>Delivery notes</b> del pedido) → pestaña <b>Tariff codes / Origin</b>, junto a <b>Items</b>.</li>
<li><b>Exportar la pestaña como hoja de cálculo:</b> en la pestaña <b>Tariff codes / Origin</b>, pulsa <b>Export</b> y elige las columnas que necesites.</li>
<li><b>Corregir un código arancelario u origen faltante:</b> tu organización → <b>Goods → Trade units</b>, abre el trade unit y rellena el código arancelario y el país de origen.</li>
<li><b>Ver la agrupación arancelaria propia de la factura:</b> abre la pestaña <b>Invoices</b> del pedido → abre la factura → la opción <b>Group by Tariff Code</b>.</li>
</ul>
</aside>

<aside class="permissions">
<strong>Permisos que necesitas</strong>
Para ver la pestaña <b>Tariff codes / Origin</b> de un albarán necesitas acceso de visualización de dispatching o de fulfilment para ese almacén, o acceso de visualización a los pedidos de la tienda. Cambiar el código arancelario o el país de origen de un trade unit, o fijar una sobrescritura de dígitos nacionales de una organización, requiere acceso de edición de contabilidad.
</aside>
