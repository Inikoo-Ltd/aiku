---
title: Cambiar muchos artefactos a la vez
summary: Marca los artefactos y usa el selector a la derecha de la barra que aparece para fijar un tamaño de lote, moverlos a otra familia o departamento, discontinuarlos, o recuperarlos.
date: 2026-09-09
source_date: 2026-09-09
tags: production, crafts
category: production
---

<aside class="tldr">
Para quien lleva el catálogo de la fábrica. Todas las listas de artefactos tienen casillas de marcar. Marca algunas filas y aparece una barra encima de la tabla con un selector a su derecha. El selector ofrece cuatro tareas: <b>Batch size</b> (tamaño de lote), <b>Move to family</b> (mover a familia), <b>Move to department</b> (mover a departamento) y <b>Discontinue</b> (discontinuar), además de <b>Make active</b> (activar) para deshacer la última. Editar los artefactos uno a uno sigue funcionando; esto es el mismo cambio hecho a cincuenta filas a la vez.
</aside>

## Cómo encontrarla

No hay nada que activar ni ningún botón que la abra. La barra está oculta hasta que marcas algo, por eso la mayoría nunca la descubre.

Marca la casilla a la izquierda de cualquier fila. Aparece una barra encima de la tabla indicando cuántos artefactos has elegido, con **Clear** (borrar selección) al lado y el selector de acciones en el extremo derecho. Marca la casilla en el encabezado de la tabla para tomar todos los artefactos de la página de una vez.

Obtienes la misma barra en tres sitios, y es la misma barra cada vez:

| Dónde | Qué ofrece |
| --- | --- |
| **Crafts → All artefacts** (todos los artefactos) | las cuatro tareas, sobre cualquier artefacto de la fábrica |
| Una página de familia, pestaña **Artefacts** | todo menos mover a un departamento |
| Una página de departamento, pestaña **Artefacts** | las cuatro tareas, sobre los artefactos de ese departamento |

## Elegir qué hacer

El selector nombra la tarea. Cámbialo y el control de al lado cambia con él. El selector en sí nunca se mueve ni cambia de ancho, así que una vez sabes dónde está puedes trabajar rápido.

**Batch size** (tamaño de lote) te da una casilla y un botón **Set** (fijar). Escribe el número de unidades que produce una tanda normal y pulsa **Set**. Tiene que ser uno o más. Es el número que ve la fábrica al planificar una orden de trabajo, y un artefacto sin él aparece en las columnas rojas de la lista de familias.

**Move to family** (mover a familia) y **Move to department** (mover a departamento) te dan un cuadro de búsqueda. Empieza a escribir un código o un nombre, elige el destino, pulsa **Move** (mover). Hay un enlace **New family** (nueva familia) al lado por si la familia que buscas todavía no existe. Una familia pertenece a un solo departamento, así que mover artefactos a una familia también los mueve al departamento de esa familia, sea cual sea el que tuvieran antes.

**Discontinue** (discontinuar) pregunta antes de hacer nada. El diálogo indica cuántos artefactos vas a discontinuar, y no ocurre nada hasta que pulsas **Yes, discontinue** (sí, discontinuar).

**Make active** (activar) recupera artefactos discontinuados. No pregunta, porque es el sentido seguro.

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

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Todos los artefactos:</b> tu organización → <b>Factory</b> → <b>Crafts</b> → <b>All artefacts</b>.</li>
<li><b>Una familia:</b> <b>Crafts</b> → <b>Families</b> → la familia → pestaña <b>Artefacts</b>.</li>
<li><b>Un departamento:</b> <b>Crafts</b> → <b>Departments</b> → el departamento → pestaña <b>Artefacts</b>.</li>
<li><b>Empezar:</b> marca una fila → aparece la barra → elige la tarea a la derecha.</li>
<li><b>Ver los discontinuados:</b> los chips de <b>State</b> encima de la tabla → marca <b>Discontinued</b>.</li>
<li><b>Un solo artefacto:</b> ábrelo y usa el lápiz, los mismos campos están ahí.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Los puestos se asignan en la ficha del empleado bajo Human Resources y llevan los derechos consigo.</li>
<li>Ver las listas: un puesto de producción para esa fábrica, o supervisor de la organización.</li>
<li>Usar la barra: el mismo puesto con derechos de edición sobre la fábrica, o supervisor de la organización. Sin eso las casillas de marcar no aparecen.</li>
</ul>
</aside>
