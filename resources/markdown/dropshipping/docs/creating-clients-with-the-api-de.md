---
title: Kunden mit der API anlegen
summary: Wie Ihr System über unsere API Kunden anlegt, ändert und findet, mit einer vollständigen Beispielanfrage und -antwort, den Feldern, die Sie senden können, und der Bedeutung jedes Fehlers.
date: 2026-10-05
source_date: 2026-10-05
tags: API, Kunden, Kunde anlegen, Adresse, Ländercode, Integration, Entwickler
category: sales-channels
series: manual
order: 5
shops: awd, dssk, dse
---

<aside class="tldr">
Senden Sie <b>POST</b> an <b>https://api.aiku.io/dropshipping/clients</b> mit Ihrem API-Token und einem JSON-Body. Wir brauchen nur die Lieferadresse mit dem Land als ISO-Code, zum Beispiel <b>"country_code": "GB"</b>. Wir antworten mit <b>201</b> und dem neuen Kunden; bewahren Sie seine <b>id</b> auf, um Bestellungen für diesen Kunden zu erstellen. Auf Staging verwenden Sie stattdessen <b>https://api.aiku-sandbox.uk</b>.
</aside>

## Was ein Kunde ist

Ein Kunde ist die Person oder das Unternehmen, an die Sie eine Bestellung senden: Ihr Käufer. Jede Bestellung, die Sie über die API erstellen, gehört zu einem Kunden, daher legt Ihr System zuerst den Kunden an und erstellt dann Bestellungen für ihn. Über die API angelegte Kunden erscheinen unter <b>Clients</b> in Ihrem Kanal, genau wie die, die Sie von Hand hinzufügen.

Sie brauchen zuerst ein API-Token. Siehe [The Manual/API channel](/docs/manual-and-api-channel), wie Sie eines erhalten. Das Token bestimmt den Kanal: Kunden werden in dem Kanal angelegt, zu dem das Token gehört. Das Token darf nicht nur lesend sein.

## Die Anfrage

| | |
|---|---|
| Methode | <b>POST</b> |
| Adresse (live) | <b>https://api.aiku.io/dropshipping/clients</b> |
| Adresse (Staging) | <b>https://api.aiku-sandbox.uk/dropshipping/clients</b> |
| Header | <b>Authorization: Bearer</b>, gefolgt von Ihrem Token<br><b>Content-Type: application/json</b> |

Die Antwort ist immer JSON. Sie müssen keinen <b>Accept</b>-Header senden.

### Ein vollständiges Beispiel

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

Die kleinste Anfrage, die wir akzeptieren, ist nur das Land:

```json
{
  "address": { "country_code": "GB" }
}
```

Ein solcher Kunde kann keine Pakete empfangen, senden Sie deshalb immer die vollständige Lieferadresse, wenn Sie sie haben.

### Die Felder

| Feld | Erforderlich | Was zu senden ist |
|---|---|---|
| <b>address</b> | Ja | Die Lieferadresse, als Objekt mit den folgenden Feldern. |
| <b>address.country_code</b> | Ja | Das Land als ISO-Code: zwei Buchstaben (<b>GB</b>, <b>ES</b>, <b>DE</b>) oder drei Buchstaben (<b>GBR</b>, <b>ESP</b>, <b>DEU</b>). Groß- oder Kleinschreibung. |
| <b>address.address_line_1</b> | Für die Lieferung | Straße und Hausnummer. |
| <b>address.address_line_2</b> | Nein | Wohnung, Stockwerk, Gebäude. |
| <b>address.locality</b> | Für die Lieferung | Ort oder Stadt. |
| <b>address.administrative_area</b> | Je nach Land | Grafschaft, Bundesland oder Provinz, wenn das Land eine verwendet. |
| <b>address.postal_code</b> | Für die Lieferung | Postleitzahl. |
| <b>address.dependent_locality</b> | Nein | Stadtteil oder Ortsteil, wenn das Land einen verwendet. |
| <b>address.sorting_code</b> | Nein | Nur für Länder, die einen Sortiercode verwenden. |
| <b>reference</b> | Nein | Ihr eigener Code für den Kunden, zum Beispiel die Kundennummer in Ihrem Shop. Er muss in Ihrem Kanal eindeutig sein. |
| <b>contact_name</b> | Nein | Der Name der Person. |
| <b>company_name</b> | Nein | Der Firmenname. |
| <b>email</b> | Nein | Eine gültige E-Mail-Adresse. |
| <b>phone</b> | Nein | Mindestens 6 Zeichen. Verwenden Sie das internationale Format, zum Beispiel <b>+44 7700 900123</b>, damit der Zusteller anrufen kann. |

Adressfelder, die nicht in dieser Liste stehen, wie <b>city</b>, <b>state</b>, <b>county</b> oder <b>country</b>, werden ignoriert. Verwenden Sie die oben genannten Namen. Es gibt keine Felder <b>first_name</b> oder <b>last_name</b>: Senden Sie den vollständigen Namen in <b>contact_name</b>.

Ältere Integrationen senden möglicherweise <b>address.country_id</b>, unsere eigene Nummer für das Land. Das funktioniert weiterhin, verwenden Sie aber <b>country_code</b>: Wenn Sie beide senden, gilt <b>country_code</b>.

### Die Antwort, wenn es funktioniert

Wir antworten mit <b>201 Created</b> und dem neuen Kunden:

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

<b>name</b> ist der Firmenname, wenn Sie einen senden, sonst der Kontaktname. <b>formatted_address</b> ist die Adresse als HTML, bereit zur Anzeige.

Bewahren Sie <b>data.id</b> auf. Sie brauchen sie, um Bestellungen für diesen Kunden zu erstellen: <b>POST /dropshipping/order/client/{id}/store</b>.

## Einen Kunden ändern

Senden Sie <b>PATCH</b> an <b>/dropshipping/clients/{id}</b> mit nur den Feldern, die Sie ändern möchten. Wenn Sie die Adresse ändern, senden Sie die ganze Adresse einschließlich <b>country_code</b>.

```bash
curl -X PATCH https://api.aiku.io/dropshipping/clients/52817 \
  -H "Authorization: Bearer 123|AbCdEf..." \
  -H "Content-Type: application/json" \
  -d '{ "phone": "+44 7700 900999" }'
```

Wir antworten mit <b>200</b> und dem Kunden, in derselben Form wie oben, und <b>"message": "Client updated successfully"</b>.

## Ihre Kunden finden

- <b>GET /dropshipping/clients</b> listet die Kunden Ihres Kanals seitenweise auf.
- <b>GET /dropshipping/clients/{id}</b> zeigt einen Kunden.
- <b>DELETE /dropshipping/clients/{id}</b> deaktiviert einen Kunden. Seine vergangenen Bestellungen bleiben erhalten.

Um zu vermeiden, denselben Käufer zweimal anzulegen, senden Sie Ihre eigene Kundennummer als <b>reference</b>. Ein zweiter Kunde mit derselben <b>reference</b> im selben Kanal wird abgelehnt.

## Wenn etwas schiefgeht

Lehnen wir eine Anfrage wegen der Daten ab, antworten wir mit <b>422</b> und einer <b>message</b> sowie dem fehlerhaften Feld unter <b>errors</b>:

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

- <b>"The client's delivery address is required"</b>. Der Body enthält kein <b>address</b>-Objekt. Die Adressfelder müssen innerhalb von <b>address</b> stehen, nicht auf der obersten Ebene des Bodys.
- <b>"The country is required"</b>. Die Adresse hat kein <b>country_code</b>. Fügen Sie es hinzu.
- <b>"Unknown country code"</b>. Der Code ist kein ISO-Ländercode. Das Vereinigte Königreich ist <b>GB</b> oder <b>GBR</b>, nicht <b>UK</b>.
- <b>"The reference has already been taken"</b>. Ein anderer Kunde in diesem Kanal hat diese <b>reference</b>. Verwenden Sie eine andere, oder ändern Sie den bestehenden Kunden mit <b>PATCH</b>.
- <b>"The phone must be at least 6 characters"</b>. Senden Sie die vollständige Telefonnummer, oder lassen Sie <b>phone</b> weg.
- <b>401</b> mit einer Meldung über den <b>Authorization</b>-Header. Das Token fehlt, ist unvollständig oder wurde gelöscht. Kopieren Sie das ganze Token, einschließlich der Zahl und des <b>|</b> am Anfang. Staging braucht sein eigenes Token.
- <b>403 "This API token is read only."</b>. Erzeugen Sie ein Token ohne angehaktes <b>Read only</b>.
- <b>429</b>. Jedes Token kann bis zu 120 Anfragen pro Minute stellen. Verlangsamen Sie Ihr System und versuchen Sie es nach einer Minute erneut.

Jede Anfrage Ihres Systems wird im Tab <b>API calls</b> auf der Seite <b>API</b> Ihres Kanals aufgeführt, mit der Antwort, die wir gegeben haben. Schauen Sie zuerst dort nach, wenn eine Anfrage nicht das tut, was Sie erwarten.
