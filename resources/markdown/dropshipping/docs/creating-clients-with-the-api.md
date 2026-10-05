---
title: Creating clients with the API
summary: How your system creates, changes and finds clients through our API, with a complete example request and answer, the fields you can send and what each error means.
date: 2026-10-05
tags: api, clients, create client, address, country code, integration, developer
category: sales-channels
series: manual
order: 5
help_routes: retina.dropshipping.customer_sales_channels.api.dashboard
shops: awd, dssk, dse
---

<aside class="tldr">
Send <b>POST</b> to <b>https://api.aiku.io/dropshipping/clients</b> with your API token and a JSON body. The only thing we need is the delivery address with its country as an ISO code, for example <b>"country_code": "GB"</b>. We answer <b>201</b> with the new client; keep its <b>id</b> to create orders for that client. On staging use <b>https://api.aiku-sandbox.uk</b> instead.
</aside>

## What a client is

A client is the person or business you send an order to: your buyer. Every order you create through the API belongs to a client, so your system creates the client first, then creates orders for it. Clients created through the API appear in <b>Clients</b> under your channel, the same as the ones you add by hand.

You need an API token first. See [The Manual/API channel](/docs/manual-and-api-channel) for how to get one. The token decides the channel: clients are created in the channel the token belongs to. The token must not be read only.

## The request

| | |
|---|---|
| Method | <b>POST</b> |
| Address (live) | <b>https://api.aiku.io/dropshipping/clients</b> |
| Address (staging) | <b>https://api.aiku-sandbox.uk/dropshipping/clients</b> |
| Headers | <b>Authorization: Bearer</b> followed by your token<br><b>Content-Type: application/json</b> |

The answer is always JSON. You do not need to send an <b>Accept</b> header.

### A complete example

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

The smallest request we accept is just the country:

```json
{
  "address": { "country_code": "GB" }
}
```

A client like that cannot receive parcels, so always send the full delivery address when you have it.

### The fields

| Field | Required | What to send |
|---|---|---|
| <b>address</b> | Yes | The delivery address, as an object with the fields below. |
| <b>address.country_code</b> | Yes | The country as an ISO code: two letters (<b>GB</b>, <b>ES</b>, <b>DE</b>) or three letters (<b>GBR</b>, <b>ESP</b>, <b>DEU</b>). Upper or lower case. |
| <b>address.address_line_1</b> | For delivery | Street and number. |
| <b>address.address_line_2</b> | No | Flat, floor, building. |
| <b>address.locality</b> | For delivery | Town or city. |
| <b>address.administrative_area</b> | Depends on the country | County, state or province, where the country uses one. |
| <b>address.postal_code</b> | For delivery | Postcode or ZIP. |
| <b>address.dependent_locality</b> | No | District or neighbourhood, where the country uses one. |
| <b>address.sorting_code</b> | No | Only for countries that use a sorting code. |
| <b>reference</b> | No | Your own code for the client, for example the customer number in your shop. It must be unique in your channel. |
| <b>contact_name</b> | No | The person's name. |
| <b>company_name</b> | No | The company name. |
| <b>email</b> | No | A valid email address. |
| <b>phone</b> | No | At least 6 characters. Use the international format, for example <b>+44 7700 900123</b>, so the courier can call. |

Address fields that are not in this list, such as <b>city</b>, <b>state</b>, <b>county</b> or <b>country</b>, are ignored. Use the names above. There are no <b>first_name</b> or <b>last_name</b> fields: send the full name in <b>contact_name</b>.

Older integrations may send <b>address.country_id</b>, our own number for the country. It still works, but use <b>country_code</b>: if you send both, <b>country_code</b> wins.

### The answer when it works

We answer <b>201 Created</b> with the new client:

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

<b>name</b> is the company name when you send one, otherwise the contact name. <b>formatted_address</b> is the address as HTML, ready to show.

Keep <b>data.id</b>. You need it to create orders for this client: <b>POST /dropshipping/order/client/{id}/store</b>.

## Change a client

Send <b>PATCH</b> to <b>/dropshipping/clients/{id}</b> with only the fields you want to change. When you change the address, send the whole address including <b>country_code</b>.

```bash
curl -X PATCH https://api.aiku.io/dropshipping/clients/52817 \
  -H "Authorization: Bearer 123|AbCdEf..." \
  -H "Content-Type: application/json" \
  -d '{ "phone": "+44 7700 900999" }'
```

We answer <b>200</b> with the client, in the same shape as above, and <b>"message": "Client updated successfully"</b>.

## Find your clients

- <b>GET /dropshipping/clients</b> lists the clients of your channel, page by page.
- <b>GET /dropshipping/clients/{id}</b> shows one client.
- <b>DELETE /dropshipping/clients/{id}</b> deactivates a client. Its past orders are kept.

To avoid creating the same buyer twice, send your own customer number as <b>reference</b>. A second client with the same <b>reference</b> in the same channel is refused.

## When something goes wrong

When we refuse a request because of the data, we answer <b>422</b> with a <b>message</b> and the field that is wrong under <b>errors</b>:

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

- <b>"The client's delivery address is required"</b>. The body has no <b>address</b> object. The address fields must be inside <b>address</b>, not at the top level of the body.
- <b>"The country is required"</b>. The address has no <b>country_code</b>. Add it.
- <b>"Unknown country code"</b>. The code is not an ISO country code. The United Kingdom is <b>GB</b> or <b>GBR</b>, not <b>UK</b>.
- <b>"The reference has already been taken"</b>. Another client in this channel has that <b>reference</b>. Use a different one, or change the existing client with <b>PATCH</b>.
- <b>"The phone must be at least 6 characters"</b>. Send the full phone number, or leave <b>phone</b> out.
- <b>401</b> with a message about the <b>Authorization</b> header. The token is missing, incomplete or deleted. Copy the whole token, including the number and <b>|</b> at the start. Staging needs its own token.
- <b>403 "This API token is read only."</b>. Generate a token without <b>Read only</b> ticked.
- <b>429</b>. Each token can make up to 120 requests a minute. Slow down and try again after a minute.

Every request your system makes is listed in the <b>API calls</b> tab of your channel's <b>API</b> page, with the answer we gave. Look there first when a request does not do what you expect.
