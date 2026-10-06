---
title: Crear clientes con la API
summary: Cómo tu sistema crea, cambia y busca clientes a través de nuestra API, con un ejemplo completo de petición y respuesta, los campos que puedes enviar y qué significa cada error.
date: 2026-10-05
source_date: 2026-10-05
tags: api, clientes, crear cliente, dirección, código de país, integración, desarrollador
category: sales-channels
series: manual
order: 5
shops: awd, dssk, dse
---

<aside class="tldr">
Envía <b>POST</b> a <b>https://api.aiku.io/dropshipping/clients</b> con tu token de API y un cuerpo JSON. Lo único que necesitamos es la dirección de entrega con su país como código ISO, por ejemplo <b>"country_code": "GB"</b>. Respondemos <b>201</b> con el nuevo cliente; guarda su <b>id</b> para crear pedidos de ese cliente. En staging usa <b>https://api.aiku-sandbox.uk</b> en su lugar.
</aside>

## Qué es un cliente

Un cliente es la persona o empresa a la que envías un pedido: tu comprador. Cada pedido que creas a través de la API pertenece a un cliente, así que tu sistema crea primero el cliente y después crea pedidos para él. Los clientes creados a través de la API aparecen en <b>Clientes</b> bajo tu canal, igual que los que añades a mano.

Primero necesitas un token de API. Consulta [El canal Manual/API](/docs/manual-and-api-channel) para saber cómo obtenerlo. El token decide el canal: los clientes se crean en el canal al que pertenece el token. El token no debe ser de solo lectura.

## La petición

| | |
|---|---|
| Método | <b>POST</b> |
| Dirección (sitio real) | <b>https://api.aiku.io/dropshipping/clients</b> |
| Dirección (staging) | <b>https://api.aiku-sandbox.uk/dropshipping/clients</b> |
| Cabeceras | <b>Authorization: Bearer</b> seguida de tu token<br><b>Content-Type: application/json</b> |

La respuesta siempre es JSON. No necesitas enviar la cabecera <b>Accept</b>.

### Un ejemplo completo

```bash
curl -X POST https://api.aiku.io/dropshipping/clients \
  -H "Authorization: Bearer 123|AbCdEf..." \
  -H "Content-Type: application/json" \
  -d '{
    "reference": "WEB-10045",
    "contact_name": "Jane Smith",
    "company_name": "Smith Gifts Ltd",
    "email": "jane@example.com",
    "phone": "+44 7700 900123",
    "address": {
      "address_line_1": "12 High Street",
      "address_line_2": "Flat 3",
      "locality": "Sheffield",
      "administrative_area": "South Yorkshire",
      "postal_code": "S1 2AB",
      "country_code": "GB"
    }
  }'
```

La petición más pequeña que aceptamos es solo el país:

```json
{
  "address": { "country_code": "GB" }
}
```

Un cliente así no puede recibir paquetes, por eso envía siempre la dirección de entrega completa cuando la tengas.

### Los campos

| Campo | Obligatorio | Qué enviar |
|---|---|---|
| <b>address</b> | Sí | La dirección de entrega, como un objeto con los campos de abajo. |
| <b>address.country_code</b> | Sí | El país como código ISO: dos letras (<b>GB</b>, <b>ES</b>, <b>DE</b>) o tres letras (<b>GBR</b>, <b>ESP</b>, <b>DEU</b>). En mayúsculas o minúsculas. |
| <b>address.address_line_1</b> | Para la entrega | Calle y número. |
| <b>address.address_line_2</b> | No | Piso, planta, edificio. |
| <b>address.locality</b> | Para la entrega | Población o ciudad. |
| <b>address.administrative_area</b> | Depende del país | Condado, estado o provincia, si el país usa uno. |
| <b>address.postal_code</b> | Para la entrega | Código postal. |
| <b>address.dependent_locality</b> | No | Distrito o barrio, si el país usa uno. |
| <b>address.sorting_code</b> | No | Solo para países que usan un código de clasificación. |
| <b>reference</b> | No | Tu propio código para el cliente, por ejemplo el número de cliente en tu tienda. Debe ser único en tu canal. |
| <b>contact_name</b> | No | El nombre de la persona. |
| <b>company_name</b> | No | El nombre de la empresa. |
| <b>email</b> | No | Una dirección de correo válida. |
| <b>phone</b> | No | Al menos 6 caracteres. Usa el formato internacional, por ejemplo <b>+44 7700 900123</b>, para que el transportista pueda llamar. |

Los campos de dirección que no están en esta lista, como <b>city</b>, <b>state</b>, <b>county</b> o <b>country</b>, se ignoran. Usa los nombres de arriba. No existen los campos <b>first_name</b> ni <b>last_name</b>: envía el nombre completo en <b>contact_name</b>.

Las integraciones antiguas pueden enviar <b>address.country_id</b>, nuestro propio número para el país. Sigue funcionando, pero usa <b>country_code</b>: si envías los dos, gana <b>country_code</b>.

### La respuesta cuando funciona

Respondemos <b>201 Created</b> con el nuevo cliente:

```json
{
  "data": {
    "id": 52817,
    "ulid": "01M4639WS5Z7QT08CNW0M0DEJQ",
    "reference": "WEB-10045",
    "active": null,
    "name": "Smith Gifts Ltd",
    "contact_name": "Jane Smith",
    "company_name": "Smith Gifts Ltd",
    "location": ["GB", "United Kingdom", "Sheffield"],
    "email": "jane@example.com",
    "phone": "+44 7700 900123",
    "created_at": "2026-10-05T13:15:59.000000Z",
    "updated_at": "2026-10-05T13:15:59.000000Z",
    "address": {
      "id": 9123456,
      "address_line_1": "12 High Street",
      "address_line_2": "Flat 3",
      "sorting_code": null,
      "postal_code": "S1 2AB",
      "locality": "Sheffield",
      "dependent_locality": null,
      "administrative_area": "South Yorkshire",
      "country_code": "GB",
      "country_id": 48,
      "checksum": "74d55290109e98f64b7d908a3e66b9d8",
      "created_at": "2026-10-05T13:15:59.000000Z",
      "updated_at": "2026-10-05T13:15:59.000000Z",
      "country": { "code": "GB", "iso3": "GBR", "name": "United Kingdom" },
      "formatted_address": "<p translate=\"no\">...12 High Street<br>Flat 3<br>Sheffield<br>S1 2AB<br>United Kingdom</p>",
      "can_edit": null,
      "can_delete": null
    }
  },
  "message": "Client created successfully"
}
```

<b>name</b> es el nombre de la empresa cuando envías uno; si no, el nombre de contacto. <b>formatted_address</b> es la dirección en HTML, lista para mostrar.

Guarda <b>data.id</b>. Lo necesitas para crear pedidos de este cliente: <b>POST /dropshipping/order/client/{id}/store</b>.

## Cambiar un cliente

Envía <b>PATCH</b> a <b>/dropshipping/clients/{id}</b> solo con los campos que quieres cambiar. Cuando cambies la dirección, envía la dirección completa, incluido <b>country_code</b>.

```bash
curl -X PATCH https://api.aiku.io/dropshipping/clients/52817 \
  -H "Authorization: Bearer 123|AbCdEf..." \
  -H "Content-Type: application/json" \
  -d '{ "phone": "+44 7700 900999" }'
```

Respondemos <b>200</b> con el cliente, con la misma forma de arriba, y <b>"message": "Client updated successfully"</b>.

## Buscar tus clientes

- <b>GET /dropshipping/clients</b> lista los clientes de tu canal, página por página.
- <b>GET /dropshipping/clients/{id}</b> muestra un cliente.
- <b>DELETE /dropshipping/clients/{id}</b> desactiva un cliente. Sus pedidos anteriores se conservan.

Para no crear dos veces al mismo comprador, envía tu propio número de cliente como <b>reference</b>. Un segundo cliente con la misma <b>reference</b> en el mismo canal se rechaza.

## Cuando algo va mal

Cuando rechazamos una petición por los datos, respondemos <b>422</b> con un <b>message</b> y el campo incorrecto en <b>errors</b>:

```json
{
  "message": "Unknown country code \"UK\". Send the ISO code of the country, two letters (GB) or three letters (GBR).",
  "errors": {
    "address.country_code": [
      "Unknown country code \"UK\". Send the ISO code of the country, two letters (GB) or three letters (GBR)."
    ]
  }
}
```

- <b>"The client's delivery address is required"</b>. El cuerpo no tiene el objeto <b>address</b>. Los campos de dirección deben ir dentro de <b>address</b>, no en el nivel superior del cuerpo.
- <b>"The country is required"</b>. La dirección no tiene <b>country_code</b>. Añádelo.
- <b>"Unknown country code"</b>. El código no es un código ISO de país. El Reino Unido es <b>GB</b> o <b>GBR</b>, no <b>UK</b>.
- <b>"The reference has already been taken"</b>. Otro cliente de este canal tiene esa <b>reference</b>. Usa otra, o cambia el cliente existente con <b>PATCH</b>.
- <b>"The phone must be at least 6 characters"</b>. Envía el número de teléfono completo, o deja fuera <b>phone</b>.
- <b>401</b> con un mensaje sobre la cabecera <b>Authorization</b>. El token falta, está incompleto o fue borrado. Copia el token entero, incluido el número y el <b>|</b> del principio. Staging necesita su propio token.
- <b>403 "This API token is read only."</b>. Genera un token sin marcar <b>Read only</b>.
- <b>429</b>. Cada token puede hacer hasta 120 peticiones por minuto. Ralentiza tu sistema e inténtalo de nuevo pasado un minuto.

Cada petición que hace tu sistema aparece en la pestaña <b>API calls</b> de la página <b>API</b> de tu canal, con la respuesta que dimos. Mira ahí primero cuando una petición no haga lo que esperas.
