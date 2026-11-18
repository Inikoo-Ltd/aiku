---
title: Calcular el coste de una entrega de stock
summary: Introduce lo que costó realmente una entrega - la factura del agente o del proveedor y las facturas pagadas en destino por transporte, aduanas y portes - para que el stock se valore a su coste puesto en almacén.
date: 2026-11-18
source_date: 2026-11-18
tags: procurement, stock deliveries, costing, landed cost, service invoices, customs
category: procurement
---

<aside class="tldr">
El coste de una entrega tiene dos partes. La primera es la <b>factura del agente o del proveedor</b>: la mercancía y todo lo que cobraron además. La segunda son los <b>landed costs</b> (costes de llegada): todas las facturas que pagas a terceros para traer la mercancía, como el transporte internacional, el despacho de aduanas, los aranceles y el camión hasta el almacén. aiku suma las dos, reparte los costes de llegada entre los artículos y valora el stock por ese total. Introduce los costes antes de que llegue la mercancía: a partir del 12 de octubre de 2026 la mercancía no se puede colocar hasta que estén introducidos.
</aside>

## Dónde está el cálculo del coste

Abre la entrega en **Procurement → Stock Deliveries**. Cerca de la parte superior de la página hay una tabla con tres filas:

- **Agent invoice** o **Supplier invoice** (factura del agente o del proveedor) - lo que facturó el agente o el proveedor.
- **Landed costs** (costes de llegada) - lo que se pagó en destino para traer la mercancía.
- **Landed total** (total puesto en almacén) - las dos juntas.

Cada fila muestra su **Status**, su **Amount** en la moneda de tu organización y su **Balance due**. Cuando la factura está en otra moneda, ese importe aparece pequeño debajo. Las filas llegan cerradas; pulsa una para abrirla y ver sus líneas y sus botones.

Los costes se pueden introducir en cuanto la entrega está confirmada. No hace falta esperar a la mercancía.

## La factura del agente o del proveedor

Abre la fila de la factura y pulsa **Invoice**. Introduce el **Invoice number**, la **Invoice date**, el importe de las **Goods** y cada uno de los cargos propios del agente o del proveedor, marcados como **Freight** u **Other**. Adjunta el PDF en **Invoice file** y pulsa **Save invoice**.

Mientras no haya una factura real, una entrega que viene del sistema antiguo muestra una factura **Estimated** (estimada) construida con lo que se registró allí. Al guardar la factura real, esta sustituye a la estimación, y la estimación se conserva para poder compararlas.

La misma ventana contiene el **Invoice exchange rate**, el **Settlement** con los depósitos aplicados y el saldo pendiente, y los costes leídos de una factura adjunta.

Un contenedor comprado a través de un agente necesita su factura antes de marcarlo como enviado, por eso su botón **Invoice** está disponible mientras sigue en proceso.

### Leer la factura del proveedor desde su archivo

Adjunta la factura del proveedor a la entrega como **Invoice** o **Proforma** y, en **Costs from the supplier invoice**, pulsa **Read invoice** y, cuando esté leída, **Review invoice**. Se lee dos veces, con dos modelos de IA distintos, y aiku comprueba la lectura igual que una factura de los costes de importación: las dos lecturas deben coincidir en cada línea, código, cantidad e importe, cada importe debe estar impreso en el documento, las líneas y los cargos deben sumar el total, y debe nombrar la entrega o uno de sus pedidos de compra y ninguna otra entrega.

Cada línea de la factura se asocia a un producto de la entrega por el código del proveedor, luego por nuestro código de SKO y luego por su código de barras (de unidad, de SKO o de caja). Si una línea no coincide con ninguno, se sugiere por su descripción cuando un solo producto encaja, y tú lo confirmas. En la revisión no se pueden cambiar los importes. El coste de cada producto es el precio de la factura por lo recibido. Solo indicas a dónde va una línea de la factura que no coincide con ningún producto (a un producto, o **Invoiced but not in the delivery**) y qué tipo de gasto es cada cargo, y pulsas **Apply to costing**. Un cargo marcado como **Freight** pasa a ser el transporte, repartido por peso, y las facturas de flete del transitario se suman a él; si el transporte se escribió a mano, pon allí el flete. Se puede aplicar en cuanto la entrega está revisada, para que los costes estén antes de ubicar la mercancía.

Si se recibió menos de lo facturado, la revisión indica cuánto cobra la factura por mercancía que no llegó: ese importe queda fuera del coste de la mercancía, así que pide un abono al proveedor. Si se recibió más de lo facturado, la mercancía de más se valora al precio de la factura.

La lectura se rechaza, y la factura se introduce a mano, cuando falla una comprobación, cuando falta en la factura un producto de la entrega, cuando un precio de la factura queda lejos del precio del pedido (la factura puede contar paquetes o cajas), cuando la factura tiene un descuento o abono, o cuando la factura de la entrega ya está introducida. Una factura sin total impreso y un contenedor comprado a través de un agente se introducen siempre a mano.

Para una entrega comprada a través de un agente, pulsa **Agent payments** para aprobar los cargos del agente y registrar lo que le has pagado. Los cargos del agente solo cuentan en el coste cuando los apruebas.

## Los costes de llegada: una línea por factura

Abre la fila **Landed costs** y pulsa **Add** junto a **Service invoices**. Introduce una factura de servicio por cada factura que hayas recibido:

- el tipo de factura (mira la lista de abajo),
- quién la envió y su referencia,
- la fecha,
- la **moneda** y el importe - el transporte puede estar en dólares y la factura del agente de aduanas en euros,
- el PDF de la factura.

Una factura que cubre varias entregas se puede repartir entre ellas, según el valor de su mercancía o según los importes que escribas.

Los tipos de factura son:

| Kind | Cómo se reparte entre los artículos |
| --- | --- |
| Freight | por peso |
| Local transport (port to warehouse) | por peso |
| Customs duty | por línea de aduana, o por valor cuando no hay declaración |
| Anti-dumping duty | por valor, solo entre los productos de los códigos arancelarios que elijas |
| Other import taxes (plastic, excise) | igual que los aranceles |
| Import VAT | no se imputa al coste: se recupera en la declaración del IVA |
| Customs clearance | por valor |
| Port and terminal charges | por valor |
| Storage and demurrage | por valor |
| Inspection and certificates | por valor |
| Documentation fees | por valor |
| Insurance | por valor |
| Fines | por valor |
| Other | por valor |

El transporte se reparte por valor en vez de por peso cuando algún producto de la entrega no tiene peso registrado; la ventana te dice qué productos son.

Cada factura rellena el coste por sí sola, y un coste rellenado por una factura solo se puede cambiar cambiando la factura. Todas las facturas de tu organización aparecen en **Service invoices**, a la que se llega desde el encabezado de esa lista, donde puedes filtrarlas por tipo, remitente, pagadas o sin pagar, y periodo.

Si un coste ya se escribió a mano, aiku te avisa antes de que guardes una factura del mismo tipo, tanto si escribes la factura como si la haces leer desde su archivo. Una factura de transporte o de aranceles sustituye al importe escrito a mano, y el aviso muestra los dos importes cuando no son iguales. Una factura de cualquier otro tipo de coste cuenta junto al escrito a mano, así que quita el que escribiste si es el mismo coste. Un importe escrito como estimación simplemente lo sustituye la factura.

## Leer una factura desde su archivo

En lugar de escribir una factura, aiku puede leerla. Abre la fila **Landed costs** y, bajo **Read from a document**, pulsa **Read an invoice** y elige el PDF, la hoja de cálculo o una foto de la factura. Muestra **Reading…** durante un minuto o dos como máximo; después pulsa **Review**.

La revisión muestra quién emitió la factura, su referencia, fecha y moneda, y cada línea con su importe y el tipo de coste que aiku cree que es. Compáralos con la factura y cambia el tipo de una línea si está mal. Los importes no se pueden cambiar: son los que están impresos en la factura. Pulsa **Add to the service invoices**. Las líneas del mismo tipo se convierten en una sola factura de servicio, así que la factura de un transitario con flete marítimo y despacho de aduanas se convierte en dos, cada una repartida entre los artículos a su manera, y cada una conserva el archivo.

No se calcula ningún coste hasta que pulsas ese botón. La factura se lee dos veces, por dos modelos de IA distintos, y después aiku comprueba la lectura por su cuenta. Rechaza la lectura, y dice por qué, cuando:

- las dos lecturas no coinciden en un importe, la referencia, la fecha o la moneda,
- un importe, la referencia o la moneda que se leyó no está impreso en el documento,
- el documento no menciona la entrega, o menciona también otra entrega, incluso una que ya está calculada o que pertenece a otra organización,
- las líneas no suman el total de la factura,
- la factura no tiene referencia, fecha o moneda,
- ya hay introducida una factura con la misma referencia, o ya hay en la entrega una del mismo importe,
- la factura es mayor que el valor de la mercancía,
- la entrega está calculada, cancelada o es una entrega de un socio.

Una factura rechazada muestra **Enter it by hand**: añádela con **Add**, como en la sección anterior. Un escaneo o una foto no tiene texto que aiku pueda comprobar, así que te pide que marques que has comprobado tú mismo cada importe.

El IVA de la factura se deja fuera del coste y se escribe en las notas de la factura de servicio.

## Escribir un coste sin factura

Pulsa **Edit landed costs** para abrir los costes como una tabla. Cada coste tiene:

- **N/A** - márcalo cuando la entrega no tiene ese coste, por ejemplo sin aranceles en una compra dentro del país.
- **Amount** y **Currency** - al escribir un importe, el coste queda como recibido. La moneda empieza siendo la de tu organización.
- **Rate** - solo cuando la moneda no es ni la de la entrega ni la de tu organización.
- **Waiting for invoice** - mira más abajo.
- **Invoice** - **Upload** (sube) el PDF que respalda ese coste.
- **History** - cada cambio de ese coste, con quién lo hizo y cuándo.

Se puede añadir una nota bajo cada coste. **Add extra expense** añade un coste de alguno de los tipos de la lista anterior; para **Other**, indica de qué se trata.

### Derechos antidumping

Los derechos antidumping se cobran solo sobre algunos códigos arancelarios de un contenedor, así que tienen su propia fila y nunca se reparten sobre toda la entrega. Bajo la fila, elige los códigos arancelarios a los que se aplican: la lista muestra cada código arancelario de la entrega con sus productos. El importe se reparte entonces por valor solo entre los productos con esos códigos, y cuenta como arancel en su coste. La fila indica sobre cuántos productos está.

Hasta que se eligen los códigos arancelarios la entrega no se puede costear, y la fila lo indica en rojo. Un producto sin código arancelario no se puede elegir; la fila lista esos productos para que primero des un código arancelario a su unidad comercial. Hasta que la entrega se costea por primera vez, el importe sigue a los productos que llevan los códigos elegidos, así que se tiene en cuenta un código arancelario corregido después. A partir de entonces se queda en los productos con los que se costeó.

Una factura antidumping introducida en las facturas de servicios recibe su propia fila de la misma forma: su importe viene de la factura y eliges sus códigos arancelarios en la entrega.

## Cuando una factura todavía no ha llegado

Marca **Waiting for invoice** en ese coste y, si lo sabes, escribe cuántos días esperas que tarde. La página de la entrega mostrará entonces que el total va a aumentar.

Si escribes un importe mientras el coste está en espera, es una **estimación**. Una estimación cuenta en el cálculo, así que la entrega se puede dar por calculada y el stock se puede valorar, y la entrega sigue marcada como **Costed with an estimate** hasta que la factura real la sustituya.

## Cuando el cálculo está terminado

Una entrega está calculada cuando su factura está introducida y su transporte, aranceles y costes extra tienen cada uno un importe, una estimación o **N/A**. En ese momento el stock colocado a partir de la entrega toma su coste puesto en almacén. La fila **Landed total** lista entonces todos los costes con su porcentaje sobre la mercancía, y dice cuánto coste extra hay por cada euro de mercancía.

Después de eso, los costes quedan bloqueados. Para corregirlos, o para sustituir una estimación por la factura real, un responsable de contabilidad pulsa **Update costing**, hace los cambios y pulsa **Finish costing**. El stock se valora de nuevo y el cambio queda registrado con su nombre y el motivo.

## Los costes van antes que la mercancía

Para las entregas creadas a partir del 12 de octubre de 2026:

- **Colocar la mercancía queda bloqueado** hasta que la factura, el transporte y los aranceles tengan cada uno un importe, una estimación o **N/A**. La mercancía se puede recibir y comprobar, pero no se puede poner en una estantería, así que nada se vende con el coste equivocado.
- **Marcar una entrega como recibida** mientras faltan costes pide un motivo. El motivo se escribe en el historial de la entrega y se avisa a los supervisores de compras.

Las entregas de socios no se ven afectadas: su coste se calcula con los precios del pedido del socio.

## Ver lo que falta por calcular

El dashboard de **Procurement** se abre con una barra que muestra cuántas entregas quedan por calcular y cuántas de ellas están detenidas. Cada cifra abre la lista de entregas de stock con su filtro. En la propia lista, el filtro **Costing** ofrece **To cost**, **Costs missing, put-away blocked** y **Costed with an estimate**.

## Con tu asistente de IA

Las personas que calculan el coste de las entregas pueden pedir a su asistente de IA que les muestre los costes de una entrega o que guarde su transporte, aranceles y costes extra. Primero enseña lo que va a cambiar y solo guarda cuando lo confirmas. Rechaza una entrega que ya está calculada, y un total de costes superior al valor de la mercancía a menos que lo confirmes, lo que normalmente significa un importe en la moneda equivocada. Cada guardado queda en el registro de cambios de IA (AI changes) y se puede revertir ahí. El IVA de importación, la factura del agente o del proveedor y los archivos PDF se introducen en la página, no a través del asistente.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Abrir el cálculo de coste de una entrega:</b> tu organización → <b>Procurement → Stock Deliveries</b> → la entrega → la tabla con <b>Agent invoice</b>, <b>Landed costs</b> y <b>Landed total</b>.</li>
<li><b>Introducir la factura:</b> abre la fila de la factura → <b>Invoice</b> → <b>Save invoice</b>.</li>
<li><b>Aprobar los cargos de un agente o registrar un pago:</b> abre la fila de la factura → <b>Agent payments</b>.</li>
<li><b>Añadir una factura:</b> abre <b>Landed costs</b> → <b>Add</b> junto a <b>Service invoices</b>.</li>
<li><b>Hacer que se lea una factura desde su archivo:</b> abre <b>Landed costs</b> → <b>Read an invoice</b> bajo <b>Read from a document</b> → <b>Review</b> → <b>Add to the service invoices</b>.</li>
<li><b>Escribir un coste, marcarlo como N/A o en espera:</b> abre <b>Landed costs</b> → <b>Edit landed costs</b>.</li>
<li><b>Corregir una entrega ya calculada (responsables de contabilidad):</b> <b>Update costing</b> en la parte superior de la entrega, y luego <b>Finish costing</b>.</li>
<li><b>Ver lo que está pendiente:</b> dashboard de <b>Procurement</b>, la barra de arriba; o el filtro <b>Costing</b> de la lista de entregas de stock.</li>
<li><b>Dejar que alguien guarde costes a través de su asistente de IA (administradores):</b> <b>Sysadmin → Users</b> → abre el usuario → <b>Edit</b> → activa <b>Can save stock delivery costs through their AI assistant</b>.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permisos que necesitas</strong>
Necesitas permiso para ver compras (procurement) o contabilidad de la organización para ver el cálculo del coste, y permiso para editar compras o contabilidad para introducir costes y facturas. Cambiar una entrega que ya está calculada requiere el puesto de responsable de contabilidad. Colocar la mercancía y marcar una entrega como recibida siguen los permisos de entrada de mercancía del almacén.
</aside>
