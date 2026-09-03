---
title: Comprar a una organización socia
summary: Por qué el comercio entre organizaciones hermanas usa una lista de la compra en lugar de pedidos de compra, y cómo funciona todo el ciclo desde la necesidad listada hasta el stock dado de alta.
date: 2026-09-01
source_date: 2026-09-01
tags: procurement, warehouse, intercompany
category: procurement
series: Ordering from partners
order: 1
---

<aside class="tldr">
Cuando compras a una organización hermana no generas un pedido de compra. Añades lo que necesitas a una lista de la compra; la organización vendedora lo recoge cuando puede enviarlo. A partir de ahí todo fluye solo: su almacén lo prepara y empaqueta, y una entrega de stock entrante aparece en tu lado, lista para dar de alta cuando llegue la mercancía. Si tú <em>haces</em> estos pedidos, empieza por el <a href="/docs/reading-the-partner-shopping-dashboard-es">panel de compras</a> y lee <a href="/docs/buying-from-a-partner-es">Comprar a un socio</a>; si tú los <em>preparas</em>, lee <a href="/docs/fulfilling-partner-orders-es">Preparar pedidos de un socio</a>.
</aside>

<figure><img src="/art/docs/draw-partner-shopping.svg" alt="Boceto en acuarela: la tarjeta de la lista de la compra del comprador (Procurement › Partners › Shopping list, con Auto-fill) y la tarjeta de envío del vendedor con líneas marcadas y un botón Send to warehouse, una flecha discontinua entre ambas, y un camión llevando la mercancía a una caja etiquetada como la entrega de stock entrante" width="1200" height="750" loading="eager"><figcaption>Tú escribes la lista, ellos la preparan y la envían, una entrega de stock llega a tu lado.</figcaption></figure>

## Por qué no hay pedido de compra

Un pedido de compra tiene sentido con un proveedor externo: te comprometes a unas cantidades, ellos confirman, y ambos lados siguen el mismo documento. Entre nuestras propias organizaciones esa ceremonia estorba. El vendedor conoce su propio stock mejor que el comprador, y obligar al comprador a adivinar qué se puede enviar acaba en interminables pedidos modificados.

Así que el flujo se invierte. El **comprador dice lo que necesita**, el **vendedor decide qué se envía y cuándo**. Nadie modifica el pedido de nadie, porque no hay pedido que modificar — solo una lista de necesidades abiertas y un flujo de envíos contra ella.

## El ciclo, de principio a fin

1. El comprador abre el [panel de compras](/docs/reading-the-partner-shopping-dashboard-es) para ver qué se está agotando y cuánto margen hay, y luego [añade lo que necesita a la lista de la compra](/docs/buying-from-a-partner-es) — a mano, desde el catálogo del socio, o con una propuesta de auto-fill.
2. El vendedor [selecciona las líneas que puede enviar y manda el envío a su almacén](/docs/fulfilling-partner-orders-es). Se prepara, empaqueta y despacha como cualquier otro pedido.
3. En el momento en que el envío entra en el almacén del vendedor, aparece una **entrega de stock** entrante en el lado del comprador. Sigue el progreso del vendedor por sí sola — el vendedor es la fuente de verdad hasta que la mercancía llega.
4. Cuando la mercancía llega físicamente, el comprador la recibe, la comprueba y la coloca en ubicaciones exactamente igual que cualquier entrega de proveedor.

## La lista tiene un tope a propósito

La lista del comprador no es una caja de deseos. Se limita a aproximadamente un ciclo de pedido de lo que el socio realmente nos entrega, y los productos nuevos están limitados por el espacio libre de almacén y por una parte proporcional de ese espacio para cada socio. Una lista que nadie puede inundar es una lista que el vendedor puede leer: cuando todo está en ella, nada es urgente. Los artículos sin stock y de rango A están exentos del tope, así que una crisis real nunca queda en cola detrás del límite.

## Dinero, facturas y problemas

No hay facturas de proveedor separadas entre organizaciones. La propia factura del vendedor por el envío **es** el documento, y la entrega de stock entrante está vinculada a ella. Si algo llega incompleto, dañado o equivocado, gestiónalo *después* de haber recibido la entrega — ese es el punto donde la responsabilidad pasa a tu lado — y cualquier reembolso o abono se gestiona contra esa factura vinculada.

## Cosas que conviene saber

- La primera vez que un vendedor prepara un pedido para un socio, se crea en su tienda una cuenta de cliente con el nombre de la organización compradora. Es lo esperado — así es como el envío recorre la maquinaria normal del vendedor.
- Las preparaciones parciales son normales. Una línea preparada en parte deja el resto abierto para un envío posterior; nada se pierde.
- Los precios son los precios actuales de la tienda del vendedor con el descuento intercompañía habitual de la organización compradora aplicado, mostrados en la moneda propia del comprador. Nada se negocia línea a línea; si el acuerdo cambia, se anunciará.

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li><b>Ver las listas de compra y de envío:</b> acceso de <i>ver</i> a procurement en tu organización.</li>
<li><b>Añadir líneas, seleccionar y enviar al almacén:</b> acceso de <i>editar</i> a procurement en la organización que realiza la acción (la del comprador para la lista, la del vendedor para preparar y enviar).</li>
<li><b>Recibir y dar de alta la mercancía llegada:</b> acceso de stock de almacén en el almacén del comprador, igual que cualquier entrega de proveedor.</li>
<li>¿Te falta alguno de estos? Pide a tu administrador que conceda el rol en <b>Sysadmin → Users</b> — los permisos son por organización, así que tenerlos en una no se traslada a su socia.</li>
</ul>
</aside>
