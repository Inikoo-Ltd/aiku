---
title: Países a los que no podemos enviar
summary: Qué significa el mensaje "We cannot deliver to" en una cesta o un pedido, y cómo arreglar los checkouts de Shopify que se niegan a enviar nuestros productos a un país.
date: 2026-10-28
source_date: 2026-10-28
tags: entrega, países, envío, shopify, perfil de envío, dirección prohibida
category: orders
shops: awd, dssk, dse
---

<aside class="tldr">
Cada una de nuestras webs tiene una lista de países a los que no envía. Cuando la dirección de entrega de un pedido está en uno de ellos, ves <b>We cannot deliver to ...</b>, no puedes pagar, y el pedido no llega al almacén. Un pedido de Shopify a uno de estos países se rechaza en Shopify y se queda en tu tienda. Cambia la dirección mientras el pedido todavía es una cesta, o contacta con atención al cliente. Un problema distinto es un checkout de Shopify que no envía nuestros productos a un país: eso es una configuración de envío de tu tienda Shopify.
</aside>

## "We cannot deliver to ..." en tu cesta o pedido

Si la dirección de entrega está en un país al que la web no envía, ves esto en rojo:

<b>We cannot deliver to (country). Please update the address or contact support.</b>

Qué pasa entonces:

- En una cesta, los botones <b>Continuar con el pago</b> y <b>Realizar pedido</b> están ocultos.
- **Shopify**: rechazamos la solicitud de cumplimiento en Shopify con el mensaje <b>No realizamos envíos a (país).</b> El pedido se queda en tu tienda Shopify, así que decides tú qué decirle a tu comprador. Aquí aparece como un pedido <b>Cancelled</b> con el mismo motivo.
- **Otras tiendas** (WooCommerce, eBay, Wix, TikTok Shop y el resto): el pedido no se paga y se queda en <b>Submitted</b>, con <b>En espera: No realizamos envíos a (país).</b> en sus notas. No se envía al almacén, y te enviamos un correo avisando de que está en espera.
- En la página del pedido, el recuadro amarillo que te pide recargar saldo y el botón <b>Pay ... with balance</b> están ocultos, porque el pedido no se puede enviar. El pedido sigue mostrándose como <b>No pagado</b>.

Qué hacer:

- **Cesta (canal Manual/API)**: haz clic en <b>Editar</b> junto a la dirección de entrega y cámbiala, si la dirección estaba mal.
- **Pedido de Shopify**: si la dirección estaba mal, corrígela en Shopify y solicita el cumplimiento de nuevo. La nueva solicitud sustituye aquí al pedido cancelado.
- **Pedido de otra tienda**: no puedes cambiar la dirección en el pedido. Avisa a tu comprador y cancela el pedido en tu tienda, o contacta con atención al cliente con la referencia del pedido.

Algunos países están bloqueados solo en parte, por código postal. Se muestra el mismo mensaje.

La lista es distinta para cada web. Esta web no envía a estos países:

{blocked_delivery_countries}

## "Your current billing address is marked as forbidden"

Este mensaje es sobre tu propia dirección de facturación, no la de tu comprador. Actualiza la dirección en tu cuenta, o contacta con atención al cliente.

## Shopify: "unable to deliver" en el checkout de tu tienda

Esto pasa en tu tienda Shopify, antes de que el pedido nos llegue. Shopify bloquea el checkout cuando no tiene ninguna tarifa de envío desde la ubicación del producto hasta el país del comprador. Los productos que has hecho tú mismo pueden seguir funcionando, porque usan una ubicación distinta.

Nuestros productos se almacenan en Shopify en nuestra ubicación de logística. Su nombre es <b>aiku-</b> seguido del código de la web, y luego el código de tu canal entre paréntesis, por ejemplo <b>aiku-awd (my-store)</b>. Consulta [La ubicación de logística de AW en Shopify](/docs/shopify-fulfilment-location). Comprueba esto en tu administrador de Shopify:

1. **Locations** (Settings → Locations): nuestra ubicación debe estar activa. Elimina ubicaciones de dropshipping antiguas o duplicadas que ya no uses.
2. **Shipping profile** (Settings → Shipping and delivery): abre el perfil que contiene nuestros productos y comprueba que nuestra ubicación está en él.
3. **Zones and rates**: en ese perfil, el país del comprador debe estar en una zona de envío, y la zona necesita al menos una tarifa (de pago o gratuita).
4. **Product**: abre el producto que falla y comprueba qué perfil de envío usa. Muévelo al perfil del paso 2 si hace falta.

<!-- screenshot: el perfil de envío de Shopify con la ubicación aiku- y una zona que contiene el país del comprador -->

Si los cuatro puntos son correctos y el checkout sigue fallando, contacta con el soporte de Shopify. Las zonas y tarifas de envío se configuran en tu tienda, así que no podemos cambiarlas por ti.

Incluso cuando Shopify permite el checkout, solo podemos enviar el pedido si el país no está en nuestra lista de arriba.

## Cuando algo va mal

**Mi pedido lleva días en Submitted y no hay botón de pago.** Abre el pedido. Si ves <b>We cannot deliver to ...</b>, el país está bloqueado. Contacta con atención al cliente con la referencia del pedido.

**El comprador dio un país equivocado por error.** En Shopify, corrige la dirección y solicita el cumplimiento de nuevo. En otras tiendas, contacta con atención al cliente con la referencia del pedido y la dirección correcta.
