---
title: Comprobar códigos de barras de SKO con un escáner
summary: Recorre el almacén con un escáner, lee cada etiqueta de SKO como una inscripción en una excavación, y coloca el código de barras en el SKO correcto cuando la etiqueta y la estantería no coinciden.
date: 2026-09-03
source_date: 2026-09-03
tags: warehouse, inventory, barcodes, stock
category: warehouse
---

<aside class="tldr">
Cada caja exterior del almacén lleva un <em>código de barras de SKO</em> (SKO barcode), y aiku guarda un registro de a qué SKO pertenece cada código. Con los años las etiquetas se reimprimen, las cajas se reutilizan y los registros se desvían, así que el código de la estantería y el código en aiku dejan de coincidir. La página <em>Scan SKO barcodes</em> (comprobar códigos de barras de SKO) es una excavación: recorres los pasillos con un escáner en la mano, aiku lee cada etiqueta y te muestra qué cree que contiene, y tú lo confirmas o lo corriges en el momento. El personal de almacén puede comprobar; el personal con acceso de edición de stock también puede corregir.
</aside>

## Qué es un código de barras de SKO

Un SKO es la unidad que cuenta el almacén: una caja de seis, una bolsa de cien, una unidad suelta. Cada SKO puede llevar dos números. El **SKO barcode** (código de barras de SKO) es el CODE 128 impreso en el embalaje exterior, el que escanean los pickers y el personal de recepción de mercancía. El **unit EAN13** es el pequeño código de barras de venta al público que va en el propio producto, que pertenece al producto y aparece en la web. La página del escáner lee ambos, pero solo *mueve* el exterior. El EAN de la unidad tiene su propio editor en la página del SKO y aquí no se toca.

<figure><img src="/art/docs/draw-barcode-dig.svg" alt="Boceto en acuarela de un explorador con sombrero de fieltro agachado en un pasillo del almacén dibujado como un yacimiento arqueológico, iluminando con un escáner de mano una piedra alta tallada con un código de barras; junto a ella flota una tarjeta que muestra un SKO con su foto, ubicaciones y stock, y dos botones grandes, uno verde con el texto All OK y otro ámbar con el texto Move" width="1200" height="750" loading="lazy"><figcaption>Cada etiqueta es un artefacto. Escanéala, léela, decide.</figcaption></figure>

## Abrir el yacimiento

En tu almacén, abre **Inventory** y pulsa **Scan SKO barcodes** (comprobar códigos de barras de SKO) arriba a la derecha. La página está pensada para un teléfono o una tableta: una columna, una caja de escaneo, y botones lo bastante anchos para el pulgar. Cualquier escáner de códigos de barras que escriba como un teclado funciona, y no hace falta tocar antes la caja, la página escucha al escáner estés donde estés en ella. Si no tienes escáner, escribe el número y pulsa Enter.

## Leer una inscripción

Escanea la etiqueta de una caja. Ocurre una de tres cosas.

**El código es conocido.** Aparece una tarjeta con la foto, el código y el nombre del SKO, su estado, el tamaño del paquete, el número que coincidió y si era el código de barras exterior (SKO barcode) o el EAN de la unidad, el stock total, y cada ubicación que lo tiene con la cantidad en cada una. Mira la estantería. Si la caja que tienes en la mano es ese SKO, pulsa **All OK**. La tarjeta se limpia, el contador de arriba sube en uno, y pasas a la siguiente caja.

**El código es conocido pero la estantería no coincide.** La tarjeta indica un SKO y la caja contiene otro. Pulsa **Wrong SKO, move barcode** (SKO incorrecto, mover código de barras). Se abre una caja de búsqueda; escribe parte del código o el nombre de lo que hay realmente en la caja, tócalo en la lista, y confirma con **Assign**. El código de barras deja el SKO que lo tenía por error y pasa al que has elegido. La tarjeta se pone verde con *Barcode assigned* (código de barras asignado) para que sepas que se ha hecho.

**El código es desconocido.** Aparece una tarjeta roja *Barcode not found* (código de barras no encontrado) con el número que aiku acaba de leer. Si sabes qué es la caja, pulsa **Assign to a SKO** (asignar a un SKO), búscalo, tócalo y **Assign**. Si no lo sabes, pulsa **Skip** (omitir) y sigue adelante; el número no queda registrado en ningún sitio.

## Trabajar al revés

A veces empiezas por el SKO en lugar de por la etiqueta: una caja se ha reetiquetado y quieres registrar la pegatina nueva. Desde una página vacía pulsa **Find a SKO** (buscar un SKO), busca y toca el SKO, y luego escanea la etiqueta. El botón **Assign** se activa en cuanto se ha leído un número, y al pulsarlo ese código de barras queda puesto en ese SKO.

## Qué hace mover un código de barras por debajo

Un SKO barcode es una única verdad para todo el grupo. Cuando mueves uno, aiku se lo quita a todos los SKO que lo llevaban, en todas las organizaciones que comparten ese stock, y se lo da al SKO que has elegido, también en todas las organizaciones. Si el SKO elegido ya tenía otro código exterior distinto, el nuevo lo sustituye. Cada cambio queda escrito en la historia (**History**) del SKO, así que un supervisor siempre puede ver cuándo se movió un código de barras y quién lo movió.

Hay dos cosas que la página rechaza. Un número que ya es el código de barras de otro SKO del mismo stock en tu propia organización no se puede repartir entre los dos; y un número con caracteres que una impresora de etiquetas no puede imprimir se rechaza. En ambos casos un aviso rojo lo explica, y nada cambia.

## Una buena sesión

Trabaja un pasillo de principio a fin en lugar de saltar de un sitio a otro, para que el contador te diga algo. Una tarjeta encontrada con un sonido es el caso normal; un zumbido bajo significa no encontrado y merece una mirada. Cuando te encuentres una etiqueta cuyo SKO no puedes identificar, omítela y anota la ubicación, y vuelve luego con alguien que conozca esa gama en lugar de adivinar. Una asignación equivocada es fácil de deshacer, vuelve a escanear la etiqueta y muévela de vuelta, pero una suposición que nadie comprueba se queda mal.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Abrir el escáner:</b> tu almacén → <b>Inventory</b> → botón <b>Scan SKO barcodes</b> arriba a la derecha.</li>
<li><b>Confirmar una coincidencia:</b> escanea → <b>All OK</b>.</li>
<li><b>Mover un código de barras al SKO correcto:</b> escanea → <b>Wrong SKO, move barcode</b> → busca → toca el SKO → <b>Assign</b>.</li>
<li><b>Registrar una etiqueta desconocida:</b> escanea → <b>Assign to a SKO</b> → busca → toca → <b>Assign</b>; o primero <b>Find a SKO</b> y escanea después.</li>
<li><b>Ver los códigos de barras y la historia de un SKO:</b> tu almacén → <b>Inventory → SKOs</b> → abre el SKO → tarjeta <b>Barcodes</b> e <b>History</b>.</li>
</ul>
</aside>
<aside class="permissions"><strong>Permisos que necesitas</strong>
<p>Escanear y confirmar requiere acceso de visualización de inventario o de stock para el almacén. Mover o asignar un código de barras requiere acceso de edición de stock (o el rol de supervisor de stock) para ese almacén; sin él, los botones de mover y asignar quedan ocultos y la página funciona como una comprobación de solo lectura.</p>
</aside>
