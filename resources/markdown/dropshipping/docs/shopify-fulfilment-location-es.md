---
title: La ubicación de envío de AW en Shopify
summary: Qué hace la ubicación aiku- en tu tienda de Shopify, cómo se añade a tu perfil de envío por ti, y qué hacer cuando los productos aparecen como agotados o los pedidos no nos llegan.
date: 2026-09-25
source_date: 2026-09-25
tags: shopify, ubicación de envío, perfil de envío, stock, agotado
category: sales-channels
series: shopify
order: 3
shops: awd, dssk, dse
---

<aside class="tldr">
Cuando instalas nuestra app, añadimos una ubicación de envío a tu tienda de Shopify. Su nombre empieza por <b>aiku-</b>. La añadimos a tu perfil de envío predeterminado por ti, así que normalmente no tienes que hacer nada. Si usas más de un perfil de envío, comprueba que la ubicación <b>aiku-</b> está en el perfil de nuestros productos, o Shopify los muestra como agotados y no nos envía sus pedidos.
</aside>

## Para qué sirve la ubicación

Shopify guarda el stock por ubicación. Nuestros productos se envían desde nuestro almacén, así que añadimos nuestro almacén a tu tienda como ubicación de envío.

- Su nombre es <b>aiku-</b>, luego el código de nuestra web, y luego el código de tu canal entre paréntesis. Por ejemplo <b>aiku-awd (sho-ab12cd-3e)</b>.
- El stock de cada producto que conectas se guarda en esta ubicación. Lo actualizamos por ti.
- Cuando un cliente compra uno de estos productos, Shopify nos envía una solicitud de fulfilment desde esta ubicación. Así es como nos llega el pedido.

No elimines esta ubicación ni muevas nuestros productos a otra ubicación. Si lo haces, el stock deja de actualizarse y los pedidos dejan de llegarnos.

## Añadida a tu perfil de envío por ti

Shopify solo vende stock de ubicaciones que están en un perfil de envío. Cuando se instala la app, añadimos la ubicación <b>aiku-</b> por ti:

- a tu perfil de envío predeterminado, o
- si tu tienda todavía tiene una ubicación <b>aiku-dse</b> antigua de una conexión anterior, a todos los perfiles de envío en los que esté esa ubicación antigua.

Si la ubicación ya está en uno de tus perfiles de envío, no cambiamos nada.

Ya no necesitas añadir la ubicación a mano, como decían las guías antiguas.

## Compruébalo tú mismo

Hazlo si nuestros productos aparecen agotados en tu tienda, o el checkout no muestra tarifa de envío para ellos.

1. En tu administrador de Shopify, abre <b>Settings</b>.
2. Abre <b>Shipping and delivery</b>.
3. Abre el perfil de envío en el que están nuestros productos. En la mayoría de tiendas es el perfil general.
4. Mira las ubicaciones desde las que envía el perfil. La ubicación <b>aiku-</b> debe estar ahí.
5. Si no está, añádela al perfil y guarda.

<!-- captura de pantalla: Shopify Shipping and delivery, un perfil de envío con la ubicación aiku-awd en su lista de ubicaciones -->

Shopify cambia sus menús de vez en cuando, así que los nombres pueden ser un poco distintos en tu administrador.

Si creaste un perfil de envío personalizado para algunos de nuestros productos, añade también la ubicación <b>aiku-</b> a ese perfil. Solo la añadimos al perfil predeterminado.

## Cuando algo falla

**Nuestros productos aparecen agotados en Shopify.** Revisa el perfil de envío como arriba. Comprueba también que el producto está conectado: en <b>Mis productos</b> debe mostrar el apretón de manos verde. Consulta [Gestionar productos en Shopify](/docs/managing-products-on-shopify).

**Error al subir "No Shopify location, the AW fulfilment service is not installed on this store so stock can not be sent".** Falta la ubicación <b>aiku-</b>. Abre el canal. Si ves <b>Haga clic aquí para instalar</b>, púlsalo e instala la app en Shopify. Si el canal muestra <b>Restablecer canal</b>, úsalo para crear la ubicación de nuevo.

**Mensaje de registro "The specified inventory item is not stocked at the location".** El producto en Shopify no tiene stock en la ubicación <b>aiku-</b>, por ejemplo porque se movió a otra ubicación en Shopify. Vuelve a vincular el producto con <b>Conectar con otros productos</b> en <b>Mis productos</b>.

**Los pedidos no nos llegan.** Shopify solo nos envía pedidos de artículos con stock en la ubicación <b>aiku-</b>. Si el producto tenía stock en tu propia ubicación, Shopify espera que lo envíes tú. Consulta [Tus pedidos de Shopify y su estado](/docs/shopify-order-status).

<aside class="wayfinder"><strong>Dónde pulsar</strong>
<ul>
<li><b>Comprobar la ubicación en Shopify:</b> administrador de Shopify → <b>Settings</b> → <b>Shipping and delivery</b> → tu perfil de envío.</li>
<li><b>Comprobar el canal:</b> <b>Canales</b> → tu tienda de Shopify → los tres iconos junto a su nombre.</li>
</ul>
</aside>
