---
title: Comprar a un socio
summary: La guía del comprador - empieza por el panel de compras, rellena la lista a mano, desde el catálogo del socio o con auto-fill, y recibe la mercancía cuando llega.
date: 2026-09-01
source_date: 2026-09-01
tags: procurement, intercompany, shopping-list
category: procurement
series: Ordering from partners
order: 3
---

<aside class="tldr">
Para quienes <em>cursan</em> pedidos a socios. Mantienes una lista abierta de lo que necesita tu organización; el socio la sirve a su propio ritmo. Empieza por el <a href="/docs/reading-the-partner-shopping-dashboard-es">panel de compras</a> para ver qué está en riesgo y cuánto margen tienes, y luego añade líneas a mano, desde su catálogo, o deja que auto-fill proponga una reposición dentro de un presupuesto. ¿Nuevo en el flujo? Empieza por la <a href="/docs/ordering-from-a-partner-organisation-es">visión general</a>.
</aside>

## Empieza por el panel

**Procurement → Partners → {socio} → Shopping** abre el [panel de compras](/docs/reading-the-partner-shopping-dashboard-es): qué está a punto de agotarse, qué ya viene de camino, y los dos límites dentro de los que vive tu lista — el **order budget** para este socio y el **warehouse space** disponible. Trabaja las casillas de riesgo desde ahí y la mayor parte de la lista se escribe sola; todo lo de abajo es cómo se comporta la lista una vez estás dentro.

## La lista de la compra

Junto al panel, la pestaña **Shopping list** contiene todas las líneas abiertas.

- **Add stocks** abre la lista de stock del socio con su disponibilidad, cómo se empaqueta cada artículo, tu propio stock actual y cuánto has usado en los últimos cuatro trimestres. Las cantidades están en las unidades de envío del vendedor (SKOs).
- Cada línea cuenta la historia del stock de un vistazo — *su stock*, *nuestro stock* y cuándo *nos quedamos sin él* — más el importe a tu precio de compra, con el total de las líneas abiertas al pie de la tabla.
- Las líneas abiertas son totalmente tuyas: elige la **priority** (low → urgent) directamente en el desplegable de la tabla, o elimina la línea con su botón de papelera. Para cambiar una cantidad, usa **Browse** — el stepper del mismo artículo ahí edita la línea abierta directamente. En cuanto el socio recoge una línea, esta se bloquea, y su estado te dice dónde está.

## Explorar el catálogo del socio

Junto a la lista de la compra hay una pestaña **Browse**: todo el catálogo del socio como una tienda, con stock y precios en vivo. Muévete por **Departments** o **Collections**, baja hasta las familias, o simplemente escribe en el buscador. Cada ficha de producto muestra el precio actual, una insignia **Their stock** con lo que el socio tiene disponible y — para los artículos que usas — tus propios números: *our stock*, *our sales / quarter* y *we run out in* tantos días (en rojo cuando quedan dos semanas o menos).

Hay dos cosas que conviene saber sobre ese catálogo. Los precios son **tuyos, no del estante**: el precio de lista del vendedor con tu descuento intercompañía ya restado, convertido a la moneda de tu propia organización, así que lo que ves es lo que dirá la factura. Y incluye productos que el socio ha hecho **exclusivos para ti** — líneas que nunca aparecen en su tienda pública pero existen para tu organización. Si no encuentras algo que esperabas, vale la pena preguntar; si encuentras algo que no esperabas, probablemente es tuyo por acuerdo.

El pedido ocurre directamente en la ficha: la casilla de cantidad **es** tu lista de la compra. Escribe o incrementa un número y la línea se añade o se actualiza en la lista abierta; ponlo a 0 y la línea se elimina. Al lado, una insignia discontinua de **suggested** muestra la cantidad que aiku pediría — un clic rellena la casilla con ella.

Mientras exploras, tu lista de la compra te acompaña como un recibo fijado a la derecha — cada línea agrupada por familia, con el total corriendo — así siempre sabes cómo va el pedido. **Go to Shopping list** te lleva de vuelta a la lista completa editable.

<figure><img src="/art/docs/draw-partner-browse.svg" alt="Boceto en acuarela del explorador del catálogo del socio: un buscador, pestañas Departments y Collections, fichas de producto con botones de más, y el recibo de la lista de la compra fijado a la derecha con su total corriendo" width="1200" height="750" loading="lazy"><figcaption>La tienda del socio, con tu lista acompañándote.</figcaption></figure>

## Auto-fill: un presupuesto y, si quieres, una instrucción

Auto-fill existe para que la reposición no dependa de que alguien recuerde cada artículo. Le das un solo número — un **budget**, en la misma moneda que los precios a los que compras — y construye una propuesta que cabe dentro de él:

- Mira cada artículo que el socio puede suministrar y que realmente usas, los ordena por **cuánto tardas en quedarte sin él** (el mismo pronóstico *we run out in* que ves al explorar), y repone primero los que se agotan antes, cada uno a su cantidad de pedido recomendada.
- Cada línea propuesta muestra su **motivo** ("Our sales/quarter ~48 · our stock 0 · we run out now"), la cantidad y el coste, así puedes ver por qué está ahí. Las cantidades siguen el mismo pronóstico que las insignias *suggested* de Browse.
- El **instruction box** es opcional y acepta lenguaje natural: *"prioritise essential oils, skip anything we hold over 8 weeks of"*, *"focus on candles, nothing seasonal"*. Una IA lee tu instrucción junto con los mismos datos de consumo y remodela la propuesta en consecuencia — pero su resultado se contrasta con la realidad antes de que lo veas: las cantidades quedan topadas a lo que el socio realmente tiene, y el total se fuerza de vuelta dentro de tu presupuesto. Si la instrucción no se puede seguir, recibes la propuesta estándar en su lugar.
- **Nada se añade por sí solo.** La propuesta es un conjunto de líneas marcadas que puedes desmarcar, recalcular o regenerar con otro presupuesto o instrucción; solo **Add items to shopping list** confirma algo.
- **Algunos artículos quedan fuera.** Un SKO con **Do not auto order** activado (en la pantalla de edición del SKO, bajo Stock Data) nunca aparece en una propuesta — para artículos que procurement quiere mantener bajo control manual. Aun así puedes pedirlo a mano desde Browse o la lista de stock; solo la vía automática lo salta. Los SKOs marcados como **On Demand** quedan excluidos por completo de las compras a socios.

Auto-fill también puede abrirse ya acotado: **+ fill** en una casilla de riesgo del panel lo abre solo para ese cubo, con la propuesta ya generada. Mismas reglas — ajustas, desmarcas y confirmas; nada se añade por sí solo.

Un buen hábito: trabaja las casillas del panel empezando por las peores, luego ejecuta Auto-fill una vez por ciclo de reposición para lo que quede, lee los motivos, desmarca lo que no te convenza y añade el resto.

## Cuando la lista dice que no

Los añadidos se rechazan en tres casos, a propósito: la lista ha alcanzado el **budget** para este socio (los artículos de rango A y sin stock están exentos — una emergencia siempre cabe), el almacén está por debajo del 5% de ubicaciones libres, o este socio ya ha reclamado su parte proporcional de las plazas libres con productos que nunca has almacenado. Atiende el mensaje en vez de buscar otra vía: la misma protección cubre los añadidos manuales, en bloque y de Auto-fill. El [artículo del panel](/docs/reading-the-partner-shopping-dashboard-es) explica de dónde vienen esos límites.

## Cuando la mercancía va de camino

En cuanto el socio [envía un cargamento a su almacén](/docs/fulfilling-partner-orders-es), aparece una **stock delivery** entrante bajo **Stock deliveries** de tu socio. Déjala tranquila mientras diga confirmed o dispatched — refleja el almacén del vendedor y se actualiza sola. Cuando las cajas llegan físicamente: **receive**, comprueba y coloca en ubicaciones exactamente como harías con cualquier entrega de proveedor. Cualquier falta o daño se gestiona después de recibir, contra la factura vinculada — consulta [la visión general](/docs/ordering-from-a-partner-organisation-es) para cómo funciona el dinero.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Ver qué hay que comprar:</b> tu organización → <b>Procurement → Partners</b> → abre el socio → <b>Shopping</b> (el panel) → trabaja las casillas de riesgo.</li>
<li><b>Añadir a la lista:</b> <b>Shopping list</b> → <b>Add stocks</b>, o <b>Browse</b> y fija cantidades en las fichas de producto, o <b>Auto-fill</b> (o <b>+ fill</b> en una casilla del panel) para una propuesta.</li>
<li><b>Ajustar líneas abiertas:</b> cambia la priority o elimina líneas en la tabla de la lista de la compra; cambia cantidades desde las fichas de producto en <b>Browse</b>.</li>
<li><b>Mantener un artículo fuera de auto-fill:</b> tu organización → <b>Warehouse → Inventory</b> → abre el SKO → <b>Edit SKO</b> → activa <b>Do not auto order</b>.</li>
<li><b>Seguir y recibir el envío:</b> misma página del socio → <b>Stock deliveries</b> → cuando llega la mercancía, <b>Receive</b> → comprueba → coloca en ubicaciones.</li>
</ul>
</aside>
