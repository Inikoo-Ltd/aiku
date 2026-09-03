---
title: Cómo leer el panel de compras a un socio
summary: La pantalla que dice qué comprar a un socio y cuánto margen tienes para comprarlo — tres tarjetas de límites, un donut de stock en riesgo con ocho cubos y la tubería de pedidos.
date: 2026-09-02
source_date: 2026-09-02
tags: procurement, intercompany, shopping-list, stock
category: procurement
series: Ordering from partners
order: 2
---

<aside class="tldr">
El panel es el comienzo de cada sesión de compra. La fila superior dice cuánto margen tienes — dinero y espacio en el almacén. La parte central dice qué productos del socio necesitan pedido, empezando por los peores. La inferior dice qué está ya en camino. No hace falta que recuerdes nada; la pantalla te dice qué necesita atención. Cursar el pedido en sí se explica en <a href="/docs/buying-from-a-partner-es">Comprar a un socio</a>.
</aside>

Se abre en **Procurement → Partners → {socio} → Shopping**. Sustituye la costumbre de abrir la lista de la compra e intentar acordarse de qué faltaba.

## Las tres tarjetas de arriba: el margen que tienes

Son límites, no adornos. Existen porque una lista de la compra en la que cualquiera puede volcar cualquier cosa deja de significar nada — un socio que recibe mil líneas no puede saber cuáles dos son urgentes.

- **Order budget used** (presupuesto de pedido usado). El valor de tu lista abierta frente a lo que este socio realmente te entrega en un ciclo de pedido, en la moneda de tu propia organización — todas las cifras de dinero de estas pantallas se convierten por ti, así que nunca tienes que pensar en la del socio. Si hay suficiente historial de entregas, el presupuesto se mide con esas entregas reales; si no lo hay, es un ciclo de pedido de lo que de verdad vendes de sus productos. Nadie teclea este número — ni tú, ni tu responsable. Cuando la barra se llena, la tarjeta dice **at capacity** (al límite).
- **Warehouse space** (espacio de almacén). Cuántas ubicaciones quedan libres del total, con una barra que separa lo que está *in use* (en uso), lo que viene *inbound* en pedidos y entregas abiertas, y lo que ocuparía *esta lista de la compra*. Debajo, la parte proporcional del socio: cuántas de las plazas libres pueden ocupar sus productos completamente nuevos. Las ubicaciones se cuentan como plazas — no tenemos datos de volumen, así que no fingimos medir metros cúbicos.
- **Lead time** (plazo de entrega). Con el nombre del socio en el título, esta tarjeta muestra su plazo medido de **order → booked in** (pedido → dado de alta), de cuántas entregas se ha medido (o si todavía es una estimación), en cuántos pedidos va con retraso y de cuánto, y el tamaño de su catálogo.

## Stock en riesgo: el donut y los ocho cubos

Este bloque cubre todo el catálogo del socio, dividido en ocho cubos según cuánto te dura tu propio stock. Los cubos de riesgo se dimensionan con el plazo de entrega medido, no con semanas de calendario — y ahí está la gracia.

Empieza con un **gráfico de donut**: todos los productos del catálogo, una porción por cubo, con el total en el centro. Pasa el cursor por una porción para ver el recuento y el porcentaje; pulsa una porción — o una fila en la leyenda de al lado — para navegar por ese cubo en el catálogo del socio. Un vistazo basta para saber si hoy toca un repaso tranquilo o una emergencia: mucho rojo significa problemas, mayoritariamente verde significa que vas bien.

Debajo del gráfico, los cubos se agrupan en dos. **Needs ordering** (necesitan pedido) reúne los cinco que piden tu atención:

- **Out of stock** — nada en la estantería.
- **Doomed** — todavía tienes stock, pero se agotará antes de que pudiera llegar una entrega, aunque pidieras ahora mismo.
- **Critical / Danger / Watch** — se agota dentro de dos, tres o cuatro plazos de entrega.

**Not for ordering** (no necesitan pedido) reúne los otros tres:

- **Covered** — sin problema por ahora.
- **Dead stock** — nada se vende, dinero parado en una estantería; la fila muestra cuánto vale.
- **We never stocked** — el socio lo vende, pero tú nunca lo has tenido.

Hay un tipo de artículo que no aparece aquí en absoluto: los SKO que has marcado como **On Demand** en tu propio inventario. Su stock no se controla, así que "sin stock" no significaría nada — el panel, las tablas de los cubos y Auto-fill los dejan fuera.

Cada casilla responde a una pregunta: **¿cuántos siguen necesitándome?** El recuento de *N* **need action** ignora todo lo que ya está en la lista o ya viene en camino, así que baja según trabajas. Debajo, ese mismo recuento desglosado por **rank** (rango) — primero los productos A, con la D y la Z difuminadas al final. Dos productos A sin stock importan más que quinientos productos D, así que ese es el orden en el que se trabaja.

Tres cosas puedes hacer desde una casilla:

- **Pulsar el número** para abrir el cubo como tabla: cada artículo, ordenado por rango, con su stock, tu stock, cuándo te quedas sin él y una casilla de cantidad que escribe directamente en la lista de la compra.
- **Pulsar el nombre del cubo o una letra de rango** para navegar por esos productos en el catálogo del socio.
- **Fill** para abrir Auto-fill ya acotado a ese cubo y ya generado — tú solo ajustas y confirmas. Da más trabajo que un botón mágico, y da mucho más control. Los recuentos de la casilla — *N on the way · N on list* — muestran qué parte del cubo ya has resuelto.

En **Covered** y **Dead stock** aparece en su lugar un aviso rojo cuando algo de ese cubo está en tu lista de la compra: eso es stock que no necesitas. **remove** borra esas líneas de un clic.

## La tubería de pedidos

La franja inferior sigue todo desde la necesidad hasta la estantería: **on shopping list → being prepared → ready to ship → in transit → arrived, booking in**. Cada columna muestra sus entregas y cuántos artículos llevan; cada tarjeta abre la entrega en modo solo lectura — el almacén del vendedor es su dueño hasta que la mercancía llega a ti.

Las tarjetas envejecen a la vista. Pasado el triple del plazo de entrega se vuelven ámbar; pasado diez veces, rojas. Una tarjeta vieja es una pregunta para el socio, no un número que admirar. Todo lo que va realmente tarde aparece además en la lista **Late from this partner**, con el mayor retraso primero, señalando los casos de "no delivery date given" (sin fecha de entrega).

## Por qué a veces la pantalla dice que no

Añadir a la lista puede ser rechazado. Es deliberado, y solo hay tres motivos:

- **At the budget cap** (tope de presupuesto alcanzado) — quita algo primero o bájale la prioridad. Una crisis real siempre cabe: **los artículos de rango A y sin stock están exentos**, así que una urgencia nunca espera detrás de un tope.
- **The warehouse floor** (el suelo del almacén) — por debajo del 5% de ubicaciones libres no se añade ningún producto *nuevo* de nadie. Los artículos que ya tienes rellenan sus propias plazas y pasan sin problema.
- **This partner's fair share** (la parte proporcional de este socio) — un socio puede reclamar en torno a una quinta parte de las plazas libres con productos nunca almacenados. Los demás proveedores también necesitan sitio.

La misma regla se aplica dondequiera que añadas — a mano, en bloque o desde Auto-fill — así que una propuesta nunca contiene líneas que no puedas confirmar.

## Medido, o etiquetado honestamente como estimación

Dos números mueven casi toda esta pantalla: el plazo de entrega y el presupuesto. La regla es la misma para ambos. **Si tenemos el historial, el número está medido y no se puede editar.** Si no lo tenemos, la tarjeta lo dice, y la estimación sí es editable — pero en los ajustes, nunca directamente en el panel: un **estimated delivery time** (plazo de entrega estimado) por producto, en el producto de proveedor o en los propios ajustes del SKO. En cuanto existen suficientes entregas reales, la medición toma el relevo y el campo de estimación desaparece. Nadie puede sobrescribir lo que de verdad ocurrió.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>El panel:</b> tu organización → <b>Procurement → Partners</b> → abre el socio → <b>Shopping</b>.</li>
<li><b>Saltar desde el donut:</b> pulsa una porción, o una fila en la leyenda, para navegar por ese cubo en el catálogo.</li>
<li><b>Trabajar un cubo:</b> pulsa el número de la casilla para ver la tabla de artículos, una letra de rango para navegar por esos productos, o <b>Fill</b> para una propuesta de Auto-fill acotada.</li>
<li><b>Limpiar la lista:</b> <b>remove</b> en la casilla de Covered o Dead stock.</li>
<li><b>Corregir una estimación de plazo:</b> los ajustes del SKO, o los del producto de proveedor — solo mientras siga diciendo <i>estimate</i>.</li>
</ul>
</aside>
