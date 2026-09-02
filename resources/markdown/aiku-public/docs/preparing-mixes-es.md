---
title: Preparar mezclas
summary: Para el preparador y el planificador - cómo una mezcla o base se convierte en algo que la fábrica rastrea, cómo la pestaña Mixes calcula qué preparar, y cómo fluyen las órdenes de trabajo del preparador.
date: 2026-09-02
source_date: 2026-09-02
tags: production, crafts
category: production
series: Ordering from partners
order: 6
---

<aside class="tldr">
Para la persona que prepara mezclas y bases antes de que los artesanos puedan empezar, y para el planificador que les manda el trabajo. Una mezcla se hace en casa, así que aiku la trata a la vez como <b>materia prima</b> (los artesanos la consumen) y como <b>artefacto</b> (el preparador la hace). Una vez enlazadas, la pestaña <b>Mixes</b> (Mezclas) en <a href="/docs/fulfilling-partner-orders-es">To produce</a> calcula cuánto se necesita de cada mezcla a partir de las órdenes de trabajo abiertas, y un botón lo convierte en órdenes de trabajo para el preparador. La configuración de categorías y artesanos está en <a href="/docs/who-makes-what-es">Quién hace qué</a>.
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

**Factory → To produce → Mixes** lista cada materia prima hecha en casa que necesita una orden de trabajo abierta. Una orden de trabajo está abierta desde que se crea hasta que se recibe en el stock.

Para cada mezcla ves:

- **Needed** (Necesario): las cantidades de las órdenes de trabajo abiertas multiplicadas por la cantidad por unidad de la receta, sumadas entre productos.
- **On hand** (Disponible): el stock de la mezcla ahora mismo.
- **Being made** (En preparación): la cantidad en órdenes de trabajo abiertas para la propia mezcla.
- **Short** (Falta): necesario menos disponible menos en preparación. Las líneas con falta van primero y se muestran en rojo.
- **Needed for** (Necesario para): los códigos de producto que la consumen, para que el preparador sepa qué está esperando.

Marca las mezclas que hay que preparar, ajusta la cantidad si la falta no coincide con el tamaño de hornada correcto, y pulsa **Create job orders**. Se crea una orden de trabajo por preparador, dirigida a esa persona, en borrador. Ábrela y pulsa *Release to floor* (Liberar a planta) cuando deba empezar.

## Qué hace el preparador

El preparador trabaja como cualquier artesano: sus tareas aparecen en la pantalla de planta, pulsa START y DONE, y cuando el último paso está hecho, la orden de trabajo se recibe en el stock con un código de hornada. Desde ese momento la mezcla aparece como disponible y los artesanos pueden hacer sus productos.

Si al preparador no se le paga a destajo, eso es un ajuste de nómina, no un motivo para saltarse la planta. El registro de quién preparó cada hornada y cuándo es lo que da trazabilidad desde el producto terminado hasta sus ingredientes.

## Cosas que conviene saber

- Una mezcla no puede necesitarse a sí misma. Si la propia receta del artefacto de la mezcla incluye la misma materia prima, esa línea se ignora.
- La pestaña Mixes solo lee órdenes de trabajo de esta fábrica. Un producto hecho en otra fábrica no genera demanda aquí.
- "Being made" cuenta una orden de trabajo hasta que se recibe en el stock, aunque todas las tareas estén hechas. Recibe las órdenes de trabajo con prontitud y los números se mantienen honestos.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Enlazar una mezcla:</b> <b>Factory → Crafts → Raw materials</b> → abre la mezcla → <b>Edit</b> → <b>Made in-house as</b>.</li>
<li><b>Ver qué preparar:</b> <b>Factory → To produce → Mixes</b>.</li>
<li><b>Mandar el trabajo:</b> marca mezclas → <b>Create job orders</b> → abre la orden de trabajo → <b>Release to floor</b>.</li>
<li><b>Hacer el trabajo:</b> <b>Factory → Floor</b> (My tasks) → <b>START</b> / <b>DONE</b>; luego la orden de trabajo se recibe en el stock desde su página.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Enlazar una materia prima con su artefacto: derechos de edición sobre las crafts de la fábrica, o supervisor de la organización.</li>
<li>Ver la pestaña Mixes: derechos de vista sobre las operaciones o compras de la fábrica.</li>
<li>Crear y liberar órdenes de trabajo: derechos de orquestación sobre las operaciones de la fábrica, o supervisor de la organización.</li>
</ul>
</aside>
