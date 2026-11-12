---
title: Ayudar a los clientes a encontrar productos en tu web
summary: Dos interruptores en los ajustes de la web ayudan a los clientes profesionales a encontrar qué tener en stock. El asesor de gama responde una pregunta escrita en el cuadro de búsqueda con productos y un motivo para cada uno, y Keep exploring añade búsquedas relacionadas, familias y productos bajo cada búsqueda.
date: 2026-11-12
source_date: 2026-11-12
tags: website, search, shop, crm
category: marketing
---

<aside class="tldr">
Abre <b>Website</b> en el menú de la tienda y haz clic en el icono de deslizadores entre <b>Export</b> (exportar) y <b>Workshop</b> (taller). Aquí importan dos secciones. <b>AI shopping assistant</b> (asistente de compra con IA) activa el asesor de gama: un cliente profesional con sesión iniciada puede preguntar qué necesita su tienda y recibe productos con un motivo de una línea cada uno. <b>Search page</b> (página de búsqueda) activa <b>Keep exploring</b> (sigue explorando): búsquedas relacionadas, familias que se venden bien juntas, más vendidos, novedades y los favoritos olvidados del cliente bajo los resultados de la búsqueda, también cuando una búsqueda no encuentra nada. Ambos están desactivados hasta que los actives, para cada web por separado. Para ver qué aportan, abre <b>Analytics</b> (analítica) en el menú de la web y después <b>AI Assistant</b> (asistente de IA).
</aside>

## Activarlos

1. Ve a **Website** en el menú de la tienda.
2. Haz clic en el icono de deslizadores de arriba a la derecha, entre **Export** y **Workshop**. Se abren los ajustes de la web.
3. Abre **AI shopping assistant** o **Search page** en la lista de la izquierda.
4. Activa el interruptor y pulsa el icono de guardar que hay junto a él.

El cambio llega a la web en unos diez minutos. Cada web tiene sus propios interruptores, así que puedes probar primero con una web.

## El asesor de gama

El asesor de gama es un asistente que sugiere qué productos debería tener en stock un minorista. Solo se muestra a los clientes que han iniciado sesión en la web de una tienda mayorista. Los visitantes que no han iniciado sesión no lo ven.

En **AI shopping assistant** hay tres campos:

- **AI shopping assistant** lo activa para esta web.
- **Assistant name** (nombre del asistente) es el nombre que ven los clientes, de hasta 30 caracteres. Si se deja vacío, la web usa el nombre definido para todo el grupo, que es "Aura" salvo que alguien lo haya cambiado en **Sysadmin → Settings → AI shopping assistant**. Cámbialo allí para renombrar al asesor en todas las webs que no tengan nombre propio.
- **Monthly AI budget** (presupuesto mensual de IA) es lo máximo que la web puede gastar en respuestas en un mes natural, en dólares estadounidenses. Si se deja vacío, son US$30. La pequeña "i" junto al campo muestra lo gastado este mes.

Antes de activarlo por primera vez en una tienda, pide al help desk que prepare el catálogo de esa tienda para ello. El asesor encuentra productos por su significado, no solo por las palabras del nombre, y eso requiere una preparación única de los productos de la tienda.

### Qué ve el cliente

- Un botón **Ask** (preguntar) con el nombre del asistente dentro del cuadro de búsqueda.
- Cuando una búsqueda se lee como una pregunta o una necesidad, una oferta para preguntar al asesor: en el desplegable de búsqueda y en la parte superior de la página de resultados. Una búsqueda cuenta como pregunta cuando tiene cuatro palabras o más, o un signo de interrogación. La misma oferta aparece cuando una búsqueda no encuentra nada.
- Una página de conversación. El cliente describe su tienda y lo que necesita, y el asesor responde con una frase corta y dos pestañas de productos, **Must-haves** (imprescindibles) y **Add-ons to your range** (complementos para tu gama). Los productos son las tarjetas de producto habituales, así que el cliente puede añadirlos a la cesta desde ahí, y cada tarjeta lleva un motivo de una línea como "Popular with shops like yours in your area" (popular entre tiendas como la tuya en tu zona), "You used to order this" (solías pedir esto) o "Matches what you asked for" (coincide con lo que pediste).
- Una respuesta muestra hasta 8 productos en cada pestaña, y hasta 12 cuando el cliente pide más o una selección más amplia.
- **Add all to basket** (añadir todo a la cesta), bajo cada respuesta, pone en la cesta una unidad de cada producto mostrado y no toca los que ya están. El cliente también puede pedirlo con palabras ("añádelos a mi cesta") y el asesor hace lo mismo, solo con productos que ha mostrado en esa conversación. Las cantidades se cambian en las tarjetas de producto o en la cesta.
- Un cliente puede pedir al asesor que borre la conversación. Entonces desaparece de su pantalla y no puede volver a abrirla. El registro se conserva en aiku: en la pestaña **Conversations** su estado es **Cleared by customer** (borrada por el cliente), y la transcripción muestra lo que el asesor añadió a la cesta y que el cliente pidió borrarla.
- El asesor encuentra un producto o una familia entera por su código, aunque se escriba de forma aproximada ("tiba 04", "bbskt"), y avisa cuando el producto pedido está agotado.
- Si se le piden los más baratos o los más caros, muestra los productos ordenados por precio. Sigue sin escribir precios: los muestran las tarjetas.
- En preguntas sobre Navidad y regalos añade los productos que se vendieron sobre todo entre septiembre y Navidad del año pasado, marcados "Sold well last Christmas" (se vendió bien la Navidad pasada), lleven o no Navidad en el nombre.
- Si se le pregunta cuánto se tarda en enviar los pedidos, responde con la cifra real de la tienda en los últimos 30 días. Si se le pregunta cuánto cuesta el envío, da el envío de la cesta actual del cliente, tal como se calcula en la cesta. Ambas frases las escribe aiku a partir de los datos, no la IA.
- Bajo la respuesta, botones con preguntas de seguimiento, un cuadro para escribir otra pregunta y **Start a new conversation** (empezar una conversación nueva).

Las búsquedas normales por palabra o por código de producto no cambian y no se ralentizan.

### Qué sabe y qué nunca dice

El asesor responde a partir de cuatro cosas: el catálogo de la tienda, los más vendidos de los últimos 12 meses entre clientes como el que pregunta, los productos que ese cliente todavía no ha probado y los productos que antes pedía y dejó de pedir. "Clientes como el que pregunta" significa primero el mismo tipo de tienda, en la misma zona, luego el mismo país y después todos.

Si el cliente dice qué tipo de tienda tiene, el asesor lo guarda como su **Shop type** (tipo de tienda). En **CRM → Customers → Shop types** cuenta en **Told us** (nos lo ha dicho). Consulta <a href="/docs/looking-after-customers-es">Cuidar de los clientes</a> para los tipos de tienda.

Está hecho para cumplir estas reglas:

- Solo sugiere productos que están activos en la web y en stock.
- No escribe precios, descuentos ni niveles de stock en sus frases. El precio está en la tarjeta de producto, como en el resto de la web.
- No dice nada sobre lo que compra ningún otro cliente, y no se muestran recuentos de compradores ni cifras de ventas.
- Responde en el idioma de la tienda.

### Mejores precios y pedidos grandes

Cuando un cliente pide un mejor precio o un descuento, o dice que algo es demasiado caro, el asesor no negocia. Muestra productos más baratos del tipo que pidió y añade tres cosas calculadas con nuestros datos, nunca por la IA:

- si el cliente es miembro de **Gold Reward** y hasta cuándo, o cómo hacerse miembro (un pedido facturado le hace miembro durante 30 días);
- una amnistía de precios para miembros, cuando la tienda tiene una activa;
- el importe de pedido a partir del cual nuestro equipo llama para hablar de mejores condiciones.

Después pregunta cuánto piensa pedir. Cuando el pedido previsto llega a ese importe, el asesor lo pasa enseguida, incluso antes de que el cliente acepte la llamada, para que un pedido grande nunca se pierda. Ofrece la llamada, pregunta si usar el teléfono de su cuenta u otro y cuándo le viene bien, y añade la respuesta al mismo aviso. Nunca dice quién llamará ni promete un descuento.

Cada aviso es una tarea de prioridad alta para el equipo de **atención al cliente** de la organización, enlazada al cliente, con el importe previsto, el teléfono, la mejor hora y un enlace a la conversación. Todo el equipo recibe el aviso en aiku. Los responsables de oportunidades de venta de la organización están en la tarea y reciben el aviso por email, Slack y notificación del navegador (cada uno puede cambiarlo en su perfil). Mientras nadie haya cogido la tarea, se les recuerda a las 4 horas y después una vez al día, en días laborables entre las 09:00 y las 18:00 de la zona horaria de la organización.

Lo configuran los administradores del sistema en **Organisations**: se abre la organización, se pulsa **Edit** y se va a **Sales leads**.

- **Sales leads manager** (responsable de oportunidades): quién recibe cada aviso. Si se deja vacío, lo reciben los supervisores de atención al cliente y, si no hay ninguno, todos los administradores de la organización. Conviene indicar un responsable para que los avisos lleguen a la persona adecuada y no a todos los administradores.
- **Big order from (EUR)** (pedido grande desde): el importe de pedido previsto a partir del cual se ofrece la llamada, convertido a la moneda de cada tienda. Si se deja vacío son 2.000 EUR, más o menos el mayor de cada 100 pedidos.
- **Sales leads Slack channel** (canal de Slack): dónde se publica cada aviso y sus recordatorios. El bot de aiku tiene que ser miembro del canal.

### Cuándo vuelve un producto

Cuando un cliente pregunta cuándo vuelve un producto agotado, el asesor mira lo que hay pedido. Solo da un periodo cuando la fecha es fiable: una entrega en camino con fecha prevista aún por llegar, o un pedido hecho en los últimos 9 meses cuya llegada prevista aún no ha pasado. Dice "previsto entre el 1 y el 15 de diciembre", nunca un día exacto ni una cantidad. Muchos pedidos no tienen fecha prevista y algunos antiguos nunca se cerraron, así que a menudo no hay fecha fiable.

Entonces el asesor lo dice y ofrece que nuestro equipo de compras se ponga en contacto con el cliente. Solo si el cliente acepta, la pregunta se convierte en una tarea para las personas de compras de la organización, enlazada al producto, con el cliente, su pregunta y lo que muestran nuestros pedidos. Reciben el aviso por email, Slack y notificación del navegador, y un recordatorio mientras nadie la coja, como los pedidos grandes.

Para responder, escriba la respuesta para el cliente en la nota al marcar la tarea como hecha (o como último mensaje de la tarea antes de marcarla). Se traduce al idioma del cliente, se añade a su conversación con el asesor y se le envía con el email de notificación de chat de la tienda, con un enlace a la conversación. En una tienda donde ese email no está activado, atención al cliente recibe una tarea para enviar la respuesta. Si la tarea se marca como hecha sin respuesta, no se envía nada y la tarea lo indica.

Lo configuran los administradores del sistema en **Organisations**: se abre la organización, se pulsa **Edit** y se va a **Restock questions**. El interruptor **Ask purchasing when there is no reliable date** está activado por defecto; desactivado, el asesor solo responde con nuestros pedidos. **Restock questions go to** elige quién las recibe; si se deja vacío, van a los compradores de la organización.

### Límites y qué se registra

Cada cliente puede hacer 6 preguntas por minuto, 40 por hora y 120 al día. Una respuesta tarda unos segundos y la página espera. Cada respuesta es uso de IA de pago, así que activa el asesor donde quieras que los clientes lo usen.

Cuando el coste de IA del mes llega al **Monthly AI budget**, el asesor se pausa en esa web hasta el primer día del mes siguiente: el botón **Ask** deja de ofrecerse y no se aceptan preguntas nuevas. Una respuesta que ya está en camino llega igualmente. Sube el presupuesto y el asesor vuelve en uno o dos minutos. Si varios clientes preguntan en el mismo momento, el gasto puede superar el presupuesto ligeramente.

Cada conversación se guarda, con los productos que mostró el asesor, los que el cliente pulsó y los que añadió a la cesta dentro de las 24 horas siguientes a verlos. También se registra el aumento de la cantidad de un producto que mostró el asesor, aparte de las adiciones. Cada llamada a la IA se registra con su coste y lo que tardó, y también cada pregunta rechazada por los límites de arriba o por el presupuesto, con el motivo.

## Keep exploring

**Search page → Keep exploring suggestions on the search page** (sugerencias de Keep exploring en la página de búsqueda) añade un bloque bajo los resultados de cada búsqueda de la web, para visitantes y para clientes con sesión iniciada. Cuando una búsqueda no encuentra nada, el bloque queda justo bajo "We couldn't find any suitable results" (no hemos encontrado resultados adecuados), así que el cliente siempre tiene adónde ir después.

De arriba abajo muestra, cada parte solo cuando hay algo que mostrar:

- **Did you mean** (¿quisiste decir?), solo en una búsqueda sin resultados, cuando la palabra es una ortografía cercana a una búsqueda que hicieron muchos clientes y que encontró productos. "beeswax" ofrece "bees wax".
- **Related searches** (búsquedas relacionadas): lo que otros clientes buscaron en la misma visita que esta búsqueda. Cuando no hay ninguna, el encabezado dice **Popular searches** (búsquedas populares) y lista los términos más buscados de la web.
- **Sells well with** (se vende bien con): las familias definidas como relacionadas con las familias que encontró la búsqueda.
- Pestañas de productos: **Best sellers** (más vendidos), **New arrivals** (novedades) y **Your forgotten favourites** (tus favoritos olvidados).

### De dónde sale cada sugerencia

- **Las búsquedas relacionadas y populares** salen de las búsquedas de los últimos 180 días en esta web que encontraron productos. Solo se cuentan las búsquedas de clientes con sesión iniciada: un término necesita 5 clientes distintos para ser popular y 3 clientes distintos para estar relacionado con otro. Los términos con un dígito o una @ nunca se muestran, así que los códigos de producto, los números de pedido y las direcciones de correo se quedan fuera.
- **Best sellers** son los productos que más clientes compraron en los últimos 12 meses. Un cliente con sesión iniciada recibe los más vendidos entre clientes como él, y la pestaña dice **Best sellers for shops like yours** (más vendidos para tiendas como la tuya) solo cuando todos los productos que contiene vienen de tiendas de su mismo tipo de tienda. Los visitantes reciben los más vendidos de toda la tienda.
- **New arrivals** son los productos en sus primeros 30 días en la web, los más nuevos primero.
- **Your forgotten favourites** solo se muestra a un cliente con sesión iniciada: productos que pidió antes y que no ha pedido en los últimos 12 meses.

Todo producto mostrado está activo y en stock en ese momento. Las cifras de ventas que hay detrás de los más vendidos y de los favoritos olvidados se reconstruyen cada noche, las búsquedas populares una vez al día, y las sugerencias para un término de búsqueda se guardan durante una hora.

El bloque se carga después de los resultados de la búsqueda, así que la búsqueda en sí es tan rápida como antes.

### Qué se registra

Cada búsqueda registra qué sugerencias se ofrecieron y si el bloque llegó a verse en la pantalla del cliente. Se registra cada clic, en un resultado o en una sugerencia, con el tipo de sugerencia que era (did you mean, búsqueda relacionada o popular, familia, best sellers, new arrivals o forgotten favourites) y si fue en el producto o en un botón de su tarjeta, hasta 20 clics por búsqueda. Cada búsqueda registra también si el botón **Ask** se ofreció a ese cliente. Las búsquedas de las cuentas de personal no se registran en absoluto, así que prueba con una cuenta de cliente que no sea de personal si quieres ver tus propios clics.

## Ver cómo funciona

Ve a **Website** en el menú de la tienda, luego a **Analytics** en el menú superior y a **AI Assistant**. La página informa de una web. Quienes se ocupan de todas las webs del grupo también la encuentran en **WebOps → AI Assistant**, para todas las webs juntas y con un filtro de web.

Arriba eliges las fechas y puedes acotar las cifras por idioma, por dónde se abrió la conversación (cuadro de búsqueda, página de perfil o cuadro de chat), por tipo de tienda y por país. Cada cifra se compara con el periodo de la misma duración inmediatamente anterior, que nombra la línea bajo los filtros. Las conversaciones de las cuentas de personal quedan fuera.

### La pestaña Overview

- **Conversations** (conversaciones), con el número de clientes y cuántos de ellos volvieron.
- **Orders after being shown** (pedidos tras mostrarse) y **Revenue of the products shown** (ingresos de los productos mostrados): un pedido cuenta cuando el cliente pidió un producto que el asistente le mostró, dentro de los 7 días siguientes a verlo. Solo se suman las líneas de esos productos, nunca el pedido entero, y cada línea se cuenta una vez. Los más vendidos y los favoritos de antes se vuelven a pedir de todos modos, así que léelo como "pedido tras mostrarse", no como prueba de que el asistente causó el pedido.
- Bajo ambas, la cifra **strict** (estricta) cuenta solo las líneas que el cliente añadió a la cesta directamente desde el asistente. Es lo mínimo que puede ser la cifra real.
- **Conversion** (conversión) es la proporción de conversaciones seguidas de un pedido así. Deja fuera las conversaciones de los últimos 7 días, porque todavía pueden convertirse en pedidos. Una nota ámbar indica desde qué día las cifras aún no son definitivas.
- **AI cost** (coste de IA), en total, por conversación y como proporción de los ingresos.
- **AI budget this month** (presupuesto de IA de este mes) muestra, por cada web con el asesor activado, lo gastado frente a su presupuesto mensual. La barra se vuelve ámbar a partir del 80% y roja con **Paused until** (en pausa hasta) y la fecha cuando se agota el presupuesto.
- **Passed on to the team** (pasado al equipo) lista los pedidos grandes y las preguntas de reposición del periodo, primero los abiertos: el cliente, el pedido previsto o el producto, si pidió una llamada o cómo le llegó la respuesta, el estado de la tarea y quién la cogió. Lo que nadie ha cogido sale en ámbar con cuántos recordatorios se han enviado.

**Funnel** (embudo) sigue las conversaciones paso a paso: productos mostrados, pulsados, añadidos a la cesta y pedidos, cada uno con su proporción respecto al paso anterior y respecto a todas las conversaciones. **Ordered** (pedidos) puede ser mayor que **Added to basket** (añadidos a la cesta), porque también cuenta productos que llegaron a la cesta de otra forma. El gráfico de al lado muestra los mismos pasos por día, o por semana en un periodo largo.

**Where customers leave** (dónde se van los clientes) cuenta las conversaciones según el número de preguntas hechas. **Engagement and value** (participación y valor) da el tiempo y los mensajes medios de una conversación, el pedido medio de los clientes que usaron el asistente frente a los demás clientes, y las familias que un cliente pidió por primera vez después de que el asistente se las mostrara. **Families suggested** (familias sugeridas) lista las familias mostradas con más frecuencia, con cuántas conversaciones las pulsaron, las añadieron y las pidieron.

**Search on the website** (búsqueda en la web) cubre Keep exploring: las búsquedas del periodo, las que no tuvieron resultados, cuántas búsquedas recibieron sugerencias y cuántos clientes las vieron, y cuántas búsquedas sin resultados fueron seguidas de un clic en una sugerencia. Para cada tipo de sugerencia muestra cuántas veces se ofreció, los clics y la tasa. Solo sigue las fechas, la web y el idioma.

**From the search box to a question** (del cuadro de búsqueda a una pregunta) muestra en cuántas búsquedas se ofreció el botón **Ask**, en cuántas de ellas se mostró la oferta de preguntar, y cuántas conversaciones se abrieron desde el cuadro de búsqueda, con las preguntas rechazadas por los límites o por el presupuesto debajo.

Las cifras principales y los pasos del embudo son enlaces: abren las conversaciones que hay detrás.

### La pestaña Conversations

Una fila por conversación: cuándo empezó, el cliente, desde dónde se abrió, la primera pregunta, el número de mensajes, los productos mostrados, pulsados y añadidos, si siguió un pedido y por cuánto, y el coste de IA. Busca por cliente o por las palabras de una pregunta.

Haz clic en la primera pregunta para leer la conversación. Bajo cada respuesta ves los productos que mostró con el motivo dado, cuáles pulsó, añadió y pidió el cliente, y las preguntas de seguimiento que se ofrecieron. Las conversaciones son solo para el personal: solo las personas que pueden ver la web en aiku pueden abrirlas.
