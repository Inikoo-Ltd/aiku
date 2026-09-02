---
title: Quién hace qué
summary: Enseña a aiku qué artesanos hacen normalmente cada categoría o artefacto, para que la lista To produce se reparta sola en montones por persona. Una recomendación, nunca un candado.
date: 2026-09-02
source_date: 2026-09-02
tags: production, crafts, hr
category: production
series: Ordering from partners
order: 5
---

<aside class="tldr">
Para el jefe de fábrica o el planificador. Dos pequeños ajustes hacen útil la lista <a href="/docs/fulfilling-partner-orders-es">To produce</a>: agrupar los artefactos en <b>categorías</b>, y asignar los <b>artesanos</b> que normalmente hacen cada categoría o artefacto. Después de eso, la vista <i>By artisan</i> reparte el montón de cada persona sola. Nada de esto impide que un trabajo vaya a otra persona; solo dice quién lo hace normalmente.
</aside>

## Categorías

Un artefacto es una cosa que hace la fábrica. Una categoría es una estantería de ellas: bombas de baño, jabón, aceites esenciales, una gama de marca. Cada artefacto pertenece como mucho a una categoría.

- **Factory → Crafts → Artefact families** lista las categorías con cuántos artefactos tiene cada una. Abre una para ver sus artefactos.
- Para mover artefactos entre categorías, márcalos en cualquier lista de artefactos y usa **Move to family**. Para crear una categoría, usa el botón **new** de la lista.

Las categorías gobiernan dos cosas: la vista *By category* de la lista To produce, y el valor por defecto para los artesanos, que se explica a continuación.

## Artesanos

En cada página de categoría y en cada página de artefacto hay una fila bajo el título: **Usually made by** (Normalmente lo hace).

- Elige un nombre en **Add artisan…** para asignar a alguien. Solo se ofrecen empleados en activo de tu organización.
- Puedes asignar a tantas personas como quieras. La primera queda resaltada; esa es la dueña por defecto.
- Haz clic en la cruz pequeña de una chip para quitarla. El orden importa: la primera persona asignada sigue siendo la primera hasta que se elimina.

Así lo lee aiku. Para una línea de To produce, mira primero el artefacto. Si el artefacto tiene artesanos, el primero es el dueño de la línea. Si no, mira la categoría del artefacto y toma el primer artesano de ahí. Si ninguno de los dos tiene a nadie, la línea queda bajo *Unassigned*.

Así que la forma barata de configurar una fábrica es: asignar artesanos a las categorías, y tocar artefactos individuales solo para las excepciones. Una persona hace todo el jabón salvo la única hornada que necesita otras manos.

## Lo que no es

- **No es un candado.** Las órdenes de trabajo y las sesiones de tarea no lo comprueban. Cualquiera puede hacer cualquier cosa.
- **No es un registro de habilidades.** Dice quién lo hace normalmente, lo que es una pista razonable de quién es bueno en ello, pero a nadie se le puntúa por esto.
- **No es historial.** Quién hizo realmente qué está en las pantallas de artesanos bajo **Factory → Operations → Artisans**, construido a partir de sesiones de tarea cerradas.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Categorías:</b> tu organización → <b>Factory</b> → <b>Crafts</b> → <b>Artefact families</b>.</li>
<li><b>Mover artefactos:</b> marca artefactos en cualquier lista de artefactos → <b>Move to family</b>.</li>
<li><b>Asignar un artesano:</b> abre una categoría o un artefacto → <b>Usually made by</b> → <b>Add artisan…</b>. Quítalo con la cruz de la chip.</li>
<li><b>Ver el efecto:</b> <b>Factory</b> → <b>To produce</b> → <b>By artisan</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Asignar y quitar artesanos: derechos de orquestación sobre las operaciones de la fábrica, o supervisor de la organización. Todo el que puede ver la página ve los nombres.</li>
</ul>
</aside>
