---
title: Crear etiquetas de cumplimiento normativo
summary: Para el equipo de cumplimiento normativo — cómo un SKO consigue su etiqueta de cumplimiento: quién decide lo que debe mostrar, cómo colocar nombres, ingredientes, advertencias en cada idioma, símbolos y texto libre, de dónde viene cada dato, y cómo se publica una etiqueta para que los agentes puedan imprimirla.
date: 2026-09-25
source_date: 2026-09-25
tags: warehouse, inventory, labels, compliance, printing
category: warehouse
series: Compliance labels
order: 1
---

<aside class="tldr">
Cada SKO puede tener su propia etiqueta de cumplimiento normativo: el nombre del producto, el peso, los ingredientes, las advertencias y las instrucciones de uso en cada idioma, la persona responsable, los símbolos de reciclaje y seguridad, y el código de lote y la fecha de caducidad. Tres personas se reparten el trabajo. El <b>Compliance Manager</b> decide lo que debe mostrar cada etiqueta de un SKO, el <b>Compliance Worker</b> diseña la etiqueta, y el <b>Compliance Supervisor</b> la publica. Una vez publicada, los agentes que compran ese producto para nosotros la imprimen con el código de lote y la fecha de caducidad de la mercancía, tal como se explica en <a href="/docs/printing-labels-as-an-agent-es">imprimir etiquetas como agente</a>.
</aside>

## Dónde viven las etiquetas de cumplimiento

Abre un SKO y elige **Labels**, junto a **Batch codes**. La página tiene dos pestañas.

- **Labels** — lo que debe mostrar cada etiqueta, las etiquetas ya creadas para este SKO, y el botón para crear una nueva.
- **Compliance** — la lista de certificados, pruebas de seguridad, códigos arancelarios y otros documentos que necesita el producto, cada uno con su referencia, las fechas entre las que es válido y si está en regla.

Una etiqueta pertenece al SKO de la organización que compra el producto. El mismo producto comprado en el Reino Unido y en Eslovaquia tiene dos SKOs, así que cada uno puede llevar la persona responsable y los idiomas correctos para su propio mercado.

La fábrica llega a las mismas etiquetas desde sus artefactos. Cómo funciona el propio editor de etiquetas — el diseño, la rejilla de etiquetas en la hoja A4, el código de barras — se explica en <a href="/docs/designing-and-printing-labels-es">diseñar e imprimir etiquetas</a>. Esta guía cubre lo que añade una etiqueta de cumplimiento normativo.

## Quién hace qué

| Puesto | Qué hace |
| --- | --- |
| **Compliance Manager** | Marca la información que debe mostrar cada etiqueta de un SKO. |
| **Compliance Worker** | Diseña las etiquetas y las mantiene al día. |
| **Compliance Supervisor** | Revisa las etiquetas y las publica, para que los agentes puedan imprimirlas. |

Los agentes nunca diseñan ni cambian una etiqueta. Solo las ven una vez publicadas.

## Decidir lo que debe mostrar una etiqueta

En la parte superior de la pestaña **Labels** está el recuadro **Mandatory information**. El Compliance Manager marca lo que debe mostrar cada etiqueta de este SKO — por ejemplo los ingredientes, la persona responsable en la UE, las advertencias en alemán y los símbolos de reciclaje — y pulsa **Save**.

Cada etiqueta de la lista muestra entonces cada dato obligatorio como una pequeña marca: verde con un tic cuando la etiqueta lo tiene, roja con un aviso cuando falta. **Una etiqueta con una marca roja no se puede publicar.**

A veces la información ya viene impresa en la caja del proveedor. En ese caso marca **on artwork** en la marca: la etiqueta la cuenta como presente sin imprimirla dos veces.

## Colocar la información

Pulsa **New label**, dale un nombre y, si la tienes, sube el diseño del proveedor como fondo. Después añade lo que debe mostrar la etiqueta.

- **Batch code**, **Expiry date** y **Barcode** tienen sus propios botones. El código de lote y la fecha de caducidad del diseño son solo ejemplos: los reales se escriben cada vez que se imprimen las etiquetas.
- Todo lo demás está en el menú **+ Product information**. Elige un elemento y aparece en la etiqueta con el texto o los símbolos tomados de la ficha del producto. Arrástralo hasta su sitio.

Un elemento que la ficha del producto todavía no tiene aparece en el menú como *not on the product record* y no se puede elegir. Rellena primero la ficha del producto y después vuelve a la etiqueta.

**El texto se copia al colocarlo.** Si la ficha del producto cambia más tarde, quita el elemento de la etiqueta y vuelve a colocarlo, para que la etiqueta lea el texto nuevo.

### Textos en varios idiomas

El nombre del producto, las advertencias y las instrucciones de uso se ofrecen una vez por idioma: *Warnings (German)*, *Warnings (French)* y así sucesivamente. Coloca un bloque por cada idioma que necesite la etiqueta.

Los idiomas ofrecidos son los que pide la ficha del producto, más cada idioma al que ya están traducidos los textos del producto. Un idioma sin traducción todavía se muestra como *type it in*: colócalo y escribe el texto en la etiqueta.

Los textos largos, como ingredientes, advertencias o una dirección, deben ajustarse a varias líneas. Selecciona el elemento, marca **Wrap in a box** y define el ancho en milímetros; el texto se divide entonces en líneas dentro de la caja y conserva sus propios saltos de línea.

### Símbolos

Se colocan como imágenes, y cada uno solo cuando la ficha del producto dice que el producto lo lleva.

| Símbolo | Se muestra cuando la ficha del producto tiene |
| --- | --- |
| Pictogramas de peligro | Los peligros marcados en el producto. |
| Marcas de material de envase, como PET 1 o PAP 21 | Sus códigos de material de envase. |
| Marcas CE, UKCA y WEEE | La marca correspondiente marcada. |
| Bote abierto con los meses, como 12M | Un periodo tras apertura (PAO) elegido como su fecha de consumo preferente. |
| Logotipo e instrucción de clasificación francesa ("FR", "Cet emballage se trie") | **Sorting / Recycling Information** marcado. |

Selecciona un símbolo para cambiar su altura en milímetros.

### Texto libre

**Free text** sirve para cualquier cosa que no sea información del producto: encabezados fijos como "Weight / Peso / váha / Waga / Poids / Gewicht", o "Ingredients / Ingrédients / Inhaltsstoffe". Colócalo y escribe lo que debe decir la etiqueta. El texto libre nunca se toma de la ficha del producto, así que no se puede marcar como obligatorio.

## De dónde viene la información

| En la etiqueta | Se rellena en |
| --- | --- |
| Nombre del producto, peso neto, ingredientes, país de origen, fabricante, CPNP, UFI, SCPN | La unidad comercial. |
| Advertencias e instrucciones de uso | La unidad comercial, en la sección **GPSR** (**Warnings**, **How To Use**). |
| Nombre del producto, advertencias e instrucciones en otros idiomas | El producto en cada tienda, en sus traducciones de **Name** y **GPSR**. |
| Idiomas que debe llevar la etiqueta | La unidad comercial, **Labeling & Compliance Marks** → **Languages**. |
| Persona responsable en el Reino Unido y en la UE | Los datos de nuestras propias empresas del Reino Unido y de la UE, ofrecidos cuando la unidad comercial incluye ese mercado en **Markets**. |
| Importador | Los datos de la organización que compra el producto. |
| Símbolos | La unidad comercial, **Labeling & Compliance Marks** y sus peligros. |
| Código de barras | El código de barras del SKO. |
| Código de lote y fecha de caducidad | Se escriben al imprimir las etiquetas. |

Una etiqueta solo puede ser tan buena como la ficha del producto que hay detrás. Si falta una advertencia en un idioma, añade la traducción al producto en lugar de escribirla en una sola etiqueta: así la próxima etiqueta, y también la web, la tendrán.

## Publicar

Cuando todas las marcas obligatorias están en verde o marcadas como on artwork, el Compliance Supervisor abre la etiqueta y pulsa **Publish**. A partir de ese momento los agentes que compran el producto para nosotros ven la etiqueta y pueden imprimirla.

Una etiqueta publicada todavía se puede mejorar. Cámbiala y pulsa **Publish again**: los cambios quedan activos y la etiqueta sigue publicada. **Unpublish** la retira de los agentes hasta que se publique de nuevo.

<aside class="wayfinder">
<b>Dónde pulsar en aiku</b><br>
Tu almacén → <b>Inventory</b> → <b>SKOs</b> → abre el SKO → <b>Labels</b>. La pestaña <b>Labels</b> tiene <b>Mandatory information</b> y <b>New label</b>; la pestaña <b>Compliance</b> tiene los certificados y las pruebas. La ficha del producto está en <b>Trade Units</b> → abre la unidad comercial → <b>Edit</b>, y las traducciones en el producto de cada tienda → <b>Edit</b>.
</aside>

<aside class="wayfinder">
<b>Permisos que necesitas</b><br>
Uno de los puestos <b>Compliance Manager</b>, <b>Compliance Worker</b> o <b>Compliance Supervisor</b>, en la fila <b>Compliance</b> de los permisos del grupo. Los administradores del grupo pueden hacer los tres.
</aside>
