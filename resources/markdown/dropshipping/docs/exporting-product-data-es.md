---
title: Exportar datos e imágenes de productos
summary: Descarga los productos de un canal como archivo CSV, elige tus propias columnas y filtros, y descarga todas las fotos de producto como un único archivo zip.
date: 2026-09-25
source_date: 2026-09-25
tags: productos, exportar, csv, imágenes, descargar, feed de datos
category: products
shops: awd, dssk, dse
---

<aside class="tldr">
En <b>Mis productos</b> de un canal hay tres botones de descarga: <b>CSV</b> da los detalles completos de cada producto de la lista, <b>⋮</b> te deja elegir las columnas y los filtros para un CSV más pequeño, e <b>Imágenes</b> mete todas las fotos en un único archivo zip. También puedes descargar un producto, familia, departamento o colección desde su página en el <b>Catálogo</b>.
</aside>

## Dónde están los botones

1. Abre <b>Canales</b> en el menú, haz clic en tu canal y abre <b>Mis productos</b>.
2. Arriba a la derecha ves un grupo de botones: <b>CSV</b>, <b>⋮</b> e <b>Imágenes</b>.

Los botones solo aparecen cuando el canal tiene productos y no está cerrado, y no en la pestaña <b>My Bundles</b>. Los archivos contienen solo los productos de este canal. Para exportar otro canal, abre su <b>Mis productos</b>.

<!-- screenshot: el grupo de botones CSV / ⋮ / Images en la parte superior de My Products -->

## Detalles completos de producto (CSV)

Pulsa <b>CSV</b>. El archivo se descarga al momento. Ábrelo en Excel, Google Sheets o cualquier programa de hojas de cálculo. Cada fila es un producto, cada columna un detalle.

Las columnas son:

- <b>Estado</b>: <b>Activo</b>, <b>Descontinuando</b> o <b>Interrumpido</b>.
- <b>Código de producto</b>, <b>Product user reference</b> (tu propia referencia del producto, si has puesto una).
- <b>Department code</b>, <b>Departamento</b>, <b>Subdepartment code</b>, <b>Subdepartment</b>, <b>Family code</b>, <b>Familia</b>.
- <b>Código de barras</b>, <b>CPNP number</b> (número de cosméticos de la UE, cuando el producto tiene uno).
- <b>Precio</b>: tu precio por una caja exterior (el paquete que pides). <b>Units per outer</b>, <b>Etiqueta de unidad</b>, <b>Unit price</b>.
- <b>Unit Name</b>: el nombre del producto.
- <b>Unit RRP</b>: precio de venta recomendado para una unidad.
- <b>Unit net weight</b> y <b>Package weight (shipping)</b>, en kilogramos. <b>Unit dimensions</b>.
- <b>Materiales/Ingredientes</b>.
- <b>Webpage description (html)</b> y <b>Webpage description (plain text)</b>.
- <b>Country of origin</b>, <b>Código arancelario</b>, <b>Tasa de derecho</b>, <b>HTS EE. UU.</b>.
- <b>Inventario</b>: un nivel de stock, no un número: <b>Normal</b>, <b>Low</b> (menos de 20), <b>VeryLow</b> (menos de 5), <b>OutofStock</b>, <b>Descontinuando</b> o <b>Interrumpido</b>.
- <b>Imágenes</b>: enlaces a las fotos a tamaño completo, separados por comas.
- <b>Data updated</b>, <b>Stock updated</b>, <b>Price updated</b>, <b>Images updated</b>: cuándo cambió cada parte por última vez.
- <b>Available Quantity</b>: el número de unidades en stock. Es 0 cuando el producto no está en venta.
- <b>For sale</b>: <b>Sí</b> o <b>No</b>.

Los paquetes se dejan fuera. Para incluirlos, abre <b>⋮</b> y marca <b>Include bundles</b> primero.

## Tus propias columnas y filtros

Pulsa <b>⋮</b> (<b>Otras opciones de exportación</b>). Se abre un panel:

- <b>Bundles</b>: marca <b>Include bundles</b> para añadir tus paquetes. Está desactivado por defecto y afecta a las dos descargas CSV.
- <b>Columnas para exportar</b>: marca las columnas que quieras. <b>Seleccionar todo</b> y <b>Deseleccionar todo</b> están arriba. Las columnas son los códigos y nombres de producto, departamento, subdepartamento y familia, código de barras, materiales, dimensiones, pesos, origen y códigos de aduana, <b>Inventario</b> (un número), <b>Estado</b> (<b>In stock</b> o <b>Agotado</b>), <b>For sale</b> y <b>Data updated</b>.
- <b>Estado del producto</b>: <b>Activo</b>, <b>Descontinuando</b>, <b>Interrumpido</b>. Solo <b>Activo</b> está marcado al principio.
- <b>Estado de venta del producto</b>: <b>Excluir productos que no estén a la venta</b>, <b>Excluir productos que están fuera de stock</b>, <b>Only products that are not for sale</b>.

Pulsa <b>Exportar propiedades extendidas</b>. El archivo se abre en una pestaña nueva y se descarga. Este archivo no tiene precios, descripciones ni enlaces de imágenes: usa el <b>CSV</b> completo para eso.

<!-- screenshot: el panel Export Options con Columns to Export, Product State y Product Sale Status -->

## Todas las fotos de producto (zip)

1. Pulsa <b>Imágenes</b>. Una ventana dice <b>Your download images request is being processed.</b> Recogemos las fotos de cada producto de la lista.
2. Cuando está listo, la ventana dice <b>Your images are ready for download.</b> Pulsa <b>Descargar</b> y guarda el archivo zip.
3. El botón ahora dice <b>Descargar imágenes</b>. Pasa el ratón por encima para ver cuánto tiempo sigue funcionando el enlace. El enlace caduca un día después de crearse.

Cada foto se nombra con el código del producto y un número, por ejemplo <b>abc-01__12345.jpg</b>, para que puedas ver a qué producto pertenece.

Cuando se añaden o cambian productos en el canal, se borra el zip antiguo. Pulsa <b>Imágenes</b> de nuevo para crear uno nuevo.

No hay vídeos de producto en esta descarga.

## Un producto, familia o colección

En <b>Catálogo</b>, abre un producto, familia, subdepartamento, departamento o colección. Arriba a la derecha:

- <b>CSV</b> descarga sus productos con las mismas columnas que el CSV completo.
- En las páginas de producto, familia y colección, pulsa <b>⋮</b> y elige <b>images</b> en <b>Select another download file type</b> para descargar sus fotos como un archivo zip.

En las páginas de catálogo de nuestra web, cada familia y producto de la lista tiene dos iconos de descarga: <b>Download products (csv)</b> y <b>Download images (zip)</b>.

## Cuando algo va mal

- **No veo los botones CSV e Images.** El canal todavía no tiene productos, el canal está cerrado, o estás en la pestaña <b>My Bundles</b>. Añade productos primero, o vuelve a la pestaña <b>Mis productos</b>.
- **El CSV tiene menos productos que My Products.** El <b>CSV</b> completo deja fuera los paquetes. El archivo de <b>Exportar propiedades extendidas</b> también usa los filtros de <b>Estado del producto</b> y <b>Estado de venta del producto</b>: marca todos los estados para obtenerlo todo.
- **"Select at least one column".** Marca al menos una columna en <b>Columnas para exportar</b>.
- **El enlace de imágenes dice Expired o no se abre.** Pulsa <b>Imágenes</b> de nuevo para crear un zip nuevo.
- **El zip solo tiene un archivo llamado error.txt.** Ninguno de los productos de la lista tiene foto. Comprueba que el canal tiene productos.
- **Excel muestra letras raras.** Abre el archivo con <b>Data → From Text/CSV</b> y elige UTF-8, o ábrelo en Google Sheets.
- **"The data feed for ... is not available yet, please try again later."** El archivo para esa familia o departamento todavía se está generando. Inténtalo de nuevo en unos minutos.
