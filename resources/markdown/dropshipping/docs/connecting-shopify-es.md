---
title: Conectar tu tienda Shopify
summary: Enlaza tu tienda Shopify con tu cuenta de dropshipping con su nombre myshopify.com, instala nuestra aplicación en Shopify, y arregla una tienda que todavía dice que no está conectada.
date: 2026-09-25
source_date: 2026-09-25
tags: shopify, canal de ventas, conectar, instalar, myshopify
category: sales-channels
series: shopify
order: 1
shops: awd, dssk, dse
---

<aside class="tldr">
Ve a <b>Canales</b>, pulsa <b>Agregar canal de ventas</b> y luego <b>Conectar</b> en la tarjeta de Shopify. Escribe el nombre <b>myshopify.com</b> de tu tienda, no tu propio dominio, y pulsa <b>Conectar</b>. Shopify se abre en una pestaña nueva: pulsa <b>Install</b> ahí. La conexión solo se termina cuando la aplicación está instalada en Shopify.
</aside>

## Antes de empezar

- Necesitas el nombre <b>myshopify.com</b> de tu tienda. Shopify te lo dio cuando creaste la tienda, por ejemplo <b>mystore.myshopify.com</b> o un código como <b>ab12cd-3e.myshopify.com</b>. Tu propio dominio, como <b>www.mystore.com</b>, no funciona.
- Para encontrarlo, abre tu administrador de Shopify y ve a <b>Settings</b>, <b>Domains</b>. También puedes mirar la barra de direcciones de tu administrador de Shopify: en <b>admin.shopify.com/store/ab12cd-3e</b>, el nombre es <b>ab12cd-3e</b>.
- Inicia sesión en tu administrador de Shopify en el mismo navegador, como propietario de la tienda o como personal que pueda instalar aplicaciones.
- Shopify está disponible en todas nuestras webs de dropshipping. Conéctalo desde la web donde tienes tu cuenta de dropshipping.

## Conectar tu tienda

1. Abre <b>Canales</b> en el menú. La página <b>Canales de venta</b> muestra los canales que ya tienes.
2. Pulsa <b>Agregar canal de ventas</b>.
3. Busca la tarjeta de <b>Shopify</b> y pulsa <b>Conectar</b>. Se abre una ventana: <b>Ingrese su nombre de dominio único de Shopify</b>.
4. Escribe el nombre de tu tienda en el recuadro. El final, <b>.myshopify.com</b>, ya está escrito por ti. También puedes pegar la dirección completa <b>xxx.myshopify.com</b> o la dirección <b>admin.shopify.com/store/...</b>: solo nos quedamos con el nombre de la tienda.
5. Pulsa <b>Conectar</b>.
6. Shopify se abre en una pestaña nueva y te pide instalar nuestra aplicación. Pulsa <b>Install</b>. Si no se abre ninguna pestaña nueva, tu navegador la bloqueó: permite las ventanas emergentes para nuestra web, o usa <b>Haga clic aquí para instalar</b> en la página del canal (ver más abajo).
7. Cuando la aplicación está instalada, Shopify muestra la página de la aplicación. Puedes cerrar esa pestaña y volver a tu cuenta de dropshipping.

<!-- screenshot: la ventana de conexión de Shopify con un nombre de tienda escrito y el final .myshopify.com a la derecha -->

El nuevo canal ya está en tu lista de <b>Canales de venta</b>. Ábrelo para ver su panel.

Si no estás seguro de cuál es el nombre de tu tienda, pulsa el enlace <b>haga clic aquí</b> junto a <b>¿No estás seguro de cuál es el nombre de tu tienda Shopify?</b> en esa misma ventana.

## Lo que configuramos en tu tienda

Al instalar la aplicación hacemos esto en tu tienda Shopify por ti:

- Añadimos una ubicación de logística cuyo nombre empieza por <b>aiku-</b>. El stock de los productos que conectas se guarda en esta ubicación, y Shopify nos envía los pedidos de esos productos a través de ella.
- Añadimos esta ubicación a tu perfil de envío por defecto, para que Shopify pueda vender y enviar desde ella. Consulta [La ubicación de logística de AW en Shopify](/docs/shopify-fulfilment-location).
- Configuramos los mensajes que Shopify nos envía cuando llegan pedidos.

No necesitas hacer nada de esto a mano.

## Comprobar que el canal está conectado

Abre el canal desde <b>Canales</b>. Cuando nuestra aplicación está instalada, aparecen tres iconos pequeños junto al nombre de la tienda. Pasa el ratón por encima para leer sus nombres:

- <b>Aplicación instalada</b>: nuestra aplicación está instalada y podemos leer tu tienda.
- <b>Existen en la plataforma</b> y <b>Estado de la plataforma</b>: nuestra ubicación de logística está configurada en tu tienda.

Cuando las tres son marcas verdes, el panel muestra los recuadros <b>Pedidos</b> y <b>Productos</b> y, en el menú izquierdo bajo tu canal, <b>Mis productos</b> y <b>Pedidos</b>. Ahora puedes añadir productos: consulta [Gestionar productos en Shopify](/docs/managing-products-on-shopify).

<!-- screenshot: el panel del canal con las tres marcas verdes, el botón Fetch orders y los recuadros Orders y Products -->

## Si dice que el canal todavía no está conectado

Si ves <b>Your channel is not connected yet to the platform. Please connect it to be able to synchronize your products.</b>, la aplicación no se instaló en Shopify. Este es el problema más común. Pasa cuando se cerró la pestaña de Shopify antes de pulsar <b>Install</b>, o cuando tu navegador bloqueó la pestaña nueva.

1. Inicia sesión en tu administrador de Shopify en el mismo navegador.
2. En la página del canal, haz clic en <b>Haga clic aquí para instalar</b>, al final de <b>Make sure you click the button "Install" in the Shopify dashboard to finalize the connection.</b>
3. Shopify se abre en la misma pestaña. Pulsa <b>Install</b>.
4. Vuelve a la página del canal y recárgala.

Si sigue sin funcionar, puedes pulsar <b>Borrar</b> junto a <b>O elimina el canal y vuelve a intentarlo.</b>, y conectar la tienda de nuevo desde el principio.

## Eliminar o reiniciar un canal

- <b>Delete channel</b>: aparece en un canal conectado. Pregunta <b>¿Estás seguro de que deseas eliminar el canal?</b>; pulsa <b>Sí, eliminar canal</b> para confirmar. Si conectas la misma tienda Shopify más tarde, podemos reabrir el canal antiguo con sus productos en lugar de crear uno nuevo.
- <b>Restablecer canal</b>: aparece cuando el canal perdió la conexión pero todavía tiene productos. Vuelve a configurar la ubicación de logística y los mensajes de pedidos. Tus productos deben enlazarse de nuevo después. Los pedidos ya realizados no cambian.

## Cuando algo va mal

**"This does not look like a Shopify store name. Use the .myshopify.com name, not your own domain."** Escribiste tu propio dominio, como <b>mystore.com</b>. Escribe en su lugar el nombre <b>myshopify.com</b>. Lo encuentras en Shopify en <b>Settings</b>, <b>Domains</b>.

**"Shopify shop ... not found".** Ninguna tienda Shopify tiene ese nombre. Comprueba la ortografía. El nombre suele ser un código de letras y números, no el nombre de tu tienda.

**"Shopify shop ... already exists, please use other name".** Esta tienda ya está conectada a una cuenta de dropshipping. Comprueba tu lista de <b>Canales de venta</b>. Si está conectada a otra de tus cuentas, bórrala ahí primero.

**"Shop name cannot contain spaces".** El nombre myshopify.com nunca tiene espacios. Cópialo de Shopify en lugar de escribir el nombre de tu tienda.

**Conecté Shopify pero sigue diciendo que no está conectado.** La aplicación no se instaló. Sigue los pasos de <b>Si dice que el canal todavía no está conectado</b> arriba.

**"Click here to install" muestra "Something went wrong".** El canal perdió su enlace con tu tienda. Pulsa <b>Borrar</b> junto a <b>O elimina el canal y vuelve a intentarlo.</b>, y conecta la tienda de nuevo.

**Después de pulsar Connect, no se abre ninguna pestaña de Shopify.** Tu navegador bloqueó la pestaña nueva. Permite las ventanas emergentes para nuestra web, o abre el canal y usa <b>Haga clic aquí para instalar</b>.

**Los productos no se venden en Shopify, o aparecen como agotados.** Comprueba que la ubicación <b>aiku-</b> está en tu perfil de envío. Consulta [La ubicación de logística de AW en Shopify](/docs/shopify-fulfilment-location).

Si nada de esto ayuda, contacta con atención al cliente y dales tu nombre myshopify.com.

<aside class="wayfinder"><strong>Dónde hacer clic</strong>
<ul>
<li><b>Conectar una tienda nueva:</b> <b>Canales</b> → <b>Agregar canal de ventas</b> → <b>Shopify</b> → <b>Conectar</b>.</li>
<li><b>Terminar una instalación:</b> abre el canal → <b>Haga clic aquí para instalar</b> → <b>Install</b> en Shopify.</li>
<li><b>Comprobar la conexión:</b> abre el canal → los tres iconos junto a su nombre.</li>
<li><b>Cambiar la configuración de stock:</b> abre el canal → <b>Gestionar el canal de ventas</b>.</li>
</ul>
</aside>
