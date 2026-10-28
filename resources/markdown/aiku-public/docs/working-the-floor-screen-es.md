---
title: Trabajar la pantalla de planta
summary: La guía del artesano - la pantalla Mis tareas, la lista de trabajos que se actualiza sola, START y DONE, qué pulsar cuando hiciste menos de lo pedido, y tomarte un descanso.
date: 2026-10-23
source_date: 2026-10-23
tags: production, floor, artisan
category: production
series: Ordering from partners
order: 9
---

<aside class="tldr">
Para las personas que fabrican. <b>Factory → Jobs</b> (Fábrica → Trabajos) es todo tu día en una sola pantalla: los trabajos asignados a ti a la izquierda, el que estás haciendo en el medio. Pulsa <b>START</b> (Empezar), hazlo, escribe cuántos hiciste, pulsa <b>DONE</b> (Terminado). Si hiciste menos de lo pedido, la pantalla te hace una sola pregunta: pausar y seguir más tarde, cerrar el trabajo y pasar el resto a uno nuevo, o terminarlo aquí. No hay nada más que rellenar.
</aside>

## La pantalla

La página está organizada como una bandeja de entrada.

**Izquierda, siempre visible:** la lista. Dos contadores arriba - unidades hechas hoy y tareas terminadas hoy. Debajo, <b>Your jobs</b> (Tus trabajos), el trabajo asignado a ti: trabajos enteros con tu nombre, y pasos sueltos que el supervisor te dio en el plan del día (ver [Planificar el día de producción](/docs/planning-the-production-day-es)). Un trabajo puede tener su fabricación con una persona y su embalaje con otra; cada una ve solo su propio paso. Si tu puesto te permite coger trabajos del grupo abierto, sigue una sección <b>Open jobs</b> (Trabajos abiertos). Al final, <b>Finished today</b> (Terminado hoy), lo que ya has cerrado.

**Derecha:** lo que hayas seleccionado. Antes de empezar, son los detalles del trabajo con un botón grande <b>START</b>. Una vez empezado, es la tarjeta de trabajo con el reloj.

La lista no necesita actualizarse a mano. Cuando un planificador te asigna algo, cuando un compañero empieza uno de los trabajos abiertos, cuando tú cierras uno, la lista cambia sola, en todas las pantallas que muestran esa fábrica.

## Una fila de la lista

Cada fila es una tarea de una orden de trabajo:

| Línea | Significado |
| --- | --- |
| Código | el producto a fabricar - SKO-01 |
| Nombre | su nombre |
| Tarea · orden de trabajo | el paso (Production, Labelling...) y la referencia de la orden de trabajo |
| 0/25 | hecho hasta ahora / pedido |

Una fila con una ▶ verde y una hora es la que estás trabajando, y cuándo la empezaste. Una nota ámbar bajo una fila significa que espera una mezcla, o que otra persona ya la tiene abierta.

## Hacer un trabajo

1. Toca la fila. Los detalles aparecen a la derecha.
2. Pulsa <b>START</b> (Empezar). El reloj arranca y la fila recibe su marca verde.
3. Fabrícalo.
4. Escribe el número que hiciste en <b>Quantity made</b> (Cantidad hecha). Si eres capataz o superior, hay también una casilla <b>Rejected</b> (Rechazado) para las piezas que no pasaron.
5. Pulsa <b>DONE</b> (Terminado).

¿Hiciste todo lo que se pedía? Eso es todo. La tarea se cierra, la orden de trabajo termina y se avisa al almacén de que hay algo que guardar - ver [Guardar la producción terminada](/docs/putting-away-finished-production-es).

## Pasos que se cuentan en paquetes

Algunos pasos, sobre todo el empaquetado, se cuentan en paquetes y no en piezas sueltas: una bandeja de 8 bombas de baño cuenta como 1. La pantalla dice entonces <b>Counted in PACKS of 8</b> (Se cuenta en PAQUETES de 8) en ámbar, y la casilla se llama <b>Packs of 8 made</b> (Paquetes de 8 hechos). Escribe cuántos paquetes completos llenaste: para 56 bombas de baño en 7 bandejas escribe 7, no 56. Debajo de la casilla la pantalla muestra lo que significa tu número, <i>7 packs of 8 = 56 × ABB-06a</i>, y la confirmación lo muestra otra vez. Solo se aceptan paquetes enteros; la única excepción es el número exacto que queda por hacer, que se rellena tocando el número grande. Cuando un encargado tiene que autorizar el exceso, la ventana muestra el exceso en paquetes y en piezas antes de aprobar.

## Cuando hiciste menos de lo pedido

Escribe el número real y pulsa <b>DONE</b>. La fila de entrada se sustituye por un panel corto: *19 hechos · 6 pendientes* y cuatro botones.

- <b>Pause</b> (Pausar) - misma mezcla, sigues más tarde. El trabajo queda abierto con la misma referencia y el mismo código de lote, y la cuenta sigue desde 19. Úsalo cuando el día termina antes que la mezcla.
- <b>Close at 19 and move remaining 6 to new job …</b> (Cerrar en 19 y pasar los 6 restantes a un trabajo nuevo) - el trabajo se cierra en 19 y los 6 pasan a una nueva orden de trabajo con la letra siguiente (0127 pasa a 0127a) y su propio código de lote. Úsalo cuando el resto será una mezcla nueva. La pantalla te pide confirmar con <b>Yes, close and move</b> (Sí, cerrar y pasar), porque un trabajo cerrado no se puede continuar.
- <b>Job finished</b> (Trabajo terminado) - el trabajo se cierra en 19 y ahí acaba. El planificador ve una orden de trabajo que pedía 25 y consiguió 19.
- <b>Back</b> (Atrás) - cambiar el número.

Un trabajo pasado a uno nuevo muestra <b>Continued from 0127</b> (Continuación de 0127) en su tarjeta, y el de origen muestra <b>Continued in 0127a</b> (Continuado en 0127a). El trabajo nuevo sigue con las mismas personas y en el plan del mismo día.

## Cuando hiciste más de lo pedido

Escribe el número real y pulsa **DONE**. La pantalla avisa de las unidades por encima del objetivo y abre **Autorización del responsable para sobreproducción**.

- **Escanear la tarjeta** - un responsable o supervisor enseña a la cámara su QR personal (el mismo con el que ficha). Se acepta en cuanto se lee. **Cambiar cámara** alterna entre la cámara delantera y la trasera.
- **Usar PIN del responsable** - si la cámara no lo lee, el responsable escribe su PIN de fichaje.

Solo puede autorizar quien dirige esta planta, y nunca su propio trabajo. Tras cinco tarjetas o PIN erróneos la ventana se bloquea 15 minutos.

Una vez autorizado, el trabajo crece a lo realmente hecho: el almacén guarda todo, lo que ningún pedido pidió va a stock y las materias primas se descuentan por el lote entero. Los pasos siguientes del mismo trabajo crecen también. La página de la orden de trabajo muestra quién hizo el extra, quién lo autorizó, cómo y cuándo.

Un trabajo pasado a otro día es la excepción. Ahí ningún responsable puede autorizar que un paso supere lo que hizo el paso anterior en esa parte: si Making terminó 138 allí, Labelling se queda en 138, y la pantalla indica la otra parte del trabajo (por ejemplo JOaroma-0152a) donde se hizo el resto y donde hay que anotarlo.

¿Se escribió un número equivocado, por ejemplo piezas donde se pedían paquetes? Un encargado abre la pestaña <b>Performance</b> (Rendimiento), busca la entrada bajo el trabajador y pulsa <b>Correct quantity</b> (Corregir cantidad). La entrada solo se puede bajar. El trabajo vuelve a lo que realmente se hizo, el exceso autorizado se reduce con él y el pago de esa entrada se calcula de nuevo. Funciona hasta que el almacén empieza a guardar el trabajo.

## Un lote para varias líneas

A veces un mismo lote sirve a más de un trabajo. Por ejemplo, 100 panes HCS-48 y 100 panes cortados SLHCS-48 con el mismo aroma se mezclan, se vierten y se moldean juntos como 200 panes.

**Combinar (responsables).** Toca una de las líneas en la pantalla de planta. Bajo sus detalles, **Combine Production with other lines into one batch** lista las otras líneas abiertas que esperan el mismo paso. Marca las que van en el mismo lote y pulsa **Combine**. No se ofrece una línea que ya esté combinada, terminada o en la que alguien esté trabajando.

**Trabajarlo (artesanos).** Las líneas se ven ahora como una sola fila con un 🔗 y los códigos juntos, *HCS-48 + SLHCS-48*, y el total, *0/200*. Los detalles listan cada línea con su orden de trabajo. Pulsa **START** una vez, haz el lote, escribe el total hecho - 200 - y pulsa **DONE** una vez.

**Qué hace Aiku con ello.** El total se reparte entre las líneas según lo que le faltaba por hacer a cada una, en unidades enteras: 100 para HCS-48, 100 para SLHCS-48. El tiempo se reparte igual. Después cada línea sigue con sus propios pasos siguientes - encogido para una, cortado y luego encogido para la otra. La página de la orden de trabajo muestra el paso como *One batch with* la otra línea.

**El pago y los objetivos** se calculan sobre el lote en conjunto: 200 panes en las horas que llevó, frente al objetivo del paso. Combinar paga exactamente lo mismo que lotes separados a la misma velocidad. Cuando las líneas tienen objetivos distintos para el paso, el objetivo del lote es el que tarda las mismas horas que los lotes separados.

Hacer más de lo que pidieron todas las líneas requiere la tarjeta o el PIN de un responsable, como arriba; el extra también se reparte entre las líneas. Un responsable puede **Separate** las líneas de nuevo siempre que nadie esté trabajando en ellas. Lo que ya se hizo junto se queda con cada línea.

## La mezcla y otros pasos que se pesan en kg

La mezcla es un paso propio al principio de la receta, y se cuenta en kg, no en piezas. La pantalla muestra **kg** junto a los números y la casilla se llama **Kg made** (Kg hechos): para una mezcla de 50 kg escribe 50. Debajo de la casilla la pantalla muestra lo que da, *50 kg of mix = 500 × ABB-06a*. Una mezcla que sale un poco por encima de lo pedido se acepta tal cual, sin encargado.

Solo los artesanos nombrados en el paso lo ven y pueden empezarlo; no aparece en la lista de nadie más. Se paga por hora a la tarifa base: sin objetivo, sin tramo de pago y sin aviso de bajo objetivo, y el tiempo sigue contando en el coste del producto.

El paso siguiente queda bloqueado con **Waiting for** (Esperando) y el nombre del paso de mezcla hasta que se haya registrado algo de mezcla, y se abre en cuanto se anota la primera. Una mezcla para varias líneas funciona como en la sección anterior: un encargado combina las líneas de mezcla, quien mezcla escribe los kg una vez y cada línea recibe su parte.

**Mezclas en lotes enteros.** Cuando el producto tiene un tamaño de lote de mezcla (25 kg o 50 kg), los kg pedidos se redondean hacia arriba a lotes enteros y se muestran como lotes: 94,1 kg en lotes de 50 kg se muestra *2 × 50 kg batches = 100 kg* (2 lotes de 50 kg = 100 kg). La casilla se llama **Batches of 50 kg made** (Lotes de 50 kg hechos) y se escribe el número de lotes, 2, no los kg. En una mezcla combinada el redondeo se hace sobre el total de todas las líneas. Lo que sobra respecto a lo pedido no cambia el trabajo, las cantidades de formado ni la paga. Los encargados lo ponen en el paso de la receta del producto, en **Mix batch size** (Tamaño de lote de mezcla).

**Configuración (encargados).** En la página de la tarea de fabricación, **Only these artisans can do it** (Solo estos artesanos pueden hacerla) lista quién puede hacerla; ahí se añade o se quita gente. Su formulario de edición tiene **Paid by piece rate** (Pago a destajo; apagado para trabajo por horas) y **Recorded in kg** (Registrado en kg). Los kg de mezcla que lleva una unidad van en el paso de la receta de cada producto.

## Tomarte un descanso

Bajo la zona de trabajo hay una fila **Break:** (Descanso) con **5m**, **20m** y **30m**. Toca la duración que vas a tomar y confirma con **Yes, start break** (Sí, empezar descanso).

- Si has fichado la entrada, el descanso te ficha la salida.
- Si hay un trabajo abierto, su reloj se detiene durante el descanso: el tiempo de descanso no cuenta como tiempo en el trabajo.
- La pantalla pasa a ser un panel grande **On break** (En descanso): el tiempo desde que empezó el descanso, **Planned:** (Previsto) con la duración que elegiste, y un botón grande **Finish break** (Terminar descanso).

**El descanso no termina solo.** Cuando vuelvas a tu puesto, pulsa **Finish break**. Eso te ficha de nuevo la entrada y registra cuánto duró de verdad el descanso. Fichar con tu código QR en el quiosco también termina el descanso.

Cuando un descanso supera la duración prevista, el panel indica cuánto se ha pasado. Si se pasa en más de unos pocos minutos (5, salvo que el responsable de producción lo haya cambiado), el panel se pone rojo y el descanso aparece como excedido para los supervisores y el responsable en la pestaña **Performance** (Rendimiento).

Los artesanos que trabajan por horas no tienen botones de descanso. Hacen sus descansos fichando la salida con su código QR, y el trabajo sigue cuando vuelven a fichar la entrada.

**Para supervisores y responsables.** La pestaña **Performance** tiene una tabla **Breaks** (Descansos): por cada artesano y día, el número de descansos, los minutos previstos, los minutos realmente tomados y los minutos por encima de lo previsto. Marca **Breaks over plan only** (Solo descansos por encima de lo previsto) y pulsa **Apply** (Aplicar) para ver solo los descansos que se pasaron. Un recuadro rojo arriba lista los descansos que se están pasando ahora mismo y los que siguen abiertos después de terminar el turno. Para un descanso que quedó abierto tras el turno, pulsa **Set end** (Fijar fin), escribe la hora a la que el artesano volvió de verdad y guarda; las horas y el pago de los trabajos que el descanso solapa se calculan de nuevo, y se conserva el fin registrado primero. Los fichajes no se cambian ahí: se corrigen en Human Resources. El responsable de producción fija la tolerancia con el engranaje junto al título **Breaks**.

## Cosas que conviene saber

- **Un trabajo a la vez.** Mientras tienes un trabajo abierto, los botones START están desactivados. Ciérralo primero.
- **Tu pago por la tarea** sigue el número en Quantity made, como se explica en [Puestos de fábrica](/docs/factory-positions-es). Las piezas rechazadas no se pagan.
- **Open jobs** (Trabajos abiertos) son trabajos y pasos sin nadie asignado, o con otra persona asignada. Coger uno está bien si tu puesto lo permite; a partir de entonces es tuyo hasta que se cierre.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Tu pantalla:</b> tu organización → <b>Factory</b> → <b>Jobs</b>.</li>
<li><b>Empezar:</b> toca la fila → <b>START</b>.</li>
<li><b>Terminar:</b> escribe <b>Quantity made</b> → <b>DONE</b>.</li>
<li><b>Menos de lo pedido:</b> <b>DONE</b> → <b>Pause</b>, <b>Close at … and move remaining …</b> o <b>Job finished</b>.</li>
<li><b>Tomarte un descanso:</b> <b>Break:</b> <b>5m</b>, <b>20m</b> o <b>30m</b> → <b>Yes, start break</b>. Al volver: <b>Finish break</b>.</li>
<li><b>Descanso que quedó abierto tras el turno (supervisores):</b> <b>Factory</b> → <b>Artisans</b> → <b>Performance</b> → <b>Set end</b>.</li>
<li><b>Un lote para varias líneas:</b> toca una línea → <b>Combine … with other lines into one batch</b> → marca las líneas → <b>Combine</b>. Para deshacer: toca la fila combinada → <b>Separate</b>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Los puestos se asignan en la ficha del empleado en Human Resources y llevan los permisos consigo.</li>
<li>Ver tus propios trabajos, START y DONE: el puesto <b>Operative</b> para la fábrica.</li>
<li>Ver y coger <b>Open jobs</b>, registrar rechazos: <b>Foreman</b>, <b>Mix preparer</b> o <b>Floor supervisor</b>.</li>
<li>Combinar y separar líneas: los responsables que dirigen la planta, las mismas personas que pueden autorizar la sobreproducción.</li>
</ul>
</aside>
