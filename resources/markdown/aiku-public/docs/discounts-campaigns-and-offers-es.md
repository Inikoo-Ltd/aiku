---
title: Descuentos: campañas y ofertas
summary: Cómo se organizan en campañas los descuentos de una tienda, cómo se construye y programa cada oferta individual, y cómo acaba mostrándose el descuento en un pedido.
date: 2026-09-01
source_date: 2026-09-01
tags: discounts, offers, campaigns
category: shop
---

<aside class="tldr">
Cada tienda mantiene un conjunto fijo de <b>campañas</b> — una por cada tipo de descuento que aiku sabe gestionar, como descuentos por volumen, vales o regalos. Dentro de una campaña creas <b>ofertas</b> individuales: la regla en sí, con fecha de inicio, fecha de fin y la recompensa que otorga. Una oferta pasa sola por un pequeño conjunto de estados — programada, activa, finalizada, suspendida — y en cuanto está activa, aiku la aplica automáticamente cuando se hace un pedido que la cumple.
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

Dentro de una campaña, ve a su pestaña **Offers** y pulsa **Create Offer**. El paso de información básica pide:

- **Offer Code** — un código corto único (solo letras, números y guiones).
- **Offer Name** — el nombre por el que tu equipo la reconocerá.
- **Offer Type** — cuál de los patrones de disparo específicos de la campaña usa esta oferta (por ejemplo, pedir una cantidad mínima de un producto, gastar un importe mínimo, o realizar un número de pedido determinado).

La oferta completa, una vez construida, también incluye:

- Una **fecha de inicio** y una **fecha de fin** opcional.
- Una **duración**: **Permanent**, es decir que simplemente se ejecuta entre esas fechas, o **Interval**, es decir que se repite.
- Una o varias **allowances** — la recompensa en sí. Cada allowance tiene un tipo: **Percentage Off**, **Amount Off**, **Free Items**, **Gift**, **Shipping**, o una combinación **Mixed**.

El disparador de una oferta — lo que un cliente tiene que hacer para ganarla — depende del tipo de campaña en la que vive. Una campaña de product offers se dispara al pedir un producto dado o una cantidad de él; una campaña de category offers se dispara por un departamento, subdepartamento, familia o categoría; una campaña de shop offers se dispara por el importe total del pedido en toda la tienda; una campaña de first order se dispara por ser el primer pedido de un cliente; una campaña de vouchers se dispara al introducir un código de vale; y una campaña de shipping otorga una allowance de envío gratuito en lugar de una reducción de precio.

## Estados: cómo transcurre la vida de una oferta

El estado de una oferta se muestra como uno de estos:

- **Scheduled** — guardada con una fecha de inicio futura, todavía no está activa.
- **Active** — en marcha ahora mismo; los pedidos que la cumplen reciben el descuento.
- **Finished** — ya ha pasado su fecha de fin.
- **Suspended** — desactivada por un miembro del equipo antes de su fin natural.

La pestaña **Settings** de una oferta guarda esta información de programación y estado, junto con una pestaña **Vouchers** (para códigos de vale), una pestaña **Orders** y una pestaña **Customers** que muestra quién ha usado la oferta, y una pestaña **History**.

## Cómo se muestra un descuento en un pedido

Cuando un pedido cumple una oferta, la recompensa se aplica al pedido automáticamente — no la añades tú a mano. En el pedido, cada línea afectada muestra un **Net Amount** junto a su importe original (bruto); donde ambos difieren, el descuento se ha aplicado a esa línea, y la línea puede restablecerse a su importe original si hace falta. Los discretionary discounts — el tipo manual, aplicado por el personal, a diferencia de una oferta automática — se pueden además activar, quitar o restaurar en todas las líneas de un pedido de una vez, como una única acción de **Global discount**, con su propio porcentaje y etiqueta.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Ver todos los descuentos:</b> tu tienda → <b>Offers</b> en la navegación principal, que abre la página <b>Insights</b>.</li>
<li><b>Explorar por tipo de campaña:</b> tienda → <b>Offers → Campaigns</b> → abre una campaña → sus pestañas <b>Overview</b> y <b>Offers</b> (las campañas de Volume/GR también tienen <b>GR Amnesty</b>).</li>
<li><b>Ver todas las ofertas a la vez:</b> tienda → <b>Offers → Offers</b>, con nombre, etiqueta, tipo, fechas de inicio y fin, pedidos, facturas y ventas.</li>
<li><b>Crear una oferta:</b> abre la campaña correspondiente → pestaña <b>Offers</b> → <b>Create Offer</b>.</li>
<li><b>Ver el rendimiento:</b> tienda → <b>Offers → Insights</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permisos que necesitas</strong>
<p>Ver las campañas y ofertas de una tienda requiere acceso de visualización a los descuentos de esa tienda; modificarlas requiere acceso de edición. Pregunta a tu administrador de organización si faltan los botones de crear o editar.</p>
</aside>
