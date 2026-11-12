---
title: Fijar objetivos para los productos en desarrollo
summary: Cómo aiku sigue paso a paso los productos pagados como desarrollo, propone un objetivo por hora cuando un paso tiene suficientes ejecuciones, pide a los revisores que lo acepten, lo ajusten o mantengan el producto en desarrollo, y lista lo que está atrasado.
date: 2026-11-10
source_date: 2026-11-10
tags: production, targets, development, payroll
category: production
---

<aside class="tldr">
Un producto marcado como <b>In development</b> no tiene objetivo, así que sus artesanos cobran sin bonus. Aiku registra cuánto dura cada paso y, cuando un paso tiene suficientes ejecuciones (por defecto, 5 sesiones de 2 artesanos y 4 horas), propone como objetivo la mediana por hora. Los revisores reciben una sola tarea con los productos listos y deciden en <b>Factory → Crafts → Development → Paid as development</b>: <b>Save targets</b> (tal como se proponen o ajustados) o <b>Keep on development</b> con un motivo. Cuando todos los pasos pagados por pieza tienen objetivo, el producto deja de pagarse como desarrollo.
</aside>

## Dónde encontrarlo

Abre **Factory → Crafts → Development** y la pestaña **Paid as development**. Lista todos los productos con **In development** activado, con:

- **Status**: **Overdue** (listo para sus objetivos y con producción en las últimas 4 semanas), **Ready, not run lately** (listo, pero no fabricado en las últimas 4 semanas, así que no cuenta como atrasado), **Collecting** (aún no hay suficientes ejecuciones) o **Kept on development** (alguien decidió esperar; pasa el cursor para ver el motivo).
- **On development since**: cuándo se activó **In development** por última vez.
- **Recipe changed**: la última vez que se añadió o quitó un paso de la receta. Los productos cuya receta no ha cambiado desde que se empezó a registrar muestran un guion.
- **Last run**: la última sesión trabajada en el producto.
- **Steps**: para cada paso, las sesiones, artesanos y horas contados hasta ahora y el ritmo por hora que resulta. Una marca indica que el paso está listo para su objetivo; los pasos con objetivo lo muestran, y los pasos pagados por hora no necesitan ninguno.

Los productos atrasados aparecen primero. Siguen atrasados hasta que alguien decide.

## Cómo se calcula el objetivo propuesto

- Cada paso se calcula por separado. Las sesiones del mismo paso (misma tarea, misma etiqueta, mismas unidades por artefacto) de todos los productos de la familia se cuentan juntas, así que un color nuevo de una vela aprende de los demás.
- Solo cuentan las sesiones posteriores al último momento en que la receta ganó o perdió un paso: el trabajo cambió, así que la medición empieza de nuevo.
- La primera sesión de cada artesano en el paso se deja fuera como sesión de aprendizaje. También se dejan fuera las sesiones de menos de 10 minutos, las sesiones que siguen esperando que se revisen sus horas tras quedar abiertas más allá del turno, y los valores claramente atípicos (mucho más rápidos o lentos que el resto).
- Un paso está listo cuando lo que queda suma al menos 5 sesiones, de al menos 2 artesanos, con un total de al menos 4 horas.
- El objetivo propuesto es la mediana por hora de esas sesiones. Junto a él ves la mitad central y el rango completo, para saber cuánto varían los artesanos.

El responsable de producción o el contable pueden cambiar estos números con **Settings** en la pestaña: sesiones por paso, artesanos distintos, horas en total, la sesión más corta, y tras cuántas semanas sin producción un producto deja de estar atrasado.

## La tarea de revisión

En **Settings**, **Who reviews** elige a las personas que reciben la tarea (el responsable de producción y el contable). Cada mañana aiku revisa los productos en desarrollo. Cuando hay alguno listo, los revisores reciben una sola tarea, **Set targets for products paid as development**, con una línea por producto y los ritmos propuestos. Los productos que quedan listos más tarde se añaden a la misma tarea. Una línea se marca sola cuando el producto se decide, o cuando ya no está pendiente (se quitó de desarrollo o sus objetivos se fijaron a mano), y la tarea se cierra cuando todas las líneas están hechas.

Si no hay nadie elegido en **Who reviews**, no se envía ninguna tarea; la pestaña lo indica.

## Comentarios desde la planta

En la tableta, cada trabajo de un producto en desarrollo muestra **Product in development: the steps and timings are still being worked out**, y la lista de trabajos lo marca **In development**. El artesano puede pulsar **Send feedback**, antes de empezar o mientras trabaja, y marcar qué está mal: **A step is missing** (falta un paso), **A step is not needed** (sobra un paso), **Steps in the wrong order** (pasos en mal orden), **A material or packaging is missing or wrong** (falta o está mal un material o embalaje), con un comentario opcional.

Cada producto tiene una tarea para sus comentarios, **Feedback from the floor on …**, para la primera persona elegida en **Who reviews** (el responsable de producción). Cada comentario se añade como una línea y un mensaje. Mientras esa tarea está abierta, el producto muestra **Floor feedback open** en la pestaña, y **Review** enseña los comentarios y no deja guardar sus objetivos: corrige la receta y cierra la tarea. Aun así puedes dejar el producto en desarrollo. Añadir o quitar un paso de la receta reinicia su cronometraje, como se explica arriba.

## Decidir

Pulsa **Review** en un producto. Para cada paso ves el objetivo propuesto, las sesiones contadas y las que se dejaron fuera (con el motivo), y los objetivos de pasos similares de la fábrica como comparación. Después:

- **Save targets**: la casilla de objetivo de cada paso listo se rellena con el ritmo propuesto. Déjalo para aceptarlo, o escribe otro ritmo para ajustarlo. Los pasos que aún no están listos quedan vacíos; también puedes escribirles un objetivo. Los objetivos se aplican desde hoy, así que las sesiones ya trabajadas se quedan como se pagaron.
- **Keep on development**: escribe el motivo (por ejemplo, viene un molde nuevo). El producto sigue en desarrollo y vuelve a revisión cuando sus pasos acumulen otra vez el mismo número de sesiones nuevas.

Cuando todos los pasos pagados por pieza tienen objetivo, **In development** se desactiva y desde entonces el producto se paga según sus objetivos. Si a un paso aún le falta el objetivo, el producto sigue en desarrollo hasta que lo tenga.
