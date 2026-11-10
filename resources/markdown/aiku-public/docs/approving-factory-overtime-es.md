---
title: Aprobar las horas extra de la fábrica
summary: Cómo el responsable de producción planifica y aprueba las horas extra en Daily hours, para un artesano o varios a la vez, elige entre pagarlas o darlas como tiempo libre a cambio, y cómo se ven en Payroll las horas extra de fin de semana, el TOIL y las horas extra rechazadas.
date: 2026-11-10
source_date: 2026-11-10
tags: production, payroll, overtime, artisan
category: production
---

<aside class="tldr">
En <b>Factory → Artisans → Daily hours</b>, pulsa <b>Plan overtime</b> (planificar horas extra) para indicar las horas previstas de un día y aprobarlas para uno o varios artesanos, o marca los días que se pasaron del turno y pulsa <b>Overtime for … selected</b>. Elige <b>Paid</b> (pagadas) o <b>Time off in lieu</b> (tiempo libre a cambio). Las horas extra de sábado y domingo aprobadas como pagadas se pagan a 1.5 × Band 0; las de entre semana se pagan como horas normales. El tiempo libre a cambio no se paga: pasa al saldo TOIL del artesano. Las horas extra que nadie ha aprobado ni rechazado aparecen señaladas en Payroll, y los responsables reciben un aviso el lunes por la mañana, antes de que la semana de pago se cierre al final del lunes.
</aside>

## Qué cuenta como hora extra

Hora extra es el tiempo que un artesano ha fichado por encima de su turno de ese día (el turno de su horario de trabajo, menos la pausa no pagada). En un sábado o domingo sin turno, todas las horas fichadas son horas extra. Los festivos no se tratan como fines de semana.

## Planificar las horas extra con antelación

1. Abre **Factory → Artisans → Daily hours** y pulsa **Plan overtime**.
2. Elige el **Day** (día) y los **Artisans** (puedes elegir varios a la vez).
3. Indica **Planned from** (previsto desde) y **Planned to** (previsto hasta), por ejemplo 07:00 y 12:00.
4. Elige **Approve overtime** (aprobar horas extra) y luego **Paid** o **Time off in lieu**.
5. Pulsa **Save** (guardar).

Cada artesano recibe su propio registro con quién lo aprobó, cuándo y las horas previstas. Cuando ya han fichado, **Daily hours** muestra las horas previstas junto a las fichadas. Si alguien ficha la entrada antes, o la salida después, de las horas previstas y del turno por más de la tolerancia, el día muestra **Outside planned times** (fuera de lo previsto) y vuelve a quedar señalado hasta que lo revises y guardes de nuevo las horas extra. Solo las horas previstas se pagan como horas extra o se suman como TOIL: el tiempo fichado por encima se paga como horas normales hasta que vuelvas a guardar las horas extra después del día.

## Aprobar las horas extra después del día

Los días que superan el turno en más de la tolerancia muestran **Over shift time** (por encima del turno). Puedes pulsar **Approve or not** (aprobar o no) en el día, o marcar varios días y pulsar **Overtime for … selected** para resolverlos de una vez. Aquí las horas previstas son opcionales.

## No autorizar las horas extra

Elige **Do not authorise** (no autorizar) e indica el motivo. Las horas no se descuentan del pago: se quedan en **Payroll**, marcadas como **Overtime not authorised** con tu motivo, para que el contable y RR. HH. decidan qué hacer.

## Pagadas o tiempo libre a cambio

- **Paid, sábado o domingo**: cada hora extra se paga a 1.5 × la tarifa de Band 0 (1.5 × £12.71 = £19.07 la hora), sea cual sea el destajo. Estas horas no llevan pago por bandas ni bonus Guru. Cuando cambia la tarifa de Band 0, la tarifa de fin de semana la sigue desde la misma fecha.
- **Paid, entre semana**: se pagan como horas fichadas normales, con las bandas del destajo como siempre.
- **Time off in lieu (TOIL)**: las horas extra no se pagan. Se suman al saldo TOIL del artesano, multiplicadas por la proporción TOIL.

## El saldo TOIL

Pulsa **Time off in lieu** en Daily hours para ver lo que se le debe a cada artesano y para **Record TOIL taken** (registrar TOIL disfrutado: artesano, día y horas). No se puede registrar más que el saldo. El responsable de producción o el contable fija **TOIL hours per overtime hour** (horas de TOIL por hora extra) en la misma ventana (1 por defecto). Las horas extra ya dadas como TOIL conservan la proporción con la que se aprobaron.

## Qué ve el contable en Payroll

- **Weekend overtime** (horas extra de fin de semana): las horas y el importe a 1.5 × Band 0, incluidos en el total. El CSV también da la tarifa y el reparto por bandas de esas horas, solo a título informativo.
- **TOIL hours (not paid)** y **TOIL balance**: el CSV añade las horas debidas, el TOIL disfrutado en el periodo y el saldo al final.
- **Overtime not authorised** y **Overtime awaiting decision** (pendientes de decisión) junto al nombre del artesano, y como horas en el CSV.

La página Payroll y el CSV no deciden cómo cuenta el TOIL para el salario mínimo: eso le corresponde al contable.

## El aviso del lunes

El lunes por la mañana, los responsables de la fábrica reciben un correo con todos los días de la semana pasada cuyas horas extra nadie ha aprobado ni rechazado todavía, con un enlace a esos días en Daily hours. La semana de pago se cierra al final del lunes; después, solo el contable puede cambiarla, indicando un motivo.
