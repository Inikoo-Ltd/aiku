---
title: Configurar transportistas
summary: Añade los transportistas con los que despacha tu almacén, conecta los que imprimen sus propias etiquetas y enseña a aiku qué transportista preferir para cada destino.
date: 2026-08-31
source_date: 2026-08-31
tags: dispatch, shippers
category: dispatch
---

<aside class="tldr">
Un <em>shipper</em> es un transportista al que tu almacén entrega paquetes — APC, GLS, DPD, Packeta o el mensajero local de la esquina. Mantienes la lista en la pantalla <b>Dispatching → Shippers</b> del almacén, y en el <b>Settings</b> de la organización puedes decirle a aiku qué transportista sugerir — o exigir — para cada destino. A partir de ahí, la pantalla de despacho elige casi sola.
</aside>

## La lista de transportistas

Cada organización mantiene su propia lista de transportistas. La encuentras dentro del almacén: abre tu warehouse y luego **Dispatching → Shippers**. La pestaña **Current** muestra los transportistas en uso hoy; la pestaña **Inactive** conserva los retirados, para que los envíos antiguos sigan sabiendo quién los llevó.

Cada fila muestra el nombre del transportista, el nombre comercial con el que opera, y su **type** — que indica si aiku se comunica directamente con ese transportista (más abajo, sobre esto).

## Añadir un transportista

Pulsa **Create Shipper** en la parte superior de la lista. El formulario pide cuatro cosas:

- **Code** — una referencia interna corta, como `APC` o `GLS`.
- **Name** — el nombre completo del transportista tal como lo conoce tu equipo.
- **Trade as** — el nombre corto que aparece en los envíos y en el papeleo.
- **Tracking url** — la página de seguimiento del transportista. Cuando alguien escribe un número de seguimiento a mano, aiku usa esto para construir el enlace en el que puede pulsar el cliente.

Eso es todo lo que necesita un transportista básico. Desde el momento en que se guarda, se puede elegir al despachar un albarán: el equipo elige el transportista, escribe el número de seguimiento del propio sistema del transportista, y aiku guarda el registro y el enlace de seguimiento.

## Transportistas conectados: etiquetas sin teclear nada

Algunos transportistas hacen más que figurar en una lista. aiku puede comunicarse directamente con **APC**, **GLS** (Eslovaquia y España), **DPD** (Reino Unido y Eslovaquia), **Packeta**, **CTT** e **ITD**. Para un transportista conectado, crear el envío en el albarán le pide al transportista un envío real: el número de seguimiento vuelve solo y la **etiqueta de envío se pone en cola directamente en la impresora** — nadie teclea nada, nadie vuelve a introducir una dirección en la web del transportista.

La conexión necesita credenciales de cuenta de tu contrato con el transportista, así que se configura junto con el equipo de aiku en lugar de desde el formulario de creación. Si abres cuenta con alguno de los transportistas de arriba, pide que se conecte — la diferencia en el puesto de embalaje es real.

## Transportistas preferidos: enseñar a aiku dónde es mejor cada uno

La mayoría de los almacenes no quiere que los packers decidan el transportista paquete a paquete. En el **Settings** de la organización, dentro de **Preferred Shipping**, puedes escribir reglas sencillas: *para este país — o este país y estos códigos postales — usa este transportista*. Una regla puede aplicarse a todos tus shops o acotarse a algunos de ellos.

Cada regla puede ser suave o firme:

- Una regla normal convierte al transportista en **sugerencia**: la pantalla de despacho lo preselecciona, pero el equipo puede elegir otro.
- Una regla marcada **important** **bloquea** el transportista para esos destinos. La pantalla de despacho no deja que un packer elija otra cosa sin más — saltarse un bloqueo requiere un supervisor de dispatching o un administrador de la organización, y hasta ellos reciben antes un aviso, porque enviar un paquete con el transportista equivocado puede significar que al cliente se le cobró el precio de envío incorrecto.

Solo cuentan los transportistas activos: una regla que apunta a un transportista que ya has retirado simplemente deja de aplicarse.

## Cómo se hace la elección en el despacho

Cuando un albarán llega al paso de envío, aiku calcula su sugerencia en este orden:

1. **La elección del cliente va primero.** Si el pedido lleva un transportista que el cliente fijó, ese transportista queda bloqueado también en el albarán.
2. Si no, y el pedido ya tiene un transportista, o su zona de envío solo usa nunca un transportista, se sugiere ese.
3. Si no, se comprueban tus reglas de **Preferred Shipping** contra el país y el código postal de entrega — la sugerencia aparece preseleccionada, bloqueada si la regla estaba marcada como important.

Si no coincide nada, el equipo elige de la lista de transportistas como siempre. En cualquier caso, un transportista conectado imprime su propia etiqueta, y uno manual pide el número de seguimiento.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Ver o añadir transportistas:</b> tu almacén → <b>Dispatching → Shippers</b> → <b>Create Shipper</b>. Los transportistas retirados están en la pestaña <b>Inactive</b>.</li>
<li><b>Fijar transportistas preferidos:</b> tu organización → <b>Settings</b> → <b>Preferred Shipping</b> → añade reglas por país y código postal; marca <b>important</b> para bloquear uno.</li>
<li><b>Usarlos:</b> en el paso de envío del albarán el transportista sugerido aparece preseleccionado — confírmalo, y los transportistas conectados imprimen la etiqueta por sí solos.</li>
</ul>
</aside>
