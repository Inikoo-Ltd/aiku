---
title: Trabajar la lista To produce (Por producir)
summary: La guía de la fábrica - una sola cola con todo lo que la fábrica debe, a organizaciones socias y a sus propios clientes, agrupada como piensa un planificador de producción.
date: 2026-09-09
source_date: 2026-09-09
tags: production, procurement, intercompany, dispatch
category: production
series: Ordering from partners
order: 4
---

<aside class="tldr">
Para quienes <em>fabrican</em> las cosas y para quien planifica el día de la fábrica. <b>To produce</b> (Por producir) es la cola de la fábrica: cada línea que una organización socia ha pedido, más cada línea que un cliente propio ha comprado y que la fábrica no tiene en stock. El <b>Board</b> (Tablero) es donde planificas: arrastras una línea entre los carriles para decidir cuántas hacer y quién las hace, y se crea una orden de trabajo para el artesano. Las vistas de lista agrupan las mismas líneas por artesano, categoría o comprador, y desde ahí marcas lo que puedes enviar a los socios; el resto del papeleo sigue solo. ¿Nuevo en el flujo de socios? Empieza por la <a href="/docs/ordering-from-a-partner-organisation-es">visión general</a>. ¿Quieres que la lista sepa quién hace qué? Lee antes <a href="/docs/who-makes-what-es">Quién hace qué</a>.
</aside>

## De dónde salen las líneas

**Factory → To produce** se alimenta de dos sitios. Tú nunca escribes una línea aquí a mano.

- **Peticiones de socios.** Las organizaciones hermanas ponen lo que necesitan en su [lista de la compra](/docs/buying-from-a-partner-es). Cada línea abierta dirigida a tu fábrica aparece aquí con el comprador, la cantidad y la prioridad que le pusieron.
- **Clientes propios.** Cuando se envía un pedido en tu propia tienda, aiku mira cada producto. Si el stock que hay detrás anda corto y ese stock lo hace la fábrica, la falta aparece aquí como una línea, etiquetada con el cliente y la referencia del pedido. Cuando ese pedido se despacha, la línea se cierra sola.

Los pedidos que llegan por el sistema antiguo no alimentan la lista. Solo los pedidos enviados en aiku.

El filtro **Source** (Origen) en la parte superior de la pestaña *All* te deja ver solo líneas de socios o solo líneas de clientes propios.

## Las vistas

La barra de pestañas encima del título es lo importante de la página. Las mismas líneas, seis maneras de verlas.

- **Board** (Tablero). La vista de planificación, y con la que se abre la página. Cada línea es una tarjeta que avanza por carriles desde *Backlog* hasta *Done*. Se explica en la siguiente sección.
- **All.** La tabla plana, ordenable y buscable, con el recuento de líneas abiertas. Úsala cuando buscas algo concreto.
- **By artisan** (Por artesano). Un bloque por persona, usando el artesano asignado al artefacto o, si no lo tiene, el de su categoría. Las líneas sin nadie asignado quedan bajo *Unassigned*. Esta es la vista para repartir el trabajo del día.
- **By category** (Por categoría). Un bloque por categoría de artefacto, así el que hace bombas de baño ve bombas de baño y el que hace jabón ve jabón.
- **By buyer** (Por comprador). Un bloque por organización socia o cliente propio, para cuando estás montando un envío.
- **Mixes** (Mezclas). Las bases y mezclas que necesitan las órdenes de trabajo abiertas, para el preparador. Se explica en [Preparar mezclas](/docs/preparing-mixes-es).

En las vistas agrupadas, cada bloque tiene una cápsula encima de la lista con su nombre y el número de líneas. Haz clic en la cápsula para ocultar ese bloque, y otra vez para que vuelva. aiku recuerda tu elección en este navegador, así que un planificador que solo se ocupa de dos categorías solo ve esas dos.

## El Board

Seis carriles, de izquierda a derecha. Una tarjeta se mueve hacia la derecha a medida que avanza el trabajo, y la mayoría de los movimientos son un arrastre.

| Carril | Qué hay ahí |
| --- | --- |
| Backlog | líneas con un artefacto que nadie ha revisado todavía |
| Preparing | líneas que has decidido fabricar, con la cantidad ya fijada |
| Assigned | existe una orden de trabajo y va dirigida a un artesano, pero nadie la ha empezado |
| Producing | un artesano ha pulsado START en una de sus tareas |
| Done | todas las tareas de la orden de trabajo están hechas; espera a que el almacén la guarde |

Cada tarjeta muestra el producto, la cantidad pedida, quién la pidió, y **In stock** para que veas si merece la pena fabricarla siquiera. Al Board solo llegan líneas con un artefacto en esta fábrica; las líneas cuyo stock se puede coger directamente del estante viven en su propia página, **Factory → Pre-pick**, ver [Recoger las mercancías de un socio](/docs/gathering-a-partners-goods-es).

Debajo de los carriles hay una línea más: **N lines too small for a batch are waiting for company · show**. Un socio puede pedir menos de una hornada completa, y esa línea no se puede fabricar por sí sola con sentido, así que espera al margen en vez de amontonarse en el Backlog. Se recoge cuando la demanda abierta de ese mismo stock entre todas las listas de socios llega a una hornada, cuando un pedido de cliente propio en la puerta hace que el trabajo se ejecute de todos modos, o cuando pulsas *show* y lo fabricas igualmente. Una línea puede esperar mucho tiempo; eso describe su situación con más honestidad que cualquier estado que pudiéramos inventar. Ver [Hornadas, packs y hornadas parciales](/docs/batches-packs-and-part-batches-es).

**Backlog → Preparing.** Suelta la tarjeta y aiku pregunta *¿Cuántas fabricar?*. Propone la cantidad pedida; escribe más y el extra se marca *para stock*. Si el artefacto tiene un tamaño de hornada recomendado, un pequeño botón **↑** redondea la cantidad a hornadas completas. Lo que finalmente se le pide al artesano está en **units**, redondeado hacia arriba a la siguiente hornada completa, y lo que vuelve se divide por el tamaño del pack camino del estante: 16 unidades de un pack de diez llegan como 1,6 SKOs. El número sigue editable en la tarjeta mientras está en Preparing.

**Preparing → Assigned.** Suelta la tarjeta y aiku pregunta *¿Quién lo hace?*. Propone el artesano asignado al artefacto o a su categoría, ver [Quién hace qué](/docs/who-makes-what-es). Elige un nombre y se crea una orden de trabajo en borrador, dirigida a esa persona. Ábrela y pulsa **Release to floor** (Liberar a planta) cuando deba empezar; hasta entonces el artesano no la ve. Para cambiar el artesano después, haz clic en el nombre de la tarjeta.

**Producing** y **Done** se mueven solos según lo que pasa en la pantalla de planta. Una tarjeta sale del tablero cuando el almacén guarda el producto terminado, ver [Guardar la producción terminada](/docs/putting-away-finished-production-es), o cuando la orden de trabajo se recibe en el stock desde su propia página.

Varias tarjetas a la vez: haz clic en las tarjetas para seleccionarlas, luego arrastra cualquiera de ellas y se mueve toda la selección. El menú **Everybody** (Todos) encima del tablero lo estrecha a uno o dos artesanos, y los filtros de familia, comprador y prioridad hacen lo mismo con las tarjetas.

La barra lateral de la fábrica lleva los recuentos en vivo de **To produce** y **Pre-pick** junto a sus nombres, y se mueven solos a medida que cambian las listas de la compra; no hace falta recargar la página para ver si ha entrado algo nuevo.

Debajo del Board y de la vista By artisan está **Open job orders per artisan** (Órdenes de trabajo abiertas por artesano): una chip por persona con cuántas órdenes de trabajo tiene abiertas. Rojo significa ninguna, ámbar significa una; todo el mundo debería tener al menos dos para que nadie se quede sin trabajo. La cruz de una chip marca a la persona como que no es artesano y la quita del recuento.

## Enviar líneas de socios

Las líneas de socios se despachan desde aquí; las de clientes propios no, esas viajan con su propio pedido.

- Marca las líneas de socios que puedes enviar. Ajusta la cantidad para una **partial pick** (recogida parcial), y el resto queda abierto para un envío posterior.
- **Pick into order** reúne tus marcas en un envío pendiente por organización compradora. Queda abierto en el cuadro *Picked orders* hasta que lo envías.
- **Send to warehouse** entrega el envío a tu almacén como un pedido normal: se recoge, se empaqueta, se despacha y se factura como todo lo demás. La entrada de stock (stock delivery) de la organización compradora se crea para ellos y sigue el progreso de tu almacén. Nadie actualiza el lado del comprador a mano.

Marcar una línea de cliente propio no sirve de nada. Se ignora al pulsar Pick into order, porque ese producto ya pertenece a un pedido de cliente.

## Cosas que conviene saber

- La lista abierta de un comprador está limitada a aproximadamente un ciclo de pedido de lo que históricamente le entregas, así que lo que te llega es una petición filtrada, no un volcado del catálogo. Si una línea te resulta rara, pregunta; el comprador tuvo que renunciar a algo para ponerla ahí.
- La primera recogida para un socio nuevo crea una cuenta de cliente con el nombre de la organización compradora en tu tienda. Es normal. Avisa a atención al cliente para que nadie la "limpie".
- Hasta que pulsas Send to warehouse, el pedido recogido es invisible en las pantallas de pedidos normales; la página To produce es su sitio.
- Lo que despachas es lo que dice la entrada de stock del comprador. Nunca infles cantidades para "cuadrar con la lista".

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Ver la cola:</b> tu organización → <b>Factory</b> → <b>To produce</b>. Cambia de vista con las pestañas <b>Board · All · By artisan · By category · By buyer · Mixes</b>.</li>
<li><b>Decidir la cantidad:</b> <i>Board</i> → arrastra la tarjeta de <b>Backlog</b> a <b>Preparing</b> → escribe el número, o pulsa <b>↑</b> para hornadas completas.</li>
<li><b>Crear la orden de trabajo:</b> arrastra la tarjeta de <b>Preparing</b> a <b>Assigned</b> → elige el artesano → abre la orden de trabajo → <b>Release to floor</b>.</li>
<li><b>Líneas que solo necesitan recogida:</b> <b>Factory</b> → <b>Pre-pick</b>, su propia página.</li>
<li><b>Líneas pequeñas esperando una hornada:</b> pulsa <b>show</b> en la línea bajo el tablero.</li>
<li><b>Qué se está agotando de todos modos:</b> <b>Factory</b> → <b>To restock</b>, ver <a href="/docs/keeping-the-factory-stocked-es">Mantener la fábrica abastecida</a>.</li>
<li><b>Ocultar un bloque:</b> en una vista agrupada, clic en su cápsula encima de la lista. Clic otra vez para mostrarlo.</li>
<li><b>Solo socios o solo clientes:</b> pestaña <i>All</i> → filtro <b>Source</b>.</li>
<li><b>Enviar a un socio:</b> marca líneas → <b>Pick into order</b> → <b>Send to warehouse</b> en el cuadro <i>Picked orders</i>.</li>
<li><b>Decidir quién hace qué:</b> ver <a href="/docs/who-makes-what-es">Quién hace qué</a>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Los puestos se asignan en la ficha del empleado en Human Resources y llevan los permisos consigo.</li>
<li>Ver la lista: puesto <b>Production operative</b> para la fábrica, o superior.</li>
<li>Mover tarjetas en el Board, crear y liberar órdenes de trabajo, recoger y enviar: puesto <b>Production floor supervisor</b> para la fábrica, o supervisor de la organización. El <b>Mix preparer</b> (preparador de mezclas) puede hacer lo mismo solo para mezclas.</li>
</ul>
</aside>
