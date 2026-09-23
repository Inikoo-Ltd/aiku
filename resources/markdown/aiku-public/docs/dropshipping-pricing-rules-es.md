---
title: Cómo se fijan los precios de venta de un cliente de dropshipping
summary: De dónde sale el precio en Mis productos de un cliente, qué hace realmente una regla de precio de +100% o +200%, por qué cambiar la regla no cambia el precio de los productos ya añadidos y cómo el cliente los vuelve a calcular.
date: 2026-09-23
source_date: 2026-09-23
tags: dropshipping, crm, prices, shopify, sales channels
category: crm
help_routes: grp.org.shops.show.crm.customers.show.customer_sales_channels.
---

<aside class="tldr">
El precio de venta de un cliente de dropshipping es <b>nuestro PVP más la regla de precio que el cliente puso en ese canal de venta</b>. La regla se aplica <b>en el momento de añadir el producto</b> al canal. Si la regla cambia después, los productos ya añadidos no cambian, así que un canal puede tener productos con márgenes distintos. <b>+100% es el doble del PVP, +200% es el triple.</b> El cliente corrige los productos existentes él mismo con <b>Edit Price</b> en Mis productos.
</aside>

## De dónde sale el precio

Cada producto tiene nuestro **PVP**, el precio de venta recomendado. Es el punto de partida para todos los clientes de dropshipping y es igual para todos.

Cada canal de venta del cliente (una tienda Shopify, una tienda WooCommerce, una tienda manual, etc.) puede tener su propia **regla de precio**:

- **Porcentaje** — un porcentaje por encima del PVP.
- **Fijo** — un importe por encima del PVP.
- **Sin regla** — el producto se pone al PVP.

Cuando el cliente añade un producto al canal, aiku toma el PVP, aplica la regla del canal y ese es el precio que aparece en **Mis productos**. Es también el precio que se envía a su tienda Shopify u otra cuando se sube el producto.

El precio de coste que el cliente nos paga no entra en el cálculo. La regla se suma sobre el PVP, no sobre el coste.

## Qué significan realmente +100% y +200%

El porcentaje se **suma** al PVP. Mucha gente lo lee como un multiplicador, y no lo es:

| Regla | Precio | Ejemplo con PVP 16,18 |
|---|---|---|
| sin regla o +0% | PVP | 16,18 |
| +50% | 1,5 × PVP | 24,27 |
| +100% | 2 × PVP | 32,36 |
| +200% | 3 × PVP | 48,54 |

Comparado con el precio de coste, el salto parece aún mayor: un producto que cuesta 7,17 con un PVP de 16,18 se vende a 48,54 con una regla de +200%, casi siete veces su coste.

## Cambiar la regla no cambia lo que ya está en el canal

La regla se usa **en el momento en que se añade un producto**. Si el cliente cambia la regla después:

- los productos que se añadan **a partir de entonces** llevan la regla nueva;
- los productos **que ya están en el canal** conservan el precio que recibieron.

Por eso, un cliente que añade productos, cambia la regla y añade más verá márgenes distintos en el mismo canal. Algunos productos están al PVP porque se añadieron antes de que hubiera regla, y otros están al doble o al triple según la regla que había ese día. Esta es la causa habitual de los avisos de «el margen no es uniforme». Nuestros PVP están bien; los precios son exactamente lo que decía la regla en ese momento.

## Cómo el cliente vuelve a calcular sus precios

Lo hace el propio cliente, desde su cuenta:

1. Abrir **Mis productos** en el canal de venta.
2. Seleccionar los productos (seleccionar todos para el canal entero).
3. Pulsar **Edit Price**.
4. En **Price Mapping**, elegir **± % over live RRP** (o el importe fijo) y escribir el margen, por ejemplo **100** para el doble del PVP.
5. Guardar. Los precios nuevos se calculan con el PVP de hoy y se envían a su tienda.

A partir de entonces, los productos recalculados así cuentan como productos con precio propio.

## Cuando un cliente avisa de un «PVP incorrecto»

1. Abrir el cliente, ir a sus canales de venta y anotar la regla de precio de cada uno.
2. Comparar algunos productos: el PVP del producto y el precio en el canal. Un múltiplo exacto del PVP (2×, 3×) indica una regla, no un error.
3. Explicarle la regla y la tabla de arriba, e indicarle **Edit Price** para recalcular.

Si el precio no es un múltiplo exacto del PVP y ninguna regla lo explica, abrir un ticket con los códigos de producto y el nombre del canal.

<aside class="wayfinder">

### Dónde hacer clic en aiku

- **Ver los canales de un cliente y sus productos** — **CRM → Customers**, abrir el cliente y luego **Channels**.
- **Comprobar nuestro PVP** — abrir el producto en el catálogo de la tienda.

### Dónde hace clic el cliente

- **Poner la regla de precio** — en los ajustes de su canal de venta, **Pricing Policy**. Muestra un PVP de ejemplo y el precio que resulta.
- **Recalcular productos ya añadidos** — **Mis productos**, seleccionar productos, **Edit Price**.

### Permisos necesarios

- Ver clientes y sus canales forma parte del trabajo normal de atención al cliente en esa tienda. Los precios de los canales del cliente los fija el cliente; no los cambiamos por él.

</aside>
