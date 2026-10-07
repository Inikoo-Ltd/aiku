---
title: Creare clienti con l'API
summary: Come il tuo sistema crea, modifica e trova clienti tramite la nostra API, con un esempio completo di richiesta e risposta, i campi che puoi inviare e il significato di ogni errore.
date: 2026-10-05
source_date: 2026-10-05
tags: api, clienti, creare cliente, indirizzo, codice paese, integrazione, sviluppatore
category: sales-channels
series: manual
order: 5
shops: awd, dssk, dse
---

<aside class="tldr">
Invia <b>POST</b> a <b>https://api.aiku.io/dropshipping/clients</b> con il tuo token API e un corpo JSON. Ci serve solo l'indirizzo di consegna con il paese come codice ISO, per esempio <b>"country_code": "GB"</b>. Rispondiamo <b>201</b> con il nuovo cliente; conserva il suo <b>id</b> per creare ordini per quel cliente. Su staging usa invece <b>https://api.aiku-sandbox.uk</b>.
</aside>

## Cos'è un cliente

Un cliente è la persona o l'azienda a cui invii un ordine: il tuo acquirente. Ogni ordine che crei tramite l'API appartiene a un cliente, quindi il tuo sistema crea prima il cliente, poi crea gli ordini per lui. I clienti creati tramite l'API compaiono in <b>Clients</b> sotto il tuo canale, come quelli che aggiungi a mano.

Prima ti serve un token API. Vedi [Il canale Manual/API](/docs/manual-and-api-channel) per sapere come ottenerlo. Il token decide il canale: i clienti vengono creati nel canale a cui appartiene il token. Il token non deve essere di sola lettura.

## La richiesta

| | |
|---|---|
| Metodo | <b>POST</b> |
| Indirizzo (reale) | <b>https://api.aiku.io/dropshipping/clients</b> |
| Indirizzo (staging) | <b>https://api.aiku-sandbox.uk/dropshipping/clients</b> |
| Header | <b>Authorization: Bearer</b> seguito dal tuo token<br><b>Content-Type: application/json</b> |

La risposta è sempre JSON. Non serve inviare un header <b>Accept</b>.

### Un esempio completo

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

La richiesta più piccola che accettiamo è solo il paese:

```json
{
  "address": { "country_code": "GB" }
}
```

Un cliente così non può ricevere pacchi, quindi invia sempre l'indirizzo di consegna completo quando ce l'hai.

### I campi

| Campo | Obbligatorio | Cosa inviare |
|---|---|---|
| <b>address</b> | Sì | L'indirizzo di consegna, come oggetto con i campi qui sotto. |
| <b>address.country_code</b> | Sì | Il paese come codice ISO: due lettere (<b>GB</b>, <b>ES</b>, <b>DE</b>) o tre lettere (<b>GBR</b>, <b>ESP</b>, <b>DEU</b>). Maiuscolo o minuscolo. |
| <b>address.address_line_1</b> | Per la consegna | Via e numero. |
| <b>address.address_line_2</b> | No | Appartamento, piano, edificio. |
| <b>address.locality</b> | Per la consegna | Città o località. |
| <b>address.administrative_area</b> | Dipende dal paese | Contea, stato o provincia, dove il paese ne usa una. |
| <b>address.postal_code</b> | Per la consegna | CAP. |
| <b>address.dependent_locality</b> | No | Distretto o quartiere, dove il paese ne usa uno. |
| <b>address.sorting_code</b> | No | Solo per i paesi che usano un codice di smistamento. |
| <b>reference</b> | No | Un tuo codice per il cliente, per esempio il numero cliente nel tuo negozio. Deve essere unico nel tuo canale. |
| <b>contact_name</b> | No | Il nome della persona. |
| <b>company_name</b> | No | Il nome dell'azienda. |
| <b>email</b> | No | Un indirizzo email valido. |
| <b>phone</b> | No | Almeno 6 caratteri. Usa il formato internazionale, per esempio <b>+44 7700 900123</b>, così il corriere può chiamare. |

I campi dell'indirizzo che non sono in questo elenco, come <b>city</b>, <b>state</b>, <b>county</b> o <b>country</b>, vengono ignorati. Usa i nomi indicati sopra. Non esistono i campi <b>first_name</b> o <b>last_name</b>: invia il nome completo in <b>contact_name</b>.

Le integrazioni più vecchie possono inviare <b>address.country_id</b>, il nostro numero interno per il paese. Funziona ancora, ma usa <b>country_code</b>: se invii entrambi, vince <b>country_code</b>.

### La risposta quando funziona

Rispondiamo <b>201 Created</b> con il nuovo cliente:

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

<b>name</b> è il nome dell'azienda quando ne invii uno, altrimenti il nome della persona. <b>formatted_address</b> è l'indirizzo in HTML, pronto da mostrare.

Conserva <b>data.id</b>. Ti serve per creare ordini per questo cliente: <b>POST /dropshipping/order/client/{id}/store</b>.

## Modificare un cliente

Invia <b>PATCH</b> a <b>/dropshipping/clients/{id}</b> solo con i campi che vuoi cambiare. Quando cambi l'indirizzo, invia l'indirizzo completo incluso <b>country_code</b>.

```bash
curl -X PATCH https://api.aiku.io/dropshipping/clients/52817 \
  -H "Authorization: Bearer 123|AbCdEf..." \
  -H "Content-Type: application/json" \
  -d '{ "phone": "+44 7700 900999" }'
```

Rispondiamo <b>200</b> con il cliente, nella stessa forma di sopra, e <b>"message": "Client updated successfully"</b>.

## Trovare i tuoi clienti

- <b>GET /dropshipping/clients</b> elenca i clienti del tuo canale, pagina per pagina.
- <b>GET /dropshipping/clients/{id}</b> mostra un cliente.
- <b>DELETE /dropshipping/clients/{id}</b> disattiva un cliente. I suoi ordini passati vengono conservati.

Per non creare due volte lo stesso acquirente, invia il tuo numero cliente come <b>reference</b>. Un secondo cliente con la stessa <b>reference</b> nello stesso canale viene rifiutato.

## Quando qualcosa non funziona

Quando rifiutiamo una richiesta a causa dei dati, rispondiamo <b>422</b> con un <b>message</b> e il campo sbagliato sotto <b>errors</b>:

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

- <b>"The client's delivery address is required"</b>. Il corpo non ha un oggetto <b>address</b>. I campi dell'indirizzo devono stare dentro <b>address</b>, non al livello principale del corpo.
- <b>"The country is required"</b>. L'indirizzo non ha <b>country_code</b>. Aggiungilo.
- <b>"Unknown country code"</b>. Il codice non è un codice paese ISO. Il Regno Unito è <b>GB</b> o <b>GBR</b>, non <b>UK</b>.
- <b>"The reference has already been taken"</b>. Un altro cliente in questo canale ha quella <b>reference</b>. Usane una diversa, oppure modifica il cliente esistente con <b>PATCH</b>.
- <b>"The phone must be at least 6 characters"</b>. Invia il numero di telefono completo, oppure ometti <b>phone</b>.
- <b>401</b> con un messaggio sull'header <b>Authorization</b>. Il token manca, è incompleto o è stato eliminato. Copia tutto il token, compreso il numero e <b>|</b> all'inizio. Staging ha bisogno del suo token.
- <b>403 "This API token is read only."</b>. Genera un token senza <b>Read only</b> spuntato.
- <b>429</b>. Ogni token può fare fino a 120 richieste al minuto. Rallenta e riprova dopo un minuto.

Ogni richiesta fatta dal tuo sistema è elencata nella scheda <b>API calls</b> della pagina <b>API</b> del tuo canale, con la risposta che abbiamo dato. Guarda lì per prima cosa quando una richiesta non fa quello che ti aspetti.
