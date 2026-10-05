---
title: Tarjetas y opciones de pago
summary: Cómo pagas los pedidos, qué métodos de pago ofrece el checkout, y cómo guardar una tarjeta para que los pedidos de tus tiendas conectadas se paguen automáticamente.
date: 2026-09-25
source_date: 2026-09-25
tags: pago, tarjeta, tarjetas guardadas, checkout, paypal, apple pay, google pay, pago automático
category: payments
shops: awd, dssk, dse
---

<aside class="tldr">
Tu saldo se usa siempre primero. Lo que el saldo no cubre lo pagas en el checkout en <b>Pagos en línea</b>: tarjeta, Apple Pay, Google Pay, PayPal y otros métodos, según tu país y tu dispositivo. Los pedidos que llegan de tus tiendas conectadas se pagan sin ti: primero con tu saldo, luego con una tarjeta que hayas guardado en <b>Tarjetas guardadas</b>. Guarda una tarjeta, o mantén tu saldo recargado, para que estos pedidos no se queden sin pagar.
</aside>

## Dos formas de pagar los pedidos

- <b>Pedidos que creas tú mismo</b> (pedidos manuales): pagas en el checkout mientras lo ves.
- <b>Pedidos de tus tiendas conectadas</b> (Shopify, WooCommerce, eBay, TikTok y las demás, o por la API): nadie está en el checkout, así que cobramos nosotros. Usamos primero tu saldo, luego tus tarjetas guardadas. Consulta [Recargar y pagar con saldo](/docs/topping-up-and-paying-with-balance).

## Pagar en el checkout

1. En el <b>Panel Control</b>, en <b>Enlaces rápidos (Accesos directos)</b>, pulsa <b>Crear pedido manual</b>.
2. En <b>Seleccionar Cliente / Consumidor</b>, elige la persona a la que envías, o pulsa <b>Crea un nuevo cliente aquí</b>. Pulsa <b>Crear orden</b>.
3. Añade productos a la cesta, comprueba la dirección y pulsa <b>Continuar con el pago</b>. Si tu saldo cubre todo el pedido, la cesta muestra <b>Realizar pedido</b> en su lugar: púlsalo y el pedido se paga con tu saldo.
4. El checkout muestra tu <b>Número de orden</b> y el resumen. Si tienes dinero en tu saldo, se usa primero: ves cuánto se pagará con saldo y <b>Por favor pague el resto con su método preferido a continuación:</b>.
5. En <b>Pagos en línea</b>, elige cómo pagar el resto y sigue los pasos. Tu banco puede pedirte que confirmes el pago en su app o con un código.
6. Después de pagar, ves <b>Pago realizado. Esperando confirmación...</b>. Cuando se confirma el pago, el pedido se envía a nuestro almacén.

Si tu saldo cubre todo el pedido, no hay formulario de pago: solo ves <b>Realizar pedido</b>.

<!-- captura de pantalla: la página de checkout con el resumen del pedido y el formulario Online payments mostrando tarjeta, Apple Pay y PayPal -->

## Qué métodos de pago puedes usar

El formulario <b>Pagos en línea</b> muestra los métodos que funcionan para tu país, moneda y dispositivo. Los clientes usan:

- Tarjetas de débito y crédito
- Apple Pay (en dispositivos Apple) y Google Pay
- PayPal
- Klarna
- En algunos países europeos: iDEAL, Przelewy24 y Bancontact

Si no ves un método que esperabas, no está disponible para tu país, moneda o dispositivo. La transferencia bancaria y el contra reembolso no se ofrecen en el checkout de dropshipping.

## Guardar una tarjeta para pagos automáticos

Las tarjetas guardadas se usan para pagar los pedidos de tus tiendas conectadas cuando tu saldo no es suficiente.

El elemento <b>Tarjetas guardadas</b> aparece en el menú de la izquierda en cuanto conectas una tienda, creas un token de API o guardas una tarjeta. Un punto pequeño indica que aún no tienes ninguna tarjeta guardada.

Para guardar una tarjeta:

1. Pulsa <b>Tarjetas guardadas</b> en el menú de la izquierda. La página se llama <b>Panel de control de tarjetas de crédito</b>.
2. Pulsa <b>Guardar tarjeta de crédito</b> arriba (o <b>Agregar tarjeta de crédito</b> encima de tu lista de tarjetas).
3. Introduce los datos de tu tarjeta. Tu banco te pedirá que confirmes. Esto es necesario para poder cobrar la tarjeta más adelante sin que estés presente.
4. La tarjeta aparece en la lista, que muestra su <b>Card type</b>, estado <b>Expired</b>, <b>Last 4 digits</b> y <b>Added date</b>.

Aquí solo se pueden guardar tarjetas. Apple Pay, Google Pay y PayPal no se pueden guardar para pagos automáticos.

<!-- captura de pantalla: el Credit Card Dashboard con una tarjeta guardada marcada como predeterminada y los botones Set as default y Unlink -->

## Más de una tarjeta

- La tarjeta predeterminada tiene un tic verde. Pulsa <b>Establecer como predeterminado</b> en otra tarjeta para usarla primero.
- Cuando necesitamos pagar un pedido, probamos primero la tarjeta predeterminada, luego tus otras tarjetas, una por una, hasta que una funcione.
- Para eliminar una tarjeta, pulsa <b>Desconectar</b> y confirma.

Revisa la fecha de caducidad de tus tarjetas. Cuando una tarjeta caduque, guarda la nueva y elimina la antigua.

## Cuando algo falla

- <b>Algo salió mal</b> / <b>No se pudo comunicar con el servicio de pago.</b>: el pago no llegó a iniciarse. Actualiza la página del checkout e inténtalo de nuevo, o elige otro método.
- <b>Payment still processing</b> / <b>Your order will be submitted automatically once the payment is confirmed.</b>: tu banco aún no ha confirmado. No pagues de nuevo. Comprueba el pedido en unos minutos.
- <b>Order already submitted</b> / <b>This order has already been submitted and cannot be paid again.</b>: el pedido ya está pagado. Se te lleva a la página del pedido.
- <b>Online payments are temporarily unavailable</b>: el servicio de pago no responde. Inténtalo más tarde, o recarga tu saldo y paga con él.
- <b>Insert file missing</b>: un inserto de tu pedido no tiene archivo. Vuelve a la cesta y sube el archivo antes de pagar.
- <b>We cannot deliver to …</b> o <b>Your current billing address (…) is marked as forbidden</b>: no podemos cobrar para esta dirección. Cambia la dirección o contacta con nosotros.
- Un pedido de tu tienda muestra <b>No pagado</b> y te llegó un correo diciendo que está en espera: tu saldo no era suficiente y ninguna tarjeta guardada funcionó. Recarga tu saldo y pulsa <b>Pay … with balance</b> en el pedido. Consulta [Recargar y pagar con saldo](/docs/topping-up-and-paying-with-balance).
- Tu tarjeta fue rechazada en un pago automático: tu banco rechazó el cobro. Compruébala en <b>Saved Cards</b> — puede estar rechazada o caducada — luego recarga tu saldo y paga con él, o guarda otra tarjeta y ponla como predeterminada.
