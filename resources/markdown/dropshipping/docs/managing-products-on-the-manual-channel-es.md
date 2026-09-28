---
title: Gestionar productos en el canal Manual/API
summary: Añade los productos que vendes a My Products en un canal Manual/API, impórtalos desde una hoja de cálculo u otro canal, descarga tus datos e imágenes de producto, y elimina los productos que ya no vendes.
date: 2026-09-25
source_date: 2026-09-25
tags: manual, api, mis productos, portafolio, añadir productos, importar, csv, imágenes
category: products
series: manual
order: 2
shops: awd, dssk, dse
---

<aside class="tldr">
<b>Mis productos</b> es la lista de productos que vendes en un canal. Ábrela en tu canal Manual/API y pulsa <b>Añadir productos</b> para elegir productos de nuestro catálogo. En un canal Manual/API no se sube nada a ninguna parte: la lista es para tu propio uso y para la API. No la necesitas para poner pedidos a mano. Para dejar de vender un producto, pulsa el botón <b>X</b> en su fila.
</aside>

## Abrir My Products

Ve a tu canal Manual/API en el menú y abre <b>Mis productos</b>. También puedes pulsar <b>View all</b> en el recuadro <b>Productos</b> de la página del canal.

Si la lista está vacía, la página dice <b>No tienes ningún artículo en tu portafolio</b> y muestra un botón <b>Agregar producto</b>.

Cada fila de producto muestra la foto, el nombre y el código, el stock que tenemos (<b>Cepo:</b>), el peso, tu precio (<b>Precio:</b>) y el precio de venta recomendado (<b>PVP:</b>).

## Añadir productos

1. Pulsa <b>Añadir productos</b>.
2. Se abre una ventana <b>Añade productos a tu producto</b>.
3. Elige por qué buscar: <b>Producto</b> busca nombres y códigos de producto, <b>Departamento</b>, <b>Subdepartamento</b> y <b>Familia</b> encuentran los productos de un grupo con ese nombre.
4. Escribe en el cuadro de búsqueda y marca los productos que quieras.
5. Pulsa <b>Add … products and close</b>. El número es cuántos has marcado.

<!-- screenshot: la ventana Add products con el filtro Product / Department / Sub-department / Family y el botón Add products and close -->

Ves <b>Portafolios añadidos con éxito</b> y los productos aparecen en la lista.

## Añadir muchos productos a la vez

### Desde una hoja de cálculo

1. Pulsa el botón de subida junto a <b>Añadir productos</b> (con el texto <b>Importar desde un archivo xlsx</b>).
2. En la ventana <b>Carteras de importación masiva</b>, pulsa <b>Download template (.xlsx)</b>.
3. Rellena la columna <b>sku</b> con nuestros códigos de producto, uno por fila. La columna <b>título</b> es opcional.
4. Sube el archivo.

Las filas se omiten cuando el código no existe en nuestra tienda o el producto no está en venta. El historial de subida muestra qué se añadió y qué falló.

### Desde otro canal

Si ya tienes productos en otro canal, puedes copiarlos. Pulsa el botón de los tres puntos junto a <b>Añadir productos</b>. En <b>Clonar portafolio del canal:</b> elige el canal desde el que copiar. El número entre paréntesis es cuántos productos tiene. La copia se hace en segundo plano y la página se recarga cuando termina.

## Encontrar productos en tu lista

Usa el cuadro de búsqueda, o los botones de filtro encima de la lista:

- <b>Solo en venta</b>: productos que puedes pedir ahora.
- <b>No está a la venta</b>: productos que no vendemos en este momento.
- <b>Interrumpido</b>: productos que no volveremos a vender.
- <b>Agotado</b>: productos sin stock en este momento.

Un icono de caja tachada significa que el producto está interrumpido. Su texto emergente dice <b>This product line has been discontinued. Please remove this item</b>. Un icono de dinero tachado significa <b>Esta línea de productos no está actualmente a la venta.</b>. Retira estos productos de tu propia web para que tus compradores no puedan pedirlos.

## Obtener datos e imágenes de producto para tu web

En un canal Manual/API no subimos productos a tu web. Toma los datos desde aquí:

- <b>CSV</b>: descarga tu lista de productos con precios, stock y descripciones.
- El botón de tres puntos junto a <b>CSV</b> abre <b>Opciones de exportación</b>. Elige las columnas, el <b>Estado del producto</b> y el <b>Estado de venta del producto</b> que quieras, y pulsa <b>Exportar propiedades extendidas</b>. Marca <b>Include bundles</b> para añadir tus paquetes.
- <b>Imágenes</b>: prepara una descarga de las fotos de tus productos. Cuando está lista, pulsa <b>Descargar imágenes</b>. El enlace solo funciona durante un tiempo limitado, indicado en el texto emergente del botón.
- A través de la API, tu sistema puede leer la misma lista, y descargarla como un feed CSV o JSON. Consulta [El canal Manual/API](/docs/manual-and-api-channel).

El stock y los precios cambian. Descarga la lista de nuevo, o léela por la API, con la frecuencia necesaria para que tu web esté al día.

## Eliminar un producto

Pulsa el botón <b>X</b> en la fila del producto (texto emergente <b>Eliminar producto de la lista</b>). El producto sale de tu lista. Los pedidos que ya hiciste con él no cambian. Puedes añadirlo de nuevo más tarde con <b>Añadir productos</b>.

## Cuando algo va mal

- <b>No encuentro un producto en la ventana Add products.</b> Comprueba que buscas en la pestaña correcta: <b>Producto</b> busca nombres y códigos de producto, <b>Familia</b> y <b>Departamento</b> buscan nombres de grupo. La ventana no muestra los productos que ya están en tu lista, los que no están en venta y los productos interrumpidos.
- <b>Mi subida de hoja de cálculo omitió filas con "SKU not found in this shop".</b> El código de la columna <b>sku</b> no es uno de nuestros códigos de producto en esta web. Copia el código exactamente como aparece en el producto.
- <b>Mi subida de hoja de cálculo omitió filas con "Product is not for sale".</b> No vendemos ese producto en este momento. Déjalo fuera.
- <b>Un producto aparece como interrumpido o no en venta.</b> No puedes pedirlo. Elimínalo de tu propia web y de <b>Mis productos</b>.
- <b>Un producto está agotado.</b> Se queda en tu lista. Usa <b>Agotado</b> para encontrar estos productos y ocultarlos en tu web hasta que vuelvan.
- <b>El enlace de descarga de imágenes ya no funciona.</b> El enlace caduca. Pulsa <b>Imágenes</b> de nuevo para crear uno nuevo.
- <b>Mis productos no están en mi web.</b> Nunca subimos nada desde un canal Manual/API. Cárgalos tú mismo con la descarga CSV o la API. Si vendes en una plataforma de las que aparecen en la página <b>Agregar canal de ventas</b>, como Shopify, WooCommerce, eBay o TikTok Shop, conecta esa plataforma como su propio canal y los productos se suben por ti.
