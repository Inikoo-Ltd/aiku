---
title: Registrar el progreso de un pedido como agente
summary: Para agentes — las fechas que registras en cada pedido a un proveedor (depósito pagado, producción iniciada, producción terminada, fecha de listo propuesta, entregado) y tu propio número de pedido, cuándo y dónde registrarlas, qué registra en su lugar la empresa compradora, y por qué una fecha que falta aparece como un retraso.
date: 2026-11-11
source_date: 2026-11-11
tags: procurement, agents, supply-chain
category: procurement
series: PO journey
order: 2
---

<aside class="tldr">
Para agentes de coordinación. La empresa compradora sigue cada pedido en ocho etapas: PO Launch (lanzamiento del pedido), Agreement (acuerdo), Making It Happen (manos a la obra), Ready to Roll (listo para salir), Clean Hand Over (entrega limpia), Homeward Bound (rumbo a casa), Counted & Costed (contado y costeado) y Mission Complete (misión cumplida). Las etapas 1 a 5 son tu parte del trabajo. Puede ver por sí sola cuándo se despacha la mercancía y cuándo llega, pero no puede ver qué pasa en el proveedor. Esa parte depende de ti: en cada pedido a un proveedor registras cuándo se pagó el depósito, cuándo empezó la producción, cuándo terminó la producción, la fecha en la que esperas que la mercancía esté lista, y el día en que entregas la mercancía al agente de transporte. Registra cada una el día en que ocurre. La parte del comprador se explica en <a href="/docs/reading-the-po-journey-board-es">cómo leer el panel de PO journey</a>.
</aside>

## Por qué importan tus fechas

Cada pedido que la empresa compradora hace a través de ti muestra sus etapas en la parte superior de la página del pedido, y otra vez como una fila en el panel de PO journey. Cada etapa muestra su fecha y quién la registró. Cuando una etapa pasa su fecha objetivo, se pone en rojo en el panel y muestra cuántos días de retraso lleva.

Sin tus fechas, la empresa compradora solo ve dos cosas de tu lado: el día en que se te envió el pedido y el día en que salió la mercancía. Todo lo que pasa entre medias es invisible, y un retraso se ve igual tanto si ocurrió en el proveedor, en el control de calidad o en la entrega. Con tus fechas, las etapas muestran exactamente dónde está un pedido, y un proveedor con retraso se ve semanas antes de la fecha en que la mercancía debía salir.

En cuanto registras la primera fecha en un pedido, se esperan las siguientes. Si la fecha objetivo de una etapa pasa y no la has registrado, esa etapa aparece como retrasada, aunque el trabajo ya estuviera hecho. Una fecha que falta se ve exactamente igual que un retraso.

## Las fechas que registras

| Fecha | Regístrala cuando | Dónde |
|---|---|---|
| Deposit paid | El proveedor ya tiene el depósito. | **Pay deposit** en el panel de pagos del pedido, con el importe y el extracto bancario. El primer pago registrado es el depósito; lo que se paga después es el saldo. |
| Production started | El proveedor empieza a fabricar la mercancía. | El botón **Production started** en las etapas de la parte superior del pedido. |
| Production done | La mercancía está terminada en el proveedor. Terminada significa todo el pedido, no el primer lote. | El botón **Production done** en las etapas de la parte superior del pedido. |
| Proposed ready date | Sabes cuándo estará lista la mercancía para la entrega. Una fecha en la que confías. El comprador la aprueba. | La sección **Clean handover** de la página **Edit** del pedido. |
| Handed over | La mercancía llega al agente de transporte. Todo el pedido, no la primera parte. Esto completa la etapa 5, Clean Hand Over. | El botón **Handed over** en las etapas de la parte superior del pedido. Solo si entregas la mercancía a un agente de transporte: ver más abajo. |
| Your order number | Tienes tu propio número para el pedido. | **Agent's order number** en la página **Edit** del pedido. Se muestra bajo PO submitted. |

## Clean Hand Over, etapa 5

Clean Hand Over es el momento en que termina tu trabajo en un pedido. Su significado se define para cada agente:

- **Ready for the next container** (China, Indonesia). La etapa se completa sola en cuanto todos los artículos del pedido están en un envío. No hay botón **Handed over**: registras la mercancía con los albaranes de recepción, y sale cuando se despacha su contenedor.
- **Sent to the shipping agent** (India, Nepal). Registras **Handed over** el día en que la mercancía llega al agente de transporte.

Un envío despachado siempre completa la etapa. La empresa compradora también puede darle a una etapa su propio nombre para ti, por ejemplo "Sent to forwarder": se muestra en el pedido con ese nombre.

## Las etapas van en orden

Las etapas van estrictamente en orden, una tras otra:

- **Mientras el pedido está Submitted (enviado)**, solo se puede hacer la etapa 2, Agreement (acuerdo): el depósito está pagado, o el pedido se marca como **No deposit needed** (no hace falta depósito). Cualquiera de las dos acuerda las condiciones y el pedido pasa a **Agreed** (acordado). Hasta entonces todos los botones posteriores están en gris, y al señalarlos dice qué hay que hacer primero.
- **Desde Agreed**, solo funcionan los botones de la etapa en la que está el pedido, junto con los anteriores. Una etapa posterior sigue en gris hasta que se hace la anterior, y al señalarla nombra la etapa que hay que terminar primero.
- Los pasos automáticos nunca se bloquean: las recepciones de mercancía, las listas de contenedores, los pagos y los contenedores despachados registran lo que ocurrió, sea cual sea la etapa.

Registra cada fecha el mismo día en que ocurre. Una fecha de más de un día en el pasado, o una fecha que borras, te pide un motivo. El motivo queda guardado en el historial del pedido, con tu nombre, para que el comprador vea por qué.

## Cuando un paso no se da

Un pedido nunca se queda atascado en un paso que no se hace para él: cada etapa tiene un botón de omisión. Una omisión pide un motivo breve, se muestra en la etapa como **Skipped** (omitido) con quién, cuándo y por qué, pasa al historial del pedido y se puede deshacer.

- **QC skipped** (control de calidad omitido) es tuyo: en Ready to Roll, cuando no se hace ningún control de calidad para el pedido, para que puedas registrar **Handed over** (entregado) sin esperar a uno.
- Las demás omisiones son de la empresa compradora: **Sample skipped** (muestra omitida), **Shipped direct** (enviado directo; la mercancía no pasa por tu almacén), **Compliance skipped** (cumplimiento omitido) y **Handed over without checks** (entregado sin comprobaciones).

## Qué registra la empresa compradora

Hay fechas que no puedes introducir tú, y es intencionado:

- **Sample approved.** El comprador aprueba la muestra. Solo productos nuevos; los pedidos repetidos no tienen muestra.
- **Approved ready date.** El comprador aprueba tu fecha propuesta. A partir de ahí queda fijada, y marca los plazos: el control de calidad debe completarse ese día, y la entrega limpia dentro de los siete días siguientes.
- **QC passed.** El comprador lo confirma, normalmente a partir de tu evidencia de calidad. Aprobar el control de calidad libera el pago del saldo: hasta entonces, pagar el saldo pide un motivo. Cuando no se hace ningún control de calidad, marcas **QC skipped** en su lugar, con el motivo.
- **Compliance pack complete.**

Tu <a href="/docs/your-clean-handover-score-es">Clean Handover Score</a> se calcula a partir de estas fechas y de la fecha de entrega que registras. La fecha de entrega la registras tú, así que registra el día en que la mercancía llegó realmente al agente de transporte. El comprador ve quién la registró y cuándo, puede contrastarla con el recibo del agente de transporte y puede corregirla; una corrección queda guardada en el historial del pedido con su motivo.

## Tu gasto por unidad en cada línea

Mientras un pedido está enviado o confirmado, puedes escribir tu propio **Your unit expense** (gasto por unidad) en cada línea, en la pestaña **Items** del pedido. Es por unidad y en la moneda del pedido. El gasto de una línea es tu gasto por unidad multiplicado por la cantidad pedida, y **Agent expenses** (gastos del agente) en el pedido es la suma de sus líneas.

- Una línea donde no escribes nada usa la estimación de la empresa compradora para ese producto.
- Vacía el campo para volver a esa estimación.
- Una línea nueva del mismo artículo en tu próximo pedido, en la misma moneda, empieza con el valor que usaste la última vez, así que solo editas lo que cambió.
- La empresa compradora ve tu valor en la línea, pero no puede cambiarlo.

Es solo una estimación para el presupuesto. No se paga al proveedor, no se incluye en el total del pedido y no se usa para calcular el coste del stock.

## Cuando el proveedor cambia un precio

Si un proveedor pide un precio nuevo antes de que confirmes un pedido, no confirmes al precio antiguo. Abre el pedido, busca el producto en **Items** y pulsa **Supplier changed the price** en su línea. Introduce el precio que pide el proveedor, por caja tal como se muestra, y una nota breve si ayuda, y después **Ask the organisation**.

La empresa compradora recibe una notificación y responde en la línea: acepta el precio nuevo, mantiene el antiguo o fija otro precio. Tú recibes una notificación con la respuesta. Mientras quede sin responder alguna pregunta de precio del pedido, **Confirm** queda en gris. Si la empresa compradora mantiene el precio antiguo y el proveedor no quiere vender a ese precio, cancela esa línea antes de confirmar.

## Cuando el proveedor cambia la cantidad

Si el proveedor no puede suministrar la cantidad pedida, o solo vende en lotes mayores, pregunta a la empresa compradora antes de confirmar. Abre el pedido, busca el producto en **Items** y pulsa **Ask for another quantity** bajo su cantidad. Introduce la cantidad, en el nivel que muestra la pestaña Items (cajas, SKO o unidades), y una nota breve si ayuda, y después **Ask the organisation**.

La empresa compradora recibe una notificación y responde en la línea: acepta la cantidad nueva o mantiene la antigua. Tú recibes una notificación con la respuesta. Mientras quede sin responder alguna pregunta de cantidad del pedido, no se puede confirmar.

## Cuando el proveedor no puede fabricar un producto

Si un proveedor no puede fabricar un producto del pedido, dáselo a otro de tus proveedores en lugar de cancelarlo. Abre el pedido, busca el producto en <b>Items</b> (artículos) y haz clic en <b>Move</b> (mover) en su línea. Elige el proveedor y cuántas unidades mover: todas, o solo una parte y el resto se queda con este proveedor.

Si ese proveedor aún no vende este SKO, el diálogo muestra el código, el nombre, el coste unitario y las unidades por caja de su nuevo producto, rellenados a partir del actual. Revísalos, corrige el coste y pulsa <b>Move</b>: el producto se crea para el mismo SKO.

Las unidades van al pedido de ese proveedor en el mismo estado, o a un pedido nuevo si no tiene ninguno, al precio que acordó la empresa compradora. Si el coste del nuevo proveedor es distinto, la empresa compradora recibe la pregunta de precio descrita arriba y la responde en el nuevo pedido. La empresa compradora recibe un aviso del cambio, y las dos líneas muestran de dónde vinieron o adónde fueron las unidades.

Solo puedes mover una línea mientras aún podrías cancelarla: antes de que empiece la producción y antes de que esté en una entrega de stock. Si en el pedido original no queda nada, se cancela, salvo que se haya pagado un depósito: entonces queda abierto para poder recuperarlo.

Si un pedido se canceló por error, o la empresa compradora lo quiere después de todo, ábrelo y pulsa **Undo cancel**. Vuelve al estado que tenía, con sus líneas, importes y costes. Indica un motivo y confirma que la empresa compradora y el proveedor saben que el pedido sigue adelante. Si los mismos productos están en un pedido creado después de la cancelación, el diálogo lo muestra primero para que no se pidan dos veces. Un pedido cancelado porque sus líneas se movieron a otro proveedor, o porque quedó vacío, no se puede recuperar.

## Buenos hábitos

- **Regístralo el mismo día.** Cinco segundos el día que toca te ahorran una discusión después sobre una etapa en rojo, y un motivo que escribir.
- **Propón fechas de listo en las que confíes.** Una fecha realista que cumples es mejor que una fecha optimista que fallas.
- **Avisa pronto al comprador** cuando una fecha se vaya a retrasar. Una nueva fecha aprobada a tiempo mantiene el pedido en plazo; una fecha que se retrasa sin que nadie la haya acordado aparece como retraso.

<aside class="wayfinder">
<b>Dónde pulsar en aiku</b><br>
Inicia sesión → <b>Purchase Orders</b> → abre el pedido. Cada orden de compra es para uno de tus proveedores. Las etapas están en la parte superior de la página: usa ahí los botones <b>Production started</b>, <b>Production done</b>, <b>QC skipped</b> y, si entregas la mercancía a un agente de transporte, <b>Handed over</b>. Un botón en gris pertenece a una etapa posterior: señálalo para ver qué va primero. El depósito se registra con <b>Pay deposit</b> en el panel de pagos. Después abre <b>Edit</b>: <b>Proposed ready date</b> y <b>Agent's order number</b> están en la sección <b>Clean handover</b>.
</aside>

<aside class="wayfinder">
<b>Permisos que necesitas</b><br>
Un acceso para tu organización de agente. Solo puedes marcar tus propias etapas, en tus propios pedidos: producción iniciada, producción terminada y entregado en las etapas, QC skipped, el depósito como pago, y tu fecha de listo propuesta y tu número de pedido en la página de edición. La aprobación de la muestra, la fecha de listo aprobada, el control de calidad y el cumplimiento normativo no están abiertos para ti, porque los registra la empresa compradora.
</aside>
