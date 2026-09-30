---
title: Cambiar muchos artefactos a la vez
summary: Marca los artefactos y usa el selector a la derecha de la barra que aparece para fijar un tamaño de lote o una vida útil, darles a todos los mismos pasos de fabricación, moverlos a otra familia o departamento, discontinuarlos, o recuperarlos.
date: 2026-09-29
source_date: 2026-09-29
tags: production, crafts
category: production
---

<aside class="tldr">
Para quien lleva el catálogo de la fábrica. Todas las listas de artefactos tienen casillas de marcar. Marca algunas filas y aparece una barra encima de la tabla con un selector a su derecha. El selector ofrece las tareas: <b>Batch size</b> (tamaño de lote), <b>Shelf life</b> (vida útil), <b>Manufacture task</b> (tarea de fabricación), <b>Move to family</b> (mover a familia), <b>Move to department</b> (mover a departamento) y <b>Discontinue</b> (discontinuar), además de <b>Make active</b> (activar) para deshacer la última. Editar los artefactos uno a uno sigue funcionando; esto es el mismo cambio hecho a cincuenta filas a la vez.
</aside>

## Cómo encontrarla

No hay nada que activar ni ningún botón que la abra. La barra está oculta hasta que marcas algo, por eso la mayoría nunca la descubre.

Marca la casilla a la izquierda de cualquier fila. Aparece una barra encima de la tabla indicando cuántos artefactos has elegido, con **Clear** (borrar selección) al lado y el selector de acciones en el extremo derecho. Marca la casilla en el encabezado de la tabla para tomar todos los artefactos de la página de una vez.

Obtienes la misma barra en tres sitios. Se ve igual cada vez, pero no todas las tareas se ofrecen en todos:

| Dónde | Qué ofrece |
| --- | --- |
| **Crafts → All artefacts** (todos los artefactos) | todas las tareas, sobre cualquier artefacto de la fábrica |
| Una página de familia, pestaña **Artefacts** | tamaño de lote, tarea de fabricación, mover a familia, discontinuar y activar |
| Una página de departamento, pestaña **Artefacts** | tamaño de lote, tarea de fabricación, mover a familia o departamento, discontinuar y activar |

## Elegir qué hacer

El selector nombra la tarea. Cámbialo y el control de al lado cambia con él. El selector en sí nunca se mueve ni cambia de ancho, así que una vez sabes dónde está puedes trabajar rápido.

**Batch size** (tamaño de lote) te da una casilla y un botón **Set** (fijar). Escribe el número de unidades que produce una tanda normal y pulsa **Set**. Tiene que ser uno o más. Es el número que ve la fábrica al planificar una orden de trabajo, y un artefacto sin él aparece en las columnas rojas de la lista de familias.

**Shelf life** (vida útil) funciona igual, en días: 365 es un año, 730 son dos. Es cuánto dura un artefacto una vez hecho, y limita cuánto se le pide a la fábrica que haga de una vez. Solo la lista **All artefacts** la ofrece.

**Manufacture task** (tarea de fabricación) te da un botón, **Make a unified manufacture task** (crear una tarea de fabricación unificada), que abre una ventana donde preparas los pasos una sola vez para todos los artefactos que marcaste. Tiene su propia sección más abajo.

**Move to family** (mover a familia) y **Move to department** (mover a departamento) te dan un cuadro de búsqueda. Empieza a escribir un código o un nombre, elige el destino, pulsa **Move** (mover). Hay un enlace **New family** (nueva familia) al lado por si la familia que buscas todavía no existe. Una familia pertenece a un solo departamento, así que mover artefactos a una familia también los mueve al departamento de esa familia, sea cual sea el que tuvieran antes.

**Discontinue** (discontinuar) pregunta antes de hacer nada. El diálogo indica cuántos artefactos vas a discontinuar, y no ocurre nada hasta que pulsas **Yes, discontinue** (sí, discontinuar).

**Make active** (activar) recupera artefactos discontinuados. No pregunta, porque es el sentido seguro.

## Dar a muchos artefactos los mismos pasos de fabricación

Cada artefacto tiene una lista de pasos que sigue un artesano para hacerlo, como verter, retractilar y encajar. Cada paso es una **manufacture task** (tarea de fabricación), creada una vez para la fábrica y reutilizada por todos los artefactos que la necesitan. Puedes fijar los pasos de un artefacto desde su pestaña **Manufacture tasks**. Cuando toda una gama se hace de la misma forma, la tarea **Manufacture task** lo hace para todos a la vez.

La ventana muestra los pasos a la izquierda y los artefactos que marcaste a la derecha.

**Preparar los pasos.** Cada tarjeta de paso tiene:

- **Task** (tarea): el trabajo en sí, elegido entre las tareas de fabricación de la fábrica. Escribe para buscar por nombre o código.
- **Units per artefact** (unidades por artefacto): cuántas unidades de esa tarea necesita un artefacto. Una orden de trabajo de 10 artefactos a 2 unidades por artefacto pide al artesano 20 unidades de trabajo.
- **Raw materials** (materias primas): lo que consume el paso por cada unidad de trabajo, con una cantidad para cada una.

Pulsa **Add step** (añadir paso) para otra tarjeta. Las flechas de una tarjeta la suben o bajan, y la papelera la quita. Los pasos se numeran en el orden en que se hacen, el paso 1 primero, y los artesanos los ven en ese orden en la planta. Una tarea solo puede usarse una vez en la lista. Si la tarea que necesitas todavía no existe, el enlace **Task missing? Create a manufacture task** (¿falta una tarea? crear una tarea de fabricación) te lleva a la página donde se crean.

**Dar otras materias primas a un solo artefacto.** Casi siempre todos los artefactos usan los mismos materiales, y así empieza la lista de la derecha: **All artefacts** (todos los artefactos), con los materiales compartidos. Cuando un artefacto necesita otra cosa, por ejemplo otro aceite de fragancia:

1. Pulsa ese artefacto en la lista de la derecha. Cada paso muestra ahora sus materiales para ese artefacto.
2. En el paso que cambia, pulsa **Use different materials for** (usar materiales distintos para) ese artefacto. Empieza como una copia de los materiales compartidos.
3. Cambia, añade o quita materiales solo para ese artefacto.

Un artefacto con materiales propios lleva la etiqueta **Own materials** (materiales propios) en la lista, y cada paso donde los materiales difieren tiene un borde rojo y un asterisco rojo en la esquina. Pasa el ratón por el asterisco para ver qué artefactos difieren. Para volver a los materiales compartidos, elige otra vez el artefacto y pulsa **Use the same materials as all artefacts** (usar los mismos materiales que todos los artefactos) en ese paso.

**Guardar sustituye lo que había.** Esta es la parte con la que hay que tener cuidado. Al guardar, cada artefacto que marcaste queda exactamente con los pasos de la ventana, ni uno más:

- Los pasos que ya tenían y no están en la lista **se quitan, junto con sus materias primas**. Esto incluye el paso estándar **Production (PROD)** que aiku da a cada artefacto nuevo.
- Los pasos que ya tenían y están en la lista se quedan, pero toman las nuevas unidades por artefacto y las nuevas materias primas.
- No hay deshacer. Para volver atrás tendrías que fijar otra vez los pasos antiguos.

La ventana te lo recuerda en un recuadro amarillo encima de los botones. El botón de guardar sigue en gris hasta que marcas **I understand the existing steps will be replaced** (entiendo que los pasos existentes se sustituirán). El botón dice cuántos artefactos va a cambiar, por ejemplo **Replace steps on 5 artefacts** (sustituir los pasos de 5 artefactos).

**Órdenes de trabajo en marcha.** Las órdenes de trabajo todavía abiertas reciben los pasos nuevos. Un paso quitado desaparece de esas órdenes solo si nadie lo ha empezado. El trabajo ya registrado en la planta se conserva.

## Qué hace y qué no hace discontinuar

Un artefacto discontinuado sale de las listas de trabajo. Conserva todo lo demás: su receta, sus tareas, su historial y las órdenes de trabajo en las que ha estado. No se borra nada y no se mueve stock.

Las listas de artefactos se abren mostrando solo **In process** (en proceso) y **Active** (activo), así que un artefacto discontinuado desaparece de la vista. Para volver a verlos, usa los chips de **State** (estado) encima de la tabla y marca **Discontinued** (discontinuado).

Las familias toman su estado de los artefactos que contienen. Una familia está activa mientras algún artefacto en ella esté activo o en proceso, y solo pasa a discontinuada cuando todos lo están. Eso significa que discontinuar el último artefacto de una familia también la retira silenciosamente de la lista de familias, y los mismos chips de **State** la traen de vuelta.

## Cosas que conviene saber

- **Los artefactos que ya están en el estado elegido se saltan.** Discontinuar una selección que ya está mitad discontinuada solo informa de los que realmente cambiaron.
- **La selección es solo lo que ves.** Marcar la casilla del encabezado toma las filas de la página, no todos los artefactos detrás del filtro. Cambia el tamaño de página primero si quieres más.
- **Nada de esto toca el stock.** Son registros de receta, no las mercancías del almacén.
- **Los recuentos de la lista de familias se actualizan al instante.** Fija un tamaño de lote en veinte artefactos y la columna roja de la lista de familias baja en veinte en cuanto se recarga la página.
- **No hay deshacer para un movimiento.** Mover artefactos a la familia equivocada se arregla moviéndolos de vuelta, que son los mismos dos clics.
- **Sustituir los pasos tampoco se puede deshacer.** Antes de guardar una tarea de fabricación en muchos artefactos, comprueba que la lista de la derecha son los artefactos que querías.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Todos los artefactos:</b> tu organización → <b>Factory</b> → <b>Crafts</b> → <b>All artefacts</b>.</li>
<li><b>Una familia:</b> <b>Crafts</b> → <b>Families</b> → la familia → pestaña <b>Artefacts</b>.</li>
<li><b>Un departamento:</b> <b>Crafts</b> → <b>Departments</b> → el departamento → pestaña <b>Artefacts</b>.</li>
<li><b>Empezar:</b> marca una fila → aparece la barra → elige la tarea a la derecha.</li>
<li><b>Los mismos pasos para muchos:</b> elige <b>Manufacture task</b> → <b>Make a unified manufacture task</b>.</li>
<li><b>Crear una tarea de fabricación:</b> <b>Factory</b> → <b>Operations</b> → <b>Tasks</b>.</li>
<li><b>Ver los discontinuados:</b> los chips de <b>State</b> encima de la tabla → marca <b>Discontinued</b>.</li>
<li><b>Un solo artefacto:</b> ábrelo y usa el lápiz, los mismos campos están ahí. Sus pasos están en su pestaña <b>Manufacture tasks</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Los puestos se asignan en la ficha del empleado bajo Human Resources y llevan los derechos consigo.</li>
<li>Ver las listas: un puesto de producción para esa fábrica, o supervisor de la organización.</li>
<li>Usar la barra: el mismo puesto con derechos de edición sobre la fábrica, o supervisor de la organización. Sin eso las casillas de marcar no aparecen.</li>
</ul>
</aside>
