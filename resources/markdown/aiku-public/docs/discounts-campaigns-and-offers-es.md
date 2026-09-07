---
title: Descuentos: campañas y ofertas
summary: Cómo se organizan en campañas los descuentos de una tienda, cómo se construye y programa cada oferta individual, y cómo acaba mostrándose el descuento en un pedido.
date: 2026-09-02
source_date: 2026-09-02
tags: discounts, offers, campaigns
category: shop
---

<aside class="tldr">
Cada tienda mantiene un conjunto fijo de <b>campañas</b> — una por cada tipo de descuento que aiku sabe gestionar, como descuentos por volumen, vales o regalos. Dentro de una campaña creas <b>ofertas</b> individuales: la regla en sí, con fecha de inicio, fecha de fin y la recompensa que otorga. Una oferta pasa sola por un pequeño conjunto de estados — programada, activa, finalizada, suspendida — y en cuanto está activa, aiku la aplica automáticamente cuando se hace un pedido que la cumple. Conviene saber una cosa antes de empezar: el botón que crea una oferta no siempre está en la página de la campaña — los step discounts y los gifts se crean desde un producto, y los mix &amp; match desde una familia.
</aside>

## Campañas: una por tipo de descuento

Abre una tienda y ve a **Offers → Campaigns**. Cada fila es una campaña, y su tipo indica qué clase de descuento puede gestionar:

- **Order recursion**
- **Volume/GR discount**
- **First order**
- **Customer offers**
- **Shop offers**
- **Category offers**
- **Product offers**
- **Step offers**
- **Discretionary discounts**
- **Shipping discount**
- **Gifts**
- **Vouchers**

La lista muestra el nombre de la campaña, cuántas ofertas actuales tiene, y a cuántos clientes y pedidos ha afectado. No creas campañas nuevas tú mismo — una tienda ya tiene una de cada tipo — abres la que coincide con el descuento que quieres y añades una oferta dentro de ella.

Abre una campaña y llegarás a su pestaña **Overview**, que resume sus ofertas. Desde ahí puedes pasar a la pestaña **Offers** para ver o añadir las ofertas que contiene, y a **History** para ver qué ha cambiado. Una campaña de Volume/GR discount tiene una pestaña extra, **GR Amnesty**, para su propio tipo de oferta.

Una campaña también tiene su propio estado, mostrado junto a cada una de sus ofertas como grupo: **In process**, **Active**, **Finished** o **Suspended**.

## Ofertas: la regla de descuento en sí

Cada campaña tiene su propio botón de crear, con el nombre de lo que hace — **Create Product Offer**, **Create Voucher**, **Create Gift Offer** — y cada uno abre un formulario hecho para ese único tipo de descuento. Lo que piden todos los formularios es:

- Un **offer name** — el nombre por el que tu equipo la reconocerá.
- Un **disparador** — normalmente una elección entre **By quantity** y **By minimum amount**, más aquello de lo que cuelga la oferta: un producto, una familia, un cliente.
- Un **descuento** — un porcentaje en la mayoría de formularios, un importe fijo en los vouchers, artículos gratis en los gifts.

La oferta completa, una vez construida, también incluye:

- Una **fecha de inicio** y una **fecha de fin** opcional. Deja el inicio vacío y la oferta empieza inmediatamente.
- Una **duración**: **Permanent**, es decir que se ejecuta desde la fecha de inicio sin fin, o **Interval**, es decir que está acotada y la fecha de fin es obligatoria.
- Una o varias **allowances** — la recompensa en sí. Cada allowance tiene un tipo: **Percentage Off**, **Amount Off**, **Free Items**, **Gift**, **Shipping**, o una combinación **Mixed**.

El disparador de una oferta — lo que un cliente tiene que hacer para ganarla — depende del tipo de campaña en la que vive. Una campaña de product offers se dispara al pedir un producto dado o una cantidad de él; una campaña de category offers se dispara por un departamento, subdepartamento, familia o categoría; una campaña de shop offers se dispara por el importe total del pedido en toda la tienda; una campaña de first order se dispara por ser el primer pedido de un cliente; una campaña de vouchers se dispara al introducir un código de vale; y una campaña de shipping otorga una allowance de envío gratuito en lugar de una reducción de precio.

## Dónde se crea realmente cada oferta

En la mayoría de campañas el botón de crear está en la propia página de la campaña, pero no en todas:

| Campaña | Dónde está el botón | Qué dice |
| --- | --- | --- |
| Product offers | página de la campaña | Create Product Offer |
| Category offers | página de la campaña, o la pestaña **Offers** de una familia | Create Category Offer |
| Shop offers | página de la campaña | Create Shop Offer |
| Customer offers | página de la campaña | Create Customer Offer |
| Vouchers | página de la campaña | Create Voucher |
| Gifts | página de la campaña, o la pestaña **Offers** de un producto | Create Gift Offer |
| Shipping discount | página de la campaña | Create Discount Shipping |
| First order | página de la campaña | Create First Order Bonus |
| Step offers | la pestaña **Offers** de un producto | Create Step Discount |
| Volume/GR discount | página de la campaña, en la cabecera | Set up Vol/GR Gift · New GR Amnesty |
| Discretionary discounts | en ningún sitio — la oferta ya existe | — |
| Order recursion | no expuesta | — |

Las rutas del catálogo son las que más se pasan por alto. Abre una familia, ve a su pestaña **Offers**, y tendrás **Create Category Offer** y **Create Mix & Match Offer**; abre un producto y esa misma pestaña lleva **Create Gift Offer** y **Create Step Discount**. Las ofertas creadas ahí se archivan solas en la campaña correcta — un mix & match empezado desde un producto acaba en Product offers, y uno empezado desde una familia acaba en Category offers — así que después aparecen en la campaña aunque nunca la hayas abierto.

## Estados: cómo transcurre la vida de una oferta

El estado de una oferta se muestra como uno de estos:

- **Scheduled** — guardada con una fecha de inicio futura, todavía no está activa.
- **Active** — en marcha ahora mismo; los pedidos que la cumplen reciben el descuento.
- **Finished** — ya ha pasado su fecha de fin.
- **Suspended** — desactivada por un miembro del equipo antes de su fin natural.

Abre una oferta y la página lleva su código como título, mostrando las horas de inicio y fin, su estado, su tipo, y una previsualización del descuento tal y como lo verá el cliente. Sus pestañas son **Orders** y **Customers**, que muestran quién ha usado la oferta, e **History**.

## Cómo se muestra un descuento en un pedido

Cuando un pedido cumple una oferta, la recompensa se aplica al pedido automáticamente — no la añades tú a mano. En el pedido, cada línea afectada muestra un **Net Amount** junto a su importe original (bruto); donde ambos difieren, el descuento se ha aplicado a esa línea, y la línea puede restablecerse a su importe original si hace falta. Los discretionary discounts — el tipo manual, aplicado por el personal, a diferencia de una oferta automática — se pueden además activar, quitar o restaurar en todas las líneas de un pedido de una vez, como una única acción de **Global discount**, con su propio porcentaje y etiqueta.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Ver todos los descuentos:</b> tu tienda → <b>Offers</b> en la navegación principal, que abre la página <b>Insights</b>.</li>
<li><b>Explorar por tipo de campaña:</b> tienda → <b>Offers → Campaigns</b> → abre una campaña → sus pestañas <b>Overview</b> y <b>Offers</b> (las campañas de Volume/GR también tienen <b>GR Amnesty</b>).</li>
<li><b>Ver todas las ofertas a la vez:</b> tienda → <b>Offers → Offers</b>, con nombre, etiqueta, tipo, fechas de inicio y fin, pedidos, facturas y ventas.</li>
<li><b>Crear una oferta:</b> la página de la campaña para la mayoría de tipos; la pestaña <b>Offers</b> de un producto para step discounts y gifts; la pestaña <b>Offers</b> de una familia para category y mix &amp; match.</li>
<li><b>Ver el rendimiento:</b> tienda → <b>Offers → Insights</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permisos que necesitas</strong>
<p>Ver las campañas y ofertas de una tienda requiere acceso de visualización a los descuentos de esa tienda; modificarlas requiere acceso de edición. Pregunta a tu administrador de organización si faltan los botones de crear o editar.</p>
</aside>
