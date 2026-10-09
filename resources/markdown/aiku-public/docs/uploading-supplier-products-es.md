---
title: Subir productos nuevos de proveedor
summary: Rellena la plantilla de productos de proveedor, súbela en la página del proveedor, revisa cada fila en la vista previa, decide lo que requiera una decisión e importa. Aiku crea las unidades comerciales, los SKO, los productos de proveedor y los pedidos de compra en borrador de una sola vez, y no se crea nada hasta que pulsas Import.
date: 2026-10-07
source_date: 2026-10-07
tags: procurement, supply chain, products, upload
category: procurement
help_routes: grp.supply-chain.suppliers.supplier_products.index, grp.supply-chain.suppliers.supplier_products.uploads.show, grp.supply-chain.suppliers.supplier_products.create
---

<aside class="tldr">
Descarga la plantilla desde la página <b>Products</b> (Productos) del proveedor, rellena una fila por producto y súbela con <b>Attach file</b> (Adjuntar archivo). Aiku lee la hoja, revisa cada fila y abre una <b>vista previa</b>. Las filas marcadas <b>Fix in the sheet</b> (Corregir en la hoja) hay que corregirlas en el archivo. Las filas marcadas <b>Needs a decision</b> (Requiere una decisión) necesitan que marques <b>This is OK, I accept responsibility</b> (Está bien, asumo la responsabilidad) o que omitas la fila. Cuando no quede nada que corregir ni decidir, pulsa <b>Import</b> (Importar): aiku crea las familias, las unidades comerciales, los códigos de barras, los SKO, los productos de proveedor y los pedidos de compra en borrador. Hasta entonces no se crea nada, y <b>Cancel upload</b> (Cancelar subida) lo descarta todo. Para un solo producto, <b>New Supplier Product</b> (Nuevo producto de proveedor) hace lo mismo desde un formulario, sin hoja.
</aside>

## En qué se convierte una fila

Cada fila de la hoja describe un producto que nos vende el proveedor, e Import lo convierte en:

| Se crea | A partir de las columnas |
| --- | --- |
| **Familia de SKO** y **familia de unidades comerciales** | Family |
| **Unidad comercial** (el artículo individual que compra un cliente) | Part reference, Unit recommended description, Unit label, Unit weight, Unit dimensions, Materials, Tariff code, Unit barcode |
| **SKO** (lo que prepara el almacén) | Part reference, Units per SKO, SKO weight, SKO dimensions |
| **Producto de proveedor** (lo que compramos a este proveedor) | Supplier's product code, Unit cost, Unit expense, Extra costs %, SKOs per carton, Minimum order, Average delivery time, Carton CBM, Carton Weight |
| **Precios recomendados** para el futuro producto maestro | Unit recommended price y RRP en £ y € y Recommended SKOs per selling outer |
| **Pedidos de compra en borrador** | Order Cartons UK / SK / ES / Aroma |

Cada organización que compra al proveedor recibe además el SKO de inmediato, vinculado a su copia del producto de proveedor.

Si la Part reference ya existe, no se crea nada nuevo: el proveedor se añade a esa unidad comercial como una fuente más, y solo se rellenan sus campos vacíos.

## La plantilla

Descárgala desde la página **Products** (Productos) del proveedor. La subida lee las columnas por el **encabezado** de la fila de encabezados, así que puedes mover columnas de sitio o añadir tus propias columnas de trabajo; lo que aiku no conoce se ignora. Las notas por encima de la fila de encabezados no molestan.

La fila sobre los encabezados dice **Required** u **Opt**. Si falta un encabezado Required, el archivo entero se rechaza al instante y el mensaje nombra la columna que falta.

Unas cuantas reglas que evitan la mayoría de los errores:

- **Una fila es una sola unidad.** "Unit recommended description" es el nombre de un artículo, nunca "Pack of 6 …". El pack se describe con Units per SKO.
- **Supplier's product code** puede dejarse vacío: se usa la Part reference.
- **Las columnas de dinero van en su propia moneda**: Unit cost y Unit expense en la moneda del proveedor, los precios recomendados en £ y €. Una celda con formato o texto en otra moneda se rechaza.
- **Pesos en kg, medidas en cm** escritas como 20x10x5, Carton CBM en m³, Extra costs como 40% o 0.4.
- **Unit barcode**: un EAN real, o `auto` para tomar el siguiente código de barras libre del fondo común al importar. Vacío significa sin código de barras y requiere una decisión.
- **Units per SKO, SKOs per carton y Minimum order** son números enteros.

## Datos de cumplimiento normativo (plantilla v7)

La plantilla tiene tres pestañas. **Product data** conserva todas las columnas anteriores en su sitio. Después vienen columnas opcionales para GPSR, la categoría regulatoria del producto y EUDR. Las otras dos pestañas contienen lo que una fila por producto no puede. Todo es opcional: un archivo sin estas columnas o pestañas se sube exactamente igual que antes.

| Dónde | En qué se convierte |
| --- | --- |
| **Product data**: Manufacturer, EU responsible person, Warnings and safety information, Instructions for use, Languages of warnings and instructions | Los campos GPSR de la unidad comercial, que los productos que la venden muestran y traducen |
| **Product data**: Brand, Batch traceability, Regulatory category, Toy status, Batteries / magnets, SVHC above 0.1%, SVHC substance, CLP signal word, Material composition (% by weight) | La pestaña **Compliance** (Cumplimiento) de la unidad comercial |
| **Product data**: EUDR status, commodity, species (scientific name), country of production, region of production, plot geolocation, certification, legality evidence | El bloque EUDR en la pestaña **Compliance** de la unidad comercial |
| **Packaging components**: una fila por componente, por nivel de embalaje, por referencia | La familia de embalaje de la unidad comercial (PPWR, devoluciones EPR) |
| **Supplier declarations**: company, signed by, position, date y una respuesta por declaración | Una declaración firmada en la pestaña **Declarations** (Declaraciones) del proveedor |

Columnas de **Packaging components**: Part reference, Packaging level (Primary, Secondary, Tertiary, Pallet o Service), Component, Material, Material code (PAP 20, PE-LD 4 …), Weight (g), Quantity at this level, Recycled content %, Recycled content evidence, Recyclability, Separable, Marks on the packaging, National marks, Artwork owner, Notes. Quantity es cuántos hay del componente en su nivel: 2 etiquetas en una botella son 2. Aiku calcula cuánto lleva de él una unidad de venta. Un componente Secondary lo comparten las unidades del SKO, y una caja Tertiary, todas las unidades que contiene. Los accesorios de palé no se cuentan por unidad.

El embalaje se introduce una sola vez. Las referencias empaquetadas exactamente igual comparten una familia de embalaje, y un componente ya conocido, como la misma botella o la misma caja, se comparte en lugar de copiarse.

Como con las demás columnas, los datos de cumplimiento solo rellenan lo que está vacío. Una unidad comercial que ya tiene texto GPSR, una respuesta de cumplimiento o una familia de embalaje la conserva.

La vista previa avisa, sin detener la importación, cuando:

- EUDR status dice Yes pero falta la commodity, el country of production, la plot geolocation o la legality evidence. EUDR se aplica a AW desde el 30 dic 2026, y AW presenta la declaración de diligencia debida, así que necesita estos datos.
- Material composition no suma 100%.
- Se declara un SVHC por encima de 0.1% sin nombrar la sustancia.
- Una fila de embalaje no tiene peso, o tiene un nivel que aiku no conoce (la fila se deja entonces fuera).

La vista previa también enumera las filas de embalaje sin Part reference, o con una que no está en Product data, y cualquier declaración que no se haya respondido Yes, incluidas las que no tienen respuesta.

**Supplier declarations** lee Company, Signed by, Position y Date (cada uno como una etiqueta con su valor al lado), y luego un encabezado **Statement | Answer** con una declaración por fila. Las fechas se leen con el día primero: 01/10/2026 es el 1 de octubre. La declaración se conserva cuando se importa al menos una fila, una vez por subida: si subes el archivo otra vez, se conserva otra vez, con la fecha de esa subida.

## Subir

Abre el proveedor, ve a **Products**, pulsa **Attach file** y elige el archivo. Unos segundos después se abre la vista previa.

## Añadir un producto sin hoja

Para un solo producto, pulsa **New Supplier Product** en la página **Products** del proveedor. El formulario tiene los mismos campos que la plantilla, con los mismos encabezados, agrupados en Product, Packing and ordering, Cost and prices, y Weights and sizes. Se aplican las mismas reglas: una unidad por producto, dinero en la moneda de la columna (un símbolo está bien, "€10.20" en un campo en €, pero un importe en £ en un campo en € se rechaza), pesos en kg, medidas como 20x10x5, `auto` para un código de barras del fondo común.

Guardar lleva dos pasos:

1. **Save** (Guardar). Mientras escribes, las comprobaciones de campo de la subida ya aparecen bajo cada campo. Al pulsar **Save** se ejecutan todas de nuevo, además de las mismas comprobaciones de IA que recibe una subida: las preguntas por producto y la revisión de IA. Esto tarda hasta un minuto. Si no sale nada, el producto se crea de inmediato.
2. **Check before saving** (Revisar antes de guardar). Si sale algo, se abre una ventana con la corrección sugerida por la IA, cada hallazgo y los campos afectados, para que los corrijas ahí mismo. Marca lo que aceptas y pulsa **Submit** (Enviar). Submit es definitivo: no se vuelve a consultar a la IA. Si cambias un campo sobre el que la IA avisó, el aviso desaparece. Una decisión que la IA pidió se mantiene, marcada como referida al valor anterior, y sigue necesitando que la marques. Cambiar la Part reference requiere un nuevo Save.

Los hallazgos usan los mismos colores que la vista previa:

- **rojo**: hay que corregirlo;
- **naranja**: requiere una decisión. Marca **This is OK, I accept responsibility** si es correcto. Si cambias el campo y el mensaje cambia, márcalo otra vez;
- **azul**: la Part reference ya existe. Marca **Add this supplier to it** (Añadirle este proveedor) para añadir el proveedor a esa unidad comercial como una fuente más;
- **ámbar**: conviene echarle un vistazo, nada que marcar.

Si las comprobaciones de IA no pueden ejecutarse, la ventana pide en su lugar **I accept responsibility** (Asumo la responsabilidad), igual que una subida.

Cuando un SKO contiene más de una unidad, aparece **SKO name** (Nombre del SKO) en Packing, rellenado de antemano como "Pack of N …". Cámbialo si la redacción no es correcta.

Submit crea las familias, la unidad comercial, el código de barras, el SKO y el producto de proveedor de una sola vez, exactamente como lo hace Import con una fila, y abre el nuevo producto de proveedor. Cada organización que compra al proveedor recibe de inmediato el producto de proveedor y su SKO, vinculados entre sí, de modo que el producto se puede pedir al instante. Quién marcó cada decisión, y cuándo, queda guardado en el producto de proveedor.

El formulario no pide cajas. Añade el producto a un pedido de compra después, o usa la hoja cuando también quieras pedidos de compra en borrador.

## La vista previa

La vista previa enumera cada fila con lo que aiku encontró, de tres tipos:

| Marca | Significado | Qué hacer |
| --- | --- | --- |
| **Fix in the sheet** | La fila no se puede importar tal como está: una celda obligatoria está vacía, un número no es un número, el código de barras es incorrecto, un precio está en la moneda equivocada, la misma Part reference aparece dos veces | Corrige el archivo y súbelo otra vez, o omite la fila |
| **Needs a decision** | La fila se puede importar, pero algo parece arriesgado: un nombre que parece un pack, un margen por debajo del objetivo, un producto existente que se va a actualizar, sin código de barras, una caja que no se divide en outers | Marca **This is OK, I accept responsibility** si es correcto; aiku registra quién lo marcó y cuándo. Si no, corrige el archivo |
| **Check** (Revisar) | Conviene echarle un vistazo, no detiene nada: una familia nueva, un peso o tamaño que parece raro, valores lejos de los de los demás productos del proveedor | Léelo; nada que marcar |

**Skip row** (Omitir fila) deja una fila fuera de esta importación. **SKO name** aparece cuando un SKO contiene más de una unidad: viene rellenado como "Pack of N …" y puedes cambiarlo.

**Import** permanece desactivado mientras alguna fila tenga algo que corregir o decidir; la lista de abajo dice exactamente qué está esperando.

### Qué comprueba aiku

- **Márgenes**: nuestro margen sobre el precio recomendado debe ser de al menos el 60% tras el coste puesto en almacén (unit cost más unit expense más extra costs, convertidos al tipo de cambio de aiku). El margen del minorista (RRP frente a nuestro precio) suele rondar el 58%; por debajo del 50% requiere una decisión.
- **Los precios en £ y en €** deben coincidir entre sí con un margen del 25% tras la conversión.
- **Embalaje**: una caja debe dividirse en outers de venta enteros; las unidades deben caber en el SKO y los SKO en la caja; los pesos deben cuadrar y la densidad debe ser creíble.
- **Familias**: se marca una familia nueva, y también una que se parece a una existente (un error tipográfico). Se rechaza una Part reference cuyo prefijo no coincide con el de los demás productos de la familia.
- **Productos existentes**: una Part reference existente se vincula a esa unidad comercial; un código de proveedor existente actualiza ese producto de proveedor, y un cambio de coste de más del 20% requiere una decisión.
- **Minimum order**: las cajas pedidas entre todas las organizaciones deben alcanzar el mínimo del proveedor.

### Comprobaciones de IA

Mientras lees la vista previa, se ejecutan en segundo plano dos comprobaciones de IA y la página se actualiza sola:

- cada fila se revisa en busca de faltas de ortografía, materiales que no encajan con el artículo, números que parecen incorrectos para el artículo, un producto en la familia equivocada y filas que parecen desplazadas;
- después se revisa la hoja entera: arriba aparece una breve **AI review** (Revisión de IA) y, bajo las filas que necesitan un cambio, una nota **AI suggests** (La IA sugiere) con la corrección exacta.

Import las espera. Si la IA no puede ejecutarse, cada fila pide en su lugar **I accept responsibility**, así que una subida nunca se queda atascada.

### Precios de abastecimiento (proveedores en China)

Cuando la dirección del proveedor está en China, la IA busca después cada producto nuevo en las webs de abastecimiento y muestra, bajo la fila, el rango de precios que encontró para una unidad, con enlaces a los artículos que comparó:

- **Within the sourcing price range** (Dentro del rango de precios de abastecimiento): el coste es justo.
- **More than 30% above** (Más de un 30% por encima): puede que estemos pagando de más; pide al proveedor un mejor precio.
- **Well below** (Muy por debajo): comprueba la calidad y la especificación antes de pedir.

Los precios al por mayor dependen de la cantidad pedida, así que toma el rango como una guía. Solo se buscan las 20 primeras filas nuevas, y un nombre de producto ya buscado en los últimos 30 días reutiliza esa respuesta. Esta comprobación es solo un consejo: Import no la espera. Comparte los límites de gasto de la revisión de IA y se detiene cuando se alcanzan.

Las webs de abastecimiento son los competidores configurados para vender a **Factory** en la página Competitors de una tienda maestra. Sin ninguno, esta comprobación no se ejecuta.

## Pedidos de compra en borrador

Las columnas **Order Cartons UK / SK / ES / Aroma** piden cajas para cada organización. La vista previa muestra, por organización, cuántas cajas y líneas se pedirán y en qué pedido:

- si la organización ya tiene un **borrador abierto** para este proveedor (enviado a través del agente cuando el proveedor tiene uno), las líneas se añaden a él y la hoja fija la cantidad;
- si no, se crea un borrador nuevo. Marca **New draft instead** (Nuevo borrador en su lugar) para obtener siempre uno nuevo.

Las líneas que ya están en el borrador y no están en la hoja se dejan como están. Los pedidos siguen siendo borradores hasta que alguien los envía.

## Importar

Pulsa **Import**. Cada fila se crea por separado, así que una fila que falle no detiene las demás; la página muestra entonces qué filas se crearon, se omitieron o fallaron, y los pedidos de compra en borrador que se rellenaron.

## Subir la misma hoja otra vez

Puedes subir un archivo corregido tantas veces como necesites. Las filas cuyo código de proveedor ya existe se ofrecen como **update** (actualización) de ese producto de proveedor, y las filas cuya Part reference existe se vinculan, nunca se duplican.

## Después de la importación

Cuando alguien crea el **producto maestro** a partir de una de estas unidades comerciales, su precio en £ y € y su RRP se rellenan de antemano con los precios recomendados de la hoja, y el formulario muestra el número recomendado de unidades por outer. En los pedidos de compra, la estimación de gasto unitario del proveedor se añade como **Estimated total incl. supplier expenses** (Total estimado con gastos del proveedor) bajo el total real, para ayudar con los presupuestos.
