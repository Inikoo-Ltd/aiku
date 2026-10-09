---
title: Configurar la producción con tu asistente de IA
summary: Pide al asistente de IA que conectas a aiku que cree artefactos, materias primas y tareas, fije costes unitarios, y dé a un artefacto, a una lista o a familias enteras sus pasos de fabricación e ingredientes. Siempre te muestra el cambio primero, y todos los cambios se pueden deshacer.
date: 2026-10-08
source_date: 2026-10-08
tags: production, crafts, ai
category: production
---

<aside class="tldr">
Para quien diseña lo que fabrica la fábrica. Cuando un administrador lo activa para ti, el asistente de IA que conectas a aiku puede hacer por ti, con palabras sencillas, el trabajo de configuración de las páginas de <b>Crafts</b> (artesanía). Puede crear y editar <b>artefactos</b>, <b>materias primas</b> y <b>tareas de fabricación</b>, fijar <b>costes unitarios</b> y dar a los artefactos su <b>receta</b>: los pasos en orden, cuánto de cada paso cuenta un artefacto, el objetivo por hora y las materias primas que usa cada paso. Puede hacerlo para un artefacto, una lista o familias enteras a la vez. Siempre te muestra lo que va a cambiar y solo guarda después de que digas que sí. Cada cambio queda registrado con tus palabras y se puede deshacer.
</aside>

## Para qué sirve

Configurar a mano una nueva línea de producto supone muchos clics: crear las tareas, crear las materias primas, crear los artefactos, abrir cada uno, añadir cada paso, añadir cada ingrediente. Con el asistente describes el resultado:

> *"Da a todos los bálsamos labiales ACLB estos pasos: verter, etiquetar, empaquetar. El empaquetado se cuenta en cajas de seis."*

El asistente deduce de qué artefactos se trata, te muestra el plan y, cuando confirmas, hace exactamente los mismos cambios que harían las páginas de Crafts, con las mismas reglas. Las órdenes de trabajo abiertas que todavía no se han recibido incorporan los pasos nuevos de inmediato, igual que cuando cambias los pasos a mano.

Funciona en cualquier asistente que pueda conectarse a aiku: Claude, ChatGPT o similares. Háblale en el idioma que quieras. Los códigos como `ACLB-01`, `POUR` o `RAWM-03` se quedan tal cual.

## Antes de empezar

**1. Pide a un administrador que lo active.** En tu cuenta de usuario, en <b>Access</b> (acceso), activa <b>Can connect AI assistant</b> (puede conectar un asistente de IA) y después <b>Can set up artefacts, raw materials and recipes through their AI assistant</b> (puede configurar artefactos, materias primas y recetas mediante su asistente de IA). Sin el segundo interruptor el asistente aún puede leer informes, pero no puede cambiar nada en producción. Si se lo pides, te lo dirá.

**2. También tienes que ser una de las personas que configuran esa fábrica.** El interruptor solo no basta. Con él, el asistente cambia una fábrica únicamente para:

- **administradores del grupo**;
- **administradores de la organización** de la fábrica;
- quien tenga un puesto de producción que pueda **editar** esa fábrica;
- **administradores de tienda y dependientes** (shop admins y shopkeepers) de las tiendas de la organización de la fábrica (para awa: AROMA, ACAR, ACFE, ARFE y EZC), porque venden lo que fabrica.

Quienes tienen un puesto de producción que solo puede ver la fábrica todavía pueden pedirle al asistente que les *muestre* recetas y costes.

**3. Conecta tu asistente a aiku.** Añade aiku como conector en tu asistente con la dirección `https://app.aiku.io/mcp/aiku` e inicia sesión con tu cuenta de aiku cuando te lo pida. Esto se hace una sola vez.

**4. Conoce el código de tu fábrica.** Cada petición tiene que decir de qué fábrica se trata, por ejemplo `awa`. Si nombras una que no existe o a la que no tienes acceso, el asistente responde con la lista de fábricas a las que sí tienes acceso.

## Cómo transcurre una conversación

Cada cambio sigue los mismos tres pasos:

1. **Pides** con tus propias palabras.
2. **El asistente te muestra el plan**: qué artefactos, qué pasos en qué orden, los números, los ingredientes. Si falta algo (una tarea que todavía no existe, un código de familia que coincide con dos familias), te lo dice antes de cambiar nada.
3. **Confirmas** con tus propias palabras: *"sí, adelante"*. Solo entonces guarda. El asistente pasa tu petición a aiku y se almacena junto con el cambio, para que cualquiera pueda ver después qué se pidió.

Si dices *"no, PACK debería ser 0.25"*, corrige el plan y vuelve a preguntar. No se guarda nada hasta que estés de acuerdo.

<aside class="tip">
Pide al asistente que <b>muestre antes de cambiar</b> siempre que tengas dudas: <i>"Muéstrame primero la receta actual de ACLB-01."</i> Leer nunca cambia nada.
</aside>

## Las palabras que usa aiku

Las verás en las respuestas del asistente.

| Palabra | Qué significa | Ejemplo |
|---|---|---|
| **Artefacto** | Algo que fabrica la fábrica. Cada artefacto pertenece a un SKO del almacén. | `ACLB-01`, una lata de bálsamo labial |
| **Familia** | Un grupo de artefactos similares. | `ACLB`, bálsamos labiales A&C |
| **Tarea de fabricación** | Un tipo de trabajo que la gente registra en las tabletas. | `POUR` Vertido, `LABEL` Etiquetado (+ tapas), `PACK` Empaquetado |
| **Paso** | Una tarea colocada en la receta de un artefacto, con su posición. | paso 1 POUR, paso 2 LABEL, paso 3 PACK |
| **Unidades por artefacto** | Cuánto de un paso cuenta un artefacto. | 1 para una lata; 0.1667 cuando el empaquetado se cuenta en cajas de seis |
| **Objetivo por hora** | Cuántas unidades de ese paso debería hacer una persona en una hora. | POUR 216 latas, PACK 11 cajas |
| **Materia prima** | Aquello de lo que está hecho un artefacto, con su unidad y su coste unitario. | `RAWM-03`, una cera en kilogramos |
| **Cantidad por artefacto** | Cuánto de una materia prima usa un artefacto, en la unidad de la materia prima. | 0.0129 kg de cera por lata |
| **Coste de materiales** | La suma de cantidad × coste unitario de todas las materias primas de la receta, por artefacto. | 0.0757 |

### Unidades por artefacto, paso a paso

Las tabletas cuentan el trabajo en la unidad del paso. En los bálsamos labiales, quienes vierten y etiquetan cuentan latas, y quienes empaquetan cuentan cajas de seis. Una lata es una unidad de POUR y una unidad de LABEL, pero solo una sexta parte de una caja, así que:

| Paso | Unidades por artefacto | Por qué |
|---|---|---|
| POUR | 1 | una lata vertida |
| LABEL | 1 | una lata etiquetada |
| PACK | 0.1667 | una lata es 1/6 de una caja de seis |

Una orden de trabajo de 600 latas pide entonces 600 POUR, 600 LABEL y 100 PACK. Se guardan hasta seis decimales, así que 0.1667 se almacena exactamente.

El **objetivo por hora** está en la misma unidad que el paso: PACK a 11 significa 11 cajas por hora, no 11 latas.

## Consultar datos

Leer es seguro. Úsalo todo lo que quieras.

> *"Muéstrame la receta y el coste de materiales de ACLB-01 y ACLB-03 en awa."*

El asistente lista los pasos de cada artefacto con las unidades por artefacto, el objetivo por hora, cada materia prima con su cantidad, coste unitario y coste de línea, y el coste total de materiales.

> *"¿Qué artefactos hay en la familia ACLB?"*

> *"Lista las tareas de fabricación de awa."*

> *"Busca materias primas con 'cera de abeja' en la descripción."*

> *"¿Cuánto cuesta ahora RAWM-03 y está vinculada a un SKO?"*

> *"Muéstrame el artefacto ABB-05a: su SKO, familia, tamaño de lote y vida útil."*

## Tareas de fabricación

Las tareas son compartidas por toda la fábrica, así que crea cada una una vez y úsala en tantas recetas como quieras.

**Crear tareas**

> *"Crea tres tareas de fabricación en awa: POUR 'Vertido', LABEL 'Etiquetado (+ tapas)', PACK 'Empaquetado'."*

**Renombrar o describir una tarea**

> *"Cambia el nombre de la tarea LABEL a 'Etiquetado y tapado' y añade la descripción 'La etiqueta va antes que la tapa'."*

**Desactivar una tarea**

> *"Pon la tarea OLDPACK como inactiva."*

Una tarea inactiva permanece en las recetas que ya la usan. Para quitarla de esas recetas, cambia sus pasos (ver más abajo).

## Materias primas

**Crear una**

> *"Crea una materia prima RAWM-90 en awa: tipo stock, descripción 'Manteca de karité', unidad kilogram, coste unitario 6.40."*

El tipo es uno de *stock*, *consumable* (consumible) o *intermediate* (intermedio). La unidad es una de *unit* (unidad), *pack* (paquete), *carton* (caja), *liter* (litro) o *kilogram* (kilogramo). Los costes están en la moneda de tu organización, por unidad.

**Cambiar un coste**

> *"La manteca de karité RAWM-90 ahora cuesta 6.85 por kilo."*

**Vincular una materia prima a su SKO**

> *"Vincula RAWM-90 al SKO SHEA-25."*

Cuando una materia prima está vinculada a un SKO, su coste unitario procede del proveedor preferido de ese SKO y sigue por sí solo cada cambio de precio del proveedor. El asistente se niega entonces a fijar un coste a mano, porque se sobrescribiría. Cambia en su lugar el coste del proveedor, o desvincula primero el SKO si el coste realmente debe fijarse a mano.

**Corregir una descripción o una unidad**

> *"RAWM-05 está en gramos en la hoja, pero aiku tiene kilogram. Deja kilogram y cambia la descripción a 'Aceite de caléndula (kg)'."*

## Artefactos

### Un artefacto nuevo cuyo SKO ya existe

> *"Crea el artefacto ACLB-14 'Bálsamo labial Mango' en awa, familia ACLB, vinculado al SKO ACLB-14, tamaño de lote 120, vida útil 730 días."*

La unidad comercial se toma automáticamente del SKO cuando el SKO tiene exactamente una.

### Un artefacto nuevo con un SKO nuevo

Un artefacto siempre necesita su SKO. Uno creado sin él deja un artefacto a medias que luego estorba al verdadero. Si el SKO todavía no existe, pide al asistente que lo cree al mismo tiempo:

> *"Crea el artefacto ACLB-15 'Bálsamo labial Cereza', familia ACLB, y crea también su SKO: una lata por SKO."*

El asistente crea, de una sola vez:

- el **stock** y su **unidad comercial**, ambos con el código del artefacto, para todo el grupo;
- el **SKO** en tu organización;
- el **artefacto**, vinculado a ambos.

El número de unidades por SKO es lo único que necesita de ti. Una lata por SKO es *units 1* (unidades 1). Un SKO que es una caja de seis latas es *units 6*.

### Editar artefactos

> *"Fija el tamaño de lote de ACLB-01 en 240."*

> *"La vida útil de ABB-01a es de 540 días."*

> *"Mueve ACLB-14 a la familia ACLB-NEW."*

> *"Marca ACLB-08 como discontinued."*

El estado es uno de *in_process* (en proceso), *active* (activo), *dormant* (inactivo) o *discontinued* (discontinuado).

<aside class="tip">
Para el mismo cambio sencillo en muchos artefactos a la vez (tamaño de lote, vida útil, estado, familia), la barra de la lista de artefactos suele ser más rápida. Consulta <a href="/docs/changing-many-artefacts-at-once-es">Cambiar muchos artefactos a la vez</a>. El asistente destaca en las recetas, donde cada artefacto necesita varios pasos e ingredientes.
</aside>

## Recetas: pasos e ingredientes

Un cambio de receta siempre **reemplaza la receta completa** de los artefactos que nombras:

- los pasos que das se añaden, o se actualizan si el artefacto ya los tiene;
- los pasos que tiene el artefacto y que **no** están en tu lista se **eliminan, junto con sus materias primas**;
- cada paso debe indicar sus unidades por artefacto y su objetivo por hora (o "sin objetivo"). El asistente copia los valores actuales de los pasos que no cambias, para que no se restablezca nada por accidente.

### Un artefacto

> *"Da a ACLB-01 estos pasos: 1 POUR, 1 por lata, objetivo 216 por hora; 2 LABEL, 1 por lata, objetivo 236; 3 PACK, contado en cajas de seis, objetivo 11 cajas."*

### Una lista de artefactos

Esta es la petición con la que se configuró la línea de bálsamos labiales:

> *"Para ACLB-01, ACLB-03, ACLB-04, ACLB-05, ACLB-06, ACLB-07, ACLB-08_, ACLB-09, ACLB-10_, ACLB-12_ y ACLB-13_: quita el paso PROD Making y añade POUR 1 por artefacto a 216 por hora, LABEL 1 a 236, PACK 0.1667 a 11 cajas de seis."*

El asistente muestra los once artefactos con sus nuevos pasos:

| Artefacto | Paso 1 | Paso 2 | Paso 3 |
|---|---|---|---|
| ACLB-01 | POUR ×1 @ 216/h | LABEL ×1 @ 236/h | PACK ×0.1667 @ 11/h |
| ACLB-03 | POUR ×1 @ 216/h | LABEL ×1 @ 236/h | PACK ×0.1667 @ 11/h |
| … | … | … | … |
| ACLB-13_ | POUR ×1 @ 216/h | LABEL ×1 @ 236/h | PACK ×0.1667 @ 11/h |

Después de tu sí, guarda los once a la vez. No hace falta que nombres PROD: desaparece porque no está en la nueva lista. Pero mira la advertencia sobre las materias primas más abajo.

### Familias enteras a la vez

No necesitas listar los artefactos. Nombra la familia:

> *"Da a todos los artefactos de la familia ABB estos pasos: MIX 1 a 40 por hora, MOULD 1 a 120, WRAP 1 a 200, PACK contado en cajas de doce a 9."*

El asistente toma todos los artefactos de la familia que **no estén discontinuados**, y te dice cuántos son y cuáles antes de guardar.

**Varias familias**

> *"Los mismos pasos para las familias ABB y ABBL."*

**Una familia excepto algunos artefactos**

> *"Toda la familia ACLB excepto ACLB-13_, que se hace de otra forma."*

**Una familia más algunos artefactos de otro sitio**

> *"La familia ABB y también ABBTH-01 y ABBTH-02."*

**Familias que comparten código**

Dos familias pueden tener el mismo código. En awa, *ACLB* es tanto los bálsamos labiales de venta al público como los probadores. El asistente se detiene entonces y pregunta a cuál te refieres, mostrando cada una con su nombre, número de artefactos y código del primer artefacto, por ejemplo:

- `agnes-cat-aclb`: bálsamos labiales A&C, 11 artefactos empezando por ACLB-01
- `washes-lotions-aclb`: bálsamos labiales A&C, 11 artefactos empezando por TACLB-01

Responde con la que quieras (*"la que empieza por ACLB-01"*) y continúa con la familia correcta. No se cambia nada mientras haya dudas.

Se pueden hacer hasta 200 artefactos de una vez. Para más, hazlo familia por familia.

### Pasos con ingredientes

Las materias primas pertenecen a un paso: la cera se usa al verter, la caja al empaquetar. Da a cada paso sus ingredientes con la cantidad **por artefacto**:

> *"Para la familia ACLB: POUR usa RAWM-03 0.0129 kg y RAWM-05 0.0016 kg; LABEL usa BOKG-03 0.0003 y FLKG-06 0.0002; PACK usa CST-427 0.1667 (una caja por cada seis latas). Mantén las mismas unidades y objetivos."*

El asistente muestra el coste de materiales que tendrá cada artefacto, para que lo compruebes antes de decir que sí.

### Artefactos similares con un ingrediente distinto

Los sabores, las fragancias y los colores suelen compartir todos los pasos y diferir en un ingrediente. Di lo que es común y luego lo que difiere:

> *"Toda la familia ACLB: POUR usa cera RAWM-03 0.0129 y aceite RAWM-05 0.0016. Excepto ACLB-01, que usa RAWM-06 0.0006 en lugar de RAWM-05, y ACLB-07, que usa RAWM-07 0.0016 en lugar de RAWM-05. LABEL y PACK como antes."*

El asistente da a todos la lista común. Para ACLB-01 y ACLB-07 usa su propia lista completa para ese paso: la cera más su propio aceite. El plan que muestra tiene una línea por artefacto, así que puedes ver que ACLB-01 tiene RAWM-06 y no RAWM-05.

<aside class="tip">
Cuando las diferencias son grandes (distinto número de pasos, otras tareas), haz esos artefactos en una petición aparte, o déjalos fuera de la petición de familia con <i>excepto</i>.
</aside>

### Cambiar un solo número

Como una receta siempre se reemplaza entera, un cambio pequeño igualmente envía todos los pasos. El asistente lo hace por ti: lee la receta actual y cambia solo lo que has pedido.

> *"Sube el objetivo de PACK de la familia ACLB a 12 cajas por hora, todo lo demás como está."*

> *"En ACLB-05, POUR usa 0.0135 de RAWM-03 en lugar de 0.0129."*

Revisa el plan que muestra. Todos los demás números deben ser los mismos que antes.

### Añadir un paso en medio

> *"Añade un paso CURE entre POUR y LABEL para toda la familia ACLB: 1 por lata, sin objetivo."*

El asistente renumera los pasos: 1 POUR, 2 CURE, 3 LABEL, 4 PACK.

### Quitar un paso

> *"Quita LABEL de ACLB-12_, deja el resto."*

LABEL y sus materias primas se eliminan de ACLB-12_. La tarea en sí permanece en la fábrica para otras recetas.

### Reemplazar un paso que tiene ingredientes

<aside class="warning">
Cuando se quita un paso, también se quitan las materias primas que tiene. Muchos artefactos traídos del sistema antiguo llevan toda su lista de ingredientes en un único paso <b>PROD Making</b>. En awa, por ejemplo, siete de los bálsamos labiales ACLB tienen seis materias primas en PROD. Reemplazar PROD por POUR, LABEL y PACK sin decir adónde van esas seis dejaría a esos artefactos <b>sin ingredientes</b>, así que el stock dejaría de consumirse cuando se reciban sus órdenes de trabajo.
</aside>

Antes de reemplazar un paso así, pregunta:

> *"Muéstrame las materias primas del paso PROD de la familia ACLB."*

Luego indica adónde va cada una en la misma petición que los pasos nuevos:

> *"Reemplaza PROD en la familia ACLB por POUR, LABEL y PACK como antes. Mueve las materias primas: RAWM-03, RAWM-05 y RAWM-06 a POUR, BOKG-03 y FLKG-06 a LABEL, CST-427 a PACK, mismas cantidades."*

Si de verdad quieres descartar los ingredientes y añadirlos más tarde, dilo: *"descarta los ingredientes, añadiré la receta más tarde"*. Si cambias de opinión, el cambio se puede deshacer (ver más abajo).

## Comprobaciones de seguridad

Algunos cambios son fáciles de hacer mal sin querer, así que aiku los detiene hasta que hayas visto lo que hacen. El asistente te muestra entonces un aviso y no cambia nada. Si lo lees y aun así quieres el cambio, dilo (*"sí, lo sé, adelante"*) y solo entonces se guarda.

| aiku se detiene y avisa cuando… | Ejemplo | Qué revisar |
|---|---|---|
| un cambio de receta **quitaría materias primas** de artefactos | sustituir PROD en ACLB-01 quita RAWM-03, RAWM-05, RAWM-06, BOKG-03, FLKG-06 y CST-427 | ¿Querías quitarlas, o deberían pasar a los pasos nuevos? |
| se cambia una **familia entera** | *"la familia ACLB"* resulta ser 11 artefactos, listados uno a uno | ¿Es la familia correcta y quedan fuera los testers? |
| un número parece una **errata** | unidades por artefacto 1667 en vez de 0.1667; un objetivo de 2.160 por hora; 500 kg de una materia prima por artefacto | ¿Está el punto decimal en su sitio? |
| un **coste unitario** cambia más de la mitad | RAWM-90 de 6.40 a 64.00 | ¿Por kilo o por gramo? |
| se **renombra** un artefacto | ACLB-14 pasa a ACLB-14M | Las etiquetas y hojas siguen llevando el código antiguo. |
| un artefacto que sigue en **órdenes de trabajo abiertas** pasa a dormant o discontinued | ACLB-05 está en dos órdenes de trabajo abiertas | Esas órdenes no se cancelan con esto. |

Un error se rechaza siempre: dar a un artefacto un **SKO que ya tiene otro artefacto**. Un SKO pertenece a un solo artefacto. Así es como acabó un ACLB-08 a medio hacer junto al ACLB-08_ real. Edita en su lugar el artefacto que ya tiene ese SKO.

## Deshacer un cambio

Cada cambio que hace el asistente queda registrado: quién lo pidió, cuándo, tus palabras exactas y cómo estaba antes y después.

> *"Deshaz el último cambio que hiciste."*

> *"¿Qué cambié hoy en producción?"*

> *"Revierte el cambio de receta de la familia ABB de esta mañana."*

El asistente lista los cambios y devuelve las cosas a como estaban, después de que confirmes.

Deshacer se rechaza en dos casos:

- **Se ha vuelto a cambiar desde entonces.** Si alguien editó el mismo artefacto después, a mano o con un asistente, deshacer se detiene en lugar de sobrescribir su trabajo. Revísalo y corrígelo a mano.
- **El cambio creó algo.** Un artefacto, materia prima, tarea o SKO nuevos no se eliminan al deshacer, porque ya puede haber órdenes de trabajo, recetas y stock colgando de ellos. Ponlo como discontinuado o inactivo en su lugar.

Puedes deshacer los cambios hechos en fábricas que tienes permitido configurar. Los administradores pueden ver y deshacer todos los cambios desde el registro de <b>AI changes</b> (cambios de IA).

## Pedir bien

| En lugar de | Di | Por qué |
|---|---|---|
| *"configura los bálsamos labiales"* | *"configura la familia ACLB en awa"* | un código no deja nada a la imaginación |
| *"el empaquetado es 6"* | *"el empaquetado se cuenta en cajas de seis"* | 6 por artefacto y 1/6 por artefacto son muy distintos |
| *"añade verter"* | *"añade POUR como paso 1, 1 por lata, 216 por hora, mantén los otros pasos"* | una receta se reemplaza entera |
| *"usa la cera"* | *"usa RAWM-03, 0.0129 kg por lata"* | la cantidad es por artefacto, en la unidad de la materia prima |
| *"sí"* a un plan largo que no leíste | lee la tabla y luego *"sí"* | el plan es lo que se guardará |

Buenos hábitos:

- **Lee antes de escribir.** *"Muéstrame primero la receta"* no cuesta nada.
- **Una familia por petición** cuando las familias necesitan pasos distintos.
- **Comprueba el recuento.** Si el asistente dice 22 artefactos y esperabas 11, probablemente un código también coincidió con los probadores.
- **Comprueba el coste de materiales** que muestra. Un coste diez veces mayor que el de sus vecinos suele significar una cantidad en gramos donde la unidad es kilogramos.

## Cuando el asistente dice que no

| Lo que dice | Qué hacer |
|---|---|
| La configuración de producción no está activada para este usuario | Pide a un administrador que active el ajuste de producción en tu cuenta. |
| Este usuario no puede configurar la producción … | No eres una de las personas que configuran esta fábrica (ver *Antes de empezar*). Pídeselo a un administrador. |
| No existe la tarea / materia prima … | Créala primero: *"crea la tarea CURE"*, y luego repite la petición. |
| Hay más de una familia con el código … | Elige la familia que lista, por su primer artefacto o por su nombre. |
| Un artefacto nuevo necesita su SKO | Nombra el SKO existente, o pídele que cree el SKO junto con el artefacto e indica cuántas unidades por SKO. |
| … está vinculada a un SKO, así que su coste unitario procede del proveedor preferido | Cambia el coste del producto del proveedor, o desvincula primero el SKO. |
| Una materia prima aparece dos veces en el paso … | Indícala una sola vez con las cantidades sumadas. |
| Son … artefactos; haz como máximo 200 por llamada | Hazlo familia por familia. |
| Esto se cambió de nuevo después del cambio de IA | Alguien lo ha editado desde entonces. Corrígelo a mano en las páginas de Crafts. |

## Lo que no hace

- No **elimina** artefactos, materias primas ni tareas. Discontinúalos o desactívalos en su lugar.
- No cambia las **bandas de pago** ni los niveles de recompensa de la fábrica. Los objetivos por hora son la base que multiplican esos niveles.
- No crea **órdenes de trabajo** ni cambia lo que se está fabricando hoy. Cambia lo que piden las próximas órdenes de trabajo y las abiertas.
- No toca los **probadores** ni ningún otro artefacto que no hayas nombrado o incluido mediante una familia.
- No puede ver ni cambiar fábricas a las que no tienes acceso.

<aside class="wayfinder"><strong>Dónde hacer clic en aiku</strong>
<ul>
<li><b>Activarlo para alguien (administradores):</b> <b>Sysadmin → Users</b> (administración del sistema → usuarios) → abre el usuario → <b>Edit</b> (editar) → <b>Access</b> (acceso) → activa <b>Can connect AI assistant</b>, y después <b>Can set up artefacts, raw materials and recipes through their AI assistant</b>.</li>
<li><b>Ver todos los cambios que hizo un asistente (administradores):</b> <b>Sysadmin</b> → el recuadro <b>AI insights</b> → <b>All queries & per-user stats</b> (todas las consultas y estadísticas por usuario) → <b>AI changes</b> (cambios de IA). Filtra por tipo <b>Artefact recipe</b> (receta de artefacto) o <b>Artefact, raw material or task</b> (artefacto, materia prima o tarea).</li>
<li><b>Comprobar una receta a mano:</b> tu organización → <b>Factory</b> (fábrica) → <b>Crafts</b> → <b>All artefacts</b> (todos los artefactos) → abre el artefacto → pestaña <b>Manufacture tasks</b> (tareas de fabricación).</li>
<li><b>Los mismos cambios sin el asistente:</b> <a href="/docs/changing-many-artefacts-at-once-es">Cambiar muchos artefactos a la vez</a>.</li>
</ul>
</aside>

<aside class="wayfinder"><strong>Permisos que necesitas</strong>
<ul>
<li>El interruptor <b>Can set up artefacts, raw materials and recipes through their AI assistant</b> en tu cuenta.</li>
<li>Para cambiar cualquier cosa: administrador del grupo, administrador de la organización, un puesto de producción que pueda editar esa fábrica, o administrador de tienda o dependiente en una de las tiendas de la organización de la fábrica.</li>
<li>Para solo ver recetas y costes: un puesto de producción que pueda ver esa fábrica.</li>
</ul>
</aside>
