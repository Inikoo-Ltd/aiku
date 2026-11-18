---
title: Planificar el día de producción
summary: Para el responsable de producción y el supervisor de planta - poner trabajos en el plan de un día sin nombrar a nadie, dar cada paso a un operario, y qué pasa con el trabajo que no se terminó en su día.
date: 2026-10-15
source_date: 2026-10-15
tags: production, planning, floor
category: production
series: Ordering from partners
order: 13
---

<aside class="tldr">
Dos personas, dos tareas. El <b>responsable</b> decide qué se fabrica un día: en el tablero <b>To produce</b> (Por producir) elige las tarjetas y pulsa <b>Plan for this day</b> (Planificar para este día), sin nombrar a nadie. El <b>supervisor</b> abre <b>Operations → Day plan</b> (Operaciones → Plan del día) y da cada paso de cada trabajo planificado a un operario. Cada operario ve entonces sus propios pasos en la pantalla de planta. Lo que no se termine en su día sigue en el plan, en ámbar, hasta que alguien lo confirme para un día nuevo.
</aside>

## El responsable planifica el día

En **Factory → To produce** (Fábrica → Por producir), el tablero funciona como siempre: las tarjetas van de **Backlog** (Pendientes) a **Preparing** (En preparación), donde se fija la cantidad, y de **Preparing** a **Assigned** (Asignados), donde se crea la orden de trabajo.

Cuando se suelta una tarjeta en **Assigned**, la ventana que pregunta *¿Quién lo hace?* empieza ahora con una fecha y un botón:

1. Elige el día. Se abre en hoy.
2. Pulsa **Plan for this day**.

Se crea una orden de trabajo para ese día sin nadie nombrado. Cada tarjeta se convierte en su propia orden de trabajo, de modo que cada una se puede ordenar y mover por separado. Varias tarjetas seleccionadas y soltadas juntas se planifican juntas, una orden de trabajo cada una.

Elegir un nombre de la lista de abajo sigue funcionando como siempre: el trabajo entero va a esa persona y no está en el plan de ningún día.

Un trabajo que ya tiene a alguien nombrado también se puede poner en un plan: pulsa el lápiz junto al nombre en su tarjeta y luego **Plan for this day**. Se quita el nombre del trabajo y el supervisor da sus pasos.

## El supervisor da los pasos

**Factory → Operations → Day plan** lista los trabajos planificados para el día, una fila por producto de cada trabajo.

| Columna | Significado |
| --- | --- |
| Order (Orden) | el lugar del trabajo en el día - escribe un número para moverlo |
| Job (Trabajo) | la orden de trabajo y su estado |
| Product (Producto) | lo que se fabrica |
| Qty (Cant.) | cuántos |
| Steps and operators (Pasos y operarios) | cada paso de la receta: hecho hasta ahora / pedido, y a quién se le da |
| Planned (Planificado) | el día para el que se planificó |

Cada paso tiene su propia lista de nombres. Elige uno y ese paso aparece en la pantalla de planta de esa persona, y en la de nadie más. Un paso con borde ámbar aún no tiene a nadie; la barra de arriba los cuenta, *3 pasos sin operario*, para que no se quede nada olvidado por error.

Para cambiar quién hace un paso durante el día, elige otro nombre. Para quitarle un paso a alguien, pulsa la crucecita de la lista. Si la persona ya había empezado, el trabajo que hizo queda registrado a su nombre: la lista solo decide quién ve el paso a partir de ahora.

La fecha de arriba cambia a otro día. El plan de mañana se puede repartir hoy.

## Seguir el día

La misma página muestra cómo va el día y se actualiza sola:

- la barra de arriba: cuántos trabajos, *12 de 18 pasos hechos*, pasos sin operario, trabajos no terminados en su día;
- junto a cada paso: un círculo gris (sin empezar), un indicador ámbar girando (empezado) o una marca verde (hecho), con la cuenta hecha hasta ahora;
- *trabajando ahora* y el nombre, cuando alguien tiene el paso abierto en la planta.

Quien puede ver la página pero no dirige la planta ve los nombres, no las listas.

## Cuando el supervisor no está

El responsable abre la misma página y da los pasos él mismo. Planificar y dar pasos requieren el mismo puesto.

## Lo que no se terminó

Un trabajo planificado con pasos aún abiertos después de su día no se descarta. Desde el día siguiente aparece en el Day plan con su fecha antigua en ámbar, y la barra lo cuenta como *no terminado en su día*. Para cada uno, elige:

- **Confirm for** (Confirmar para) el día que estás mirando - el trabajo pasa al plan de ese día, al final del orden;
- **Take off the plan** (Quitar del plan) - el trabajo sigue como orden de trabajo pero no está en el plan de ningún día.

Hasta que se pulse una de las dos, el trabajo sigue delante de ti cada día. Sus pasos también siguen en las pantallas de planta de los operarios a quienes se dieron, así que quien pausó una mezcla ayer puede seguir esta mañana.

Cuando un operario cierra un trabajo corto y pasa el resto a uno nuevo (ver [Trabajar la pantalla de planta](/docs/working-the-floor-screen-es)), el trabajo nuevo conserva el día planificado, el lugar en el orden y el operario de cada paso.

## Pedir materiales al almacén

Siempre que la fábrica necesita algo del almacén, cualquiera que trabaje en producción lo pide arriba en **Factory → Operations → Materials check** (Fábrica → Operaciones → Revisión de materiales), bajo **Ask the warehouse** (Pedir al almacén). Cada línea es:

| Campo | Qué poner |
| --- | --- |
| SKO | escribe el código o el nombre; la lista muestra cuántos SKO tienen el almacén y la fábrica |
| Quantity and unit (Cantidad y unidad) | como se lo dirías al almacén: *3 Boxes*, *1 Pallet*, *80 Bags*, *2 SKOs* o *All there is* |
| Drop point (Punto de entrega) | dónde dejarlo en la fábrica (*Storage room*, *Clean room*, *Bath bomb area*...) |
| Urgent (Urgente) | el almacén ve primero las líneas urgentes |
| Note (Nota) | cualquier cosa que el almacén deba saber (*250ml*, *fuera en palés, por favor*) |

**Add line** (Añadir línea) pide varias cosas a la vez, y **Send to warehouse** (Enviar al almacén) las envía. **Asked this week** (Pedido esta semana), debajo, muestra cada petición con quién la hizo y cuándo, y de cada línea si está *To bring* (Por traer), *Delivered* (Entregado; cuántos SKO, por quién, cuándo) o *Can't bring* (No se puede traer) con el motivo del almacén.

El responsable y el supervisor mantienen la lista de puntos de entrega con el botón **Drop points** (Puntos de entrega): añadir, renombrar, subir y bajar o borrar. Un punto de entrega borrado conserva su nombre en las peticiones ya enviadas allí.

## La revisión de materiales de la mañana

El almacén lleva los materiales a la fábrica en cajas enteras, no en las cantidades exactas del día. **Factory → Operations → Materials check** (Fábrica → Operaciones → Revisión de materiales) lista lo que el plan del día aún necesita, para que la fábrica compruebe lo que hay y pida al almacén el resto en una sola lista clara.

Cada línea es un material, sumado entre todos los trabajos del día, con los trabajos que lo necesitan (*JOaroma-0141, JOaroma-0145*). Solo cuenta los pasos aún sin hacer: cuando el llenado está terminado y solo falta el embalaje, la línea muestra las cajas y las etiquetas y ya no los frascos. Las mezclas se hacen en la fábrica y no aparecen en la lista. Las cantidades salen de las recetas, así que un trabajo cuya receta no lista materiales no necesita nada: la barra ámbar sobre la lista nombra esos trabajos, para que se vea el hueco en la receta.

| Columna | Significado |
| --- | --- |
| Material | el material y el código de stock del almacén |
| For jobs (Para trabajos) | los trabajos del día que lo necesitan |
| Needed (Necesario) | cuántos gastan los pasos que faltan por hacer |
| At the factory (En la fábrica) | cuántos hay ahora en la ubicación de la fábrica, y cuántos se pidieron ya hoy |
| Check (Revisión) | marca **Have enough** (Hay suficiente), o escribe aproximadamente cuántos faltan |

Al escribir un número que falta, la línea muestra lo que traerá el almacén, redondeado a sus cajas: faltan 1.200 con 500 por caja es *traer 3 cajas (1.500)*. Un *Warehouse short* (Almacén sin existencias) en rojo significa que el almacén no tiene tantas, así que lo sabes al instante.

Pulsa **Send to warehouse** (Enviar al almacén). Las líneas que faltan van al almacén como una sola petición, y la petición aparece bajo **Asked this week** con cada material marcado *To bring* (Por traer), *Delivered* (Entregado) o *Can't bring*. Marcar todo como suficiente y enviar deja registrado que la revisión se hizo. Una segunda revisión más tarde en el día solo añade lo que aún falta.

El responsable o el supervisor hace la revisión, o se la da a un operario con **Checked by** (Revisado por) arriba. Ese operario ve entonces en la pantalla de planta un recordatorio que abre la revisión.

## El almacén trae los materiales

En el almacén, la tarjeta **For production** (Para producción) bajo **Action needed** (Requiere acción) en **Inventory** (Inventario) muestra cuántas líneas espera la fábrica, con las urgentes en la insignia pequeña. Abre **Inventory → For production**: cada línea que falta por traer, primero las urgentes y luego las más antiguas, con lo que se pidió, el punto de entrega, quién lo pidió y cuándo, la nota y la estantería de la que cogerlo. El preparador elige la estantería, escribe los SKO traídos y pulsa **Delivered**. Cuando el almacén no puede traerlo, **Can't bring** pregunta el motivo (*out of stock*, *ordered from supplier*) y avisa a la fábrica. **Done** (Hecho) lista lo que se trajo o se rechazó, con cuánto se tardó.

Si Aiku muestra menos SKO en la estantería de los que se trajeron, la entrega se hace igualmente: lo que Aiku tenía pasa a la fábrica y el resto se marca como *not in Aiku, count it* (no está en Aiku, contarlo), para que se cuente el SKO.

El stock entregado pasa de la estantería a la ubicación de la fábrica. Sigue siendo stock: se cuenta y se ve como antes, y un pedido de cliente aún puede prepararlo allí cuando las estanterías del almacén están vacías, siempre primero las estanterías. Los materiales sin usar se pueden devolver a una estantería desde la página de la ubicación de la fábrica, como cualquier otro movimiento entre ubicaciones.

Cuando se recibe en stock un trabajo terminado, sus materiales se gastan primero de la ubicación de la fábrica, con las cantidades de la receta, y solo después de otras ubicaciones. Cuenta la ubicación de la fábrica de vez en cuando para corregir roturas y mermas.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Planificar un trabajo para un día:</b> <b>Factory</b> → <b>To produce</b> → suelta la tarjeta en <b>Assigned</b> → elige el día → <b>Plan for this day</b>.</li>
<li><b>Dar un paso a un operario:</b> <b>Factory</b> → <b>Operations</b> → <b>Day plan</b> → la lista junto al paso.</li>
<li><b>Cambiar el orden del día:</b> escribe un número en <b>Order</b>.</li>
<li><b>Trabajo sin terminar de ayer:</b> <b>Confirm for</b> el día, o <b>Take off the plan</b>.</li>
<li><b>Del tablero al plan:</b> el enlace <b>Day plan</b> sobre el tablero.</li>
<li><b>Pedir algo al almacén:</b> <b>Factory</b> → <b>Operations</b> → <b>Materials check</b> → <b>Ask the warehouse</b> → <b>Send to warehouse</b>.</li>
<li><b>Cambiar los puntos de entrega:</b> <b>Materials check</b> → <b>Drop points</b>.</li>
<li><b>Revisar los materiales del día:</b> <b>Factory</b> → <b>Operations</b> → <b>Materials check</b> → <b>Have enough</b> o un número que falta → <b>Send to warehouse</b>.</li>
<li><b>Dar la revisión a un operario:</b> <b>Materials check</b> → <b>Checked by</b>.</li>
<li><b>Traer los materiales (almacén):</b> <b>Inventory</b> → <b>For production</b> → <b>Delivered</b>, o <b>Can't bring</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Los puestos se asignan en la ficha del empleado en Human Resources y llevan los permisos consigo.</li>
<li>Planificar un día, dar pasos, cambiar el orden: el puesto <b>Floor supervisor</b> para la fábrica.</li>
<li>Ver el Day plan sin cambiarlo: <b>Foreman</b>.</li>
<li>Trabajar un paso que te han dado: <b>Operative</b>, en la pantalla de planta.</li>
<li>Pedir materiales al almacén: cualquiera que vea Operations de la fábrica.</li>
<li>La revisión de materiales y los puntos de entrega: <b>Floor supervisor</b>; la revisión también el operario a quien se le dio.</li>
<li>Traer materiales a la fábrica: un puesto de almacén que trabaje en el almacén.</li>
</ul>
</aside>
