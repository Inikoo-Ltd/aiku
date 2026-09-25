---
title: El canal Manual/API
summary: Crea un canal Manual/API para vender desde tu propia web, marketplace o app, pon pedidos a mano o envíanoslos a través de nuestra API.
date: 2026-09-25
source_date: 2026-09-25
tags: manual, api, canal de ventas, web propia, token de api, integración
category: sales-channels
series: manual
order: 1
shops: awd, dssk, dse
---

<aside class="tldr">
Usa un canal <b>Manual/API</b> cuando tu tienda no está en ninguna de las plataformas que conectamos, o cuando quieres escribir los pedidos tú mismo. Ve a <b>Canales</b>, pulsa <b>Agregar canal de ventas</b>, luego <b>Crear</b> en la tarjeta <b>Manual/API</b> y dale un nombre. Después añades productos a <b>Mis productos</b>, añades a tus compradores como <b>Clientes</b> y creas pedidos para ellos, a mano o por la API.
</aside>

## Cuándo usar un canal Manual/API

Un canal Manual/API no está enlazado a ninguna tienda. No se sube nada a ninguna web y no llega ningún pedido por sí solo. Úsalo cuando:

- Vendes en tu propia web, en un marketplace al que no nos conectamos, en redes sociales o por teléfono, y quieres enviarnos cada pedido tú mismo.
- Tienes tu propio sistema o desarrollador y quieres enviarnos pedidos a través de nuestra API.

Si tu tienda está en una plataforma de las que aparecen en la página <b>Agregar canal de ventas</b>, como Shopify, WooCommerce, eBay o TikTok Shop, conecta esa plataforma en su lugar. Entonces los productos se suben por ti y los pedidos llegan por sí solos.

Puedes tener más de un canal Manual/API, por ejemplo uno por cada web.

## Crear el canal

1. Abre <b>Canales</b> en el menú. Ves la lista de tus <b>Canales de venta</b>.
2. Pulsa <b>Agregar canal de ventas</b>. La página muestra <b>Seleccione el canal que desea crear</b>.
3. En la tarjeta de <b>Manual/API</b> pulsa <b>Crear</b>.
4. Se abre una ventana <b>Crear manual de plataforma</b>. Escribe un nombre para el canal, por ejemplo el nombre de tu web. El nombre puede tener hasta 28 caracteres.
5. Pulsa <b>Crear</b>.

<!-- screenshot: la página Add Sales Channel con la tarjeta Manual/API y su botón Create, y la ventana Create platform manual -->

Ves el mensaje <b>Se ha creado su tienda Manual.</b> y se abre la página del canal.

Cada uno de tus canales necesita su propio nombre. Si el nombre ya lo usa otro de tus canales, la ventana muestra un error. Elige un nombre distinto.

## La página de tu canal

La página del canal tiene el nombre de tu canal como título y el encabezado <b>Gestión de pedidos manual/API</b>. Muestra tres recuadros, cada uno con un enlace <b>View all</b>:

- <b>Pedidos</b>: los pedidos que has puesto en este canal.
- <b>Clientes</b>: las personas a las que envías pedidos.
- <b>Productos</b>: los productos de tu lista <b>Mis productos</b>.

En el menú, bajo el nombre del canal, encuentras:

- <b>Cestas</b>: pedidos que empezaste y no pagaste todavía.
- <b>Mis productos</b>: los productos que vendes en este canal. Consulta [Gestionar productos en el canal Manual/API](/docs/managing-products-on-the-manual-channel).
- <b>Clientes</b>: tus compradores. Consulta [Gestionar clientes](/docs/managing-clients).
- <b>Pedidos</b>: tus pedidos puestos. Consulta [Poner pedidos manualmente](/docs/placing-orders-manually).
- <b>API</b>: tokens y documentación para conectar tu propio sistema.

La forma habitual de trabajar es:

1. Añade los productos que vendes a <b>Mis productos</b> con <b>Añadir productos</b>. Esto hace falta para la API. Para los pedidos que escribes tú mismo es opcional: la cesta te deja elegir cualquier producto que vendamos.
2. Cuando recibas un pedido, abre <b>Clientes</b>, busca a tu comprador o añádelo.
3. En la página del cliente pulsa <b>Crear orden</b>, añade los productos y cantidades y paga.

## Cambiar el nombre o cerrar el canal

Para renombrar el canal, pulsa <b>Editar</b> en la página del canal y cambia <b>Store name</b>.

Para cerrar un canal, ve a <b>Canales</b> y pulsa el botón de cerrar en la columna <b>Acción</b> (texto emergente <b>Canal cerrado</b>). La ventana pregunta <b>¿Estás seguro de que quieres cerrar este canal?</b> y avisa <b>Esta operación es irreversible.</b> Un canal cerrado desaparece del menú. Tus pedidos y facturas pasados se conservan.

## Conectar tu propio sistema con la API

La API deja que tu web o app haga por sí sola lo que haces en las páginas del canal: leer nuestro catálogo de productos con precios en vivo, añadir productos a <b>Mis productos</b>, crear y cambiar clientes, crear pedidos, añadirles productos, enviarlos y seguirlos. También puedes descargar tu lista de <b>Mis productos</b> como un feed CSV o JSON para cargar productos en tu propia web.

Abre <b>API</b> bajo tu canal. La página tiene estas pestañas:

- <b>Overview</b>: cómo conectar, la dirección base de la API y el botón <b>API documentation</b>. La documentación lista cada endpoint con ejemplos.
- <b>API tokens</b>: los tokens de este canal.
- <b>API calls</b>: las peticiones que hizo tu sistema.
- <b>Historia</b>: cambios hechos en tu cuenta.

### Obtener un token

1. Pulsa <b>Generate API token</b>.
2. Si el token es solo para leer datos, marca <b>Read only (cannot create, change or submit orders)</b>.
3. Pulsa <b>Click to Generate</b>.
4. Copia el token con el icono de copiar y guárdalo bien. La ventana dice <b>Put this token in a safe place, you won't be able to see it again.</b> La etiqueta corta en la lista de tokens es solo un nombre, no el token.

Envía el token en cada petición en la cabecera <b>Authorization: Bearer</b> seguida de tu token. Cada token pertenece a un canal: los productos, clientes y pedidos que crea tu sistema van a ese canal. Para que un token deje de funcionar, bórralo en la pestaña <b>API tokens</b>.

<!-- screenshot: la pestaña Overview de la página API con los botones API documentation y Generate API token -->

### Probar primero en staging

La pestaña <b>Overview</b> también tiene <b>Open staging mirror</b>. Staging es una copia aparte del sitio donde puedes probar sin pedidos ni pagos reales. Inicia sesión con el mismo correo y contraseña. Staging se restablece regularmente con una copia nueva, lo que borra lo que hayas creado ahí. Los tokens del sitio real no funcionan en staging: genera un token aparte en staging, y uno nuevo tras cada reinicio. La dirección base de staging se muestra en la pestaña <b>Overview</b>.

### Cómo se pagan los pedidos de la API

Cuando tu sistema envía un pedido, lo pagamos primero de tu saldo de cuenta, luego de tus tarjetas guardadas. Añade una tarjeta antes de empezar. En cuanto tienes un token, el menú muestra <b>Tarjetas guardadas</b>. Mientras no haya ninguna tarjeta guardada, la página API muestra <b>Aún no tienes tarjetas guardadas</b> con un botón <b>Añadir tarjeta</b>.

Si ni tu saldo ni tus tarjetas cubren el pedido, el pedido se marca <b>No pagado</b> y no pasa al almacén. Añade dinero a tu saldo con <b>Recargar Saldo</b>, abre el pedido y pulsa <b>Pay … with balance</b>. El botón aparece cuando tu saldo cubre la cantidad debida.

## Cuando algo va mal

- <b>El nombre ya está en uso cuando creo el canal.</b> Otro de tus canales abiertos tiene ese nombre. Escribe un nombre distinto. El nombre de un canal cerrado se puede volver a usar.
- <b>Mis pedidos no llegan por sí solos.</b> Un canal Manual/API nunca recoge pedidos de una web. Créalos en la página del cliente, o envíalos desde tu sistema a través de la API. Si vendes en una plataforma de las que aparecen en la página <b>Agregar canal de ventas</b>, conecta esa plataforma como su propio canal.
- <b>Mis productos no están en mi web.</b> No subimos nada desde un canal Manual/API. Cárgalos tú mismo en tu web, con la descarga CSV de <b>Mis productos</b> o a través de la API.
- <b>Perdí mi token de API.</b> No se puede volver a mostrar. Genera un token nuevo, ponlo en tu sistema y borra el antiguo.
- <b>La API responde que no puedo crear ni cambiar pedidos.</b> El token es solo de lectura. Genera un token sin marcar <b>Read only</b>.
- <b>La API rechaza mis peticiones durante un rato.</b> Cada token puede hacer hasta 120 peticiones por minuto. Ralentiza tu sistema e inténtalo de nuevo pasado un minuto.
- <b>La API dice "This order has no products yet".</b> Añade al menos un producto al pedido antes de enviarlo.
- <b>La API dice "Unable to find related portfolio item".</b> A través de la API añades un producto a un pedido por su artículo de <b>Mis productos</b>, no por el producto en sí. Añade primero el producto a <b>Mis productos</b> y usa el id de ese artículo.
- <b>La API dice que ya existe otra transacción con el mismo producto.</b> El producto ya está en el pedido. Cambia la cantidad de esa línea en lugar de añadirlo de nuevo.
- <b>La API dice que el pedido "is already in the 'submitted' state and cannot be updated".</b> Los pedidos enviados no se pueden cambiar ni borrar a través de la API. Contáctanos si el pedido debe cambiar.
- <b>Mi pedido de la API aparece como Unpaid.</b> Tu saldo y tus tarjetas guardadas no lo cubrieron. Recarga tu saldo, abre el pedido y pulsa <b>Pay … with balance</b>, y comprueba que tu tarjeta guardada sigue siendo válida.
