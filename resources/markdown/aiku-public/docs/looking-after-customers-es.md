---
title: Cuidar de los clientes
summary: Encuentra tu camino por la lista de clientes de una tienda, añade un cliente nuevo, lee la página de un cliente y entiende los estados, logins y prospectos que la rodean.
date: 2026-09-01
source_date: 2026-09-01
tags: crm, customers
category: crm
---

<aside class="tldr">
Cada tienda tiene su propia lista de <b>Customers</b>, a la que se llega desde la sección <b>CRM</b> de la tienda. Desde ahí puedes crear un cliente, abrir la página de un cliente para ver todo sobre él, gestionar los logins que usa en la web y — por separado — mantener una lista de <b>Prospects</b> que todavía no se han convertido en clientes.
</aside>

## La lista de clientes

Abre una tienda y ve a **CRM → Customers**. La lista muestra todos los clientes de esa tienda, con columnas para su **Ref**, **Name**, la fecha en que se añadieron (**Since**), la fecha de su **Last Invoice**, el número de **Invoices** y **Sales**. Puedes buscar en la lista y ordenar por cualquiera de estas columnas.

Puedes filtrar la lista con un cuadro de búsqueda global (busca por nombres, y por códigos postales si escribes uno), por **Tag**, por **Country**, y por si un cliente ha hecho alguna vez un pedido o no.

## Añadir un cliente

Pulsa **Create Customer** en la lista. El formulario es breve y va en una sola sección, **Contact**:

- **Company** — el nombre de la empresa del cliente, si tiene una.
- **Contact name** — obligatorio. La persona con la que tratas.
- **Email**
- **Phone**
- **Address** — un formulario de dirección completo, precargado con el país de la propia tienda.
- **Tax number**

Al guardar el formulario se crea el cliente y te lleva a su página.

## La página del cliente

Al abrir un cliente desde la lista llegas a su página, organizada en pestañas:

- **Overview** — el resumen destacado del cliente.
- **Timeline** — un historial de actividad.
- **Journey** — el recorrido del cliente por la tienda.
- **History** — un registro de auditoría de cambios.
- **Attachments** — archivos adjuntos al cliente.
- **Payments**
- **Credit transactions**
- **Favourites** — productos que el cliente ha marcado como favoritos.
- **Reminders**
- **Dispatched emails** — correos que aiku le ha enviado.
- **Offers**

Desde aquí también puedes llegar a los pedidos, facturas, albaranes, devoluciones, reemplazos y próximas transacciones del cliente — cada uno tiene su propia pantalla enlazada desde la página del cliente.

## Estados y status del cliente

Cada cliente lleva dos indicadores separados, y ambos se muestran como etiquetas de color.

El **state** (estado) sigue dónde está el cliente en su relación con la tienda:

- **In Process** — todavía en configuración.
- **Registered** — tiene cuenta pero aún no se ha convertido en habitual.
- **Active** — comprando actualmente.
- **Potential Comebacks** — compraba, ha dejado de hacerlo, pero podría volver.
- **Dormant** — lleva mucho tiempo sin comprar.

El **status** sigue la aprobación:

- **Pre Registration**
- **Pending Approval**
- **Approved**
- **Rejected**
- **Banned**

Normalmente un cliente necesita estar **Approved** antes de poder operar con normalidad — esta es una decisión separada de su state.

## Web users: los logins del cliente en la web

Un cliente puede tener más de un login en la web de la tienda — útil cuando varias personas de la misma empresa necesitan su propia cuenta. Estos se gestionan desde la pantalla **Web Users** del cliente.

Cada web user tiene:

- **Type** — Customer o API user.
- **Username** — debe ser único en la web de esa tienda.
- **Admin** — un interruptor que marca este login como login de administrador para el cliente.
- **Password**

Pulsa **Create Web User** desde la lista para añadir uno; al abrir un web user se muestra su username como título de la página y te deja editar sus datos.

## Direcciones

Un cliente mantiene una dirección de contacto (usada para correspondencia y a efectos fiscales) y puede tener direcciones de entrega usadas en sus pedidos, ambas capturadas como formularios de dirección completos — país, código postal y el resto — en el propio registro del cliente y al crear o editar el cliente.

## Prospects: personas que todavía no son clientes

Los prospectos son una lista separada de los clientes, guardada en **CRM → Prospects** en la misma tienda. Un prospecto avanza por sus propios estados a medida que trabajas con él:

- **No contacted**
- **Contacted**
- **Fail**
- **Success**

Los prospectos tienen su propio botón **Create Prospect**, su propia exportación y sus propios mailshots para contactar con ellos en bloque. Cuando un prospecto se convierte en cliente real, aiku los empareja en lugar de dejar dos registros separados.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Ver o añadir clientes:</b> tu tienda → <b>CRM → Customers</b> → <b>Create Customer</b>.</li>
<li><b>Abrir un cliente:</b> pulsa su fila para ver las pestañas Overview, Timeline, Journey, History, Attachments, Payments, Credit transactions, Favourites, Reminders, Dispatched emails y Offers.</li>
<li><b>Gestionar sus logins de la web:</b> en la página del cliente, abre <b>Web Users</b> → <b>Create Web User</b>.</li>
<li><b>Trabajar prospectos:</b> tu tienda → <b>CRM → Prospects</b> → <b>Create Prospect</b>.</li>
</ul>
</aside>

<aside class="permissions">
<strong>Permisos que necesitas</strong>
Para ver clientes necesitas acceso de visualización de CRM para esa tienda; para crear o editar un cliente, su dirección o un web user, necesitas acceso de edición de CRM para esa tienda. Los prospectos usan sus propios permisos de visualización y edición, separados de los de los clientes.
</aside>
