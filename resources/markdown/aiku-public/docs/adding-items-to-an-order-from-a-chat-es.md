---
title: Añadir artículos a un pedido desde un chat
summary: Un cliente en el chat olvidó algo. Añádelo al pedido que ya hizo, o crea un pedido de seguimiento que viaja en el mismo paquete, y envía un enlace de pago por la diferencia - todo desde la propia conversación.
date: 2026-09-21
source_date: 2026-09-21
tags: chat, orders, payments, customer service
category: crm
---

<aside class="tldr">
Un cliente escribe para decir que le faltó un artículo en el pedido que acaba de hacer. Abre el panel lateral de la conversación y mira <b>Last orders</b> (Últimos pedidos): un pedido que el almacén todavía no ha terminado de preparar muestra <b>+ Add items</b> (+ Añadir artículos), y los productos olvidados entran en ese mismo pedido y en la lista del preparador. Un pedido ya preparado muestra en su lugar <b>+ Follow-up order</b> (+ Pedido de seguimiento), que crea un segundo pedido y avisa al almacén, en ambos, de que los envíe juntos. Cualquier pedido que todavía deba dinero muestra <b>Payment link</b> (Enlace de pago), que genera un enlace de tarjeta por exactamente lo que se debe y lo copia para que lo pegues en el chat.
</aside>

## Dónde está

Abre la conversación en <b>Chat</b> (Chat). El panel lateral de la derecha se abre en <b>Overview</b> (Resumen), y debajo de los datos de contacto está <b>Last orders</b> (Últimos pedidos): los cinco pedidos más recientes del cliente con su estado, fecha y total. Los botones están en cada fila, junto al estado, y solo se muestran los que tienen sentido para ese pedido.

Aparecen para quienes pueden editar pedidos en esa tienda. No aparecen en pedidos de marketplace - Faire y similares - porque esos pedidos siguen lo que dice el marketplace, y cambiarlos por nuestro lado solo se deshace. Los invitados no tienen pedidos, así que no hay nada que mostrar.

## Add items (Añadir artículos)

Se muestra mientras el pedido está <b>Submitted</b> (Enviado), <b>In warehouse</b> (En almacén), en <b>Picked</b> (preparación), o <b>waiting</b> (esperando) a atención al cliente. Es decir: mientras alguien todavía vaya a recorrer el almacén por él.

Pulsa <b>+ Add items</b> (+ Añadir artículos), busca los productos, pon la cantidad de cada uno y pulsa <b>Add</b> (Añadir). No se guarda nada hasta que lo pulsas, así que puedes cambiar de idea libremente antes de eso.

Qué pasa después:

- Los productos van al <b>mismo pedido</b>. Sin segundo pedido, sin segundo paquete, sin nota que escribir.
- Si el almacén ya tiene el pedido, las líneas nuevas van directas a la <b>lista del preparador</b>. Cuando la preparación ya ha empezado, esas líneas quedan resaltadas y el preparador ve un aviso de que el pedido se modificó, el mismo que ve cuando cambia una cantidad.
- Si el pedido ya tenía ese producto, sube su cantidad en vez de aparecer una línea duplicada.
- El total del pedido sube, y un pedido que estaba pagado ahora aparece como <b>not fully paid</b> (no pagado del todo). Es correcto: el cliente debe la diferencia. Ver <b>Payment link</b> (Enlace de pago) más abajo.

<b>Si te dice que ya es tarde.</b> Un preparador puede terminar el pedido en los segundos entre que abres la ventana y pulsas <b>Add</b>. Cuando pasa eso no se añade nada y el mensaje dice que crees un pedido de seguimiento. Recarga el panel y la fila ofrecerá justo eso.

## Follow-up order (Pedido de seguimiento)

Se muestra en cuanto el pedido está <b>Picked</b> (Preparado), <b>Packing</b> (En embalaje), <b>Packed</b> (Embalado) o <b>Finalised</b> (Finalizado) - terminado en el almacén pero sin despachar. Los artículos adicionales no pueden entrar en un paquete cuya preparación ya acabó, así que viajan como un segundo pedido en la misma caja.

Pulsa <b>+ Follow-up order</b> (+ Pedido de seguimiento). Se crea un pedido nuevo, vacío, para el mismo cliente y se abre en una pestaña nueva. Ambos pedidos llevan ahora la línea <b>Send together with order ...</b> (Enviar junto con el pedido ...) en la nota de almacén, que es la nota que se imprime para quienes preparan y embalan, y que ellos leen. No tienes que escribir nada tú, y la nota que ya hubiera se conserva.

Luego, en el pedido nuevo: añade los productos y envíalo como harías con cualquier pedido que colocas para un cliente.

El pedido nuevo toma la dirección de envío habitual del cliente. Si el primer pedido iba a otro sitio, cambia la dirección del nuevo para que coincida - dos pedidos a dos direcciones no pueden compartir paquete.

## Payment link (Enlace de pago)

Se muestra en cualquier pedido que todavía tenga algo pendiente de pago, sea cual sea su estado, en tiendas que aceptan pago con tarjeta.

Pulsa <b>Payment link</b> (Enlace de pago). Se crea un enlace de pago con tarjeta por <b>exactamente el importe pendiente</b> - el total del pedido menos lo ya pagado - y se copia. Pégalo en la conversación. La confirmación te dice el importe para que se lo digas al cliente.

Cuando el cliente paga, el pago aparece en el pedido por sí solo y el pedido pasa a estar pagado. No hay nada que cuadrar ni registrar a mano, ni falta ir a la propia web del proveedor de pagos para crear el enlace.

Dos cosas que saber:

- El enlace es por el importe pendiente <b>en el momento en que lo creaste</b>. Si el pedido cambia después, crea un enlace nuevo.
- Un enlace es válido durante siete días.

Un pedido de seguimiento aparece en <b>Last orders</b> (Últimos pedidos), con su propio botón <b>Payment link</b> (Enlace de pago), en cuanto se envía.

## ¿Cuál uso?

No hace falta decidir: la fila solo ofrece lo que ese pedido puede admitir. Todavía en preparación: <b>Add items</b> (Añadir artículos). Ya preparado: <b>Follow-up order</b> (Pedido de seguimiento). Ya despachado: ninguno, porque no queda paquete al que unirse - haz un pedido nuevo de la forma normal.

<aside class="wayfinder"><strong>Dónde hacer clic en aiku</strong>
<ul>
<li><b>Ver los pedidos del cliente:</b> <b>Chat</b> &rarr; abre la conversación &rarr; panel lateral &rarr; <b>Overview</b> &rarr; <b>Last orders</b>.</li>
<li><b>Añadir artículos olvidados al mismo pedido:</b> <b>+ Add items</b> en la fila del pedido &rarr; elige productos y cantidades &rarr; <b>Add</b>.</li>
<li><b>Pedido ya preparado:</b> <b>+ Follow-up order</b> en la fila &rarr; añade los productos en el pedido que se abre &rarr; envíalo.</li>
<li><b>Cobrar la diferencia:</b> <b>Payment link</b> en la fila &rarr; pégalo en la conversación.</li>
<li><b>Abrir el pedido en sí:</b> haz clic en su referencia en <b>Last orders</b>.</li>
</ul>
</aside>
</content>
