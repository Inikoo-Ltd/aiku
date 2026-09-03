---
title: Aplicar un descuento a una colección
summary: Cómo lanzar un porcentaje de descuento sobre todos los productos de una colección, en una tienda o en varias, y cómo comprobar en una cesta que solo esos productos lo reciben.
date: 2026-09-02
source_date: 2026-09-02
tags: discounts, offers, collections
category: shop
series: Collections and collection offers
order: 3
---

<aside class="tldr">
Una <b>Shop Offer</b> (oferta de tienda) normalmente descuenta todos los productos de la cesta. Elige una colección al crearla y descontará solo los productos de esa colección, con la cantidad que quieras, en las fechas que fijes. Es la herramienta para "20% de descuento en todo lo fabricado en un país" o "15% de descuento en el estante de verano". La colección decide quién lo recibe, así que construye primero la colección, ver <a href="/docs/shop-collections-es">Colecciones de tienda</a>. Para un estante compartido por varias tiendas, constrúyelo una vez como colección maestra, ver <a href="/docs/master-collections-es">Colecciones maestras</a>, y luego crea una oferta por tienda.
</aside>

## Crear la oferta

Abre la tienda, ve a **Offers → Campaigns**, abre **Shop offers** y pulsa **Create Shop Offer** (crear oferta de tienda). Rellena:

- **Offer name** (nombre de la oferta), que es lo que el cliente ve en la línea de la cesta, así que escríbelo pensando en él.
- **Select offer type** (tipo de oferta): deja **All Orders** (todos los pedidos). **By minimum amount** (por importe mínimo) hace que el descuento espere a que la cesta alcance un importe, algo que normalmente no quieres para una promoción de colección.
- **Only products in collection** (solo productos de la colección): escribe parte del nombre o código de la colección y selecciónala. Déjalo vacío y la oferta descuenta toda la cesta.
- **Discount** (descuento), un porcentaje.
- **Offer Duration** (duración de la oferta): **Permanent** (permanente), o **Interval** (intervalo) con fecha de inicio y fin. Los botones de **1 day** a **7 day** rellenan las fechas por ti.

Guarda, y llegas a la página de la oferta, ya **Active** (activa) si la fecha de inicio es hoy. El código de la oferta es el código de la campaña, el código de la tienda y el código de la colección unidos, así que una oferta de colección es fácil de reconocer en la lista.

Repite en cada tienda que participe en la promoción. Las ofertas pertenecen a una tienda, así que una promoción a nivel de grupo es una oferta por tienda, todas apuntando a la copia de esa tienda de la misma colección maestra.

## Qué ocurre en la cesta

Cada vez que una cesta cambia, aiku vuelve a calcular su precio. Para una oferta de colección, lee la lista de productos de la colección en ese momento y descuenta las líneas cuyo producto está en ella. Los productos añadidos a la colección después de que empezara la oferta reciben el descuento a partir de entonces, y los productos quitados lo pierden.

Un descuento por línea. Un producto que ya tiene un descuento mayor, por una oferta de familia o por el nivel de recompensas del cliente, conserva el mayor. La oferta de colección nunca se acumula encima, ni nunca quita un precio mejor. Así que un cliente con un nivel de recompensas del 25% ve un 25% en un producto de la colección, no un 45% ni un 20%.

La pestaña **Orders** (pedidos) de la página de la oferta lista todos los pedidos que la usaron, y las cifras de ventas de la oferta salen de esas líneas. Ese es el número que hay que citar cuando una promoción promete ceder una parte de las ventas.

## Comprobarlo antes de anunciarlo

Usa un cliente de prueba sin nivel de recompensas.

1. Añade un producto de la colección. La línea muestra el porcentaje y el nombre de la oferta.
2. Añade un producto que no está en la colección. Sin descuento en esa línea.
3. Sube la cantidad de la línea de la colección. Mismo porcentaje, nada más.
4. Inicia sesión como un cliente con un nivel de recompensas superior al de la oferta. La línea conserva el nivel de recompensas, no ambos.
5. Mira la página del producto en el sitio web y la cesta: el producto de la colección muestra el precio con descuento, el otro producto no.

Si un producto recibe el descuento y no debería, la solución está en la colección, no en la oferta: quítalo de la colección, o quita la familia que lo trajo y añade los productos deseados directamente.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Crear la oferta:</b> tu tienda → <b>Offers → Campaigns</b> → <b>Shop offers</b> → <b>Create Shop Offer</b> → rellena <b>Only products in collection</b>.</li>
<li><b>Ver la oferta:</b> la página de la oferta se abre al guardar; más tarde, tienda → <b>Offers → Offers</b> y busca por nombre.</li>
<li><b>Pedidos que la usaron:</b> la página de la oferta → pestaña <b>Orders</b>.</li>
<li><b>Terminarla antes de tiempo:</b> la página de la oferta → editar → cambia la fecha de fin.</li>
<li><b>Corregir quién la recibe:</b> tienda → <b>Catalogue → Collections</b> → abre la colección → pestaña <b>Products</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permisos que necesitas</strong>
<p>Acceso de edición a los descuentos de la tienda para crear o cambiar la oferta, y acceso de edición a Products de la tienda para cambiar la colección a la que apunta.</p>
</aside>
