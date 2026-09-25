---
title: Gestionar productos en WooCommerce
summary: Añade nuestros productos a tu canal de WooCommerce, créalos en tu tienda o enlázalos con productos que ya vendes, mantén el stock actualizado, y arregla los errores de subida.
date: 2026-09-25
source_date: 2026-09-25
tags: woocommerce, productos, subir, emparejar, sku, stock
category: products
series: woocommerce
order: 2
shops: awd, dssk, dse
---

<aside class="tldr">
Abre tu canal de WooCommerce y ve a <b>Mis productos</b>. Pulsa <b>Añadir productos</b> y elige los productos que quieres vender. Luego envía cada uno a tu tienda: <b>Crear nuevo producto</b> crea un producto nuevo en WooCommerce, y <b>Combina con este producto</b> lo enlaza a un producto que ya tienes en tu tienda. Una marca verde significa que el producto está activo y mantenemos su stock actualizado.
</aside>

## Abrir tu lista de productos

1. Abre <b>Canales</b> en el menú y haz clic en el nombre de tu tienda WooCommerce.
2. En el panel del canal, pulsa <b>View all</b> bajo <b>Productos</b>. Se abre la página <b>Mis productos</b>.

Si la página muestra <b>Your channel is not connected yet to the platform</b>, arregla primero la conexión. Consulta [Conectar tu tienda WooCommerce](connecting-woocommerce).

## Añadir productos a tu lista

1. Pulsa <b>Añadir productos</b>. Se abre la ventana <b>Seleccione los productos que desea agregar a la tienda</b>.
2. Busca por nombre o código. También puedes elegir todo un <b>Departamento</b>, <b>Subdepartamento</b> o <b>Familia</b> en lugar de productos sueltos.
3. Marca los productos que quieras. Pulsa <b>Agregar</b>. El botón muestra cuántos has seleccionado.

Los productos ya están en tu lista, pero todavía no están en tu tienda WooCommerce. Antes tienes que crearlos o emparejarlos.

También puedes añadir muchos productos a la vez desde una hoja de cálculo con el botón de subida junto a <b>Añadir productos</b> (<b>Importar desde un archivo xlsx</b>). Si tienes productos en otro canal, el botón <b>⋮</b> te deja <b>Clone portfolio from channel</b>.

<!-- screenshot: la ventana Select products to be added to shop con algunos productos marcados y el botón Add -->

## Enviar productos a tu tienda

Cada producto tiene una columna <b>Woo Commerce product</b>. Lo que ves ahí depende del producto:

- <b>Crear nuevo producto</b>: crea un producto nuevo en tu tienda WooCommerce, con el nombre, la descripción y el precio de tu lista de productos, y nuestras imágenes, SKU, código de barras, peso, dimensiones y stock.
- <b>Combina con este producto</b>: encontramos un producto en tu tienda con el mismo SKU, o un nombre parecido. Comprueba que es el correcto, y púlsalo para enlazar ambos. Úsalo cuando ya vendes el producto y no quieres una segunda copia.
- <b>Elige otro producto de tu tienda</b> (cuando encontramos una posible coincidencia) o <b>Combínalo con un producto existente en tu tienda.</b> (cuando no encontramos ninguna): abre una lista de los productos de tu tienda. Busca el producto, selecciónalo y pulsa <b>Link ... to selected item on your platform</b>.

Cuando funciona, el producto muestra una marca verde y el nombre de tu producto de WooCommerce. A partir de entonces mantenemos su stock actualizado. Para enlazarlo con otro producto de WooCommerce más tarde, pulsa <b>Change linked listing</b>.

Cuando emparejas un producto, solo lo enlazamos y actualizamos su stock. No cambiamos el nombre, la descripción, el precio ni las imágenes que ya tienes en WooCommerce.

<!-- screenshot: filas de My Products mostrando Create new product, Match with this product, y una marca verde en un producto enlazado -->

### Varios productos a la vez

- Marca varios productos en la lista. Aparecen botones encima de la lista: <b>Create New</b> los envía todos como productos nuevos, <b>Match</b> los enlaza a los productos de tu tienda con el mismo SKU.
- Si algunos productos todavía no están en tu tienda, ves <b>You have ... products not synced yet</b>. Pulsa <b>Upload all as new product</b> para crearlos todos, o <b>Match all with default product</b> para enlazar cada producto que tenga el mismo SKU en tu tienda.

Las subidas grandes se ejecutan en segundo plano y muestran una ventana de progreso. Puedes seguir trabajando mientras se ejecutan.

El emparejamiento busca nuestro SKU, o nuestro código de producto, en tu tienda. Las mayúsculas y minúsculas no importan. Si tus SKU son distintos de los nuestros, usa <b>Combínalo con un producto existente en tu tienda.</b> y elige el producto tú mismo.

## Lo que enviamos a WooCommerce

- Nombre, descripción y precio de tu lista de productos.
- Nuestras imágenes, SKU y código de barras (como GTIN, UPC, EAN o ISBN).
- Peso en la unidad que usa tu tienda, y dimensiones cuando las tenemos.
- País de origen e ingredientes como atributos de producto, y enlaces a documentos de producto en la descripción.
- El stock que puedes vender. Los productos en venta se publican. Los productos agotados, próximamente o todavía no listos se guardan como borradores.

No elegimos una categoría por ti. Los productos nuevos llegan sin categoría, así que añade tus propias categorías en WooCommerce.

## Stock y precios

Enviamos los cambios de stock a tu tienda automáticamente. Pulsa <b>Update Stock</b> arriba de <b>Mis productos</b> para enviar el stock actual de todos tus productos a este canal ahora.

En <b>Gestionar el canal de ventas</b> puedes cambiar cómo se muestra el stock:

- <b>Actualización de stock</b>: activa o desactiva las actualizaciones de stock automáticas.
- <b>Cantidad máxima para anunciar</b>: el número máximo de stock que mostramos en tu tienda, aunque tengamos más.
- <b>Umbral de stock</b>: cuando nuestro stock baja hasta este número, el producto aparece como agotado en tu tienda.

Tu <b>Pricing Policy</b> en <b>Gestionar el canal de ventas</b> fija el precio de los productos que añadas a partir de ahora. No cambia los productos que ya están en tu lista. Para cambiar sus precios, márcalos y pulsa <b>Edit Price</b>.

## Eliminar productos

Hay tres formas de eliminar un producto. Elige con cuidado, porque el botón de calavera también borra el producto de tu tienda WooCommerce.

- El botón de calavera en una fila te pide confirmar, y luego elimina el producto de tu lista y, si está enlazado, lo borra permanentemente de tu tienda WooCommerce. No va a la papelera de WooCommerce.
- <b>Unlink & Delete</b> (tras marcar productos) elimina los productos marcados de tu lista, pero los mantiene en tu tienda WooCommerce. Ya no están enlazados, así que dejamos de actualizar su stock.
- <b>Desconectar</b> (tras marcar productos) mantiene los productos en tu lista y en WooCommerce, pero rompe el enlace. Dejamos de actualizar su stock. Puedes emparejarlos de nuevo más tarde.

Si borras un producto enlazado en WooCommerce tú mismo, también lo eliminamos de tu lista.

Los productos que ya no vendemos muestran un aviso rojo. <b>This product line has been discontinued. Please remove this item</b> significa que deberías eliminarlo de tu tienda. <b>Esta línea de productos no está actualmente a la venta.</b> significa que no puedes subirlo en este momento.

## Comprobar qué pasó

Abre la pestaña <b>Logs</b> (el icono de reloj a la derecha de las pestañas) para ver cada subida, si funcionó, y el mensaje que envió tu tienda.

La columna <b>Estado</b> muestra tres marcas para cada producto: <b>Tiene una identificación de producto válido en la plataforma</b>, <b>Existen en la plataforma</b> y <b>Estado de la plataforma</b>. Tres marcas verdes significan que el producto está enlazado y activo.

## Cuando algo va mal

Si una subida falla, la fila del producto muestra el mensaje de tu tienda y un consejo breve. Los más comunes:

- <b>The store answered with a web page instead of data</b>, un error 503, un tiempo agotado, o una respuesta vacía: tu web está caída, demasiado lenta, en modo mantenimiento, o nos bloquea. Este es el problema de subida más común. Comprueba que tu sitio se abre en el navegador, pide a tu empresa de alojamiento que permita nuestros servidores, y vuelve a subir.
- <b>A product with this SKU already exists in your store</b>, <b>Invalid or duplicated SKU</b>, o <b>product with SKU ... already present in the lookup table</b>: tu tienda ya tiene un producto con ese SKU. Cuando pulsas <b>Crear nuevo producto</b> intentamos enlazarlo con ese producto nosotros mismos. Si el mensaje sigue apareciendo, empareja el producto a mano con <b>Combínalo con un producto existente en tu tienda.</b>. Si no encuentras el producto en tu tienda, mira en la papelera de WooCommerce: un producto borrado sigue guardando el SKU hasta que lo borras definitivamente.
- <b>Invalid or duplicated GTIN</b>: otro producto de tu tienda ya usa el mismo código de barras (GTIN, UPC, EAN o ISBN). Empareja con ese producto, o quita el código de barras del otro producto en WooCommerce, y vuelve a subir.
- <b>Your store could not save the product images</b>: la carpeta de subidas de WordPress no tiene permisos de escritura. Pide a tu empresa de alojamiento que arregle los permisos de la carpeta, y vuelve a subir.
- <b>The account connected to your store is not allowed to create products</b> (o editarlos o leerlos): las claves no tienen permiso <b>Read/Write</b>. Reconecta el canal con una cuenta de administrador.
- <b>Your store rejected the credentials</b>: las claves se borraron o cambiaron en WooCommerce. Pulsa <b>Intenta reconectarte</b> en la página del canal.
- <b>This product no longer exists in your store</b>: el producto se borró en WooCommerce. Créalo de nuevo o empárejalo con otro producto.
- Falta el botón <b>Añadir productos</b>: tu tienda no respondió la última vez que intentamos acceder a ella, así que pausamos el canal. Comprueba que tu web está en línea. El botón vuelve cuando accedemos de nuevo a tu tienda.

Los problemas en tu propia web, como que esté caída, lenta o nos bloquee, y las reglas de producto que fijas en WooCommerce, solo puedes arreglarlos tú o tu empresa de alojamiento.
