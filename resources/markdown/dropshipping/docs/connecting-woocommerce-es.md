---
title: Conectar tu tienda WooCommerce
summary: Enlaza tu tienda WooCommerce con tu cuenta de dropshipping, arregla los mensajes que puedes ver al conectar, y reconecta una tienda que dejó de responder.
date: 2026-09-25
source_date: 2026-09-25
tags: woocommerce, wordpress, canal de ventas, conectar, claves api
category: sales-channels
series: woocommerce
order: 1
shops: awd, dssk, dse
---

<aside class="tldr">
Ve a <b>Canales</b>, pulsa <b>Agregar canal de ventas</b> y luego <b>Conectar</b> en la tarjeta de Woocommerce. Escribe un nombre para tu tienda y pulsa <b>Próximo</b>. Escribe la dirección de tu tienda, pulsa <b>Tienda de autorización</b>, aprueba nuestra aplicación en WooCommerce, vuelve y pulsa <b>Próximo</b>. Si tu alojamiento bloquea las claves automáticas, puedes crear las claves en WooCommerce y pegarlas tú mismo.
</aside>

## Antes de empezar

Comprueba esto primero en tu sitio de WordPress. La mayoría de las conexiones fallidas vienen de uno de estos puntos.

- WooCommerce está instalado y activo.
- La dirección de tu tienda empieza por <b>https://</b>. No nos conectamos a tiendas sin un certificado SSL válido.
- En WordPress, <b>Settings</b>, <b>Permalinks</b> no está fijado en <b>Plain</b>. Con los enlaces permanentes en Plain no se puede encontrar la API de WooCommerce.
- Tu plugin de seguridad, cortafuegos o Cloudflare no bloquea las peticiones a <b>/wp-json/</b>. Hablamos con tu tienda a través de esa dirección.
- Puedes iniciar sesión en tu administrador de WordPress como administrador. Lo necesitas para aprobar la conexión.
- Opcional pero útil: fija la unidad de peso que quieras en WooCommerce (<b>Settings</b>, <b>Productos</b>) antes de conectar. La leemos al conectar y enviamos los pesos de producto en esa unidad.

WooCommerce está disponible en todas nuestras webs de dropshipping. Conéctalo desde la web donde tienes tu cuenta de dropshipping.

## Conectar tu tienda

1. Abre <b>Canales</b> en el menú. La página <b>Canales de venta</b> muestra los canales que ya tienes.
2. Pulsa <b>Agregar canal de ventas</b>.
3. Busca la tarjeta de <b>Woocommerce</b> y pulsa <b>Conectar</b>. Se abre una ventana.
4. En <b>Nombre de la cuenta de WooCommerce</b>, escribe un nombre para tu tienda, por ejemplo el nombre de tu comercio. El nombre es obligatorio, y es el nombre que verás en tu lista de canales. Pulsa <b>Próximo</b>.
5. En <b>Configuración de autenticación</b>, escribe la dirección completa de tu tienda, por ejemplo <b>https://mystore.com</b>. Pulsa <b>Tienda de autorización</b>.
6. Comprobamos primero que tu tienda responde. Si lo hace, se abre una pestaña nueva en tu sitio de WordPress. Inicia sesión si WordPress te lo pide.
7. WooCommerce muestra que <b>AW Connect</b> pide acceso de <b>Read/Write</b>. Comprueba que has iniciado sesión en la tienda correcta, y pulsa <b>Approve</b>.
8. La pestaña muestra un mensaje breve y se cierra sola. Vuelve a la ventana de tu cuenta de dropshipping y pulsa <b>Próximo</b>.
9. Ves <b>¡Conectado!</b> Pulsa <b>DE ACUERDO</b>.

<!-- screenshot: la ventana de conexión de Woocommerce, el paso Authentication Settings con el recuadro de dirección de la tienda y el botón Auth Store -->

<!-- screenshot: la página de aprobación de WooCommerce con AW Connect pidiendo acceso Read/Write y el botón Approve -->

Termina todos los pasos en una hora. Después olvidamos el nombre y las claves con las que empezaste, y tienes que empezar de nuevo desde <b>Conectar</b>.

Si tu navegador bloquea la pestaña nueva, la página de aprobación se abre en la misma pestaña y sales de la ventana de conexión. Permite las ventanas emergentes para nuestra web, y empieza de nuevo desde <b>Conectar</b>.

## Si tu tienda no pudo enviarnos las claves

Cuando apruebas, WooCommerce envía las nuevas claves desde tu alojamiento a nuestros servidores. Algunas empresas de alojamiento lo bloquean. Entonces ves <b>Your store approved the connection but could not send us the keys</b>, y <b>Próximo</b> te dice <b>You are not connected yet</b>.

Aun así puedes conectar pegando las claves tú mismo:

1. En WordPress, ve a <b>WooCommerce</b>, <b>Settings</b>, <b>Advanced</b>, <b>REST API</b>.
2. Añade una clave. Dale cualquier descripción, elige tu usuario administrador y fija <b>Permissions</b> en <b>Read/Write</b>. Genera la clave.
3. Copia la <b>Consumer key</b> (empieza por ck_) y el <b>Consumer secret</b> (empieza por cs_). WooCommerce muestra el secreto solo una vez.
4. En la ventana de Woocommerce de tu cuenta de dropshipping, comprueba que la dirección de tu tienda sigue en el recuadro.
5. Pulsa <b>My store could not send the keys, let me paste them</b>.
6. Pega la clave y el secreto, y pulsa <b>Use these keys</b>.

<!-- screenshot: el paso Authentication Settings con la sección de claves manuales abierta, mostrando los recuadros ck_ y cs_ y el botón Use these keys -->

## Después de conectar

Tu tienda ahora aparece en la página <b>Canales de venta</b>. Haz clic en su nombre para abrir el panel del canal. Ahí ves <b>Pedidos</b>, <b>Clientes</b> y <b>Productos</b>. Pulsa <b>View all</b> en <b>Productos</b> para añadir productos. Consulta [Gestionar productos en WooCommerce](managing-products-on-woocommerce).

Al conectar, también añadimos dos webhooks a tu tienda: uno para pedidos nuevos y otro para productos eliminados. No los borres en WooCommerce, <b>Settings</b>, <b>Advanced</b>, <b>Webhooks</b>. Sin ellos los pedidos nuevos no nos llegan de inmediato.

Importamos los pedidos que están pagados, tienen el estado <b>Processing</b> en WooCommerce y tienen un país de envío. Si falta un pedido, pulsa <b>Fetch orders</b> en el panel del canal. Comprueba tu tienda en busca de pedidos de los últimos 14 días que no nos hayan llegado todavía.

Con <b>Gestionar el canal de ventas</b> puedes cambiar el nombre de la tienda, tu configuración de stock y tu regla de precios para los productos nuevos.

## Conectar la misma tienda otra vez

Si eliminas tu canal de WooCommerce y más tarde conectas la misma dirección de tienda, recuperamos el mismo canal, con sus productos y pedidos. No empiezas de cero.

## Cuando tu tienda deja de responder

Comprobamos tu tienda conectada regularmente. Si tu tienda deja de responder, o las claves dejan de funcionar, el canal muestra <b>Your channel is not connected yet to the platform</b>. Encima puedes ver el mensaje de error que envió tu tienda. Mientras esto se muestra, tu lista de productos está oculta y no puedes subir productos a tu tienda.

Para arreglarlo:

1. Asegúrate de que tu sitio web está en línea y puedes abrirlo en tu navegador.
2. En la página del canal, pulsa <b>Intenta reconectarte</b>. Se abre tu sitio de WordPress. Inicia sesión como administrador y pulsa <b>Approve</b> de nuevo. Esto crea claves nuevas.
3. Si sigue sin funcionar, pulsa <b>Prueba de conexión</b> para comprobar la conexión otra vez.
4. Como último paso, pulsa <b>Borrar</b> y conecta la tienda de nuevo. Tus productos y pedidos vuelven cuando usas la misma dirección de tienda.

Si tu tienda sigue fallando durante mucho tiempo, dejamos de comprobarla. Vuelve a funcionar en cuanto la reconectas.

<!-- screenshot: el aviso de no conectado en un canal de WooCommerce con los botones Try to reconnect, Test Connection y Delete -->

## Cuando algo va mal

Estos son los mensajes que puedes ver al pulsar <b>Tienda de autorización</b>, y qué hacer.

- <b>We could not resolve your store domain</b>: la dirección está mal escrita o el dominio no está activo. Copia la dirección de tu navegador cuando tu tienda esté abierta.
- <b>Your store SSL certificate could not be verified</b>: tu certificado ha caducado, es autofirmado o está incompleto. Pide a tu empresa de alojamiento que lo renueve o lo arregle.
- <b>Your store refused our connection</b> o <b>Your store did not answer within 2 minutes</b>: tu alojamiento o cortafuegos bloquea nuestros servidores. El mensaje lista nuestras direcciones IP. Envíaselas a tu empresa de alojamiento y pídeles que las permitan.
- <b>Your store redirects to ...</b>: tu tienda vive en una dirección distinta, por ejemplo con o sin www. Escribe la dirección indicada en el mensaje.
- <b>Your store url redirects in a loop</b>: escribe la dirección final de tu tienda, la que ves en el navegador cuando la página termina de cargar.
- <b>We could not find the WooCommerce API on this store</b>: WooCommerce no está activo, su API REST está desactivada, o tus enlaces permanentes están en <b>Plain</b>. Cambia los enlaces permanentes en WordPress, <b>Settings</b>, <b>Permalinks</b>.
- <b>Your store answered with 401</b> o <b>403</b> <b>and blocked our request</b>: un plugin de seguridad, un cortafuegos o Cloudflare nos bloquea. Permite las peticiones a <b>/wp-json/</b> en esa herramienta.
- <b>Your WooCommerce store returned an error 500</b> (u otro número que empiece por 5): tu web tiene un error. Revisa el registro de errores de tu alojamiento, o pregunta a tu empresa de alojamiento, y vuelve a intentarlo.
- <b>Your store answered with ... but did not return the WooCommerce REST API</b>: la dirección no apunta a tu sitio de WordPress. Comprueba que escribiste la propia tienda, no una landing page u otro sitio.
- <b>You are not connected yet, click auth store to connect and follow the instructions</b>: pulsaste <b>Próximo</b> antes de aprobar en WooCommerce, la aprobación no nos llegó, o ha pasado más de una hora. Pulsa <b>Tienda de autorización</b> de nuevo, o pega las claves tú mismo como se explica arriba.
- <b>We can't access your store, make sure you already put correct store url</b>: recibimos las claves, pero no pudimos usarlas en esa dirección. Comprueba la dirección, y que las claves tienen permiso <b>Read/Write</b>.

Los problemas en tu propia web, como que tu tienda esté caída, lenta o nos bloquee, solo puedes arreglarlos tú o tu empresa de alojamiento. No podemos cambiar la configuración de tu web.
