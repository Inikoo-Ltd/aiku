---
title: De AW-fulfilmentlocatie in Shopify
summary: Wat de aiku- locatie en het verzendprofiel AW Connect shipping in uw Shopify-winkel doen, welke verzendtarieven uw klanten zien voor onze producten, en wat u doet wanneer producten als uitverkocht tonen of bestellingen ons niet bereiken.
date: 2026-10-30
source_date: 2026-10-30
tags: shopify, fulfilmentlocatie, verzendprofiel, verzendtarieven, markten, voorraad, uitverkocht
category: sales-channels
series: shopify
order: 3
shops: awd, dssk, dse
---

<aside class="tldr">
Wanneer u onze app installeert, voegen we aan uw Shopify-winkel een fulfilmentlocatie toe met de naam <b>aiku-</b> en een verzendprofiel, <b>AW Connect shipping</b>, met onze producten en onze verzendprijzen. Bij het afrekenen zien uw klanten voor onze producten de verzending <b>AW Standard</b>. Meestal hoeft u niets te doen.
</aside>

## Waar de locatie voor dient

Shopify houdt voorraad bij per locatie. Onze producten worden vanuit ons magazijn verstuurd, dus voegen we ons magazijn toe aan uw winkel als fulfilmentlocatie.

- De naam is <b>aiku-</b>, gevolgd door de code van onze website en de code van uw kanaal tussen haakjes. Bijvoorbeeld <b>aiku-awd (sho-ab12cd-3e)</b>.
- De voorraad van elk product dat u verbindt wordt op deze locatie bijgehouden. Wij werken die voor u bij.
- Koopt een klant een van deze producten, dan stuurt Shopify ons een fulfilmentverzoek vanuit deze locatie. Zo bereikt de bestelling ons.

Verwijder deze locatie niet en verplaats onze producten niet naar een andere locatie. Doet u dat wel, dan stopt de voorraad met bijwerken en bereiken bestellingen ons niet meer.

## Verzending voor u ingesteld

Bij het installeren van de app voegen we een verzendprofiel toe aan uw winkel. De naam is <b>AW Connect shipping</b>, dan de code van uw kanaal tussen haakjes, bijvoorbeeld <b>AW Connect shipping (sho-ab12cd-3e)</b>. Gebruikt uw winkel nog verzendprofielen en verzendt een daarvan al vanaf de <b>aiku-</b>-locatie, dan laten we het zoals het is: uw klanten blijven uw eigen tarieven betalen voor onze producten, en u ziet dit profiel nog niet. We voegen het toe wanneer Shopify uw winkel omzet naar verzending per markt.

- Het verzendt alleen vanaf de <b>aiku-</b>-locatie.
- Het bevat elk product dat u van ons verbindt. Nieuwe producten voegen we toe en verwijderde halen we eruit, meestal binnen tien minuten.
- De tarieven zijn onze verzendprijzen, per land en pakketgewicht, in de valuta van uw winkel. Bij het afrekenen tonen ze als <b>AW Standard</b>. Wanneer we onze prijzen wijzigen, volgt het profiel binnen een dag.
- Waar we de verzending met de hand berekenen (zeer zware pakketten, landen buiten onze zones), gebruikt het profiel onze hoogste prijs voor die zone, zodat uw klanten altijd kunnen afrekenen. Wat we u voor de bestelling rekenen, verandert niet.
- Shopify laat u deze tarieven niet wijzigen. U kunt ze uitzetten, maar dan zien uw klanten geen verzending voor onze producten, tenzij een van uw eigen tarieven vanaf de <b>aiku-</b>-locatie verzendt.

Uw andere producten houden uw eigen verzendinstellingen en tarieven. Die wijzigen we niet.

## Winkels met verzending per markt

Shopify zet alle winkels om van verzendprofielen naar verzending per markt. Zo'n winkel toont <b>Shipping has moved to Markets</b> in <b>Settings</b>, <b>Shipping and delivery</b>. Ons profiel staat in elke markt als een dienst van onze app, naast uw eigen tarieven.

Verzendt een van de tarieven van uw markten vanaf de <b>aiku-</b>-locatie, bijvoorbeeld omdat u die met de hand hebt toegevoegd zoals een oudere versie van deze gids zei, dan zien uw klanten dat tarief naast <b>AW Standard</b>. Om alleen het onze te tonen, haalt u de <b>aiku-</b>-locatie uit dat tarief:

1. Open in uw Shopify-beheer <b>Markets</b> en daarna een markt waarin u verkoopt.
2. Druk bij <b>Shipping</b> naast een tarief op <b>...</b> en kies <b>Edit</b>.
3. Haal bij <b>Ships from</b> de <b>aiku-</b>-locatie weg en sla op.

Onze producten worden alleen verkocht naar de landen die uw markten bevatten. Een klant in een land dat geen van uw markten dekt, ziet ze als uitverkocht.

## Uw eigen voorraad van onze producten

Producten in ons verzendprofiel worden alleen vanaf de <b>aiku-</b>-locatie verzonden, dus Shopify verstuurt ze niet vanaf uw eigen locaties. Hebt u zelf enkele van onze producten, bijvoorbeeld een ongebruikt artikel dat een klant naar u terugstuurde, zet ze dan als een apart product in uw winkel en verstuur die bestellingen zelf.

## Controleer het zelf

Doe dit als onze producten uitverkocht tonen in uw winkel, of het afrekenen geen verzendtarief voor ze toont.

1. Open in uw Shopify-beheer <b>Settings</b>.
2. Open <b>Shipping and delivery</b>.
3. Zoek <b>AW Connect shipping</b>, onder onze app. Het moet vanaf de <b>aiku-</b>-locatie verzenden en onze producten bevatten.
4. Ontbreekt het, of ontbreekt er een product in, wacht dan een dag: we controleren elke winkel elke nacht. Komt het niet terug, neem dan contact met ons op.

<!-- screenshot: Shopify Shipping and delivery, het verzendprofiel AW Connect shipping onder de app, met de aiku-awd-locatie -->

Shopify wijzigt zijn menu's af en toe, dus de namen kunnen in uw beheer iets anders zijn.

## Als er iets misgaat

**Onze producten tonen als uitverkocht in Shopify.** Controleer het verzendprofiel <b>AW Connect shipping</b> zoals hierboven, en of het land van de klant in een van uw markten zit. Controleer ook of het product verbonden is: in <b>Mijn producten</b> moet een groene handdruk te zien zijn. Zie [Producten beheren op Shopify](/docs/managing-products-on-shopify).

**Uploadfout "No Shopify location, the AW fulfilment service is not installed on this store so stock can not be sent".** De <b>aiku-</b>-locatie ontbreekt. Open het kanaal. Ziet u <b>Click here to install</b>, druk er dan op en installeer de app in Shopify. Toont het kanaal <b>Reset channel</b>, gebruik dit dan om de locatie opnieuw aan te maken.

**Logbericht "The specified inventory item is not stocked at the location".** Het product staat in Shopify niet op voorraad op de <b>aiku-</b>-locatie, bijvoorbeeld omdat het naar een andere locatie in Shopify is verplaatst. Koppel het product opnieuw met <b>Connect with other product</b> in <b>Mijn producten</b>.

**Bestellingen bereiken ons niet.** Shopify stuurt ons alleen bestellingen voor artikelen die op de <b>aiku-</b>-locatie op voorraad staan. Stond het product op uw eigen locatie op voorraad, dan verwacht Shopify dat u het zelf verstuurt. Zie [Uw Shopify-bestellingen en hun status](/docs/shopify-order-status).

<aside class="wayfinder"><strong>Waar te klikken</strong>
<ul>
<li><b>Controleer ons verzendprofiel:</b> Shopify-beheer → <b>Settings</b> → <b>Shipping and delivery</b> → <b>AW Connect shipping</b>.</li>
<li><b>Verzending per markt:</b> Shopify-beheer → <b>Markets</b> → uw markt → <b>Shipping</b>.</li>
<li><b>Controleer het kanaal:</b> <b>Channels</b> → uw Shopify-winkel → de drie iconen naast zijn naam.</li>
</ul>
</aside>
