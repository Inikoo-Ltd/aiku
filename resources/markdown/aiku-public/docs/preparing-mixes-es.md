---
title: Preparar mezclas
summary: Para el preparador y el planificador - cómo una mezcla o base se convierte en algo que la fábrica rastrea, cómo el tablero Mixes calcula la falta, y cómo fluyen las órdenes de trabajo del preparador.
date: 2026-09-08
source_date: 2026-09-08
tags: production, crafts
category: production
series: Ordering from partners
order: 6
---

<aside class="tldr">
Para la persona que prepara mezclas y bases antes de que los artesanos puedan empezar, y para el planificador que les manda el trabajo. Una mezcla se hace en casa, así que aiku la trata a la vez como <b>materia prima</b> (los artesanos la consumen) y como <b>artefacto</b> (el preparador la hace). Una vez enlazadas, la pestaña <b>Mixes</b> (Mezclas) en <a href="/docs/fulfilling-partner-orders-es">To produce</a> calcula cuánto falta de cada mezcla a partir de las órdenes de trabajo abiertas, y al arrastrar una tarjeta al preparador se convierte en una orden de trabajo. La configuración de categorías y artesanos está en <a href="/docs/who-makes-what-es">Quién hace qué</a>.
</aside>

## Por qué una mezcla es dos cosas

Una receta de bomba de baño dice "0,5 kg de mezcla base por unidad". Esa mezcla base no se compra, se prepara en la fábrica a partir de sus propios ingredientes. Así que existe dos veces:

- Como **materia prima**, para que las recetas la consuman y el stock se descuente cuando se recibe el producto terminado.
- Como **artefacto**, con su propia receta y sus propias órdenes de trabajo, para que el preparador tenga trabajo que hacer y una hornada que recibir en el stock.

El enlace entre las dos es un solo campo en la materia prima: **Made in-house as** (Se hace en casa como). Ponlo en el artefacto de la mezcla. Esa es toda la configuración.

## Configurar una mezcla

1. **Crea el artefacto** de la mezcla en **Factory → Crafts → Artefacts**, con sus pasos de receta y sus propias materias primas, como cualquier otro artefacto. Dale un stock (SKU) para que las hornadas recibidas tengan dónde ir.
2. **Crea o abre la materia prima** de la mezcla en **Factory → Crafts → Raw materials**. Edítala, pon **Made in-house as** al artefacto del paso 1, y dale el mismo stock (SKU).
3. **Usa la materia prima en las recetas.** En cada producto que necesite la mezcla, añade la mezcla al paso de receta que corresponda, con la cantidad por unidad.
4. **Asigna al preparador** al artefacto de la mezcla, o a una categoría que agrupe todas las mezclas, en *Usually made by*. Las órdenes de trabajo de mezclas irán entonces a esa persona.

## La pestaña Mixes

**Factory → To produce → Mixes** es un pequeño tablero con cuatro carriles: **Needed** (Necesario), **Assigned**, **Mixing** y **Done**. Solo muestra materias primas hechas en casa que necesita una orden de trabajo abierta. Una orden de trabajo está abierta desde que se crea hasta que se recibe en el stock.

Una tarjeta en **Needed** es una mezcla de la que la fábrica anda corta. El número en rojo es la falta: lo que necesitan las órdenes de trabajo abiertas, según sus cantidades y la cantidad por unidad de la receta, menos lo disponible, menos lo que ya se está mezclando. Debajo, *for* lista los códigos de producto que están esperando, para que el preparador sepa qué está bloqueado, y el nombre del preparador habitual si tiene uno asignado.

Arrastra la tarjeta de **Needed** a **Assigned**. aiku pide la cantidad, proponiendo la falta para que puedas redondearla a una hornada razonable, y *¿Quién la mezcla?*. Elige al preparador y se crea una orden de trabajo en borrador, dirigida a esa persona, con su referencia en la tarjeta. Ábrela y pulsa **Release to floor** (Liberar a planta) cuando deba empezar.

**Mixing** y **Done** se mueven solos: la tarjeta pasa a Mixing cuando el preparador pulsa START, y a Done cuando la última tarea está hecha. Sale del tablero cuando la hornada se guarda en el stock.

## Qué hace el preparador

El preparador lleva su propia línea, así que tiene el puesto de <b>Mix preparer</b> (preparador de mezclas) para la fábrica. Eso le permite abrir la pestaña Mixes, crear y liberar sus propias órdenes de trabajo y recibirlas en el stock sin esperar a nadie. No puede tocar las órdenes de trabajo dirigidas a otras personas; eso queda para el planificador. En la planta trabaja como cualquier artesano: sus tareas aparecen en la [pantalla de planta](/docs/working-the-floor-screen-es), pulsa START y DONE, y cuando el último paso está hecho, la hornada se guarda en el stock con un código de hornada, ya sea por el almacén desde [Dispatching → From production](/docs/putting-away-finished-production-es) o por el propio preparador desde la página de la orden de trabajo. Desde ese momento la mezcla aparece como disponible y los artesanos pueden hacer sus productos.

Si al preparador no se le paga a destajo, eso es un ajuste de nómina, no un motivo para saltarse la planta. El registro de quién preparó cada hornada y cuándo es lo que da trazabilidad desde el producto terminado hasta sus ingredientes.

## Cosas que conviene saber

- Una mezcla no puede necesitarse a sí misma. Si la propia receta del artefacto de la mezcla incluye la misma materia prima, esa línea se ignora.
- La pestaña Mixes solo lee órdenes de trabajo de esta fábrica. Un producto hecho en otra fábrica no genera demanda aquí.
- Una orden de trabajo de mezcla cuenta como "en preparación" hasta que se guarda en el stock, aunque todas las tareas estén hechas. Guarda las hornadas con prontitud y la falta se mantiene honesta.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Enlazar una mezcla:</b> <b>Factory → Crafts → Raw materials</b> → abre la mezcla → <b>Edit</b> → <b>Made in-house as</b>.</li>
<li><b>Ver qué preparar:</b> <b>Factory → To produce → Mixes</b>.</li>
<li><b>Mandar el trabajo:</b> arrastra la tarjeta de <b>Needed</b> a <b>Assigned</b> → cantidad y preparador → abre la orden de trabajo → <b>Release to floor</b>.</li>
<li><b>Hacer el trabajo:</b> <b>Factory → Jobs</b> → <b>START</b> / <b>DONE</b>; luego la hornada se guarda en el stock desde <b>Warehouse → Dispatching → From production</b> o desde la página de la orden de trabajo.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Los puestos se asignan en la ficha del empleado en Human Resources y llevan los permisos consigo.</li>
<li>Ver la pestaña Mixes y trabajar en planta: puesto <b>Production operative</b> (operario) para la fábrica, o superior.</li>
<li>Crear órdenes de trabajo de mezclas y liberar y recibir las propias: puesto <b>Mix preparer</b> (preparador de mezclas) para la fábrica. El preparador necesita este.</li>
<li>Todo lo demás, incluidas las órdenes de trabajo de otras personas y enlazar una materia prima con su artefacto: puesto <b>Production floor supervisor</b> (supervisor de planta) para la fábrica, o supervisor de la organización.</li>
</ul>
</aside>
