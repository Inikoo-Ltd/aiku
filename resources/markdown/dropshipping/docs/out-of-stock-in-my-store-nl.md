---
title: Waarom een product in mijn winkel als niet op voorraad staat
summary: Ontdek waarom uw winkel een product als niet op voorraad toont terwijl het bij ons op voorraad is, en los dit op in Shopify, WooCommerce, eBay, TikTok Shop en Wix.
date: 2026-09-25
source_date: 2026-09-25
tags: voorraad, niet op voorraad, inventaris, shopify, wix, woocommerce, ebay, tiktok, locatie
category: troubleshooting
shops: awd, dssk, dse
---

<aside class="tldr">
We sturen alleen voorraad naar uw winkel voor producten die zijn <b>gekoppeld</b> op <b>Mijn producten</b>, en alleen zolang uw kanaal verbonden is. De gebruikelijke redenen voor "niet op voorraad" zijn: het product is niet gekoppeld, het is echt niet op voorraad of niet te koop bij ons, uw kanaalinstellingen verbergen een lage voorraad, of (op Shopify) de voorraad staat op een andere locatie. Controleer ze in de volgorde hieronder en druk daarna op <b>Update Stock</b>.
</aside>

## Hoe voorraad uw winkel bereikt

- We sturen alleen voorraad voor producten die gekoppeld zijn aan een vermelding in uw winkel: groen in de kolom <b>Status</b> van <b>Mijn producten</b>.
- Als onze voorraad verandert, werken wij uw winkel zelf bij. U hoeft niets te doen.
- We sturen 0 voor producten die niet te koop zijn of zijn stopgezet, ook als er nog eenheden over zijn.
- Uw kanaalinstellingen kunnen het aantal dat we sturen verlagen. Zie stap 4.

## Controleer dit stap voor stap

### 1. Is het kanaal verbonden?

Open <b>Channels</b> in het menu, klik op uw kanaal en open <b>Mijn producten</b>. Als een rood vak zegt <b>Your channel is not connected yet to the platform</b>, kunnen we niets sturen. Verbind het kanaal eerst opnieuw. Zorg er bij Shopify voor dat u op <b>Install</b> in Shopify heeft gedrukt om de verbinding af te ronden.

### 2. Is het product gekoppeld?

Zoek het product op <b>Mijn producten</b>. Op Shopify moet de status de groene handdruk zijn (<b>Product connected to shopify</b>). Op andere platforms moeten alle drie de vinkjes groen zijn.

Als het rood is, is de vermelding in uw winkel voor zover wij weten niet van ons, dus werken we de voorraad ervan nooit bij. Dit gebeurt vaak wanneer u het product zelf heeft gemaakt, of het heeft geïmporteerd vanuit een andere app. Koppel het met <b>Match with this product</b>, of koppel alles tegelijk met <b>Match all with default product</b>. Zie [Mijn producten gebruiken](/docs/my-products).

### 3. Is het bij ons op voorraad?

Kijk naar <b>Stocks</b> (op Shopify: <b>Stock</b>) op de rij. Behalve op Shopify kunt u het filter <b>Out of stock</b> gebruiken om alle producten zonder voorraad te tonen. Een doorgestreept dollarteken betekent <b>This product line is currently not for sale</b>, en een doorgestreept vakje betekent dat het is stopgezet. In al deze gevallen toont uw winkel terecht "niet op voorraad".

Om een melding te krijgen wanneer een product terug op voorraad komt, gebruikt u de envelopknop op het product op onze website. Uw herinneringen staan onder <b>Back In Stock Reminders</b> in het menu.

### 4. Controleer uw kanaalvoorraadinstellingen

Druk op de kanaalpagina op <b>Manage Sales Channel</b> (of <b>Edit</b>). Onder <b>Manage Stock</b>:

- <b>Stock Update</b>: staat dit uit, dan stoppen we met het automatisch bijwerken van de voorraad. Houd dit aan.
- <b>Stock Threshold</b>: zodra onze voorraad tot dit aantal of lager daalt, sturen we 0. Bij een drempel van 10 toont een product met 8 stuks bijvoorbeeld niet op voorraad. Laat leeg om de echte voorraad te sturen.
- <b>Max Quantity To Advertise</b>: het maximum dat we tonen, ook als we meer hebben. Laat leeg voor geen maximum.

<!-- screenshot: sectie Manage Stock van de kanaalinstellingen met Stock Update, Max Quantity To Advertise en Stock Threshold -->

### 5. Stuur de voorraad nu

Druk op <b>Mijn producten</b> op <b>Update Stock</b> (Shopify, WooCommerce, eBay, TikTok Shop en Wix). U ziet <b>Stock update started</b>. Dit kan een paar minuten duren. Open daarna het tabblad <b>Logs</b>: rijen van het type <b>Update Stock</b> tonen <b>Done</b> of <b>Failed</b> met het antwoord van uw platform.

Staat er <b>Nothing to update</b>, dan is nog geen van uw producten gekoppeld. Ga terug naar stap 2.

## Shopify

Op Shopify staat onze voorraad op onze eigen fulfilmentlocatie, genaamd <b>aiku-</b> gevolgd door onze winkelcode en uw kanaalcode tussen haakjes, bijvoorbeeld <b>aiku-awd (my-store)</b>.

1. Open in Shopify <b>Products</b> en het product dat niet op voorraad toont.
2. Controleer in de sectie <b>Inventory</b> dat onze locatie vermeld staat en voorraad heeft.
3. Staat de voorraad op een andere locatie (bijvoorbeeld uw eigen winkeladres) met 0, dan is dat het aantal dat Shopify voor die locatie toont. Onze voorraad staat altijd alleen op onze locatie.

Meldt Shopify dat het product niet op onze locatie op voorraad staat, dan voegen wij het zelf toe aan onze locatie, zodat de volgende voorraadupdate kan doorgaan. Staat er in het tabblad <b>Logs</b> <b>No variant on Shopify matches this sku</b>, dan komt de SKU van de Shopify-variant niet overeen met onze productcode. Wijzig de SKU in Shopify naar onze code, of koppel het product opnieuw met <b>Connect with other product</b>.

## Wix

We sturen nooit voorraad voor een Wix-product dat niet gekoppeld is. Als Wix zegt dat al uw producten niet op voorraad zijn, zijn de producten hoogstwaarschijnlijk rechtstreeks in Wix toegevoegd of niet gematcht. Gebruik op <b>Mijn producten</b> <b>Match all with default product</b> om ze op SKU te koppelen, of <b>Create new product</b> om ze door ons te laten aanmaken. Druk daarna op <b>Update Stock</b>.

## eBay

Als we 0 sturen, toont eBay de vermelding als niet op voorraad. Gebruikt uw eBay-account de eBay-optie voor niet op voorraad niet, dan kan eBay de vermelding in plaats daarvan beëindigen. Zet de optie aan in uw eBay-verkoopvoorkeuren, zodat vermeldingen blijven bestaan en terugkomen zodra we voorraad hebben.

## WooCommerce en TikTok Shop

Controleer het tabblad <b>Logs</b>. Bij WooCommerce betekent een mislukte voorraadupdate (<b>Failed</b>) met "503", "timed out" of "The store answered with a web page instead of data" dat uw website ons niet toeliet. Controleer of uw site online is en of uw hosting- of beveiligingsplugin ons niet blokkeert, en druk daarna opnieuw op <b>Update Stock</b>.

## Als er iets misgaat

- **"Stock update failed. This channel is not connected to the platform, so stock cannot be updated."** Verbind het kanaal opnieuw en probeer het dan weer.
- **"Nothing to update".** Nog geen van uw producten is gekoppeld. Koppel ze eerst (stap 2).
- **De voorraad klopt op Mijn producten maar niet in mijn winkel, en Logs toont Done.** Uw winkel voegt mogelijk zelf voorraad toe vanuit eigen locaties of apps. Controleer of geen andere app of locatie de voorraad van dat product wijzigt.
- **Het product is terug op voorraad maar mijn winkel toont nog steeds 0.** Druk op <b>Update Stock</b> en controleer het tabblad <b>Logs</b>. Staat de update op <b>Failed</b>, dan zegt het bericht daar waarom.
