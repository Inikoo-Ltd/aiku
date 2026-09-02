---
title: Cómo está organizado el catálogo
summary: Departamentos, subdepartamentos, familias, colecciones y productos — para qué sirve cada nivel, cómo se anidan, y cómo se mueve un producto entre ellos.
date: 2026-09-01
source_date: 2026-09-01
tags: catalogue, departments, families, products
category: shop
---

<aside class="tldr">
El <b>Catalogue</b> de cada tienda es un árbol: un <b>Department</b> puede contener <b>Sub-departments</b>, y ambos contienen <b>Families</b>. Todo producto pertenece exactamente a una familia. Las <b>Collections</b> están al margen de ese árbol, como una segunda forma de agrupar productos — para merchandising, no para archivarlos. Los departamentos, subdepartamentos y familias se crean a mano en la tienda; los productos casi siempre llegan ya hechos y solo necesitan colocarse en la familia correcta.
</aside>

## La forma del árbol

Un **Department** es el nivel superior — un área amplia de la tienda, como "Department A". Dentro de un departamento hay dos caminos:

- Ir directo a **Families** — el nivel al que realmente pertenece un producto.
- Pasar antes por un **Sub-department**, si el departamento es lo bastante grande como para necesitar una capa intermedia, y entonces las familias están dentro de ese subdepartamento.

Así que una familia siempre vive directamente bajo un departamento, o bajo un subdepartamento que a su vez vive bajo un departamento. Un producto solo se adjunta a una familia, nunca directamente a un departamento o subdepartamento.

## Departamentos

Los departamentos son donde empieza la estructura de la tienda. Se llega a ellos desde **Catalogue → Departments**, que lista todos los departamentos con su estado y sus ventas. Al abrir uno se ven sus propias familias (y subdepartamentos, si los tiene).

Para crear uno, pulsa **Create Department** y rellena:

- **Code** — una referencia interna corta.
- **Name** — el nombre completo del departamento.

## Subdepartamentos

Un subdepartamento es un estante intermedio opcional dentro de un departamento, para cuando "Department A" necesita dividirse más antes de llegar a las familias. Se crea desde dentro de su departamento, dándole el mismo **Code** y **Name** que un departamento. Todo lo que hay debajo de un subdepartamento funciona exactamente igual que las familias propias de un departamento — la capa extra solo afecta a dónde se ubican las cosas, no a cómo se comportan.

## Familias

Una familia es el nivel donde de verdad se colocan los productos — el cajón, no el armario. Se llega a la lista completa desde **Catalogue → Families**, o navegando hasta una a través de su departamento (y subdepartamento, si tiene uno).

Para crear una familia, pulsa **Create Family** y rellena:

- **Code**
- **Name**
- **Description** (opcional)

Una vez que existe una familia, su propia pantalla **Products** lista todo lo archivado ahí, y es donde normalmente se añaden los productos nuevos bajo ella.

## Productos

Los productos son las cosas concretas que vendes — una fila por artículo vendible. La página propia de un producto muestra su código, nombre y demás detalles; la lista **Products** bajo **Catalogue → Products** muestra todos los productos de la tienda.

La mayoría de los productos llegan ya construidos — traídos de la biblioteca general de productos en vez de escritos a mano familia por familia — y solo necesitan aterrizar en la familia correcta. Cuando a uno todavía no se le ha asignado familia, aparece como **Orphan Product**. El panel de Catalogue tiene una casilla **Orphan Products** con un contador en marcha; al abrirla se listan todos los productos sin familia, con una casilla de marcar junto a cada fila. Marca los que van juntos, pulsa **Add … to Family**, elige la familia en el cuadro de búsqueda que aparece, y pulsa **Submit** — todos los productos marcados se mueven allí de una vez. Esa pantalla es hoy por hoy la forma de mover un producto a una familia desde el lado de Catalogue; no hay un botón "mover" independiente en la página propia de un producto.

### Estados del producto

Todo producto lleva un **state** que sigue su vida en el catálogo:

- **In Process** — todavía en preparación, aún no listo.
- **Active** — normal, vendible.
- **Discontinuing** — en proceso de retirada; puede que aún quede stock en el almacén, pero va de salida.
- **Discontinued** — retirado por completo; no se volverá a usar.

Un producto también lleva un **status** aparte que refleja si de verdad se puede comprar ahora mismo: **For Sale**, **Not For Sale**, **Out of Stock**, **Coming Soon**, o **Discontinued** (junto con **In Process** mientras todavía se está construyendo). El state tiene que ver con el ciclo de vida del producto en el catálogo; el status está más cerca de lo que vería un comprador.

## Colecciones

Una **Collection** es una agrupación aparte, al margen del árbol departamento/familia en vez de dentro de él — un estante temático que construyes para merchandising, que puede traer productos de cualquier familia. Se llega a las colecciones desde **Catalogue → Collections**, y también se puede crear una desde dentro de un departamento o subdepartamento concreto si quieres que quede acotada a ese ámbito.

Para crear una colección, pulsa **Create Collection** y rellena:

- **Code**
- **Name**
- **Description** (opcional)
- **Image** (opcional)

Que un producto esté en una colección no afecta a qué familia pertenece — un producto conserva su única familia sin importar en cuántas colecciones aparezca también.

<aside class="wayfinder"><strong>Dónde pulsar en aiku</strong>
<ul>
<li><b>Ver o crear departamentos:</b> tu tienda → <b>Catalogue → Departments</b> → <b>Create Department</b>.</li>
<li><b>Añadir un subdepartamento:</b> abre un departamento, y créalo desde dentro.</li>
<li><b>Ver o crear familias:</b> tu tienda → <b>Catalogue → Families</b> → <b>Create Family</b>, o desde dentro de un departamento/subdepartamento.</li>
<li><b>Ver productos:</b> tu tienda → <b>Catalogue → Products</b>.</li>
<li><b>Mover un producto a una familia:</b> la casilla <b>Orphan Products</b> del panel de Catalogue → marca los productos → <b>Add … to Family</b> → elige la familia → <b>Submit</b>.</li>
<li><b>Ver o crear colecciones:</b> tu tienda → <b>Catalogue → Collections</b> → <b>Create Collection</b>.</li>
</ul>
</aside>

<aside class="permissions">
<strong>Permisos que necesitas</strong>
Necesitas acceso de vista a Products en esta tienda para ver el catálogo, y acceso de edición a Products en esta tienda para crear departamentos, subdepartamentos, familias o colecciones, o para mover productos entre familias.
</aside>
