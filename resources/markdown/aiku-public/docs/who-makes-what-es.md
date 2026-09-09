---
title: Quién hace qué
summary: Enseña a aiku qué artesanos hacen normalmente cada categoría o artefacto, para que la lista To produce se reparta sola en montones por persona. Una recomendación, nunca un candado.
date: 2026-09-08
source_date: 2026-09-08
tags: production, crafts, hr
category: production
series: Ordering from partners
order: 5
---

<aside class="tldr">
Para el jefe de fábrica o el planificador. Dos pequeños ajustes hacen útil la lista <a href="/docs/fulfilling-partner-orders-es">To produce</a>: agrupar los artefactos en <b>categorías</b> (llamadas departamentos en pantalla), y asignar los <b>artesanos</b> que normalmente hacen cada categoría o artefacto. Después de eso, la vista <i>By artisan</i> reparte el montón de cada persona sola. Nada de esto impide que un trabajo vaya a otra persona; solo dice quién lo hace normalmente.
</aside>

## Categorías y familias

Un artefacto es una cosa que hace la fábrica. Una categoría, un **departamento** en pantalla, es una estantería de ellas: bombas de baño, jabón, aceites esenciales, una gama de marca. Cada artefacto pertenece como mucho a un departamento. Dentro de un departamento, las **familias** son una agrupación más fina, igual que las familias de producto en el catálogo: las bombas de baño de lavanda, las de cítricos. Las familias son opcionales y solo ayudan a encontrar cosas; nada se planifica por familia.

- **Factory → Crafts → Artefacts** tiene tres pestañas: **Departments**, **Families** y **All artefacts**. Abre un departamento o una familia para ver sus artefactos, y usa el botón **new** de cualquiera de las dos para crear uno.
- Para mover artefactos, márcalos en cualquier lista de artefactos y usa **Move to department** o **Move to family**.
- Borrar una familia deja sus artefactos en su sitio, sin familia.
- Todo un catálogo de artefactos, o de materias primas, se puede cargar de golpe con el botón **upload** de la lista.

Los departamentos gobiernan dos cosas: la vista *By category* de la lista To produce, y el valor por defecto para los artesanos, que se explica a continuación.

## Artesanos

En cada página de departamento y en cada página de artefacto hay una fila bajo el título: **Usually made by** (Normalmente lo hace). Las familias no tienen una.

- Elige un nombre en **Add artisan…** para asignar a alguien. Solo se ofrecen empleados en activo de tu organización.
- Puedes asignar a tantas personas como quieras. La primera queda resaltada; esa es la dueña por defecto.
- Haz clic en la cruz pequeña de una chip para quitarla. El orden importa: la primera persona asignada sigue siendo la primera hasta que se elimina.

Así lo lee aiku. Para una línea de To produce, mira primero el artefacto. Si el artefacto tiene artesanos, el primero es el dueño de la línea. Si no, mira el departamento del artefacto y toma el primer artesano de ahí. Si ninguno de los dos tiene a nadie, la línea queda bajo *Unassigned*, y en el Board la pregunta *¿Quién lo hace?* no propone ningún nombre.

Así que la forma barata de configurar una fábrica es: asignar artesanos a los departamentos, y tocar artefactos individuales solo para las excepciones. Una persona hace todo el jabón salvo la única hornada que necesita otras manos.

## Lo que no es

- **No es un candado.** Las órdenes de trabajo y las sesiones de tarea no lo comprueban. Cualquiera puede hacer cualquier cosa, y el planificador puede elegir cualquier nombre cuando suelta una tarjeta en *Assigned*.
- **No es un registro de habilidades.** Dice quién lo hace normalmente, lo que es una pista razonable de quién es bueno en ello, pero a nadie se le puntúa por esto.
- **No es historial.** Quién hizo realmente qué está bajo **Factory → Artisans → Performance**, construido a partir de sesiones de tarea cerradas.
- **No es el censo.** Quién cuenta como artesano está en la franja *Open job orders per artisan* de To produce, donde una cruz oculta a quien no fabrica cosas.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Departamentos y familias:</b> tu organización → <b>Factory</b> → <b>Crafts</b> → <b>Artefacts</b> → pestaña <b>Departments</b> o <b>Families</b>.</li>
<li><b>Mover artefactos:</b> marca artefactos en cualquier lista de artefactos → <b>Move to department</b> o <b>Move to family</b>.</li>
<li><b>Asignar un artesano:</b> abre un departamento o un artefacto → <b>Usually made by</b> → <b>Add artisan…</b>. Quítalo con la cruz de la chip.</li>
<li><b>Ver el efecto:</b> <b>Factory</b> → <b>To produce</b> → <b>By artisan</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Asignar y quitar artesanos: puesto <b>Production floor supervisor</b> (supervisor de planta) para la fábrica, o supervisor de la organización. Los puestos se asignan en la ficha del empleado en Human Resources. Todo el que puede ver la página ve los nombres.</li>
<li>Crear departamentos y familias, mover artefactos, cargar catálogos: supervisor de la organización. Ningún puesto de fábrica cubre esto todavía.</li>
</ul>
</aside>
