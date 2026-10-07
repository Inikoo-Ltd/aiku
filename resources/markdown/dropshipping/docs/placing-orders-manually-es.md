---
title: Hacer pedidos manualmente
summary: Crea un pedido para un cliente en un canal Manual/API, añade productos, elige las opciones de entrega, paga en el checkout y sigue el pedido hasta que se expida.
date: 2026-09-25
source_date: 2026-09-25
tags: manual, pedidos, cesta, checkout, pago, saldo, recogida, expedición
category: orders
series: manual
order: 4
shops: awd, dssk, dse
---

<aside class="tldr">
Abre el cliente en tu canal Manual/API y pulsa <b>Crear orden</b>. Se abre una cesta: añade productos con <b>Añadir productos</b>, elige las opciones de entrega y pulsa <b>Continuar con el pago</b>. Usamos primero el saldo de tu cuenta y pagas el resto con tarjeta. Cuando el pedido está pagado va al almacén y lo sigues en <b>Pedidos</b>.
</aside>

## Antes de empezar

- Necesitas un canal Manual/API. Consulta [El canal Manual/API](/docs/manual-and-api-channel).
- La persona a la que envías debe ser cliente de ese canal. Consulta [Gestionar clientes](/docs/managing-clients).

## Crea el pedido

1. Abre tu canal Manual/API y luego <b>Clientes</b>.
2. Pulsa el nombre del cliente. Se abre la página del cliente.
3. Pulsa <b>Crear orden</b>.

Se abre la cesta del nuevo pedido. Arriba muestra el cliente, sus datos de contacto y la dirección de entrega. Comprueba el nombre y la dirección antes de continuar.

## Añade productos

- <b>Añadir productos</b> abre una ventana <b>Añadir productos al pedido</b>. Busca por nombre o código de producto, escribe la cantidad y añade los productos. Puedes elegir cualquier producto que vendamos, no solo los que están en <b>Mis productos</b>.
- <b>Subir productos</b> añade muchos productos desde una hoja de cálculo. Descarga la plantilla (.xlsx) en la ventana, rellena las columnas <b>code</b> y <b>cantidad</b>, y súbela.

Los productos aparecen en la lista. Puedes cambiar las cantidades ahí, o eliminar una línea. El peso estimado del paquete se muestra junto a la dirección.

<!-- captura de pantalla: una cesta con el cliente y la dirección arriba, productos en la lista, las opciones de entrega y el botón Continue to Checkout -->

## Elige las opciones de entrega

- <b>Colección</b>: actívalo si tú, o un transportista que reserves, recogerá el pedido en nuestro almacén en vez de enviarlo nosotros. Se aplica un cargo de recogida. Cuando está apagado, enviamos el pedido a la dirección mostrada. Pulsa <b>Editar</b> bajo la dirección para cambiarla en este pedido.
- Expedición más rápida: en AW Dropship UK la opción se llama <b>Same Day Dispatch</b>, en AW Dropship Europe <b>Premium Dispatch</b> y en AW Dropship España <b>Envío Premium</b>. Tiene un cargo extra, que se muestra junto a la opción. Lee el icono de información junto a ella para conocer las condiciones.
- <b>Extra protective packing for fragile items</b> (solo AW Dropship UK): embalaje extra para productos frágiles, con un pequeño cargo que se muestra junto a la opción.
- <b>Delivery Instructions</b>: una nota para el transportista. <b>This message will be printed in shipping label</b>, así que escríbela para el transportista, no para nosotros.
- <b>Other Instructions</b>: una nota para nuestro equipo.

Los cargos y el total del pedido se actualizan al activar una opción.

Estos son los cargos adicionales de esta web:

{order_charges}

## Paga

Si el saldo de tu cuenta cubre todo el pedido, la cesta muestra <b>Realizar pedido</b> en vez de <b>Continuar con el pago</b>. Púlsalo y el pedido se paga con tu saldo. La nota dice <b>This is your final confirmation. You can pay totally with your current balance.</b>

Si no:

1. Pulsa <b>Continuar con el pago</b>.
2. El checkout muestra el número de pedido. Si tienes algo de saldo, te dice cuánto se paga con saldo y te pide que pagues el resto.
3. En <b>Pagos en línea</b>, introduce los datos de tu tarjeta y confirma. Tu banco puede pedirte que apruebes el pago en su app o con un código.
4. Cuando el pago se completa, la página dice <b>Pago realizado. Esperando confirmación...</b> y luego abre el pedido.

Pulsa <b>Volver a la cesta</b> en la página de checkout para cambiar el pedido antes de pagar.

## Pedidos sin terminar: Baskets

Un pedido que creaste pero no pagaste queda en <b>Cestas</b>, dentro de tu canal. El número junto a <b>Cestas</b> en el menú muestra cuántos tienes. Abre uno para terminarlo, o pulsa <b>Borrar</b> en su fila (con el tooltip <b>Eliminar cesta</b>) para eliminarlo. Una cesta no se envía al almacén hasta que se paga.

## Sigue tus pedidos

Abre <b>Pedidos</b> dentro de tu canal. La lista muestra <b>Estado</b>, <b>Referencia</b>, el cliente, <b>Fecha</b>, los artículos y el total. Pulsa un pedido para ver sus productos, notas de entrega, envíos con enlaces de seguimiento y facturas.

El icono de estado indica en qué punto está el pedido. Pasa el ratón por encima para ver el nombre:

- <b>Submitted</b>: hemos recibido el pedido.
- <b>In Warehouse</b>, <b>Preparación de pedidos</b>, <b>Preparado</b>, <b>Embalaje</b>, <b>Empacado</b>: nuestro equipo lo está preparando.
- <b>Espera</b>: está en espera en el almacén.
- <b>Finalized</b>: listo para salir.
- <b>Dispatched</b>: enviado. El enlace de seguimiento está en el pedido.
- <b>Cancelled</b>: el pedido se canceló.

Los iconos en la parte superior de un pedido muestran las opciones que elegiste: una estrella para <b>Despacho premium</b>, una caja para <b>Embalaje extra</b>.

Si no podemos enviar algunos artículos, el pedido muestra que <b>Some items are not being sent</b>. El dinero de esos artículos se reembolsa automáticamente.

La página del pedido lista sus facturas con un botón de descarga. Todas tus facturas también están en <b>Facturas</b> en el menú.

## Cuando algo falla

- <b>No veo Create Order en la página del cliente.</b> El botón solo aparece en clientes de un canal Manual/API. En canales conectados, los pedidos llegan de tu tienda.
- <b>La cesta dice "We cannot deliver to …".</b> No enviamos a ese país. Cambia la dirección de entrega, o activa <b>Colección</b> si organizas el transporte tú mismo.
- <b>La cesta dice que tu dirección de facturación está marcada como prohibida.</b> Actualiza la dirección de facturación en tu cuenta o contacta con nosotros.
- <b>Continue to Checkout aparece en gris y me pide subir un archivo.</b> Elegiste un inserto impreso que necesita tu material gráfico. Sube el archivo, o quita el inserto, antes de pagar.
- <b>El pago con tarjeta falló.</b> El checkout dice <b>Algo salió mal</b>. Revisa los datos de la tarjeta y que tu banco aprobó el pago, e inténtalo de nuevo. También puedes recargar tu saldo y pagar con él.
- <b>El checkout dice "Payment still processing".</b> No pagues de nuevo. El pedido se envía automáticamente en cuanto se confirma el pago.
- <b>El checkout dice "Order already submitted".</b> El pedido ya está pagado. Ábrelo en <b>Pedidos</b>.
- <b>Mi pedido aparece como Unpaid.</b> El pago no cubrió el pedido. Añade dinero a tu saldo con <b>Recargar Saldo</b>, abre el pedido y pulsa <b>Pay … with balance</b>. El botón aparece cuando tu saldo cubre el importe pendiente. El pedido pasa entonces al almacén.
- <b>Necesito cambiar o cancelar un pedido que ya pagué.</b> No puedes cambiar tú mismo un pedido pagado. Contacta con nosotros cuanto antes, antes de que se expida.
