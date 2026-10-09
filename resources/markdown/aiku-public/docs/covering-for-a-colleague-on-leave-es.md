---
title: Cubrir a un compañero de baja
summary: Cuando alguien está ausente, RR. HH. elige a un compañero que lo cubra. Durante los días de la ausencia, quien cubre recibe los permisos de la persona ausente, se une a sus tareas y tickets abiertos, y ve en un solo lugar todo lo que dejó pendiente. Los permisos se retiran solos cuando termina la ausencia.
date: 2026-10-09
source_date: 2026-10-09
tags: hr, tasks
category: hr
---

<aside class="tldr">
Cuando alguien está de baja o ausente, RR. HH. puede elegir a un compañero para que lo <b>cubra</b> (cover). Durante los días de la ausencia, quien cubre puede hacer todo lo que permiten los puestos de trabajo de la persona ausente, se añade a sus tareas y tickets abiertos, y encuentra todo lo que dejó pendiente en <b>Tasks → Covering</b>. Nadie tiene que acordarse de retirar los permisos: se van cuando termina la ausencia.
</aside>

## Elegir a quien cubre

Hay dos sitios donde elegir a quien cubre:

- En <b>Leave Requests</b> (solicitudes de ausencia), pulsa <b>Record leave</b>, elige el <b>Employee</b> y luego a un compañero en <b>Covered by</b>.
- En <b>HR → Absence cover</b>, pulsa <b>Assign cover</b> junto a la ausencia, o <b>Change</b> para elegir a otra persona.

Solo se puede cubrir una ausencia aprobada. Quien cubre debe ser un compañero que trabaje en la misma organización, y una ausencia tiene una sola persona que la cubre a la vez. Si cambias el empleado en <b>Record leave</b>, <b>Covered by</b> se vacía, así que tienes que volver a elegir para la nueva persona.

## A quién elegir

Algunos nombres de la lista <b>Covered by</b> llevan una etiqueta. Pasa el ratón por encima de un nombre para ver el motivo.

- <b>Highly recommended</b> (muy recomendado): tiene el mismo rol (puesto de trabajo) que la persona ausente.
- <b>Recommended</b> (recomendado): un rol distinto, pero del mismo departamento.
- <b>Not recommended</b> (no recomendado): la persona ausente puede ver partes de aiku de ámbito de grupo (más de una organización, o secciones como Goods, Masters, Supply chain o Sysadmin) y este compañero no.
- Sin etiqueta: no tiene nada en común con la persona ausente. Aun así puedes elegirlo.

Los nombres Highly recommended salen primero y los Not recommended, al final.

¿Por qué "Not recommended"? Quien cubre toma prestado lo que viene con los <b>puestos de trabajo</b> de la persona ausente. El acceso concedido directamente a su cuenta de usuario, como el acceso de ámbito de grupo, no se presta. Un compañero que no tenga ya ese acceso no puede llegar a esa parte del trabajo.

## Qué recibe quien cubre

### Una notificación

En cuanto se le elige, se le avisa: <b>You are covering for Jane Smith</b>, con las fechas de la ausencia y "You have their permissions during this period" (tienes sus permisos durante este periodo).

### Los permisos de la persona ausente

Desde el primer día de la ausencia hasta el último, quien cubre puede hacer todo lo que permiten los puestos de trabajo de la persona ausente, además de sus propios permisos. No hay nada que marcar: basta con elegir a quien cubre. Cómo dan permisos los puestos de trabajo se explica en <a href="/docs/changing-what-a-user-can-see-and-do-es">Cambiar lo que un usuario puede ver y hacer</a>.

### Sus tareas y tickets abiertos

Mientras dura la ausencia, quien cubre se añade como colaborador en las tareas y tickets abiertos de la persona ausente. Esto incluye los que se asignen a la persona ausente durante la ausencia. Quien cubre sigue en ellos después de la ausencia, porque puede haber trabajado en ellos; quítalo a mano si ya no hace falta.

### Un solo lugar para ver el trabajo

<b>Tasks → Covering</b> lista a todas las personas a las que cubres y lo que dejaron abierto: <b>Tasks</b>, <b>Tickets</b>, <b>Chats</b> y <b>Picking and packing</b>. Esto último son los albaranes que estaban preparando o empaquetando; consulta <a href="/docs/picking-and-packing-a-delivery-note-es">Preparar y empaquetar un albarán</a>.

El panel <b>Covering for</b> de la barra lateral derecha muestra a las mismas personas, con cuántos elementos quedan por atender y si la cobertura es <b>now</b> (ahora) o <b>upcoming</b> (próxima). Un icono de llave significa que tienes sus permisos.

En <b>HR → Employees</b>, la columna <b>Covered by</b> muestra quién cubre a cada persona que está ausente hoy.

## La página Absence cover

<b>HR → Absence cover</b> lista todas las ausencias aprobadas que están en curso o próximas, tengan cobertura o no. También lista las ausencias que han terminado y tuvieron cobertura, para que puedas ver quién cubrió a quién. Las ausencias más recientes salen primero.

Las ausencias terminadas muestran <b>Ended</b> y ya no se pueden modificar. <b>End cover</b> detiene una cobertura antes de que termine la ausencia, y los permisos prestados se retiran al instante.

## Cuándo empieza y cuándo termina

- **Cobertura elegida cuando la ausencia ya está en curso:** los permisos y las tareas y tickets llegan al instante.
- **Cobertura elegida antes de que empiece la ausencia:** llegan el primer día de la ausencia.
- **Después del último día:** los permisos se retiran.
- **Ausencia editada o eliminada, o cobertura cambiada o terminada:** el cambio se aplica al instante.

aiku comprueba cada hora las ausencias que empiezan y terminan, así que un inicio o un fin se aplica dentro de la hora siguiente a medianoche. Esa medianoche es UTC, no tu hora local. En Malasia, por ejemplo, quien cubre obtiene acceso hacia las 8 o 9 de la mañana del primer día de la ausencia, y lo conserva hasta más o menos la misma hora del día siguiente al final de la ausencia.

## Conviene saber

- Una ausencia tiene una sola persona que la cubre a la vez.
- Si terminas una cobertura antes de tiempo, esa ausencia no aparecerá en la lista cuando pase la ausencia.
- Quien cubre sigue en las tareas y tickets de la persona ausente después de la ausencia.
