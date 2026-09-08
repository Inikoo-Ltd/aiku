---
title: Tipos de máquinas de fichaje
summary: Para qué sirve cada tipo de máquina de fichaje, cómo fichan entrada y salida los empleados con ella, y cómo elegir la adecuada para tu centro de trabajo.
date: 2026-08-31
source_date: 2026-08-31
tags: hr, clocking
category: hr
series: Clocking in and out
order: 1
---

<aside class="tldr">
Una <em>clocking machine</em> es lo que sea que tus empleados tocan para decir "estoy aquí" y "me voy" — una tablet junto a la puerta, un código QR impreso en la pared, un dispositivo dedicado. Cada máquina vive en un <b>workplace</b> y tiene un <b>type</b> que decide cómo se ficha con ella. Los cuatro tipos habituales — <b>PIN</b>, <b>Barcode Scanner</b>, <b>Camera QR</b> y <b>QR Code</b> — no necesitan más que una tablet o el propio teléfono de los empleados. Cada fichaje que registra llega automáticamente al parte de horas del empleado.
</aside>

## ¿Por qué máquinas?

aiku no solo quiere saber *que* alguien ha fichado — quiere saber *dónde* y *cómo*. Vincular cada fichaje a una máquina concreta en un workplace concreto hace que los partes de horas distingan la puerta del almacén de la puerta de la oficina, y puedes retirar un dispositivo sin perder el historial que registró. Por eso el primer paso siempre es "crear la máquina en aiku", incluso cuando la "máquina" no es más que un QR plastificado.

## Los tipos habituales

Estos cuatro son los que puedes crear tú mismo desde la lista de máquinas de fichaje, y ninguno necesita hardware especial.

### PIN

Una tablet compartida junto a la entrada muestra un teclado numérico. Cada empleado tiene su propio PIN personal corto; lo escribe y queda fichado como entrada — lo vuelve a escribir más tarde y queda fichado como salida. Los empleados pueden ver su propio PIN en la página **Employee Clocking** de aiku.

Mejor cuando: quieres el dispositivo compartido más simple posible y puedes confiar en que el personal no comparte PINs.

### Barcode Scanner

La misma tablet compartida, pero en lugar de escribir, cada empleado escanea su código de barras personal — que se muestra en su propia página **Employee Clocking**, así que puede vivir en la pantalla de su teléfono o imprimirse en una tarjeta. Un lector de código de barras USB o Bluetooth barato conectado a la tablet hace la lectura.

Mejor cuando: el personal ya lleva tarjetas identificativas, o quieres que fichar sea un pitido en vez de cuatro toques.

### Camera QR Scanner

De nuevo una tablet compartida, pero esta vez es la **cámara** de la propia tablet la que hace el trabajo: el empleado enseña el código QR de la página Employee Clocking de su teléfono, la cámara lo lee, listo. No hace falta lector adicional.

Mejor cuando: quieres fichaje rápido por escaneo con nada más que una tablet.

### QR Code

Este invierte la lógica: en vez de un dispositivo compartido leyendo el código del empleado, es el **propio teléfono del empleado** el que lee un código en la pared. Imprimes uno o varios códigos QR para la máquina y los pegas junto a la puerta; el empleado escanea uno con la cámara de su teléfono, que abre la página Employee Clocking de aiku, y — ya identificado como él mismo — ficha entrada o salida.

Una máquina QR Code tiene dos trucos que las demás no tienen:

- **Location matching.** Puedes exigir que el teléfono comparta su ubicación y solo aceptar fichajes dentro de un radio (en metros) de un punto que eliges en un mapa — así nadie ficha "en la oficina" desde su sofá.
- **Clocking policies.** Cada máquina puede llevar reglas que marcan el fichaje de un empleado como **onsite**, **remote** o **hybrid**, de modo que se puede permitir el teletrabajo a algunas personas sin desactivar el control de ubicación para todos.

Mejor cuando: no hay tablet de sobra, todo el personal tiene teléfono, o la gente trabaja repartida entre varias puertas y sedes.

## Cómo elegir

- Una puerta, una tablet de sobra → **PIN** para empezar; sube a **Camera QR** cuando cansen los toques.
- El personal lleva tarjetas o tienes un lector → **Barcode Scanner**.
- No hay ningún dispositivo de sobra → **QR Code** en la pared, y que los teléfonos hagan el trabajo.
- Necesitas asegurarte de que la gente está físicamente en el sitio → **QR Code** con location matching activado.

Elijas lo que elijas, nada es definitivo: el type de una máquina define cómo ficha la gente, pero todos los fichajes acaban en el mismo sitio — el parte de horas del empleado — y puedes añadir en cualquier momento una segunda máquina de otro tipo al mismo workplace.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Ver tus máquinas:</b> tu organización → <b>Human Resources → Clocking machines</b>. Cada fila muestra el type, su workplace, y si su kiosco está activo.</li>
<li><b>Máquinas de una sede:</b> <b>Human Resources → Working place</b> → abre el workplace → <b>Clocking machines</b>.</li>
<li><b>Dónde encuentra el personal su PIN, código de barras o QR:</b> la página <b>Employee Clocking</b> — cada empleado ve solo los métodos que tus máquinas tienen activados.</li>
</ul>
<strong>Permisos que necesitas</strong>
<ul>
<li>Ver máquinas requiere acceso de <b>view</b> en Human Resources; crearlas o editarlas requiere acceso de <b>edit</b> en Human Resources.</li>
</ul>
</aside>
