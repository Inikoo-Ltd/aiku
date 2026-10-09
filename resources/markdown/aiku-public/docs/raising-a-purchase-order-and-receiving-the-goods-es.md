---
title: Cursar una orden de compra y recibir la mercancía
summary: Compra a un proveedor ordinario - cursa la orden de compra, consigue que se confirme, y luego convierte la entrega en stock que puedas vender.
date: 2026-10-10
source_date: 2026-10-10
tags: procurement, purchase orders, stock deliveries, suppliers, supplier claims, customs
category: procurement
---

<aside class="tldr">
Cuando compras a un proveedor ordinario - no a una organización socia, que tiene su propia guía - el trabajo se hace en dos etapas. Primero cursas una <b>orden de compra</b> (purchase order) y consigues que el proveedor la confirme. Luego, cuando llega la mercancía, registras una <b>entrega de stock</b> (stock delivery) contra esa orden y la vas comprobando hasta que el stock queda colocado en tus estanterías. Este artículo cubre ambas partes, además de lo que hace realmente cada botón de estado por el camino.
</aside>

## Proveedores y agentes

Cada proveedor al que tu organización compra directamente vive en **Procurement → Suppliers**. La página de cada proveedor te da un botón **Purchase Order** para iniciar una orden nueva, más un menú lateral con **Products**, **Purchase Orders** y **Stock Deliveries** hasta la fecha.

Algunos proveedores solo son accesibles a través de un **agente** - una persona o empresa que compra en tu nombre en lugar de enviarte directamente. Los agentes tienen su propia lista en **Procurement → Agents**. Un pedido a través de un agente sigue siendo una orden de compra por proveedor, enviada al agente, y los pedidos que haces juntos forman un único **pedido de agente** (agent order). Ver [Hacer un pedido a través de un agente](/docs/placing-an-order-through-an-agent-es).

## Cursar una orden de compra

Desde la página del proveedor, pulsa **Purchase Order**. Esto crea una orden nueva en el estado **In process** - existe, pero todavía no se ha enviado nada al proveedor.

Mientras está en proceso:

- Usa **Add Product** para añadir una línea por cada producto que quieras, uno a uno.
- Cada línea puede ajustarse mientras la orden siga en proceso.
- **Delete** elimina la orden entera, siempre que todavía no se haya enviado nada al proveedor.

Cuando hayas añadido todo lo que quieres, pulsa **Submit**. Esto envía la orden y la pasa a **Submitted**.

## Proveedores que venden en otra unidad

Algunos proveedores cotizan y facturan en una unidad que no es la nuestra - por ejemplo, incienso por **kg** que nosotros contamos en bolsas de 500 g. En la página **Edit** del producto del proveedor, indica **Supplier sells in** (kg, g, litro, metro, pack, set, docena o pieza) y **Our units in one supplier unit** (2 para una bolsa de 500 g vendida por kg, 0,2 para un pack de 5 kg vendido por kg). Si eliges kg o g y dejas el número vacío, aiku lo calcula a partir del peso de la unidad comercial (trade unit), y el texto de ayuda del campo te avisa cuando el número que escribiste no coincide con ese peso.

Las cantidades se siguen guardando en nuestras unidades. La unidad del proveedor solo cambia cómo las escribes y las lees:

- En una orden de compra en proceso, la pestaña **Ordering supplier units** te permite escribir la cantidad en la unidad del proveedor, y cada línea la muestra junto a unidades, SKO y cajas (por ejemplo *160u. | 160sko. | 1.6C. | 80 kg*).
- El PDF de la orden de compra que se envía al proveedor muestra esas líneas en la unidad del proveedor y el precio por unidad (*80 kg* a *253.00 / kg*).
- La entrega de stock muestra ambas unidades, y la comprobación de la factura en el panel **Costing** convierte los kg de la factura a nuestras unidades antes de comparar.

## Hacerlo con tu asistente de IA

Si un administrador te ha activado hacer pedidos, tu asistente de IA puede crear y enviar la orden por ti, también para proveedores que compras a través de un agente. Dile el proveedor y lo que quieres, por ejemplo *"pide 144 CIC-25 y 20 TIB-134SET a RME"*.

- Primero te muestra la orden: el proveedor, el agente, cada línea con unidades, cajas y coste, el total, y cualquier orden que ya se esté preparando para ese proveedor.
- Las cantidades son en unidades y se redondean hacia arriba a cajas completas, nunca por debajo del mínimo de cajas del proveedor.
- Solo cuando confirmas añade las líneas (a la orden en preparación, o a una nueva) y la envía con **Submit**. No la manda por correo: envíala al proveedor desde la página de la orden.
- La orden queda registrada con tu petición pero **no se puede deshacer** desde el registro de cambios de IA: usa **Undo Submit** o **Cancel** en la orden.

## Qué significan los estados

Una orden de compra pasa por una cadena corta y deliberada:

- **In process** - todavía estás construyendo la orden. Añade productos, envíala o elimínala.
- **Submitted** - la orden ha ido al proveedor. Puedes **Confirm**arla en cuanto el proveedor la haya aceptado, **Undo Submit** para devolverla a In process si algo necesita cambiarse, o **Cancel**arla del todo.
- **Confirmed** - el proveedor ha aceptado la orden. Puedes fijar o cambiar la **Delivery date** (la llegada estimada), y pulsar **New Delivery** para crear la entrega de stock que recibirá la mercancía. Mientras no exista una entrega para ella, también puedes hacer **Undo Confirm** para devolverla a Submitted.

A partir de aquí la orden se asienta sola según avanzan sus entregas de stock - no queda nada más que pulsar en la propia orden de compra. Termina **Settled** cuando todo ha llegado, o **Not Received**/**Cancelled** si la cosa no salió bien.

## La entrega de stock: registrar lo que llegó

Pulsar **New Delivery** en una orden de compra confirmada crea la entrega de stock por ti, ya vinculada a las líneas de esa orden. También puedes empezar una desde cero en **Procurement → Stock Deliveries**, que solo pide un **número** y una **fecha** de entrega.

La página de una entrega de stock tiene pestañas para sus **Items**, los **Pending Items** todavía por resolver, **Done Items**, **Under/Over delivered items** una vez dada de alta, **Customs** una vez despachada, **Attachments** e **History**.

La entrega pasa después por sus propios estados:

- **In process / Confirmed / Ready to ship** - mientras todavía está en camino, puedes pulsar **Mark as Dispatched** en cuanto el proveedor la haya enviado, **Mark as Received** si ya ha llegado, o **Delete** si se creó por error.
- **Dispatched** - el paquete está en la carretera. **Mark as Received** en cuanto llegue a tu almacén, o **Unmark as Dispatched** para deshacerlo si en realidad todavía no ha salido.
- **Received** - la mercancía está físicamente en el almacén. Desde aquí comprueba cada artículo frente a lo que se pidió; la entrega pasa a **Checked** cuando eso está hecho, o puedes hacer **Unmark as Received** o **Cancel** de la entrega entera.
- **Checked** - si todavía no se ha colocado nada en stock, aquí todavía puedes **Cancel**ar.
- **Booking in / Booked in** - las cantidades comprobadas se están dando de alta en el stock del almacén.
- **Booked in** - pulsa **Place** para colocar el stock recibido. Este es el estado final de trabajo de la entrega.

Comprobar un artículo significa confirmar cuánto de cada línea llegó realmente - no todos los pedidos llegan completos, y las cantidades de menos o de más aparecen en la pestaña **Under/Over delivered items**, así que nada se pierde en la diferencia entre lo que pediste y lo que llegó.

Debajo de la cantidad comprobada, **Batch** (lote) guarda el código de lote y la fecha de consumo preferente impresos en los productos. Una línea puede tener varios lotes: pulsa **Add batch** y reparte la cantidad entre ellos. Al ubicar el stock, los lotes van a la estantería en el orden en que los escribiste, así el almacén sabe qué lote está en cada sitio y los informes de inventario pueden mostrar las fechas de consumo preferente. Las familias marcadas como **Batch tracked** (en la página de edición de la familia de stock) muestran un aviso hasta que cada SKO comprobado tenga lote, y señalan un lote sin fecha de consumo preferente. Nada bloquea la recepción, así que una entrega sin códigos impresos puede ir a la estantería igualmente. Un lote que ya está en una estantería no se puede bajar de lo que se ubicó; deshaz antes esa ubicación.

## Cuando lo que llegó difiere de lo esperado

La pestaña **Under/Over delivered items** lista cada línea cuyo recuento difiere, con la diferencia en unidades, SKO y valor, y una **Flag**:

- **Under delivered** u **Over delivered** - una diferencia real.
- **Possible unit mismatch** - el recuento es un múltiplo exacto de lo esperado (2×, 5×, 10× y así sucesivamente, en cualquier sentido). Casi siempre es un proveedor que factura en otra unidad, no mercancía que falta, así que comprueba la unidad antes de reclamar nada.
- **Within tolerance** - lo bastante pequeña para mostrarse pero no para señalarse. Los administradores fijan la tolerancia (un porcentaje, un importe, o ambos) en **Organisations** → la organización → **Edit** → **Procurement**; empieza en 0, así que toda diferencia se señala.

Pulsa **Resolve** en una línea y elige qué pasó:

- **Recount** - el almacén recibe una tarea para volver a contar la línea. Se te avisa cuando la cierren; entonces vuelve y elige uno de los otros resultados.
- **Unit error** - corrige lo esperado (en nuestras unidades, o en la unidad del proveedor cuando hay una) y el valor de la línea. Lo contado nunca se modifica. Si aún queda una diferencia real, la línea sigue señalada para que puedas reclamarla - una línea esperada como 80 kg de bolsas de 500 g pasa a ser 160 bolsas esperadas, y 120 contadas se muestran como 40 de menos.
- **Supplier claim** - solo para una línea con faltante. Introduce las unidades y el valor reclamados y añade fotos desde goods in; van a los adjuntos de la entrega. La reclamación empieza **Open**: púlsala en la columna **Outcome** para marcarla **Sent to supplier**, **Credit received** (con el número, importe y fecha de la nota de crédito) o **Rejected**. Una reclamación solo se hace seguimiento - no cambia el costing de la entrega.
- **Accept surplus** - solo para una línea con exceso: te quedas con las unidades extra.

Corregir un error de unidad en una entrega ya ubicada vuelve a valorar el stock que se ubicó a partir de ella, así que hazlo antes de terminar el costing; una vez calculado el costing, hay que reabrirlo primero.

## Líneas de aduana y aranceles

En la pestaña **Customs**, copia la declaración de importación: su **MRN**, la **Release date** y sus líneas arancelarias, cada una con el código arancelario, el % de arancel, el valor en aduana, el arancel y el IVA de importación. aiku nunca modifica la declaración; eso queda en manos del agente de aduanas.

Al guardar, cada artículo se asocia con la línea cuyo código arancelario más se parece al de su unidad comercial, y puedes cambiarlo por artículo. El coste de arancel de la entrega se reparte entonces línea por línea: cada artículo toma su parte del arancel de su propia línea, por valor entre los artículos de esa línea, así que una línea libre de arancel no toma nada. Los artículos sin línea se reparten el arancel que las líneas no explican. El IVA de importación solo se registra; se deduce en la declaración de IVA y nunca entra en el costing. Una reclamación a proveedor muestra si su línea pagó arancel, para que nadie pida una rectificación de aduana sobre una línea libre de arancel.


## Poniéndolo todo junto

En resumen: cursa la orden contra el proveedor, envíala, espera a que el proveedor la confirme, y luego crea la entrega desde la orden confirmada. Marca la entrega como enviada cuando el proveedor la despache, como recibida cuando llegue, ve comprobando cada artículo y, por último, colócala - momento en el que el stock ya está en el almacén y listo para vender.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Encontrar un proveedor o agente:</b> tu organización → <b>Procurement → Suppliers</b> (o <b>Agents</b> para proveedores gestionados por un agente).</li>
<li><b>Iniciar una orden de compra:</b> en la página del proveedor, pulsa <b>Purchase Order</b>; añade líneas con <b>Add Product</b>, y luego <b>Submit</b> cuando esté lista.</li>
<li><b>Avanzarla:</b> en la página de la orden, usa <b>Confirm</b>, <b>Undo Submit</b> o <b>Cancel</b> mientras está enviada; una vez confirmada, fija la <b>Delivery date</b> y pulsa <b>New Delivery</b>.</li>
<li><b>Recibir la mercancía:</b> en la página de la entrega de stock, avanza por <b>Mark as Dispatched → Mark as Received</b>, comprueba la pestaña <b>Items</b>, y luego <b>Place</b> en cuanto esté dada de alta.</li>
<li>También puedes iniciar una entrega desde cero en <b>Procurement → Stock Deliveries</b>.</li>
<li><b>Un proveedor que vende por kg:</b> el producto del proveedor → <b>Edit</b> → <b>Supplier sells in</b> y <b>Our units in one supplier unit</b>.</li>
<li><b>Cerrar una línea con faltante o exceso:</b> la entrega de stock → <b>Under/Over delivered items</b> → <b>Resolve</b>; sigue una reclamación desde la columna <b>Outcome</b>.</li>
<li><b>Introducir la declaración de importación:</b> la entrega de stock → <b>Customs</b> → <b>Add line</b> → <b>Save customs</b>.</li>
<li><b>Fijar la tolerancia de entrega (administradores):</b> <b>Organisations</b> → la organización → <b>Edit</b> → <b>Procurement</b>.</li>
<li><b>Dejar que alguien haga pedidos con su asistente de IA (administradores):</b> <b>Sysadmin → Users</b> → abre el usuario → <b>Edit</b> → <b>Access</b> → activa <b>Can place orders to the manufacturing hub, partners and suppliers through their AI assistant</b>. También necesita permiso para editar compras.</li>
</ul>
</aside>

<aside class="permissions"><strong>Permisos que necesitas</strong>
Necesitas permiso para ver procurement en la organización para consultar órdenes de compra y entregas de stock, y permiso para editar procurement para cursarlas, enviarlas, confirmarlas o modificarlas de cualquier otro modo.
</aside>
