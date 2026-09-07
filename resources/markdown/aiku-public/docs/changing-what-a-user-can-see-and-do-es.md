---
title: Cambiar lo que un usuario puede ver y hacer
summary: En aiku los permisos son puestos de trabajo. Dónde encontrar los puestos de un usuario, cómo añadir o quitar uno, qué significa cada grado y por qué algunos accesos vienen incluidos con otro departamento.
date: 2026-09-02
source_date: 2026-09-02
tags: hr, sysadmin, permissions
category: hr
---

<aside class="tldr">
En aiku a nadie se le da un permiso directamente. A las personas se les dan <em>puestos de trabajo</em> (Accounting worker, Warehouse supervisor, Organisation administrator...) y cada puesto lleva un paquete fijo de permisos. Para cambiar lo que alguien puede ver, cambias sus puestos. Hay dos puertas a la misma pantalla: <b>Sysadmin → Users</b> para cualquier usuario del grupo, y <b>HR → Employees</b> para un empleado de tu organización. Los menús se reconstruyen la próxima vez que la persona carga una página.
</aside>

## Dos puertas, una pantalla

**Desde Sysadmin.** Abre **Sysadmin → Users**, haz clic en el nombre de usuario, pulsa **Edit** y abre la pestaña **Permissions**. Puedes abrirla para cualquier usuario del grupo, sea cual sea la organización en la que trabaje. Para llegar aquí necesitas ver el menú Sysadmin.

**Desde HR.** Abre tu organización, ve a **HR → Employees**, haz clic en la persona, pulsa **Edit** y baja hasta **Job Positions (permissions)**. Es el mismo control, limitado a la organización en la que estás.

En ambos casos verás una lista de departamentos con un grado a elegir en cada uno y un icono de **guardar**. Nada cambia hasta que pulsas guardar.

## Leer la pantalla de permisos

La pantalla tiene dos partes.

**Group permissions** está arriba. Se aplican en todas partes, en todas las organizaciones:

- **Group admin** — ve y puede hacer todo, en todas las organizaciones. Al elegirlo se desactivan las demás opciones, porque no queda nada por conceder.
- **Group sysadmin** — cuentas de usuario, el menú Sysadmin, ajustes del sistema.
- **Group webmaster** — sitios web y contenido web en todo el grupo.
- **Supply Chain**, **Goods**, **Masters** — el catálogo compartido y las compras que están por encima de cada organización. Masters tiene cuatro grados: Manager, Clerk, Media y Viewer.

**Organisations** aparecen debajo con el número de puestos que la persona tiene en cada una. Haz clic en el nombre de una organización para desplegarla y verás sus departamentos, una fila por departamento:

| Departamento | Grados disponibles |
|---|---|
| Org admin | Organisation Administrator — todo en esta organización |
| Human Resources | Supervisor, Worker |
| Accounting | Supervisor, Worker |
| Shop admin | Shop Administrator — todo en las tiendas elegidas |
| Shopkeeping | Supervisor, Worker |
| Marketing | Supervisor, Worker |
| PPC | PPC |
| Customer Service | Supervisor, Worker, Viewer |
| Buyer | Buyer |
| Warehouse | Supervisor, Stock Controller |
| Goods out | Supervisor, Picker, Replenisher, Packer |
| Production | Supervisor, Worker |
| Fulfilment | Supervisor, Warehouse Clerk, Office Clerk |

Los departamentos que pertenecen a una tienda, almacén o fulfilment (Shopkeeping, Marketing, Customer Service, Warehouse, Goods out, Fulfilment...) te preguntan *qué* tiendas o almacenes cubre el puesto. Pulsa **Show details** en la fila para marcarlos (el botón solo aparece cuando la organización tiene más de uno para elegir). Una persona puede ser Customer Service worker en una tienda y nada en otra.

**Supervisor frente a Worker.** Un worker puede ver y editar los registros del día a día del departamento. Un supervisor tiene lo mismo más las pantallas de gestión y los ajustes del departamento. Elige uno u otro; al elegir el grado supervisor se sustituye el grado worker en ese departamento.

**Organisation Administrator** marca todos los departamentos de esa organización a la vez, así que si lo eliges, las demás filas de esa organización dejan de importar.

## Hacer un cambio

1. Abre los permisos del usuario por cualquiera de las dos puertas.
2. Despliega la organización.
3. Haz clic en el grado que quieras en la fila del departamento. Hacer clic en un grado ya seleccionado lo quita, así que un departamento sin nada seleccionado significa sin acceso a él.
4. En los departamentos por tienda o almacén, pulsa **Show details** y marca las tiendas o almacenes.
5. Pulsa el icono de **guardar** de esa organización. Los Group permissions tienen su propio icono de guardar arriba.

La persona no tiene que cerrar sesión. Sus menús se reconstruyen la próxima vez que carga una página.

## Accesos que vienen incluidos con otro departamento

Algunos puestos incluyen ver, solo lectura, un departamento vecino, porque el trabajo lo necesita. El caso por el que más se pregunta: **Accounting worker y Accounting supervisor pueden ver Human Resources**, solo lectura. Por eso aparece el menú HR a personal de finanzas al que nunca se le dio un puesto de HR. Forma parte del propio puesto de Accounting y no se puede desactivar por persona. Quitarlo significa cambiar lo que contiene el puesto de Accounting, para todos, lo cual es un cambio en aiku y no un ajuste.

El acceso de solo lectura a Human Resources por sí solo no es un puesto que se pueda elegir en la pantalla. Si alguien solo debe *ver* HR, la opción hoy es Accounting worker o nada.

## Comprobar quién tiene acceso a algo

Para saber quién puede ver un departamento en una organización, lo más rápido es **HR → Employees**, donde se listan los puestos de cada empleado, y **Sysadmin → Users** para cuentas que no son empleados de tu organización. Recuerda que cualquiera con **Group admin** u **Organisation Administrator** tiene acceso sin que aparezca un puesto de departamento.

<aside class="wayfinder">
<h3>Dónde hacer clic en aiku</h3>
<ul>
<li><b>Sysadmin → Users → </b><i>usuario</i><b> → Edit → Permissions</b> — cualquier usuario del grupo.</li>
<li><i>Organización</i><b> → HR → Employees → </b><i>nombre</i><b> → Edit → Job Positions (permissions)</b> — empleados de tu organización.</li>
<li>En cada bloque de organización: haz clic en el grado, <b>Show details</b> para elegir tiendas o almacenes, y luego el icono de <b>guardar</b> de esa organización.</li>
</ul>
</aside>

<aside class="wayfinder">
<h3>Permisos que necesitas</h3>
<ul>
<li>La puerta Sysadmin necesita el puesto <b>Group sysadmin</b> (o Group admin).</li>
<li>La puerta HR necesita un puesto de <b>Human Resources</b>, Worker o Supervisor, en esa organización (u Organisation Administrator).</li>
</ul>
</aside>
