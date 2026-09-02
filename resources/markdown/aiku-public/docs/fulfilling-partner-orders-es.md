---
title: Trabajar la lista To produce (Por producir)
summary: La guía de la fábrica - una sola cola con todo lo que la fábrica debe, a organizaciones socias y a sus propios clientes, agrupada como piensa un planificador de producción.
date: 2026-09-02
source_date: 2026-09-02
tags: production, procurement, intercompany, dispatch
category: production
series: Ordering from partners
order: 4
---

<aside class="tldr">
Para quienes <em>fabrican</em> las cosas y para quien planifica el día de la fábrica. <b>To produce</b> (Por producir) es la cola de la fábrica: cada línea que una organización socia ha pedido, más cada línea que un cliente propio ha comprado y que la fábrica no tiene en stock. La agrupas por artesano, por categoría o por comprador, marcas lo que puedes enviar a los socios, y el resto del papeleo sigue solo. ¿Nuevo en el flujo de socios? Empieza por la <a href="/docs/ordering-from-a-partner-organisation-es">visión general</a>. ¿Quieres que la lista sepa quién hace qué? Lee antes <a href="/docs/who-makes-what-es">Quién hace qué</a>.
</aside>

## De dónde salen las líneas

**Factory → To produce** se alimenta de dos sitios. Tú nunca escribes una línea aquí a mano.

- **Peticiones de socios.** Las organizaciones hermanas ponen lo que necesitan en su [lista de la compra](/docs/buying-from-a-partner-es). Cada línea abierta dirigida a tu fábrica aparece aquí con el comprador, la cantidad y la prioridad que le pusieron.
- **Clientes propios.** Cuando se envía un pedido en tu propia tienda, aiku mira cada producto. Si el stock que hay detrás anda corto y ese stock lo hace la fábrica, la falta aparece aquí como una línea, etiquetada con el cliente y la referencia del pedido. Cuando ese pedido se despacha, la línea se cierra sola.

Los pedidos que llegan por el sistema antiguo no alimentan la lista. Solo los pedidos enviados en aiku.

El filtro **Source** (Origen) en la parte superior de la pestaña *All* te deja ver solo líneas de socios o solo líneas de clientes propios.

## Las cuatro vistas

La barra de pestañas encima del título es lo importante de la página. Las mismas líneas, vistas de cuatro maneras.

- **All.** La tabla plana, ordenable y buscable, con el recuento de líneas abiertas. Úsala cuando buscas algo concreto.
- **By artisan** (Por artesano). Un bloque por persona, usando el artesano asignado al artefacto o, si no lo tiene, el de su categoría. Las líneas sin nadie asignado quedan bajo *Unassigned*. Esta es la vista para repartir el trabajo del día.
- **By category** (Por categoría). Un bloque por categoría de artefacto, así el que hace bombas de baño ve bombas de baño y el que hace jabón ve jabón.
- **By buyer** (Por comprador). Un bloque por organización socia o cliente propio, para cuando estás montando un envío.

En las vistas agrupadas, cada bloque tiene una cápsula encima de la lista con su nombre y el número de líneas. Haz clic en la cápsula para ocultar ese bloque, y otra vez para que vuelva. aiku recuerda tu elección en este navegador, así que un planificador que solo se ocupa de dos categorías solo ve esas dos.

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
<li><b>Ver la cola:</b> tu organización → <b>Factory</b> → <b>To produce</b>. Cambia de vista con las pestañas <b>All · By artisan · By category · By buyer</b>.</li>
<li><b>Ocultar un bloque:</b> en una vista agrupada, clic en su cápsula encima de la lista. Clic otra vez para mostrarlo.</li>
<li><b>Solo socios o solo clientes:</b> pestaña <i>All</i> → filtro <b>Source</b>.</li>
<li><b>Enviar a un socio:</b> marca líneas → <b>Pick into order</b> → <b>Send to warehouse</b> en el cuadro <i>Picked orders</i>.</li>
<li><b>Decidir quién hace qué:</b> ver <a href="/docs/who-makes-what-es">Quién hace qué</a>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Los puestos se asignan en la ficha del empleado en Human Resources y llevan los permisos consigo.</li>
<li>Ver la lista: puesto <b>Production operative</b> (operario) para la fábrica, o superior.</li>
<li>Recoger, enviar y crear órdenes de trabajo: puesto <b>Production floor supervisor</b> (supervisor de planta) para la fábrica, o supervisor de la organización.</li>
</ul>
</aside>
