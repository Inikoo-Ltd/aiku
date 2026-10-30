---
title: La ubicación de envío de AW en Shopify
summary: Qué hacen la ubicación aiku- y el perfil de envío AW Connect shipping en tu tienda de Shopify, qué tarifas de envío ven tus clientes para nuestros productos, y qué hacer cuando los productos aparecen como agotados o los pedidos no nos llegan.
date: 2026-10-30
source_date: 2026-10-30
tags: shopify, ubicación de envío, perfil de envío, tarifas de envío, mercados, stock, agotado
category: sales-channels
series: shopify
order: 3
shops: awd, dssk, dse
---

<aside class="tldr">
Cuando instalas nuestra app, añadimos a tu tienda de Shopify una ubicación de envío llamada <b>aiku-</b> y un perfil de envío, <b>AW Connect shipping</b>, con nuestros productos y nuestros precios de envío. En el checkout tus clientes ven el envío <b>AW Standard</b> para nuestros productos. Normalmente no tienes que hacer nada.
</aside>

## Para qué sirve la ubicación

Shopify guarda el stock por ubicación. Nuestros productos se envían desde nuestro almacén, así que añadimos nuestro almacén a tu tienda como ubicación de envío.

- Su nombre es <b>aiku-</b>, luego el código de nuestra web, y luego el código de tu canal entre paréntesis. Por ejemplo <b>aiku-awd (sho-ab12cd-3e)</b>.
- El stock de cada producto que conectas se guarda en esta ubicación. Lo actualizamos por ti.
- Cuando un cliente compra uno de estos productos, Shopify nos envía una solicitud de fulfilment desde esta ubicación. Así es como nos llega el pedido.

No elimines esta ubicación ni muevas nuestros productos a otra ubicación. Si lo haces, el stock deja de actualizarse y los pedidos dejan de llegarnos.

## Envío configurado por ti

Al instalar la app añadimos un perfil de envío a tu tienda. Su nombre es <b>AW Connect shipping</b>, luego el código de tu canal entre paréntesis, por ejemplo <b>AW Connect shipping (sho-ab12cd-3e)</b>. Funciona igual tanto si tu tienda usa perfiles de envío como envío por mercados.

- Envía solo desde la ubicación <b>aiku-</b>.
- Contiene cada producto que conectas desde nosotros. Añadimos los productos nuevos y quitamos los eliminados por ti, normalmente en diez minutos.
- Sus tarifas son nuestros precios de envío, por país y peso del paquete, en la moneda de tu tienda. En el checkout aparecen como <b>AW Standard</b>. Cuando cambiamos nuestros precios, el perfil los sigue en un día.
- Donde calculamos el envío a mano (paquetes muy pesados, países fuera de nuestras zonas), el perfil usa nuestro precio más alto para esa zona, para que tus clientes siempre puedan comprar. Lo que te cobramos por el pedido no cambia.
- Shopify no te deja editar estas tarifas. Puedes desactivarlas, pero entonces tus clientes no ven ningún envío para nuestros productos, salvo que una de tus propias tarifas envíe desde la ubicación <b>aiku-</b>.

Tus otros productos mantienen tu propia configuración de envíos y tus tarifas. No las cambiamos.

## Tiendas con envío por mercados

Shopify está pasando todas las tiendas de los perfiles de envío al envío por mercados. Una tienda así muestra <b>Shipping has moved to Markets</b> en <b>Settings</b>, <b>Shipping and delivery</b>. Nuestro perfil aparece en cada mercado como un servicio de nuestra app, junto a tus propias tarifas.

Si una de las tarifas de tus mercados envía desde la ubicación <b>aiku-</b>, por ejemplo porque la añadiste a mano como decía una versión antigua de esta guía, tus clientes ven esa tarifa además de <b>AW Standard</b>. Para mostrar solo la nuestra, quita la ubicación <b>aiku-</b> de esa tarifa:

1. En tu administrador de Shopify, abre <b>Markets</b> y luego un mercado al que vendes.
2. En <b>Shipping</b>, junto a una tarifa, pulsa <b>...</b> y elige <b>Edit</b>.
3. En <b>Ships from</b>, quita la ubicación <b>aiku-</b> y guarda.

Nuestros productos solo se venden a los países que incluyen tus mercados. Un cliente en un país que no cubre ninguno de tus mercados los ve agotados.

## Tu propio stock de nuestros productos

Los productos de nuestro perfil de envío solo se envían desde la ubicación <b>aiku-</b>, así que Shopify no los envía desde tus propias ubicaciones. Si tienes algunos de nuestros productos tú mismo, por ejemplo un artículo sin usar que un cliente te devolvió, publícalos en tu tienda como un producto aparte y envía tú esos pedidos.

## Compruébalo tú mismo

Hazlo si nuestros productos aparecen agotados en tu tienda, o el checkout no muestra tarifa de envío para ellos.

1. En tu administrador de Shopify, abre <b>Settings</b>.
2. Abre <b>Shipping and delivery</b>.
3. Busca <b>AW Connect shipping</b>, bajo nuestra app. Debe enviar desde la ubicación <b>aiku-</b> y contener nuestros productos.
4. Si falta, o falta un producto en él, espera un día: revisamos cada tienda cada noche. Si no vuelve, contáctanos.

<!-- screenshot: Shopify Shipping and delivery, el perfil de envío AW Connect shipping bajo la app, con la ubicación aiku-awd -->

Shopify cambia sus menús de vez en cuando, así que los nombres pueden ser algo distintos en tu administrador.

## Cuando algo falla

**Nuestros productos aparecen agotados en Shopify.** Revisa el perfil de envío <b>AW Connect shipping</b> como arriba, y que el país del cliente esté en uno de tus mercados. Comprueba también que el producto está conectado: en <b>Mis productos</b> debe mostrar el apretón de manos verde. Consulta [Gestionar productos en Shopify](/docs/managing-products-on-shopify).

**Error al subir "No Shopify location, the AW fulfilment service is not installed on this store so stock can not be sent".** Falta la ubicación <b>aiku-</b>. Abre el canal. Si ves <b>Haga clic aquí para instalar</b>, púlsalo e instala la app en Shopify. Si el canal muestra <b>Restablecer canal</b>, úsalo para crear la ubicación de nuevo.

**Mensaje de registro "The specified inventory item is not stocked at the location".** El producto en Shopify no tiene stock en la ubicación <b>aiku-</b>, por ejemplo porque se movió a otra ubicación en Shopify. Vuelve a vincular el producto con <b>Conectar con otros productos</b> en <b>Mis productos</b>.

**Los pedidos no nos llegan.** Shopify solo nos envía pedidos de artículos con stock en la ubicación <b>aiku-</b>. Si el producto tenía stock en tu propia ubicación, Shopify espera que lo envíes tú. Consulta [Tus pedidos de Shopify y su estado](/docs/shopify-order-status).

<aside class="wayfinder"><strong>Dónde pulsar</strong>
<ul>
<li><b>Comprobar nuestro perfil de envío:</b> administrador de Shopify → <b>Settings</b> → <b>Shipping and delivery</b> → <b>AW Connect shipping</b>.</li>
<li><b>Envío por mercados:</b> administrador de Shopify → <b>Markets</b> → tu mercado → <b>Shipping</b>.</li>
<li><b>Comprobar el canal:</b> <b>Canales</b> → tu tienda de Shopify → los tres iconos junto a su nombre.</li>
</ul>
</aside>
