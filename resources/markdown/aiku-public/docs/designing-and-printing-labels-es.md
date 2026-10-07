---
title: Diseñar e imprimir etiquetas
summary: Construye una hoja A4 de etiquetas sobre un artefacto: sube el arte, define la cuadrícula, coloca el código de lote, la fecha de caducidad y un código de barras que escanee bien, y luego publícala para que la planta pueda imprimirla.
date: 2026-09-25
source_date: 2026-09-25
tags: production, crafts, labels, printing
category: production
---

<aside class="tldr">
Una <b>label</b> (etiqueta) pertenece a un artefacto y describe una hoja A4 entera: el arte que hay detrás, cuántas etiquetas entran en la página, y los pocos textos que cambian en cada tanda — el código de lote, la fecha de caducidad, un código de barras. La diseñas una vez en el editor, la <b>publicas</b> (<b>Publish</b>), y desde entonces cualquiera que fabrique ese artefacto la imprime directamente desde el tablero <b>To produce</b> (a producir). Hasta que se publica es un borrador y solo tú la ves.
</aside>

## Dónde viven las etiquetas

Abre un artefacto y elige la pestaña <b>Labels</b> (etiquetas). Todo sobre las etiquetas de ese artefacto pasa ahí: la lista de lo que se ha diseñado, y el editor donde diseñas.

Hay otros dos sitios que conviene conocer. <b>Crafts → Labels</b> (artesanía → etiquetas) lista todas las etiquetas de la fábrica junto con el artefacto al que pertenece cada una, así que puedes encontrar una etiqueta cuando recuerdas el producto pero no el artefacto del que depende. Y el panel de crafts tiene una casilla <b>Labels</b> que muestra cuántas existen y cuántas están publicadas — el segundo número es el que importa, porque una etiqueta sin publicar es invisible para la planta.

## Borrador, diseñada, publicada

Una etiqueta está en uno de tres estados, y la chapa (chip) de cada fila indica cuál.

| Estado | Qué significa |
| --- | --- |
| <b>Raw</b> (sin procesar) | Todavía no se ha tocado en el editor. Suele ser una etiqueta que llegó de una importación. |
| <b>Processed</b> (procesada) | Abierta y editada. Tuya para trabajarla, invisible para la planta. |
| <b>Published</b> (publicada) | En vivo. Se puede imprimir desde el tablero To produce. |

<b>Raw casi siempre significa importada.</b> La mayoría de las etiquetas en aiku no se diseñaron aquí: se trajeron en bloque desde carpetas de PDF de arte guardadas en otro sitio, un archivo por etiqueta, emparejadas con los artefactos por código. Una etiqueta importada llega con toda la A4 siendo una sola etiqueta, con su arte y nada más: sin cuadrícula, sin código de lote, sin fecha de caducidad, sin código de barras. Eso es un reflejo fiel de lo que era el archivo antiguo, no un diseño a medio terminar.

Así que una etiqueta Raw suele ser un buen arte a la espera de que alguien decida si necesita algo impreso encima. Ábrela, añade lo que la tanda necesite, y al guardar pasa a Processed. Una etiqueta que creas tú mismo también es Raw hasta que la abres y la guardas una segunda vez, así que no interpretes Raw como "rota".

Solo una etiqueta publicada se puede imprimir fuera del editor. Ese es todo el sentido de publicar, y por eso el botón <b>Publish</b> lleva un puntito rojo hasta que lo pulsas.

Editar una etiqueta publicada <b>no</b> la devuelve a borrador. Guarda tus cambios y se queda publicada, lo que significa que el cambio está en vivo en el momento en que guardas — útil cuando estás corrigiendo una errata, algo a tener en cuenta cuando la estás rediseñando. Si quieres retirar una etiqueta publicada de circulación, pulsa <b>Unpublish</b> (despublicar): vuelve a Processed y deja de ser imprimible, y no se pierde nada más de ella.

## Empezar una etiqueta

<b>New label</b> (nueva etiqueta) abre el editor. Ponle nombre primero — el nombre es cómo la vuelves a encontrar, y <b>Save</b> (guardar) permanece deshabilitado hasta que haya uno.

<b>Save as new</b> (guardar como nueva) toma el diseño que tienes abierto y lo guarda como una etiqueta separada, la forma rápida de hacer una variante sin tocar la original.

## El arte de fondo

<b>Background artwork</b> (arte de fondo) admite una imagen o un PDF, hasta 8 MB. JPG, PNG, GIF y WebP funcionan todos.

<b>Un PDF es la mejor opción</b> si tienes uno. Se coloca como arte vectorial, así que el texto dentro se mantiene nítido a cualquier tamaño de impresión y sigue siendo seleccionable en la hoja final — una imagen se estira para ajustarse y no puede hacer ninguna de las dos cosas. Si tu PDF se guardó en un formato más nuevo del que el lector de aiku maneja, se convierte automáticamente; solo te enteras si la conversión falla, y entonces la solución es guardarlo de nuevo como PDF 1.4.

El arte se guarda junto con la etiqueta. Abre la etiqueta dentro de seis meses y seguirá imprimiendo contra el arte con el que se diseñó, sin que tengas que volver a subir nada.

<b>Canvas rotation</b> (rotación del lienzo) gira el arte de la etiqueta en cuartos de vuelta. Úsala cuando el archivo se dibujó de lado — gira la imagen, no la página.

## La cuadrícula

<b>Columns</b> (columnas) y <b>Rows</b> (filas) decide cuántas etiquetas entran en la hoja A4, hasta 20 a lo ancho y 30 a lo largo. <b>Page margin</b> (margen de página) es el borde en blanco alrededor de toda la hoja y <b>Gap</b> (espacio) el hueco entre etiquetas. Debajo de las casillas una línea te dice cuántas etiquetas salen de eso y qué tamaño tiene cada una en milímetros, y se pone en rojo si la cuadrícula ya no cabe en la página.

<b>Show cutting guides</b> (mostrar guías de corte) imprime una línea discontinua alrededor de cada etiqueta para poder cortarlas por separado.

Si tu archivo de arte <i>ya</i> es una hoja completa de etiquetas — un diseño que trae la cuadrícula dibujada dentro — marca <b>Artwork already contains the grid</b> (el arte ya contiene la cuadrícula). Toda la A4 se convierte en una sola etiqueta, las casillas de la cuadrícula se apagan, y colocas los textos directamente sobre la imagen. En ese modo duplicas cada texto y pones una copia en cada etiqueta que tenga la imagen, porque aiku ya no los repite por ti.

## Los textos que cambian en cada tanda

Bajo <b>Texts</b> (textos) hay tres botones, y cada uno añade una línea a la hoja:

- <b>Batch code</b> (código de lote) — llega ya rellenado con el código del artefacto y la fecha de hoy. Los artefactos todavía no tienen un código de lote propio, así que esto es un valor por defecto razonable que se supone que debes editar.
- <b>Expiry date</b> (fecha de caducidad) — llega como un año a partir de hoy, con el mismo criterio.
- <b>Barcode</b> (código de barras) — llega con el código de barras que tiene el SKU del artefacto, y si el exterior está en blanco recurre al de la unidad. Si el SKU no tiene ningún código de barras, te queda una línea vacía para escribir.

Cada uno de ellos es simplemente texto que puedes sobrescribir. Selecciona una línea y puedes cambiar su contenido, tamaño, color, negrita y rotación. El cuentagotas recoge un color de la pantalla, que es la forma fácil de igualar un color que ya está en el arte.

## Códigos de barras que realmente escanean

Un código de barras se imprime como barras reales con los dígitos debajo, no como un número, y las barras se dibujan como vectores para que se mantengan nítidas sea como sea que se imprima la hoja.

<b>Symbology</b> (simbología) se elige por ti al añadir el código de barras: trece dígitos se convierten en <b>EAN13</b>, cualquier otra cosa en <b>CODE 128</b>. Puedes cambiarla, y conviene saber por qué difieren las dos. EAN13 es un estándar estricto — exactamente trece dígitos que terminan en el dígito de control correcto — y aiku rechaza cualquier otra cosa antes que imprimir barras que escaneen como un número distinto al de los dígitos debajo. CODE 128 admite también letras, por eso un código de barras exterior que termina en letra acaba ahí.

Si el texto no se puede dibujar con la simbología elegida, el editor lo dice en rojo y la hoja no se genera hasta que se corrija. Aparece un aviso amarillo cuando el código de barras tiene menos de 20 mm de ancho: se seguirá imprimiendo, pero los escáneres de mano tienen dificultades por debajo de eso, así que ensánchalo si la etiqueta tiene sitio.

<b>Digits below</b> (dígitos debajo) se puede apagar si el arte ya imprime el número.

## Colocar todo

La vista previa muestra la hoja entera. La primera etiqueta aparece resaltada y es la única sobre la que arrastras cosas — todo lo que haces ahí se copia a todas las demás etiquetas de la hoja, que es lo que hace que diseñar una hoja de cincuenta merezca la pena hacerlo una sola vez.

Arrastra un texto para moverlo. El cuadradito de un texto seleccionado lo redimensiona: el tamaño de la letra para un texto, el tamaño de las barras para un código de barras. <b>Snap to</b> (ajustar a) coloca un texto exactamente en una esquina, un borde o el centro sin andar ajustando a mano. Haz zoom con los botones sobre la vista previa, o salta directamente con <b>Whole page</b> (página completa) y <b>Edited label</b> (etiqueta editada).

<b>Highlight texts</b> (resaltar textos) atenúa el arte y pone los textos sobre un fondo de contraste. Solo afecta a lo que ves aquí — nada de esto llega al PDF — y es la forma más rápida de encontrar una línea de texto oscuro sobre un fondo oscuro.

## Imprimir

<b>Download PDF</b> (descargar PDF) genera la hoja tal como está, guardada o no, para que puedas comparar una prueba de impresión con las etiquetas reales antes de comprometerte a nada.

Una vez publicada la etiqueta, la planta la imprime desde el carril <b>Preparing</b> (preparando) del tablero To produce, y la cabecera del editor también lleva un enlace <b>Published PDF</b> (PDF publicado). El tablero imprime el código de lote y la fecha de caducidad propios de cada tanda, escritos cuando la tanda se arrastra a <b>Preparing</b>. Una etiqueta que imprime un código de lote o una fecha de caducidad no se puede imprimir desde el tablero hasta que su tanda los tenga, y una tanda no se puede preparar sin ellos.

## Cosas que conviene saber

- <b>La lista dice mucho de un vistazo.</b> Cada fila lleva una miniatura del arte, la cuadrícula, e iconos pequeños para cada uno de código de lote, fecha de caducidad y código de barras que imprima esa etiqueta. Una etiqueta sin iconos no imprime ningún texto cambiante — lo cual está bien para un envoltorio sencillo, y es una señal de alarma para cualquier cosa que necesite un número de lote.
- <b>El arte en PDF muestra su tamaño real en centímetros.</b> Si una etiqueta se ve mal en la página, el tamaño en la fila suele explicarlo: el arte no tiene el tamaño que creías.
- <b>Editar una etiqueta publicada se pone en vivo al guardar.</b> No hay una copia de borrador delante de ella.
- <b>Borrar una etiqueta no se puede deshacer.</b> El diseño y su enlace con el arte se van con ella. El archivo de arte en sí se queda en el artefacto.
- <b>Una etiqueta importada no tiene texto variable por diseño.</b> Imprime exactamente el arte con el que llegó. Si una tanda necesita un número de lote, eso es una decisión que alguien toma en el editor, no algo que la importación hiciera mal.
- <b>El código de lote y la fecha de caducidad del diseño son ejemplos.</b> Solo las vistas previas del editor los muestran: el tablero y los agentes siempre imprimen los propios de la tanda. Elegir <b>Save on the label</b> (guardar en la etiqueta) al preparar una tanda cambia la fecha de caducidad de la que parten las siguientes tandas.

<aside class="wayfinder"><strong>Dónde hacer clic en aiku</strong>
<ul>
<li><b>Diseñar una etiqueta:</b> tu organización → <b>Factory</b> → <b>Crafts</b> → <b>Artefacts</b> → abre el artefacto → pestaña <b>Labels</b> → <b>New label</b>.</li>
<li><b>Todas las etiquetas de la fábrica:</b> <b>Crafts</b> → <b>Labels</b>, o la casilla <b>Labels</b> del panel de crafts.</li>
<li><b>Solo las publicadas:</b> el contador <b>Published</b> de esa casilla, o las chapas de <b>State</b> encima de la lista.</li>
<li><b>Prueba de impresión:</b> abre la etiqueta → <b>Download PDF</b>.</li>
<li><b>Imprimir de verdad:</b> <b>Operations</b> → <b>To produce</b> → el carril <b>Preparing</b>.</li>
<li><b>Retirar una de circulación:</b> ábrela → <b>Unpublish</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Los puestos se fijan en la ficha del empleado dentro de Human Resources y llevan los permisos consigo.</li>
<li>Ver etiquetas y descargar una hoja: un puesto de producción en esa fábrica, o supervisor de la organización.</li>
<li>Diseñar, publicar y despublicar: el permiso de <b>research and development</b> (investigación y desarrollo) de la fábrica, o supervisor de la organización.</li>
<li>El código de barras viene del SKU, que se edita en el almacén bajo <b>Inventory</b>, no aquí.</li>
</ul>
</aside>
