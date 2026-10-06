---
title: Créer des clients avec l'API
summary: Comment votre système crée, modifie et retrouve des clients via notre API, avec un exemple complet de requête et de réponse, les champs que vous pouvez envoyer et le sens de chaque erreur.
date: 2026-10-05
source_date: 2026-10-05
tags: api, clients, créer un client, adresse, code pays, intégration, développeur
category: sales-channels
series: manual
order: 5
shops: awd, dssk, dse
---

<aside class="tldr">
Envoyez <b>POST</b> à <b>https://api.aiku.io/dropshipping/clients</b> avec votre jeton API et un corps JSON. La seule chose dont nous avons besoin est l'adresse de livraison avec son pays sous forme de code ISO, par exemple <b>"country_code": "GB"</b>. Nous répondons <b>201</b> avec le nouveau client ; gardez son <b>id</b> pour créer des commandes pour ce client. En staging, utilisez <b>https://api.aiku-sandbox.uk</b> à la place.
</aside>

## Ce qu'est un client

Un client est la personne ou l'entreprise à qui vous envoyez une commande : votre acheteur. Chaque commande que vous créez via l'API appartient à un client, votre système crée donc d'abord le client, puis crée des commandes pour lui. Les clients créés via l'API apparaissent dans <b>Clients</b> sous votre canal, comme ceux que vous ajoutez à la main.

Il vous faut d'abord un jeton API. Voir [Le canal Manuel/API](/docs/manual-and-api-channel) pour l'obtenir. Le jeton détermine le canal : les clients sont créés dans le canal auquel le jeton appartient. Le jeton ne doit pas être en lecture seule.

## La requête

| | |
|---|---|
| Méthode | <b>POST</b> |
| Adresse (réel) | <b>https://api.aiku.io/dropshipping/clients</b> |
| Adresse (staging) | <b>https://api.aiku-sandbox.uk/dropshipping/clients</b> |
| En-têtes | <b>Authorization: Bearer</b> suivi de votre jeton<br><b>Content-Type: application/json</b> |

La réponse est toujours en JSON. Vous n'avez pas besoin d'envoyer d'en-tête <b>Accept</b>.

### Un exemple complet

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

La plus petite requête que nous acceptons est simplement le pays :

```json
{
  "address": { "country_code": "GB" }
}
```

Un client comme celui-là ne peut pas recevoir de colis, envoyez donc toujours l'adresse de livraison complète quand vous l'avez.

### Les champs

| Champ | Obligatoire | Quoi envoyer |
|---|---|---|
| <b>address</b> | Oui | L'adresse de livraison, sous forme d'objet avec les champs ci-dessous. |
| <b>address.country_code</b> | Oui | Le pays sous forme de code ISO : deux lettres (<b>GB</b>, <b>ES</b>, <b>DE</b>) ou trois lettres (<b>GBR</b>, <b>ESP</b>, <b>DEU</b>). Majuscules ou minuscules. |
| <b>address.address_line_1</b> | Pour la livraison | Rue et numéro. |
| <b>address.address_line_2</b> | Non | Appartement, étage, bâtiment. |
| <b>address.locality</b> | Pour la livraison | Ville. |
| <b>address.administrative_area</b> | Selon le pays | Comté, état ou province, quand le pays en utilise un. |
| <b>address.postal_code</b> | Pour la livraison | Code postal. |
| <b>address.dependent_locality</b> | Non | Quartier ou district, quand le pays en utilise un. |
| <b>address.sorting_code</b> | Non | Seulement pour les pays qui utilisent un code de tri. |
| <b>reference</b> | Non | Votre propre code pour le client, par exemple le numéro de client dans votre boutique. Il doit être unique dans votre canal. |
| <b>contact_name</b> | Non | Le nom de la personne. |
| <b>company_name</b> | Non | Le nom de l'entreprise. |
| <b>email</b> | Non | Une adresse e-mail valide. |
| <b>phone</b> | Non | Au moins 6 caractères. Utilisez le format international, par exemple <b>+44 7700 900123</b>, pour que le transporteur puisse appeler. |

Les champs d'adresse qui ne figurent pas dans cette liste, comme <b>city</b>, <b>state</b>, <b>county</b> ou <b>country</b>, sont ignorés. Utilisez les noms ci-dessus. Il n'y a pas de champs <b>first_name</b> ou <b>last_name</b> : envoyez le nom complet dans <b>contact_name</b>.

Les anciennes intégrations peuvent envoyer <b>address.country_id</b>, notre propre numéro pour le pays. Cela fonctionne toujours, mais utilisez <b>country_code</b> : si vous envoyez les deux, <b>country_code</b> l'emporte.

### La réponse quand tout fonctionne

Nous répondons <b>201 Created</b> avec le nouveau client :

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

<b>name</b> est le nom de l'entreprise quand vous en envoyez un, sinon le nom du contact. <b>formatted_address</b> est l'adresse en HTML, prête à afficher.

Gardez <b>data.id</b>. Il vous sert à créer des commandes pour ce client : <b>POST /dropshipping/order/client/{id}/store</b>.

## Modifier un client

Envoyez <b>PATCH</b> à <b>/dropshipping/clients/{id}</b> avec seulement les champs que vous voulez changer. Quand vous changez l'adresse, envoyez l'adresse complète, <b>country_code</b> compris.

```bash
curl -X PATCH https://api.aiku.io/dropshipping/clients/52817 \
  -H "Authorization: Bearer 123|AbCdEf..." \
  -H "Content-Type: application/json" \
  -d '{ "phone": "+44 7700 900999" }'
```

Nous répondons <b>200</b> avec le client, dans la même forme que ci-dessus, et <b>"message": "Client updated successfully"</b>.

## Retrouver vos clients

- <b>GET /dropshipping/clients</b> liste les clients de votre canal, page par page.
- <b>GET /dropshipping/clients/{id}</b> affiche un client.
- <b>DELETE /dropshipping/clients/{id}</b> désactive un client. Ses commandes passées sont conservées.

Pour éviter de créer deux fois le même acheteur, envoyez votre propre numéro de client comme <b>reference</b>. Un second client avec la même <b>reference</b> dans le même canal est refusé.

## En cas de problème

Quand nous refusons une requête à cause des données, nous répondons <b>422</b> avec un <b>message</b> et le champ erroné sous <b>errors</b> :

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

- <b>"The client's delivery address is required"</b>. Le corps n'a pas d'objet <b>address</b>. Les champs d'adresse doivent être à l'intérieur de <b>address</b>, pas au premier niveau du corps.
- <b>"The country is required"</b>. L'adresse n'a pas de <b>country_code</b>. Ajoutez-le.
- <b>"Unknown country code"</b>. Le code n'est pas un code pays ISO. Le Royaume-Uni est <b>GB</b> ou <b>GBR</b>, pas <b>UK</b>.
- <b>"The reference has already been taken"</b>. Un autre client de ce canal a cette <b>reference</b>. Utilisez-en une autre, ou modifiez le client existant avec <b>PATCH</b>.
- <b>"The phone must be at least 6 characters"</b>. Envoyez le numéro de téléphone complet, ou omettez <b>phone</b>.
- <b>401</b> avec un message sur l'en-tête <b>Authorization</b>. Le jeton est absent, incomplet ou supprimé. Copiez le jeton en entier, y compris le numéro et le <b>|</b> au début. Le staging a besoin de son propre jeton.
- <b>403 "This API token is read only."</b>. Générez un jeton sans <b>Read only</b> coché.
- <b>429</b>. Chaque jeton peut faire jusqu'à 120 requêtes par minute. Ralentissez et réessayez après une minute.

Chaque requête que fait votre système est listée dans l'onglet <b>API calls</b> de la page <b>API</b> de votre canal, avec la réponse que nous avons donnée. Regardez d'abord là quand une requête ne fait pas ce que vous attendez.
