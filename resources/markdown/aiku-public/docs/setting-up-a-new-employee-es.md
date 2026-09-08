---
title: Dar de alta a un nuevo empleado
summary: Añade un nuevo empleado a aiku — sus datos personales, condiciones laborales y puestos de trabajo — y, si lo necesita, dale su propio acceso desde el mismo formulario.
date: 2026-08-31
source_date: 2026-08-31
tags: hr
category: hr
---

<aside class="tldr">
Toda persona que trabaja en tu organización — en nómina o no — tiene un registro de <em>employee</em> en aiku. Lo creas una vez, en <b>HR → Employees → Create Employee</b>, y todo lo demás cuelga de ahí: su contrato, sus partes de horas, su PIN de fichaje y (si rellenas la última sección del formulario) el usuario y la contraseña con los que entrará en aiku.
</aside>

## Antes de empezar

Ten a mano tres cosas: el nombre completo de la persona, un **worker number** (tu propia referencia interna de personal — tiene que ser único dentro de la organización) y un **alias** — un apodo corto que el resto de aiku usa para referirse a ella, así que elige algo reconocible como `maria` o `j.smith`. Si va a entrar en aiku, decide también su nombre de usuario y una contraseña inicial.

## Crear el empleado

Abre tu organización, ve a **HR → Employees** y pulsa **Create Employee**. El formulario es una sola página con cinco secciones; solo unos pocos campos son obligatorios, y puedes volver más tarde a completar el resto desde **Edit**.

### Personal information

Aquí solo es obligatorio el **Name**. El resto — fecha de nacimiento, correo personal, teléfono, dirección, contacto de emergencia, documento de identidad y notas libres — merece la pena rellenarlo mientras tienes el papeleo delante, pero nada te impide guardar sin ello.

### Employment

Esta sección es donde están los campos obligatorios:

- **Worker Type** — ¿es esta persona un *employee*, un *volunteer* o un *temporal worker*? Esto describe qué es para la organización, no su horario.
- **Employment Type** — *full time* o *part time*.
- **Worker number** y **Alias** — las referencias únicas descritas arriba.
- **Work email** — su correo de empresa, si tiene uno. Si le das acceso más abajo, este pasa a ser el correo de su cuenta de usuario.
- **State** — elige **Hired** para alguien que ya ha firmado pero empieza en una fecha futura, o **Working** para alguien que ya está trabajando. (Más adelante el registro puede pasar a *Leaving* y finalmente a *Left* — a quien se va nunca se le borra, así que su historial queda intacto.)
- **Employment start at** — su primer día.

### Job

- **Job Title** — texto libre; el campo sugiere títulos ya usados en otros sitios para que tu nomenclatura sea coherente.
- **Position** — este es el importante. Los positions son los roles de la lista de puestos de tu organización, y son los que deciden **qué puede ver y hacer la persona en aiku**. Algunos puestos aplican a toda la organización; otros están acotados, así que eliges *qué* shops, fulfilments o warehouses cubre el rol — un supervisor de shop para un único shop, un picker en un warehouse pero no en otro. Una persona puede tener varios puestos a la vez. Si va a entrar en aiku, elige esto con cuidado: sus menús se construyen a partir de ellos.

### Contract

Opcional: una fecha de inicio y fin de contrato más sus **annual leave days**. Si indicas una fecha de inicio de contrato, aiku archiva un registro de contrato propiamente dicho, que luego encuentras en la pestaña **Contracts** del empleado — junto con cualquier contrato futuro a medida que cambien las condiciones.

### User credentials

Si dejas esta sección en blanco, la persona existe en HR pero no tiene acceso — correcto para personal de almacén o de tienda que solo va a usar una máquina de fichaje. Rellena un **Username** y una **Password** y aiku crea su cuenta de usuario en el mismo momento en que crea el empleado. La cuenta toma su nombre y su work email automáticamente, y la primera vez que entra aiku le obliga a elegir una contraseña nueva propia — así que la que escribes aquí solo abre la puerta, no es un secreto que haya que proteger para siempre.

## Qué pasa al guardar

Al pulsar guardar aterrizas en la página del nuevo empleado, y varias cosas ya han pasado por detrás:

- Aparece en la lista de **HR → Employees**, en tus cifras de plantilla y en las listas de puestos de los roles que le hayas dado.
- Si su state es **Working**, aiku ya le ha emitido un **PIN de fichaje**, así que puede fichar entrada y salida en una máquina de fichaje desde el primer día. Puedes ver el PIN — o generar uno nuevo — desde su página de empleado.
- Sus **timesheets** empiezan a acumularse en cuanto fiche por primera vez, en la pestaña **Timesheets** del empleado.
- Si le creaste un acceso, puede entrar de inmediato con el usuario y la contraseña que fijaste (cambiando la contraseña al primer intento), viendo exactamente lo que sus puestos permiten.

Cualquier cosa que te hayas saltado — dirección, fechas de contrato, puestos adicionales — está a un clic de distancia en **Edit**, en su página.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Añadir a alguien:</b> tu organización → <b>HR → Employees</b> → <b>Create Employee</b>.</li>
<li><b>Corregir o terminar más tarde:</b> abre el empleado → <b>Edit</b>. Contracts, timesheets y positions tienen sus propias pestañas en la página del empleado.</li>
<li><b>PIN de fichaje:</b> en la página del empleado — véelo o regenéralo ahí.</li>
<li><b>Varias altas a la vez:</b> la lista de Employees también ofrece una plantilla de hoja de cálculo que puedes descargar, rellenar y subir.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>Crear y editar empleados requiere permisos de <b>HR edit</b> en la organización — normalmente el rol de HR o un administrador de la organización.</li>
</ul>
</aside>
