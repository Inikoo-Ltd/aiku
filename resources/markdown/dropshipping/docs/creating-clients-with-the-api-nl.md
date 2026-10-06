---
title: Klanten aanmaken met de API
summary: Hoe uw systeem via onze API klanten aanmaakt, wijzigt en opzoekt, met een volledig voorbeeld van verzoek en antwoord, de velden die u kunt sturen en wat elke fout betekent.
date: 2026-10-05
source_date: 2026-10-05
tags: api, klanten, klant aanmaken, adres, landcode, integratie, ontwikkelaar
category: sales-channels
series: manual
order: 5
shops: awd, dssk, dse
---

<aside class="tldr">
Stuur <b>POST</b> naar <b>https://api.aiku.io/dropshipping/clients</b> met uw API-token en een JSON-body. Het enige wat wij nodig hebben is het afleveradres met het land als ISO-code, bijvoorbeeld <b>"country_code": "GB"</b>. Wij antwoorden met <b>201</b> en de nieuwe klant; bewaar het <b>id</b> om bestellingen voor die klant aan te maken. Gebruik op staging <b>https://api.aiku-sandbox.uk</b>.
</aside>

## Wat een klant is

Een klant is de persoon of het bedrijf naar wie u een bestelling stuurt: uw koper. Elke bestelling die u via de API aanmaakt, hoort bij een klant. Uw systeem maakt dus eerst de klant aan en daarna de bestellingen voor die klant. Klanten die via de API zijn aangemaakt, verschijnen onder <b>Clients</b> in uw kanaal, net als de klanten die u met de hand toevoegt.

U heeft eerst een API-token nodig. Zie [Het Manual/API-kanaal](/docs/manual-and-api-channel) om te lezen hoe u er een krijgt. Het token bepaalt het kanaal: klanten worden aangemaakt in het kanaal waartoe het token behoort. Het token mag niet alleen-lezen zijn.

## Het verzoek

| | |
|---|---|
| Methode | <b>POST</b> |
| Adres (live) | <b>https://api.aiku.io/dropshipping/clients</b> |
| Adres (staging) | <b>https://api.aiku-sandbox.uk/dropshipping/clients</b> |
| Headers | <b>Authorization: Bearer</b> gevolgd door uw token<br><b>Content-Type: application/json</b> |

Het antwoord is altijd JSON. U hoeft geen <b>Accept</b>-header mee te sturen.

### Een volledig voorbeeld

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

Het kleinste verzoek dat wij accepteren is alleen het land:

```json
{
  "address": { "country_code": "GB" }
}
```

Een klant zoals deze kan geen pakketten ontvangen, stuur dus altijd het volledige afleveradres mee als u dat heeft.

### De velden

| Veld | Verplicht | Wat u stuurt |
|---|---|---|
| <b>address</b> | Ja | Het afleveradres, als object met de onderstaande velden. |
| <b>address.country_code</b> | Ja | Het land als ISO-code: twee letters (<b>GB</b>, <b>ES</b>, <b>DE</b>) of drie letters (<b>GBR</b>, <b>ESP</b>, <b>DEU</b>). Hoofdletters of kleine letters. |
| <b>address.address_line_1</b> | Voor levering | Straat en huisnummer. |
| <b>address.address_line_2</b> | Nee | Appartement, verdieping, gebouw. |
| <b>address.locality</b> | Voor levering | Plaats of stad. |
| <b>address.administrative_area</b> | Hangt van het land af | Graafschap, staat of provincie, als het land die gebruikt. |
| <b>address.postal_code</b> | Voor levering | Postcode of ZIP. |
| <b>address.dependent_locality</b> | Nee | Wijk of buurt, als het land die gebruikt. |
| <b>address.sorting_code</b> | Nee | Alleen voor landen die een sorteercode gebruiken. |
| <b>reference</b> | Nee | Uw eigen code voor de klant, bijvoorbeeld het klantnummer in uw shop. De code moet uniek zijn in uw kanaal. |
| <b>contact_name</b> | Nee | De naam van de persoon. |
| <b>company_name</b> | Nee | De bedrijfsnaam. |
| <b>email</b> | Nee | Een geldig e-mailadres. |
| <b>phone</b> | Nee | Minstens 6 tekens. Gebruik het internationale formaat, bijvoorbeeld <b>+44 7700 900123</b>, zodat de koerier kan bellen. |

Adresvelden die niet in deze lijst staan, zoals <b>city</b>, <b>state</b>, <b>county</b> of <b>country</b>, worden genegeerd. Gebruik de namen hierboven. Er zijn geen velden <b>first_name</b> of <b>last_name</b>: stuur de volledige naam in <b>contact_name</b>.

Oudere integraties sturen soms <b>address.country_id</b>, ons eigen nummer voor het land. Het werkt nog steeds, maar gebruik <b>country_code</b>: als u beide stuurt, wint <b>country_code</b>.

### Het antwoord als het lukt

Wij antwoorden met <b>201 Created</b> en de nieuwe klant:

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

<b>name</b> is de bedrijfsnaam als u er een stuurt, anders de contactnaam. <b>formatted_address</b> is het adres als HTML, klaar om te tonen.

Bewaar <b>data.id</b>. U heeft het nodig om bestellingen voor deze klant aan te maken: <b>POST /dropshipping/order/client/{id}/store</b>.

## Een klant wijzigen

Stuur <b>PATCH</b> naar <b>/dropshipping/clients/{id}</b> met alleen de velden die u wilt wijzigen. Wijzigt u het adres, stuur dan het hele adres mee, inclusief <b>country_code</b>.

```bash
curl -X PATCH https://api.aiku.io/dropshipping/clients/52817 \
  -H "Authorization: Bearer 123|AbCdEf..." \
  -H "Content-Type: application/json" \
  -d '{ "phone": "+44 7700 900999" }'
```

Wij antwoorden met <b>200</b> en de klant, in dezelfde vorm als hierboven, en <b>"message": "Client updated successfully"</b>.

## Uw klanten opzoeken

- <b>GET /dropshipping/clients</b> toont de klanten van uw kanaal, pagina voor pagina.
- <b>GET /dropshipping/clients/{id}</b> toont één klant.
- <b>DELETE /dropshipping/clients/{id}</b> deactiveert een klant. De eerdere bestellingen blijven bewaard.

Om te voorkomen dat u dezelfde koper twee keer aanmaakt, stuurt u uw eigen klantnummer als <b>reference</b>. Een tweede klant met dezelfde <b>reference</b> in hetzelfde kanaal wordt geweigerd.

## Als er iets misgaat

Weigeren wij een verzoek vanwege de gegevens, dan antwoorden wij met <b>422</b>, een <b>message</b> en het veld dat niet klopt onder <b>errors</b>:

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

- <b>"The client's delivery address is required"</b>. De body heeft geen <b>address</b>-object. De adresvelden moeten binnen <b>address</b> staan, niet op het hoogste niveau van de body.
- <b>"The country is required"</b>. Het adres heeft geen <b>country_code</b>. Voeg het toe.
- <b>"Unknown country code"</b>. De code is geen ISO-landcode. Het Verenigd Koninkrijk is <b>GB</b> of <b>GBR</b>, niet <b>UK</b>.
- <b>"The reference has already been taken"</b>. Een andere klant in dit kanaal heeft die <b>reference</b>. Gebruik een andere, of wijzig de bestaande klant met <b>PATCH</b>.
- <b>"The phone must be at least 6 characters"</b>. Stuur het volledige telefoonnummer, of laat <b>phone</b> weg.
- <b>401</b> met een bericht over de <b>Authorization</b>-header. Het token ontbreekt, is onvolledig of is verwijderd. Kopieer het hele token, inclusief het nummer en de <b>|</b> aan het begin. Staging heeft een eigen token nodig.
- <b>403 "This API token is read only."</b>. Genereer een token zonder <b>Read only</b> aangevinkt.
- <b>429</b>. Elk token kan tot 120 verzoeken per minuut doen. Vertraag en probeer het na een minuut opnieuw.

Elk verzoek dat uw systeem doet, staat met ons antwoord in het tabblad <b>API calls</b> op de pagina <b>API</b> van uw kanaal. Kijk daar eerst als een verzoek niet doet wat u verwacht.
