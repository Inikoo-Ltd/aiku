---
title: Errores al subir productos a la tienda: ¿nuestros o suyos?
summary: Cuando un cliente de dropshipping dice que sus productos no se suben a eBay, Shopify, WooCommerce, TikTok Shop, Wix o Allegro, lee primero la respuesta de la plataforma en aiku. La mayoría de las veces es una norma de la plataforma y la guía del cliente ya le dice qué hacer.
date: 2026-09-29
source_date: 2026-09-29
tags: dropshipping, crm, sales channels, ebay, shopify, woocommerce, tiktok, wix, allegro, tickets
category: crm
help_routes: grp.org.shops.show.crm.customers.show.customer_sales_channels.index, grp.org.shops.show.crm.customers.show.customer_sales_channels.show.portfolios
---

<aside class="tldr">
Cuando los productos de un cliente no se suben a su tienda, aiku guarda la respuesta exacta que dio la plataforma. Abre el canal del cliente, luego <b>Portfolios</b> &rarr; <b>Logs</b>, y lee el <b>Response</b>. Si el mensaje habla de la cuenta del cliente, sus límites, sus políticas, sus categorías o su propia web, es <b>norma de la plataforma</b>: solo el cliente puede arreglarlo, y la guía del cliente en nuestra web le dice cómo. Envíale esa guía. Abre un ticket solo cuando el mensaje habla de nosotros o de nuestros datos de producto, o cuando nada lo explica.
</aside>

## Lee primero la respuesta

1. Abre el cliente, luego la pestaña <b>Channels</b>. Cada canal muestra su estado: <b>closed</b>, o iconos verdes y rojos para <b>App installed ok</b>, <b>Exist in platform</b> y <b>Platform status</b>. Pasa el ratón por un icono para leerlo.
2. Haz clic en el canal, luego en <b>Portfolios</b>. Los productos aparecen listados con los mismos iconos. Una cruz roja en <b>Platform status</b> significa que el producto no está publicado en la tienda.
3. Abre la pestaña <b>Logs</b>. Cada subida, actualización de stock y reintento es una fila con un <b>Status</b> de <b>Done</b>, <b>In progress</b> o <b>Failed</b>. La columna <b>Response</b> es lo que dijo la plataforma. Haz clic en el icono de código, <b>See the answer of the platform</b>, para leer la respuesta completa.

Un producto sin fila en <b>Logs</b> todavía no se ha enviado. Eso no es un error: el cliente lo ha añadido pero no ha pulsado <b>Create new product</b>, o está esperando su turno.

## ¿De quién es el problema?

<b>De la plataforma</b>, cuando la respuesta habla de:

- un límite: artículos al mes, periodo de prueba, número de productos;
- la cuenta del cliente: no verificada, no es cuenta de vendedor, inactiva, restringida;
- los ajustes del cliente en la plataforma: políticas de envío o devolución, almacenes, condiciones de reclamación, categorías que debe solicitar;
- la propia web del cliente caída, lenta, o bloqueándonos (WooCommerce y Wix);
- un producto ya existente en su tienda con el mismo SKU o código de barras.

No podemos cambiar nada de esto, y los ingenieros tampoco. El cliente tiene que hacerlo en la plataforma.

<b>Nuestro</b>, cuando la respuesta habla de:

- datos de producto que son nuestros: un peso que falta, una marca o tipo que falta, una foto demasiado pequeña, un código de barras que falta y el cliente no puede añadir;
- aiku en sí, o un mensaje que nos nombra;
- una fila <b>Failed</b> sin ningún mensaje;
- el mismo error de repente en muchos clientes de la misma plataforma.

<b>Reconectar no reinicia las normas de la plataforma.</b> Borrar el canal y volver a conectarlo, o cambiar el tipo de cuenta, no cambia nada si el límite o la restricción está en la cuenta del cliente. Un canal nuevo con la misma cuenta choca con la misma pared.

## eBay

Guía a enviar: <b>managing-products-on-ebay</b>, y <b>connecting-ebay</b> para problemas de conexión.

- <b>"This listing would cause you to exceed the number of items you can list"</b> o <b>"… the amount you can list this month"</b>: el límite de venta en eBay del cliente. Solo eBay lo aumenta, en ebay.co.uk/help/selling/listings/selling-limits. Una cuenta business no lo aumenta por sí sola.
- <b>"invalid data in the associated fulfilment policy"</b>: la política de envío que eligió no tiene servicio de envío. Lo arregla en eBay.
- <b>Seller account not finished</b>: termina el registro de vendedor en eBay.
- <b>"not allowed to revise an ended item"</b> o <b>"This Offer is not available"</b>: el anuncio terminó en eBay. Pulsa <b>Create new product</b>.
- <b>"improper words" o "in violation of eBay policy"</b>: revisión propia de eBay. Solo eBay puede responder.
- <b>Overseas Warehouse Block Policy</b>: pide aprobación a eBay.
- <b>Nuestro:</b> <b>"The item specific Brand is missing"</b> (o Type, Item Length, Item Width) y <b>"custom values for Size are no longer supported"</b>. Abre un ticket con el código de producto.

## Shopify

Guías a enviar: <b>managing-products-on-shopify</b>, <b>connecting-shopify</b>, <b>shopify-fulfilment-location</b>.

- <b>Channel not connected yet</b>: la app no se instaló. Pulsa <b>Click here to install</b> y luego <b>Install</b> en Shopify.
- <b>"No Shopify location, the AW fulfilment service is not installed"</b>: lo mismo, la instalación no se terminó.
- <b>A product with the same SKU already exists</b>, <b>"No variant on Shopify matches this sku"</b>, <b>"More than one variant … has the sku"</b>: productos de su tienda. Debe hacer coincidir o arreglar el SKU en Shopify.
- <b>"Throttled"</b>, <b>HTTP 502 o 504</b>: Shopify estaba saturado. Debe intentarlo más tarde.
- <b>Products show as sold out in Shopify</b>: falta la ubicación <b>aiku-</b> en su perfil de envío.
- <b>Nuestro:</b> <b>"You need to add option values"</b> cuando quiere enlazar con variantes existentes. Eso necesita que activemos la opción, así que abre un ticket.

## WooCommerce

Guías a enviar: <b>managing-products-on-woocommerce</b>, <b>connecting-woocommerce</b>.

Casi todos los errores de WooCommerce son la web del cliente: caída, lenta, en mantenimiento, o bloqueando nuestros servidores con un firewall, un plugin de seguridad o Cloudflare. El mensaje nombra el problema y, cuando ayuda, incluye nuestras direcciones IP para su empresa de hosting.

- <b>A web page instead of data, 503, timeout, empty reply</b>: su web. Debe comprobar que abre y pedir a su hosting que nos permita el acceso.
- <b>SKU or GTIN already exists</b>: un producto en su tienda, a veces en la papelera de WooCommerce.
- <b>Could not save the product images</b>: su carpeta de subidas. Lo arregla su hosting.
- <b>Not allowed to create products</b> o <b>rejected the credentials</b>: las claves perdieron permisos o se borraron. Debe reconectar.
- <b>The store no longer accepts our keys</b>: la página del canal muestra un recuadro ámbar con un enlace de reconexión y un botón <b>Copy</b>. Envía ese enlace al cliente. Solo el dueño de la tienda puede autorizar de nuevo.

## TikTok Shop

Guías a enviar: <b>connecting-tiktok-shop</b>, <b>tiktok-shop-warehouse</b>, <b>tiktok-shop-shipping-template</b>.

- <b>Shop probation period</b> o <b>probation tier</b>: las tiendas nuevas de TikTok solo pueden publicar unos pocos productos. El mensaje dice cuántos. Debe esperar, o quitar productos que no vende.
- <b>Requires an active seller account</b>, <b>category qualification</b>, <b>certifications</b>, <b>manufacturer is required</b>, <b>requires a return warehouse</b>: todo se configura en TikTok Seller Center.
- <b>Incorrect price</b>: su precio de venta está fuera de lo que permite TikTok. Lo cambia en <b>My Products</b>.
- <b>No warehouse matches 0</b> o <b>no warehouse yet</b>: añade un almacén por defecto en Seller Center, luego pulsa <b>Save</b> en el canal.
- <b>Nuestro:</b> <b>product_weight</b> recibido como <b>0</b>, una foto por debajo de <b>300 x 300 píxeles</b>, y <b>"the warehouse does not belong to this shop"</b>. Abre un ticket con el código de producto.

## Wix

Guías a enviar: <b>uploading-products-to-wix</b>, <b>connecting-wix</b>.

- <b>"AW Connect isn't supported with your site"</b>: Wix rechaza antes de que nuestra app llegue. Debe tener Wix Stores instalado y en el catálogo nuevo.
- <b>Wix Stores is not installed</b>, <b>channel not connected yet</b>: pulsa <b>Try to reconnect</b> e instala en el mismo sitio.
- <b>No pictures</b>, <b>out of stock</b>: la guía le lleva paso a paso.

## Allegro

Guías a enviar: <b>syncing-products-to-allegro</b>, <b>connecting-allegro</b>.

- <b>"You do not have any Complaints Terms"</b>, <b>inactive or unverified account</b>: se configura en Allegro.
- <b>Missing mandatory parameters</b>, <b>no matching category</b>: la categoría necesita datos que no tenemos. Debe elegir otro producto, o crear la oferta en Allegro y hacer <b>Match</b>.
- <b>Channel not connected</b>: el acceso a Allegro caduca. Pulsa <b>Reconnect</b>.
- <b>Nuestro:</b> <b>"No shipping price list set"</b> que sigue después de reconectar la misma cuenta.

## Enviar la guía

Las guías del cliente están en su propia web, en <b>/docs/</b> y el nombre de la guía: <b>aw-dropship.com</b> para el Reino Unido, <b>aw-dropship.eu</b> para Europa, <b>aw-dropship.es</b> para España. Por ejemplo <b>https://www.aw-dropship.com/docs/managing-products-on-ebay</b>.

Una respuesta que funciona:

> El mensaje viene de eBay, no de nosotros: tu cuenta de eBay tiene un límite mensual de anuncios. Solo eBay puede aumentarlo, aquí: https://www.ebay.co.uk/help/selling/listings/selling-limits?id=4107. Nuestra guía explica esto y los demás mensajes de eBay: https://www.aw-dropship.com/docs/managing-products-on-ebay

Di de dónde viene el mensaje, qué tiene que hacer, y da el enlace. El cliente no necesita esperar a que un ingeniero lo escuche.

## Cuándo sí abrir un ticket

Ábrelo desde el chat (ver [Abrir un ticket desde un chat](/docs/raising-a-ticket-from-a-chat-es)) e incluye:

- el enlace al canal del cliente en aiku;
- el código de producto;
- el texto de <b>Response</b> en <b>Logs</b>, copiado, no retecleado;
- lo que ya has comprobado en la guía del cliente.

Un ticket con la respuesta de la plataforma se puede responder en minutos. Un ticket que dice "error al subir" empieza con un ingeniero haciendo estos mismos pasos.

<aside class="wayfinder"><strong>Dónde hacer clic en aiku</strong>
<ul>
<li><b>Estado del canal:</b> cliente &rarr; <b>Channels</b> &rarr; los iconos en la fila del canal.</li>
<li><b>La respuesta de la plataforma:</b> cliente &rarr; <b>Channels</b> &rarr; el canal &rarr; <b>Portfolios</b> &rarr; <b>Logs</b> &rarr; <b>Response</b>, o el icono de código para la respuesta completa.</li>
<li><b>Enlace de reconexión de WooCommerce:</b> la página del canal &rarr; el recuadro ámbar &rarr; <b>Copy</b>.</li>
<li><b>Volver a enviar productos:</b> <b>Portfolios</b> &rarr; <b>Force Sync</b>.</li>
<li><b>Guías del cliente:</b> la web del cliente &rarr; <b>/docs/</b> y el nombre de la guía.</li>
</ul>
</aside>
