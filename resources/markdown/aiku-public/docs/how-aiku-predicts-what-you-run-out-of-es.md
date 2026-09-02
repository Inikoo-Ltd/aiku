---
title: Cómo predice aiku lo que se te va a acabar
summary: Qué significan de verdad "se acaba en ~12 días" y la cantidad sugerida, por qué un superventas sin stock pide tanto, y cuándo confiar en el número por encima de tu propio criterio.
date: 2026-09-01
source_date: 2026-09-01
tags: procurement, stock, intercompany, shopping-list
category: procurement
---

<aside class="tldr">
Para quien compra stock. Dos números acompañan a cada SKO por las pantallas de compra: <b>se acaba en ~N días</b> y una cantidad <b>sugerida</b>. Esta página explica de dónde salen, para que sepas cuándo aceptarlos y cuándo pasar por encima de ellos. Si solo quieres sacar un pedido, las guías prácticas son <a href="/docs/reading-the-partner-shopping-dashboard-es">el panel de compras</a> y <a href="/docs/buying-from-a-partner-es">la guía del comprador</a> — vuelve aquí cuando un número te parezca raro.
</aside>

## Los dos números

Dondequiera que estés comprando — las tarjetas de **Browse** de un socio, la **Shopping list**, el panel de un proveedor o agente, una propuesta de Auto-fill — el mismo par de números acompaña al artículo.

**Se acaba en ~N días** es lo que tienes en stock dividido entre la rapidez con la que aiku cree que se está yendo. Se pone en rojo a dos semanas o menos, en ámbar hasta un mes. "Se acaba ahora" significa que la estantería ya está vacía.

**Sugerida** es la cantidad que te llevaría hasta el próximo pedido y un poco más allá: lo suficiente para el plazo de entrega del proveedor, más el hueco hasta que normalmente volverías a pedir, más un colchón proporcional a lo errático que sea el artículo — y a eso se le resta lo que hay en la estantería y lo que ya viene de camino. Se redondea a unidades de envío completas, porque eso es lo que realmente puedes comprar.

Los dos números se actualizan solos cada vez que el stock se mueve, así que están al día cuando los miras, no son los de anoche.

## La idea que lo hace funcionar: los días vacíos no cuentan

La forma obvia de medir lo rápido que se vende algo es promediar sus ventas de los últimos tres meses. Ese método arruina un almacén en silencio.

Coge un artículo que se agotó en la primera semana y se quedó vacío el resto del trimestre. Promediado sobre noventa días parece que apenas se mueve — así que nunca se vuelve a pedir, así que sigue vacío, así que el trimestre siguiente parece aún peor. Cuanto mejor vende, más rápido desaparece, más invisible se vuelve. Casi todos los almacenes tienen unos cuantos artículos así, y suelen ser justo los que la gente está pidiendo.

Por eso aiku no promedia sobre el calendario. Reconstruye, día a día, si el artículo estuvo realmente disponible, y mide el ritmo de venta **solo en los días en que lo tuviste para vender**. Los días con la estantería vacía se tratan como días sin información — no como días sin demanda.

Esa única regla es la razón por la que un superventas a cero muestra un pedido sugerido grande en vez de pequeño. No es un fallo ni es el sistema entrando en pánico. Es el sistema viendo por fin la demanda que las semanas vacías estaban escondiendo.

## De dónde sale el número, y cuánto fiarte de él

No todos los artículos tienen la misma calidad de evidencia detrás, y ayuda saber en qué caso estás.

- **Su propio historial reciente.** El caso normal, y el que hay que fiarse. Los artículos estables reciben una estimación que sigue la tendencia; los artículos lentos e irregulares — los que salen de tres en tres cada varias semanas — se miden de otra forma, por lo grande que suele ser el pedido ocasional y lo largos que son los huecos de silencio, que es la manera honesta de describirlos.
- **Su propio historial más largo.** No hay suficiente movimiento reciente, pero el artículo tiene pasado. Razonable, algo más lento en reaccionar.
- **El mismo artículo en una organización hermana.** Tú apenas lo has vendido; en otra parte del grupo sí. aiku toma prestado su ritmo y lo divide entre dos, porque un mercado distinto es una pista, no una medición. Trátalo como punto de partida.
- **La familia a la que pertenece.** El caso más débil: una línea nueva sin historial en ningún sitio, estimada a partir de sus vecinos y muy rebajada. Esto es un sustituto de tu criterio mientras lo tengas, no un reemplazo de él.

También hay un ajuste estacional: aiku compara el mismo trimestre del año pasado contra la media de ese año y ajusta el ritmo al alza o a la baja, dentro de unos límites, para que un artículo navideño no se compre a su ritmo de agosto. Los límites importan — un trimestre atípico no puede desbocar el número por sí solo.

La regla de los días vacíos se aplica también aquí, y tiene que hacerlo. La Navidad pasada solo es evidencia sobre la Navidad si tenías el artículo para vender; un trimestre que pasaste sobre todo sin stock no dice nada de la temporada, solo del suministro. Así que cada trimestre se mide por día en que el artículo estuvo realmente disponible, y cualquier trimestre en el que estuviste sin stock más de la mitad del tiempo se descarta por completo de la comparación. Si eso deja menos de cuatro trimestres utilizables, aiku no hace ningún ajuste estacional en vez de hacer uno confiado construido sobre cuatro trimestres flojos.

## Por qué un número puede parecer equivocado (y a menudo lo está)

El pronóstico lee historial. Cualquier cosa que pase fuera de ese historial, no la puede conocer.

- **Un pedido grande puntual.** Un cliente que te deja la estantería vacía de golpe parece exactamente popularidad repentina. Pásalo por alto.
- **Una línea que estás descatalogando.** El historial dice que se vende; tu plan dice que pares. El sistema no conoce tu plan.
- **Una promoción, una foto de catálogo, una ficha de marketplace que se publica.** Demanda a punto de cambiar por un motivo que todavía no ha ocurrido.
- **Un producto totalmente nuevo.** Ver el caso de la familia más arriba — ese número es una estimación con cara de seguridad.
- **Algo que no se mueve nada pero vale dinero.** Cae en **Dead stock** del panel, y pide una decisión de una persona, no un reabastecimiento.

La norma general: el pronóstico es mejor que tú en el aburrido grueso del catálogo — cientos de artículos normales en los que nadie tiene tiempo de pensar — y peor que tú en cualquier cosa con una historia detrás. Deja que se ocupe del volumen, y dedica tu atención a las excepciones.

## Cómo leerlo en una propuesta de Auto-fill

Auto-fill ordena los candidatos por lo pronto que se te acaban y va rellenando primero los más urgentes hasta que se agota el presupuesto. Cada línea propuesta lleva su motivo en palabras claras — *"Our sales/quarter ~48 · our stock 0 · we run out now"* — que es el pronóstico enseñando su trabajo. Lee los motivos antes de confirmar; ahí es donde un número equivocado es más fácil de pillar, y desmarcar una línea es un solo clic. No se pide nada hasta que pulsas **Add items to shopping list**.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Ver los números por artículo:</b> <b>Procurement → Partners</b> (o <b>Suppliers</b>, o <b>Agents</b>) → abre uno → <b>Browse</b>: cada tarjeta muestra <i>our stock</i>, <i>our sales / quarter</i>, <i>we run out in</i> y una casilla <b>suggested</b> de líneas discontinuas que rellena la caja de cantidad.</li>
<li><b>Verlos en todo el catálogo:</b> el panel de <b>Shopping</b> del mismo socio → las casillas de stock en riesgo se construyen con el día de agotamiento; pulsa el número de una casilla para ver los artículos detrás.</li>
<li><b>Verlos en un pedido abierto:</b> <b>Shopping list</b> → la columna <b>Info</b> lleva la historia del stock de cada línea.</li>
<li><b>Pasar por encima de uno:</b> escribe tu propia cantidad en el contador de la tarjeta <b>Browse</b> — edita la línea abierta directamente. Nada se vuelve a sugerir por encima de ti.</li>
<li><b>Corregir el plazo de entrega detrás de una sugerencia:</b> los ajustes del SKO, o los del producto de proveedor, mientras siga diciendo <i>estimate</i>.</li>
</ul>
</aside>
