---
title: Gestionar clientes
summary: Añade a los compradores a los que envías como clientes de un canal Manual/API, uno a uno o desde una hoja de cálculo, y edítalos o desactívalos más tarde.
date: 2026-09-25
source_date: 2026-09-25
tags: manual, api, clientes, dirección de entrega, importar
category: orders
series: manual
order: 3
shops: awd, dssk, dse
---

<aside class="tldr">
Un cliente es la persona a la que vendes: enviamos el paquete a la dirección del cliente. En un canal Manual/API, abre <b>Clientes</b> y pulsa <b>Crear Cliente</b> para añadir uno, o <b>Upload File</b> para añadir varios desde una hoja de cálculo. Todos los pedidos de un canal Manual/API se crean desde una página de cliente.
</aside>

## Dónde viven los clientes

Los clientes pertenecen a un canal. Abre tu canal Manual/API en el menú y luego <b>Clientes</b>, o pulsa <b>View all</b> en el recuadro <b>Clientes</b> de la página del canal.

La lista muestra <b>Nombre</b>, <b>Correo electrónico</b>, <b>teléfono</b>, <b>ubicación</b> y <b>desde</b> (cuándo los añadiste). Tiene dos pestañas:

- <b>Activo</b>: tus clientes actuales.
- <b>Inactivo</b>: clientes que desactivaste.

Solo los canales Manual/API tienen una página <b>Clientes</b>. En los canales conectados, los datos del comprador llegan con cada pedido de tu tienda.

## Añadir un cliente

1. En la página <b>Clientes</b> pulsa <b>Crear Cliente</b>.
2. Se abre el formulario <b>Nuevo cliente</b>. Rellena:
   - <b>Compañía</b>: si tu comprador es una empresa.
   - <b>Nombre de contacto</b>: el nombre para la etiqueta de envío.
   - <b>Correo electrónico</b>
   - <b>teléfono</b>: al menos 6 caracteres si lo rellenas.
   - <b>Dirección</b>: la dirección de entrega. El país empieza siendo el país de nuestra tienda. Cámbialo si tu comprador vive en otro sitio.
3. Pulsa <b>Guardar</b>.

<!-- screenshot: el formulario New client con Company, Contact name, Email, phone y Address -->

Se abre la página del cliente. Desde aquí puedes pulsar <b>Crear orden</b>. Consulta [Poner pedidos manualmente](/docs/placing-orders-manually).

La dirección debe estar completa para el país que elijas. El formulario te dice qué falta, por ejemplo <b>The address is required</b>, <b>The town is required</b>, <b>The postal code is required</b> o <b>The province is required</b>. Algunos países no tienen código postal o localidad, y entonces el formulario no los pide.

## Añadir varios clientes desde una hoja de cálculo

1. En la página <b>Clientes</b> pulsa <b>Upload File</b>.
2. En la ventana <b>Import your clients</b>, descarga la plantilla.
3. Rellena un cliente por fila, con estas columnas: contact_name, company_name, email, phone, address_line_1, address_line_2, postal_code, locality, country_code. Todas las columnas menos address_line_2 deben estar rellenas, y email debe ser una dirección de correo válida.
4. Para country_code usa el código de país de dos letras, por ejemplo GB, ES, DE o FR.
5. Sube el archivo.

## Cambiar un cliente

Abre el cliente y pulsa <b>Editar</b>. La página <b>Editar cliente</b> te deja cambiar <b>Compañía</b>, <b>Nombre de contacto</b>, <b>Correo electrónico</b>, <b>teléfono</b> y <b>Dirección de entrega</b>.

Una dirección nueva se usa para los pedidos nuevos. Para un pedido que todavía está en la cesta, también puedes cambiar la dirección de entrega en la página de la cesta con <b>Editar</b> bajo la dirección.

## Desactivar un cliente

En la página <b>Editar cliente</b>, desactiva <b>estado</b>. El cliente se mueve a la pestaña <b>Inactivo</b>. Sus pedidos pasados se conservan. Vuelve a activar <b>estado</b> para usarlos de nuevo.

## Clientes a través de la API

Tu propio sistema puede listar, crear, cambiar y desactivar clientes mediante la API, y crear pedidos para ellos. Consulta [El canal Manual/API](/docs/manual-and-api-channel).

## Cuando algo va mal

- <b>No encuentro Create Customer Client.</b> El botón solo está en canales Manual/API, y solo mientras el canal está abierto. Los demás canales no tienen página <b>Clientes</b>.
- <b>El formulario dice que falta la localidad, el código postal o la provincia.</b> La dirección no está completa para ese país. Rellena el campo que indica. Comprueba que el país es correcto.
- <b>El formulario dice que el correo no es válido.</b> Comprueba si hay espacios o falta una @ o un punto. También puedes dejar el correo vacío.
- <b>Mi cliente no está en la lista.</b> Mira en la pestaña <b>Inactivo</b>. Comprueba también que estás en el canal correcto: los clientes de un canal no aparecen en otro.
- <b>La subida de mi hoja de cálculo falló en algunas filas.</b> Comprueba que cada columna obligatoria está rellena (solo address_line_2 puede estar vacía), el correo es válido, el country_code es un código de dos letras y la dirección tiene los campos que necesita el país.
