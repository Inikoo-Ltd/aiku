---
title: Configurar una máquina de fichar
summary: Crea una máquina de fichar en unos pocos clics, pon su enlace de kiosco en una tablet o sus códigos QR en la pared, y sepa dónde acaba cada fichaje.
date: 2026-08-31
source_date: 2026-08-31
tags: hr, clocking
category: hr
series: Clocking in and out
order: 2
---

<aside class="tldr">
Crear una máquina requiere un nombre, un tipo y un centro de trabajo - ese es todo el formulario. La configuración real es el paso siguiente: para las máquinas <b>PIN</b>, <b>Barcode Scanner</b> y <b>Camera QR</b> generas un <b>enlace de kiosco</b> (kiosk link) y lo abres en la tablet que está junto a la puerta; para una máquina <b>QR Code</b> generas códigos QR imprimibles y los pegas en la pared. Cada fichaje aparece después bajo la máquina, el centro de trabajo y el propio parte de horas del empleado. ¿No sabes qué tipo quieres? Lee primero <a href="/docs/types-of-clocking-machines-es">la guía de tipos</a>.
</aside>

## Antes de empezar

Una máquina de fichar siempre pertenece a un **centro de trabajo** (workplace) - el sitio físico cuya puerta vigila. Si tu organización todavía no tiene ningún centro de trabajo, crea uno primero en **Human Resources → Working place**; el formulario de la máquina no te dejará saltarte este paso. Si solo hay un centro de trabajo, aiku lo preselecciona por ti.

## Crear la máquina

Ve a **Human Resources → Clocking machines** y pulsa el botón de crear **Clocking machine**. El formulario pide tres cosas:

- **Name** - cómo la llama tu equipo: "Puerta del almacén", "Recepción oficina", "Tablet nave de packing". Los nombres deben ser únicos dentro de la organización.
- **Type** - elige uno de los cuatro tipos habituales: **QR Code**, **PIN**, **Barcode Scanner** o **Camera QR Scanner**.
- **Workplace** - el centro de trabajo en el que vive.

Guarda, y la máquina aparece en la lista, ya activa. Eso es realmente todo - los códigos identificativos de la máquina se generan por ti entre bastidores.

## Conectar el dispositivo

Lo que ocurre a continuación depende del tipo que hayas elegido.

### PIN, Barcode Scanner y Camera QR: el enlace de kiosco

Estos tres funcionan en una tablet compartida, y la tablet llega hasta ahí a través de un **enlace de kiosco**. En la fila de la máquina en la lista de máquinas de fichar, pulsa el pequeño botón de **tablet**, y luego **Generate link**. aiku crea una dirección web privada solo para esta máquina; **Copy** para copiarla y ábrela en el navegador de la tablet que vas a dejar junto a la puerta.

La página de kiosco no necesita ningún inicio de sesión - el secreto está en el propio enlace - y muestra exactamente una cosa:

- un **teclado numérico** en una máquina PIN,
- un **campo de escaneo** en una máquina Barcode Scanner (conecta el lector de códigos de barras a la tablet),
- la **vista de cámara** en una máquina Camera QR.

El personal se acerca, teclea o escanea, y ve al instante una confirmación de entrada/salida fichada. Dos apuntes de mantenimiento: el botón **Regenerate** sustituye el enlace - el antiguo deja de funcionar de inmediato, que es exactamente lo que quieres si una tablet desaparece - y la columna **Kiosk** de la lista muestra de un vistazo si el método de kiosco de cada máquina está activado.

El PIN, el código de barras y el QR de cada empleado viven en su propia página **Employee Clocking** en aiku, así que no tienes nada que imprimir ni repartir salvo que quieras tarjetas identificativas.

### Máquinas QR Code: imprimir y pegar

Una máquina QR Code no tiene tablet alguna - el trabajo lo hace la pared. Abre la máquina y usa **Generate QR code**: dale al código una **label** ("Entrada principal", "Puerta de incendios") y aiku produce una imagen QR que puedes imprimir. Genera tantos como puertas tengas; la lista de códigos QR de la máquina muestra cada uno con su etiqueta y un interruptor **active**.

Un empleado escanea el código impreso con la cámara de su teléfono, aterriza en su página Employee Clocking en aiku, y ficha entrada o salida como él mismo. Si un código impreso queda comprometido - fotografiado, compartido, llevado a casa - puedes desactivarlo o **regenerate**arlo, y el impreso antiguo se niega educadamente: *"This QR Code is no longer active."*

En la página de **edit** de la máquina, una máquina QR Code gana ajustes adicionales: **Allow Coordinates Matching** activa la comprobación de ubicación, el **map picker** te deja marcar un punto en tu edificio, y **Radius (meters)** indica cuán cerca debe estar el teléfono. Una máquina QR Code también tiene una pestaña **Clocking policies**, donde se pueden fijar reglas de presencial / remoto / híbrido para las personas que la usan.

## Dónde aparecen los fichajes

Cada entrada y salida fichada, sea cual sea el método, se convierte en un registro de **fichaje** (clocking) que puedes ver desde tres ángulos:

- **La máquina:** abre la máquina → pestaña **Clockings** - todo lo que ha registrado ese dispositivo.
- **El centro de trabajo:** abre el centro de trabajo → su lista de fichajes - todo lo registrado en el sitio, en todas sus máquinas.
- **La persona:** el fichaje se empareja en el **timesheet** (parte de horas) del empleado, que es de donde salen las horas trabajadas.

Recursos Humanos también puede abrir cualquier fichaje individual para revisarlo o corregirlo - útil para la típica conversación de "se me olvidó fichar la salida".

## Gestión del día a día

La página de **edit** de la máquina reúne los controles habituales: renombrarla, cambiar su **status** entre Connected y Disconnected (una máquina desconectada se queda en la lista pero está en reposo), y activar o desactivar cada método de fichaje. Una máquina que ya ha cumplido su función se puede eliminar de la lista con la confirmación habitual - sus fichajes registrados se quedan en los partes de horas, porque el histórico es el histórico.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Crear una máquina:</b> tu organización → <b>Human Resources → Clocking machines</b> → botón de crear <b>Clocking machine</b>. O desde un centro: <b>Working place</b> → tu centro de trabajo → <b>Clocking machines</b> → crear.</li>
<li><b>Conseguir el enlace de la tablet:</b> en la fila de la máquina, el botón <b>tablet</b> → <b>Generate link</b> → <b>Copy</b> (solo máquinas PIN, Barcode y Camera QR).</li>
<li><b>Imprimir códigos QR:</b> abre una máquina QR Code → <b>Generate QR code</b>, dale una etiqueta; gestiona etiquetas y el interruptor active en su lista de códigos QR.</li>
<li><b>Comprobación de ubicación:</b> la página <b>edit</b> de la máquina → QR Settings → <b>Allow Coordinates Matching</b>, el pin del mapa y el radio.</li>
<li><b>Ver fichajes:</b> la pestaña <b>Clockings</b> de la máquina, la lista de fichajes del centro de trabajo, o el parte de horas del empleado.</li>
</ul>
<strong>Permisos que necesitas</strong>
<ul>
<li>Crear, editar y generar enlaces de kiosco o códigos QR necesita acceso de <b>edición</b> de Human Resources; ver máquinas y fichajes necesita acceso de <b>visualización</b> de Human Resources.</li>
</ul>
</aside>
