---
title: Por qué un producto aparece agotado en mi tienda
summary: Descubre por qué tu tienda muestra un producto como agotado cuando tenemos stock, y corrígelo en Shopify, WooCommerce, eBay, TikTok Shop y Wix.
date: 2026-09-25
source_date: 2026-09-25
tags: stock, agotado, inventario, shopify, wix, woocommerce, ebay, tiktok, ubicación
category: troubleshooting
shops: awd, dssk, dse
---

<aside class="tldr">
Solo enviamos stock a tu tienda de los productos <b>vinculados</b> en <b>Mis productos</b>, y solo mientras tu canal esté conectado. Los motivos habituales de "agotado" son: el producto no está vinculado, está realmente agotado o no está a la venta con nosotros, la configuración de tu canal oculta el stock bajo, o (en Shopify) el stock está en otra ubicación. Revísalos en el orden de abajo y luego pulsa <b>Update Stock</b>.
</aside>

## Cómo llega el stock a tu tienda

- Solo enviamos stock de los productos vinculados a un anuncio en tu tienda: en verde en la columna <b>Estado</b> de <b>Mis productos</b>.
- Cuando cambia nuestro stock, actualizamos tu tienda nosotros mismos. No tienes que hacer nada.
- Enviamos 0 en los productos que no están a la venta o están interrumpidos, aunque queden algunas unidades.
- La configuración de tu canal puede reducir el número que enviamos. Consulta el paso 4.

## Revisa esto, uno por uno

### 1. ¿Está conectado el canal?

Abre <b>Canales</b> en el menú, pulsa tu canal y abre <b>Mis productos</b>. Si un aviso rojo dice <b>Your channel is not connected yet to the platform</b>, no podemos enviar nada. Reconecta el canal primero. En Shopify, asegúrate de haber pulsado <b>Install</b> en Shopify para terminar la conexión.

### 2. ¿Está vinculado el producto?

Busca el producto en <b>Mis productos</b>. En Shopify el estado debe ser el apretón de manos verde (<b>Producto conectado a Shopify</b>). En las demás plataformas los tres tics deben estar en verde.

Si está en rojo, el anuncio de tu tienda no es nuestro según lo que sabemos, así que nunca actualizamos su stock. Suele pasar cuando creaste el producto tú mismo, o lo importaste de otra app. Vincúlalo con <b>Combina con este producto</b>, o vincula todos a la vez con <b>Match all with default product</b>. Consulta [Usar My Products](/docs/my-products).

### 3. ¿Está en stock con nosotros?

Mira <b>Stocks</b> (en Shopify, <b>Inventario</b>) en la fila. Salvo en Shopify, puedes usar el filtro <b>Agotado</b> para listar todos los productos sin stock. Un símbolo de dólar tachado significa <b>Esta línea de productos no está actualmente a la venta.</b>, y una caja tachada significa que está interrumpido. En ambos casos tu tienda tiene razón en mostrar "agotado".

Para que te avisemos cuando un producto vuelva, usa el botón del sobre en el producto de nuestra web. Tus avisos están en <b>Recordatorios de disponibilidad de existencias</b> en el menú.

### 4. Revisa la configuración de stock de tu canal

En la página del canal pulsa <b>Gestionar el canal de ventas</b> (o <b>Editar</b>). En <b>Gestionar stock</b>:

- <b>Actualización de stock</b>: si está apagado, dejamos de actualizar el stock automáticamente. Déjalo encendido.
- <b>Umbral de stock</b>: cuando nuestro stock baja a este número o menos, enviamos 0. Por ejemplo, con un umbral de 10, un producto con 8 unidades aparece agotado. Déjalo vacío para enviar el stock real.
- <b>Cantidad máxima para anunciar</b>: lo máximo que mostramos, aunque tengamos más. Déjalo vacío para no poner tope.

<!-- captura de pantalla: la sección Manage Stock de la configuración del canal con Stock Update, Max Quantity To Advertise y Stock Threshold -->

### 5. Envía el stock ahora

En <b>Mis productos</b>, pulsa <b>Update Stock</b> (Shopify, WooCommerce, eBay, TikTok Shop y Wix). Verás <b>Stock update started</b>. Puede tardar unos minutos. Luego abre la pestaña <b>Logs</b>: las filas de tipo <b>Update Stock</b> muestran <b>Hecho</b> o <b>Fallido</b> con la respuesta de tu plataforma.

Si dice <b>Nothing to update</b>, ninguno de tus productos está vinculado aún. Vuelve al paso 2.

## Shopify

En Shopify nuestro stock está en nuestra propia ubicación de envío, llamada <b>aiku-</b> seguido del código de nuestra tienda y del código de tu canal entre paréntesis, por ejemplo <b>aiku-awd (my-store)</b>.

1. En Shopify, abre <b>Productos</b> y el producto que aparece agotado.
2. En la sección <b>Inventario</b>, comprueba que nuestra ubicación aparece en la lista y tiene stock.
3. Si el stock está en otra ubicación (por ejemplo la dirección de tu propia tienda) con 0, ese es el número que Shopify muestra para esa ubicación. Nuestro stock solo está en nuestra ubicación.

Si Shopify responde que el producto no tiene stock en nuestra ubicación, lo añadimos a nuestra ubicación nosotros mismos, para que la siguiente actualización de stock pueda completarse. Si la pestaña <b>Logs</b> dice <b>No variant on Shopify matches this sku</b>, el SKU de la variante de Shopify no es nuestro código de producto. Cambia el SKU en Shopify por nuestro código, o vuelve a vincular el producto con <b>Conectar con otros productos</b>.

## Wix

Nunca enviamos stock de un producto de Wix que no esté vinculado. Si Wix dice que todos tus productos están agotados, lo más probable es que se añadieran directamente en Wix o no se hayan vinculado. En <b>Mis productos</b>, usa <b>Match all with default product</b> para vincularlos por SKU, o <b>Crear nuevo producto</b> para que los creemos nosotros. Luego pulsa <b>Update Stock</b>.

## eBay

Cuando enviamos 0, eBay muestra el anuncio como agotado. Si tu cuenta de eBay no usa la opción de agotado de eBay, eBay puede terminar el anuncio en su lugar. Activa la opción en tus preferencias de venta de eBay para que los anuncios se mantengan y vuelvan cuando tengamos stock.

## WooCommerce y TikTok Shop

Revisa la pestaña <b>Logs</b>. En WooCommerce, una actualización de stock <b>Fallido</b> con "503", "timed out" o "The store answered with a web page instead of data" significa que tu web no nos dejó entrar. Comprueba que tu sitio está en línea y que tu hosting o plugin de seguridad no nos bloquea, y vuelve a pulsar <b>Update Stock</b>.

## Cuando algo falla

- **"Stock update failed. This channel is not connected to the platform, so stock cannot be updated."** Reconecta el canal e inténtalo de nuevo.
- **"Nothing to update".** Ninguno de tus productos está vinculado. Vincúlalos primero (paso 2).
- **El stock es correcto en My Products pero incorrecto en mi tienda, y Logs muestra Done.** Tu tienda puede sumar stock de sus propias ubicaciones o apps. Comprueba que ninguna otra app o ubicación cambia el stock de ese producto.
- **El producto volvió a tener stock pero mi tienda sigue mostrando 0.** Pulsa <b>Update Stock</b> y revisa la pestaña <b>Logs</b>. Si la actualización muestra <b>Fallido</b>, el mensaje explica por qué.
