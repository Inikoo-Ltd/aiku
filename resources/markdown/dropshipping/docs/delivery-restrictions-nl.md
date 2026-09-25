---
title: Landen waar wij niet naartoe kunnen leveren
summary: Wat het bericht "We cannot deliver to" betekent bij een mandje of een bestelling, en hoe u Shopify-checkouts herstelt die weigeren onze producten naar een land te verzenden.
date: 2026-09-25
source_date: 2026-09-25
tags: levering, landen, verzending, shopify, verzendprofiel, verboden adres
category: orders
shops: awd, dssk, dse
---

<aside class="tldr">
Elk van onze websites heeft een lijst met landen waar niet naartoe geleverd wordt. Ligt het afleveradres van een bestelling in zo'n land, dan ziet u <b>We cannot deliver to ...</b>, kunt u niet betalen, en gaat de bestelling niet naar het magazijn. Wijzig het adres zolang de bestelling nog een mandje is, of vraag het ons in de chat op onze website. Een ander probleem is een Shopify-checkout die onze producten niet naar een land wil verzenden: dat is een verzendinstelling in uw Shopify-winkel.
</aside>

## "We cannot deliver to ..." bij uw mandje of bestelling

Ligt het afleveradres in een land waar de website niet naartoe levert, dan ziet u dit in het rood:

<b>We cannot deliver to (country). Please update the address or contact support.</b>

Wat er dan gebeurt:

- In een mandje worden de knoppen <b>Continue to Checkout</b> en <b>Place order</b> verborgen.
- Een bestelling die vanuit uw winkel binnenkomt, wordt niet betaald en blijft <b>Submitted</b>. Ze wordt niet naar het magazijn gestuurd.
- Op de bestelpagina worden het gele vak om op te waarderen en de knop <b>Pay ... with balance</b> verborgen, omdat de bestelling niet verzonden kan worden. De bestelling toont nog steeds <b>Unpaid</b>.

Wat u kunt doen:

- **Mandje (kanaal Manual/API)**: klik op <b>Edit</b> naast het afleveradres en wijzig het, als het adres verkeerd was.
- **Bestelling vanuit uw winkel**: u kunt het adres op de bestelling niet wijzigen. Vraag het ons in de chat op onze website met de bestelreferentie.

Sommige landen zijn slechts voor een deel van het land geblokkeerd, per postcode. Hetzelfde bericht wordt dan getoond.

De lijst verschilt per website. Deze website levert niet naar deze landen:

{blocked_delivery_countries}

## "Your current billing address is marked as forbidden"

Dit bericht gaat over uw eigen factuuradres, niet dat van uw koper. Werk het adres bij in uw account, of vraag het ons in de chat op onze website.

## Shopify: "unable to deliver" bij de checkout van uw winkel

Dit gebeurt in uw Shopify-winkel, voordat de bestelling ons bereikt. Shopify blokkeert de checkout wanneer er geen verzendtarief is van de locatie van het product naar het land van de koper. Producten die u zelf heeft gemaakt, kunnen nog steeds werken, omdat zij een andere locatie gebruiken.

Onze producten worden in Shopify voorraad gehouden op onze fulfilmentlocatie. De naam ervan is <b>aiku-</b> gevolgd door de code van de website, en dan de code van uw kanaal tussen haakjes, bijvoorbeeld <b>aiku-awd (my-store)</b>. Zie [De AW-fulfilmentlocatie in Shopify](/docs/shopify-fulfilment-location). Controleer deze instellingen in uw Shopify-beheeromgeving:

1. **Locations** (Settings → Locations): onze locatie moet actief zijn. Verwijder oude of dubbele dropshippinglocaties die u niet meer gebruikt.
2. **Shipping profile** (Settings → Shipping and delivery): open het profiel dat onze producten bevat en controleer of onze locatie erin staat.
3. **Zones and rates**: in dat profiel moet het land van de koper in een verzendzone staan, en die zone heeft minstens één tarief nodig (betaald of gratis).
4. **Product**: open het product dat mislukt en controleer welk verzendprofiel het gebruikt. Verplaats het naar het profiel uit stap 2 indien nodig.

<!-- screenshot: Shopify-verzendprofiel met de aiku-locatie en een zone die het land van de koper bevat -->

Zijn alle vier correct en mislukt de checkout nog steeds, neem dan contact op met Shopify-support. Verzendzones en tarieven worden in uw winkel ingesteld, dus wij kunnen deze niet voor u wijzigen.

Zelfs wanneer Shopify de checkout toestaat, kunnen wij de bestelling alleen versturen als het land niet op onze lijst hierboven staat.

## Als er iets misgaat

**Mijn bestelling is al dagen Submitted en er is geen betaalknop.** Open de bestelling. Ziet u <b>We cannot deliver to ...</b>, dan is het land geblokkeerd. Vraag het ons in de chat op onze website met de bestelreferentie.

**De koper gaf per ongeluk een verkeerd land op.** Vraag het ons in de chat op onze website met de bestelreferentie en het juiste adres.
