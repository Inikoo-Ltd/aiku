---
title: Mantener la fábrica abastecida
summary: La página To restock - qué artefactos se agotan antes, cuánto tarda la fábrica en fabricar algo, y cómo poner tu propio trabajo de reposición en el tablero To produce.
date: 2026-09-09
source_date: 2026-09-09
tags: production, stock, planning
category: production
series: Ordering from partners
order: 11
---

<aside class="tldr">
Para quien planifica la semana de la fábrica. <a href="/docs/fulfilling-partner-orders-es">To produce</a> responde <i>qué ha pedido alguien</i>. <b>To restock</b> responde la otra pregunta: <i>de qué nos vamos a quedar sin stock, alguien lo haya pedido o no</i>. Ordena todo lo que fabrica la fábrica según cuántos días de cobertura quedan, medidos contra lo que esta fábrica tarda de verdad en fabricar algo, y te deja empujar lo que merece la pena fabricar al tablero To produce.
</aside>

## El plazo de fabricación es la vara de medir

Cada casilla de esta página se mide en **plazos de fabricación (lead times)**, no en días. El lead time es el número medio de días desde que el trabajo entra en planta hasta que vuelve al almacén, tomado de las propias órdenes de trabajo de esta fábrica durante el último año. Con menos de cinco órdenes de trabajo terminadas no hay nada que medir, así que aiku usa una estimación — siete días salvo que alguien fije otra cifra en la fábrica — y pone *estimate* junto al número.

Por eso las casillas se leen como se leen. Un artefacto con cuatro días de cobertura no está en apuros en una fábrica que da la vuelta al trabajo en dos días; ya está perdido en una que tarda una semana.

## Las casillas

| Casilla | Qué significa |
| --- | --- |
| Out of stock | nada en el estante |
| Doomed | se acabará antes de que pudiera llegar algo iniciado hoy |
| Critical | se agota dentro de dos lead times |
| Danger | se agota dentro de tres lead times |
| Watch | se agota dentro de cuatro lead times |
| Covered | más de cuatro lead times de cobertura |
| Dead stock | valor en el estante y ningún uso en absoluto |
| Never made yet | un artefacto sin ningún registro de stock detrás |

Cada casilla lleva tres números: cuántos artefactos hay en ella, cuántos están **ya en marcha** y cuántos están **sin tocar**. En marcha significa que alguien ya se ha ocupado — una línea abierta en el tablero To produce, o una orden de trabajo en planta. Sin tocar es el número a trabajar.

Haz clic en las casillas para elegir qué muestra la lista de abajo. Se abre con **Out of stock, Doomed y Critical**, que es la lista honesta de cada mañana.

## Los carriles

Debajo de las casillas el mismo trabajo se organiza en cuatro carriles:

- **To do** — artefactos en las casillas seleccionadas de los que no se ha hecho nada. Los urgentes primero. Cada fila lleva el código de stock, lo que hay en el estante, los días de cobertura, la familia del artefacto, quién suele fabricarlo, y el número de **units** para las que se levantaría un trabajo: la cantidad de pedido recomendada convertida en unidades y redondeada hacia arriba a la siguiente hornada completa. Se deja fuera todo lo que un socio ya ha pedido — eso es cosa de To produce, no de esta página.
- **Queued** — líneas que ya esperan en el tablero To produce, vengan de un socio o de aquí.
- **Producing** — líneas con una orden de trabajo en planta, con su referencia y el artesano.
- **Restocked** — lo que ha vuelto de planta en las últimas dos semanas, para que veas que la página funciona.

## Poner trabajo en el tablero

Marca filas en **To do** y pulsa el botón para ponerlas en cola. Cada una se convierte en una línea del tablero To produce sin socio ni cliente detrás: solo trabajo que la fábrica se debe a sí misma. Desde ahí se planifica, se asigna y se fabrica exactamente igual que una línea de socio, y sale del tablero cuando el producto terminado se guarda.

Una línea se salta, y lo dice, si ese mismo stock ya está abierto en el tablero. No puedes poner en cola lo mismo dos veces pulsando el botón dos veces.

## Cosas que conviene saber

- **Los artículos On demand no están aquí.** Un artefacto cuyo SKO está marcado *On Demand* se fabrica cuando se pide y no tiene cobertura que agotar.
- **Dead stock es una pregunta, no una tarea.** Valor quieto sin ningún uso normalmente pide una conversación con la tienda, no una orden de trabajo.
- **La página se recalcula al momento.** Nada se guarda, nada hay que ordenar, y una casilla se vacía sola cuando llega el producto.
- **Los días de cobertura vienen del mismo pronóstico** que usa el resto de aiku, ver [Cómo predice aiku lo que se te va a agotar](/docs/how-aiku-predicts-what-you-run-out-of-es).

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>La página:</b> tu organización → <b>Factory</b> → <b>To restock</b>.</li>
<li><b>Cambiar qué muestran los carriles:</b> haz clic en las casillas de arriba.</li>
<li><b>Poner en cola tu propio trabajo:</b> marca filas en <b>To do</b> → el botón de cola → aparecen en <b>To produce</b>.</li>
<li><b>Fijar el lead time estimado</b> mientras el historial es escaso: en la propia configuración de la fábrica; en cuanto se hayan terminado cinco órdenes de trabajo, la cifra medida toma el relevo sola.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Los puestos se asignan en la ficha del empleado en Human Resources y llevan los permisos consigo.</li>
<li>Ver la página: puesto <b>Production operative</b> para la fábrica, o superior.</li>
<li>Poner trabajo en cola en To produce: puesto <b>Production floor supervisor</b> para la fábrica, o supervisor de la organización.</li>
</ul>
</aside>
