---
title: Usar My Products
summary: Lee la lista My Products de un canal, envía productos a tu tienda o enlázalos con anuncios que ya tienes, mantén el stock actualizado y lee los errores de subida.
date: 2026-09-25
source_date: 2026-09-25
tags: productos, mis productos, portafolio, subir, emparejar, sku, stock, registros
category: products
shops: awd, dssk, dse
---

<aside class="tldr">
<b>Mis productos</b> es la lista de nuestros productos que vendes en un canal. Cada canal tiene su propia lista. Desde aquí envías cada producto a tu tienda con <b>Crear nuevo producto</b>, o lo enlazas a un anuncio que ya tienes con <b>Match</b>. Cuando el producto está enlazado mantenemos su stock actualizado, y la pestaña <b>Logs</b> muestra cada subida, emparejamiento y actualización de stock con la respuesta de tu plataforma.
</aside>

## Abrir My Products

1. Abre <b>Canales</b> en el menú. Cada canal aparece debajo con su logo.
2. Haz clic en el canal, luego en <b>Mis productos</b>. El número junto a él indica cuántos productos hay en la lista.

La página tiene tres pestañas: <b>Mis productos</b>, <b>My Bundles</b> (consulta [Crear paquetes](/docs/bundles)) y <b>Logs</b> (el icono de reloj a la derecha).

Si un recuadro rojo dice <b>Your channel is not connected yet to the platform</b>, la conexión con tu tienda está rota. No se puede subir nada ni enviar stock hasta que reconectes. Sigue la guía de conexión de tu plataforma.

<!-- screenshot: la página My Products de un canal de Shopify con las pestañas, los botones de arriba y algunas filas -->

## Qué muestra cada fila

- **Product**: nuestro código de producto (haz clic para abrir el producto), el nombre, <b>Stocks</b>, <b>Peso</b> (peso del producto / peso con embalaje), <b>Dimension</b>, nuestro <b>Precio</b> (lo que nos pagas) y el <b>PVP</b>. Si tu canal muestra precios con IVA, ves <b>Price (include VAT)</b> y <b>RRP (include VAT)</b> (no en Shopify).
- **Status**: en Shopify, un apretón de manos verde significa <b>Producto conectado a Shopify</b> y uno rojo significa <b>No conectado</b>. En otras plataformas hay tres marcas: <b>Tiene una identificación de producto válido en la plataforma</b>, <b>Existen en la plataforma</b> y <b>Estado de la plataforma</b>. Tres marcas verdes significan que el producto está activo y enlazado.
- **Message**: una marca verde cuando todo está bien. Un mensaje rojo cuando tu plataforma rechazó el producto (en Shopify, mira en la pestaña <b>Logs</b> en su lugar). Haz clic en él para ver <b>Answer of ...</b> con el texto completo de tu plataforma y, a menudo, qué hacer. Una caja tachada significa <b>This product line has been discontinued. Please remove this item</b>. Un dólar tachado significa <b>Esta línea de productos no está actualmente a la venta.</b>.
- **La columna de producto de tu plataforma** (por ejemplo <b>Shopify product</b> o <b>eBay product</b>): a qué anuncio de tu tienda está enlazado este producto, o los botones para enlazarlo.

## Enviar un producto a tu tienda

Para un producto que todavía no está enlazado tienes dos opciones.

**Crear un anuncio nuevo.** Pulsa <b>Crear nuevo producto</b>. Creamos el producto en tu tienda con nuestro nombre, descripción, imágenes, precio, SKU y stock.

**Enlazar con un anuncio que ya tienes.** Úsalo cuando ya vendes el producto y no quieres una segunda copia.
- Si encontramos un anuncio en tu tienda con el mismo SKU, aparece en la fila. Pulsa <b>Combina con este producto</b>.
- Para elegir uno distinto, pulsa <b>Elige otro producto de tu tienda</b>, o <b>Combínalo con un producto existente en tu tienda.</b> cuando no encontramos nada. Busca en tu tienda, elige el artículo y pulsa <b>Link ... to selected item on your platform</b>.
- Para cambiar un producto ya enlazado, pulsa <b>Change linked listing</b> (en Shopify: <b>Conectar con otros productos</b>).

## Hacer varios productos a la vez

Cuando algunos productos todavía no están enlazados, una barra amarilla dice <b>You have ... products not synced yet</b>. Tiene dos botones:

- <b>Upload all as new product</b>: los crea todos en tu tienda. No aparece en eBay.
- <b>Match all with default product</b>: enlaza cada producto con el anuncio de tu tienda que tenga el mismo SKU. Comparamos el SKU de tu tienda con el SKU del producto en <b>Mis productos</b> y con nuestro código de producto, y las mayúsculas o minúsculas no importan. Los productos sin ningún anuncio con ese SKU se quedan como están.

Para trabajar solo con algunos productos, márcalos en la lista. Aparecen estos botones:

- <b>Create New (...)</b>: crea los productos marcados en tu tienda.
- <b>Match (...)</b>: enlaza los productos marcados por SKU.
- <b>Unlink (...)</b> y <b>Unlink & Delete (...)</b>: consulta [Eliminar productos](/docs/removing-products).
- <b>Edit Price (...)</b>: fija tu precio de venta para los productos marcados en eBay, Shopify, WooCommerce y Wix, como un porcentaje o cantidad por encima o por debajo del PVP. No aparece cuando tu canal está configurado para mantener sus propios precios.

Los trabajos grandes se ejecutan en segundo plano. Una ventana de progreso muestra cuántos están hechos, y la página se recarga sola.

<!-- screenshot: la barra amarilla "products not synced yet" con Upload all as new product y Match all with default product -->

## Encontrar productos en la lista

Usa el cuadro de búsqueda, o los botones de filtro: <b>Solo en venta</b>, <b>No está a la venta</b>, <b>Interrumpido</b> y <b>Agotado</b>. En Shopify los filtros están en el menú <b>Filtrar</b>: <b>Solo en venta</b>, <b>No está a la venta</b>, <b>Interrumpido</b>, <b>Connected to Shopify</b> y <b>Not Connected</b>. En Shopify no hay filtro de agotado.

## Stock

Solo enviamos stock de los productos que están enlazados (estado verde). No tienes que hacer nada: cuando cambia nuestro stock, actualizamos tu tienda.

Para forzar el stock ahora, pulsa <b>Update Stock</b> arriba de la página. Envía el stock actual de los productos de este canal. Si ninguno de tus productos está enlazado todavía, dice <b>Nothing to update</b>. El botón está en los canales de Shopify, WooCommerce, eBay, TikTok Shop y Wix, no en Allegro ni en canales manuales.

Puedes limitar u ocultar el stock en la configuración del canal. Consulta [Por qué un producto aparece agotado en mi tienda](/docs/out-of-stock-in-my-store).

## Otros botones

- <b>Añadir productos</b>, el botón de subida y <b>Clonar portafolio del canal:</b>: añadir productos. Consulta [Buscar y añadir productos](/docs/sourcing-products).
- <b>CSV</b>, <b>⋮</b> (<b>Otras opciones de exportación</b>) e <b>Imágenes</b>: descarga tus datos e imágenes de producto. Consulta [Exportar datos e imágenes de productos](/docs/exporting-product-data).
- <b>Publish ... drafts</b> (solo eBay): publica los anuncios que se subieron a eBay como borradores.
- <b>Update all dimensions</b> (solo Shopify): envía nuestras dimensiones actuales a todos tus productos de Shopify.

## La pestaña Logs

La pestaña <b>Logs</b> lista cada subida, emparejamiento y actualización de stock de este canal: <b>Product Code</b>, <b>Tipo</b> (<b>subir</b>, <b>match</b> o <b>update-stock</b>), <b>Plataforma</b>, <b>Estado</b> (<b>Hecho</b>, <b>In progress</b> o <b>Fallido</b>), la <b>Response</b> de tu plataforma y la <b>Fecha</b>. Mira aquí primero cuando un producto o su stock no llegó.

## Cuando algo va mal

El mensaje en rojo de la fila, y la <b>Response</b> en <b>Logs</b>, es la respuesta de tu plataforma. Los más comunes:

- **Throttled / too many calls / request timeout / internal error.** Tu plataforma nos pidió que fuéramos más despacio, o no respondió a tiempo. No hay nada mal en el producto. Inténtalo de nuevo en unos minutos.
- **The store answered with a web page instead of data, o devolvió 503, se agotó el tiempo o dio una respuesta vacía** (WooCommerce). Tu propia web está caída, en modo mantenimiento, o su plugin de seguridad o alojamiento nos bloquea. Comprueba que tu sitio está en línea. Pide a tu alojamiento que permita nuestra conexión, y vuelve a intentarlo.
- **A product with this SKU already exists in your store / Invalid or duplicated SKU / already present in the lookup table** (WooCommerce). Ya tienes un producto con ese SKU. Usa <b>Match</b> en lugar de <b>Crear nuevo producto</b>. Si el producto antiguo está en la papelera de WooCommerce, vacíala primero.
- **Invalid or duplicated GTIN** (WooCommerce). Otro producto de tu tienda ya usa ese código de barras. Quita el código de barras del otro producto en WooCommerce, o empareja con él.
- **Cannot list more products: your Shop probation tier allows at most 100 total product listings** (TikTok). Es un límite de TikTok para tiendas nuevas, no un problema del producto. Elimina anuncios que no necesites, o pide a TikTok que suba tu nivel.
- **product_weight received 0 / weight cannot be zero** (TikTok). TikTok necesita un peso. No puedes cambiar tú mismo el peso de nuestro producto: contacta con atención al cliente con el código del producto.
- **Image must be at least 300:300** (TikTok). Una de nuestras imágenes es demasiado pequeña para TikTok. Contacta con atención al cliente con el código del producto.
- **Price out of range / incorrect price** (TikTok). TikTok decide el rango de precios que puede usar tu tienda. Comprueba el rango en TikTok Shop Seller Center. Si el precio que enviamos está fuera de él, contacta con atención al cliente con el código del producto.
- **Category qualification / category is restricted** (TikTok). Solicita la categoría en el Qualification Center de TikTok Shop Seller Center, y vuelve a subir.
- **Requires an active seller account** (TikTok) o **create a seller account** (eBay). Termina primero tu cuenta de vendedor en la plataforma.
- **The listing would cause you to exceed the amount you can list this month** (eBay). Alcanzaste tu límite de venta de eBay. Pide a eBay que lo suba, o espera al próximo mes.
- **Invalid data in the associated fulfilment policy** (eBay). Tu política de envío de eBay tiene un problema. Arréglala en eBay, y luego comprueba las políticas elegidas en la configuración de tu canal.
- **Item specific Type / Brand missing, o custom values for Size no longer supported** (eBay). eBay pide datos extra para esa categoría. Consulta [Gestionar productos en eBay](/docs/managing-products-on-ebay).
- **Not allowed to revise an ended item** (eBay). El anuncio terminó en eBay. Desenlaza el producto y créalo de nuevo.
- **Overseas Warehouse Block Policy** (eBay). Si tu cuenta está registrada en algunos países, aparece un <b>Important Notice</b> rojo arriba. eBay puede bloquear anuncios de artículos almacenados fuera. Contacta con el soporte de eBay para pedir aprobación.
- **This product line has been discontinued.** Ya no lo vendemos. Elimínalo de tu lista y de tu tienda.
