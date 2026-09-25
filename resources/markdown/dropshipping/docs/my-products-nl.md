---
title: Mijn producten gebruiken
summary: Lees de lijst My Products van een kanaal, stuur producten naar uw winkel of koppel ze aan vermeldingen die u al heeft, houd de voorraad bij en lees de uploadfouten.
date: 2026-09-25
source_date: 2026-09-25
tags: producten, mijn producten, portfolio, uploaden, koppelen, sku, voorraad, logboeken
category: products
shops: awd, dssk, dse
---

<aside class="tldr">
<b>My Products</b> is de lijst met onze producten die u in één kanaal verkoopt. Elk kanaal heeft zijn eigen lijst. Van hieruit stuurt u elk product naar uw winkel met <b>Create new product</b>, of koppelt u het aan een vermelding die u al heeft met <b>Match</b>. Zodra het product gekoppeld is, houden wij de voorraad bij, en het tabblad <b>Logs</b> toont elke upload, match en voorraadupdate met het antwoord van uw platform.
</aside>

## My Products openen

1. Open <b>Channels</b> in het menu. Elk kanaal staat eronder met zijn logo.
2. Klik op het kanaal, dan op <b>My Products</b>. Het getal ernaast is hoeveel producten in de lijst staan.

De pagina heeft drie tabbladen: <b>My Products</b>, <b>My Bundles</b> (zie [Bundels maken](/docs/bundles)) en <b>Logs</b> (het klokicoontje rechts).

Staat er een rood vak met <b>Your channel is not connected yet to the platform</b>, dan is de koppeling met uw winkel verbroken. Er kan niets geüpload worden en er wordt geen voorraad gestuurd totdat u opnieuw verbindt. Volg de koppelingshandleiding voor uw platform.

<!-- screenshot: de pagina My Products van een Shopify-kanaal met de tabbladen, de knoppen bovenaan en een paar rijen -->

## Wat elke rij toont

- **Product**: onze productcode (klik erop om het product te openen), de naam, <b>Stocks</b>, <b>Weight</b> (productgewicht / gewicht met verpakking), <b>Dimension</b>, onze <b>Price</b> (wat u ons betaalt) en de <b>RRP</b>. Toont uw kanaal prijzen met btw, dan ziet u <b>Price (include VAT)</b> en <b>RRP (include VAT)</b> (niet op Shopify).
- **Status**: op Shopify betekent een groene handdruk <b>Product connected to shopify</b> en een rode <b>Not connected</b>. Op andere platforms zijn er drie vinkjes: <b>Has valid platform product id</b>, <b>Exist in platform</b> en <b>Platform status</b>. Drie groene vinkjes betekenen dat het product live en gekoppeld is.
- **Message**: een groen vinkje wanneer alles in orde is. Een rood bericht wanneer uw platform het product weigerde (kijk op Shopify in plaats daarvan in het tabblad <b>Logs</b>). Klik erop om <b>Answer of ...</b> te zien met de volledige tekst van uw platform en, vaak, wat te doen. Een doorgestreept doosje betekent <b>This product line has been discontinued. Please remove this item</b>. Een doorgestreepte dollar betekent <b>This product line is currently not for sale</b>.
- **De productkolom van uw platform** (bijvoorbeeld <b>Shopify product</b> of <b>eBay product</b>): aan welke vermelding in uw winkel dit product gekoppeld is, of de knoppen om het te koppelen.

## Een product naar uw winkel sturen

Voor een product dat nog niet gekoppeld is, heeft u twee keuzes.

**Een nieuwe vermelding aanmaken.** Druk op <b>Create new product</b>. Wij maken het product aan in uw winkel met onze naam, beschrijving, afbeeldingen, prijs, SKU en voorraad.

**Koppelen aan een vermelding die u al heeft.** Gebruik dit wanneer u het product al verkoopt en geen tweede exemplaar wilt.
- Vonden wij een vermelding in uw winkel met dezelfde SKU, dan staat die in de rij. Druk op <b>Match with this product</b>.
- Om een andere te kiezen, drukt u op <b>Choose another product from your shop</b>, of op <b>Match it with an existing product in your shop</b> wanneer wij niets vonden. Zoek in uw winkel, kies het artikel en druk op <b>Link ... to selected item on your platform</b>.
- Om een al gekoppeld product te wijzigen, drukt u op <b>Change linked listing</b> (op Shopify: <b>Connect with other product</b>).

## Meerdere producten tegelijk

Zijn sommige producten nog niet gekoppeld, dan zegt een gele balk <b>You have ... products not synced yet</b>. Deze heeft twee knoppen:

- <b>Upload all as new product</b>: maakt ze allemaal aan in uw winkel. Niet getoond op eBay.
- <b>Match all with default product</b>: koppelt elk product aan de vermelding in uw winkel met dezelfde SKU. Wij vergelijken de SKU in uw winkel met de SKU van het product in <b>My Products</b> en met onze productcode, en hoofdletters of kleine letters maken geen verschil. Producten zonder vermelding met die SKU blijven ongewijzigd.

Om alleen aan sommige producten te werken, vinkt u ze aan in de lijst. Deze knoppen verschijnen:

- <b>Create New (...)</b>: maakt de aangevinkte producten aan in uw winkel.
- <b>Match (...)</b>: koppelt de aangevinkte producten op SKU.
- <b>Unlink (...)</b> en <b>Unlink & Delete (...)</b>: zie [Producten verwijderen](/docs/removing-products).
- <b>Edit Price (...)</b>: stelt uw verkoopprijs in voor de aangevinkte producten op eBay, Shopify, WooCommerce en Wix, als percentage of bedrag boven of onder de adviesprijs. Niet getoond wanneer uw kanaal is ingesteld om zijn eigen prijzen te houden.

Grote taken lopen op de achtergrond. Een voortgangsvenster toont hoeveel er klaar zijn, en de pagina laadt vanzelf opnieuw.

<!-- screenshot: de gele balk "products not synced yet" met Upload all as new product en Match all with default product -->

## Producten in de lijst vinden

Gebruik het zoekvak, of de filterknoppen: <b>Only For Sale</b>, <b>Not For Sale</b>, <b>Discontinued</b> en <b>Out of stock</b>. Op Shopify staan de filters in het menu <b>Filter</b>: <b>Only For Sale</b>, <b>Not For Sale</b>, <b>Discontinued</b>, <b>Connected to Shopify</b> en <b>Not Connected</b>. Er is geen out-of-stockfilter op Shopify.

## Voorraad

Wij sturen alleen voorraad voor producten die gekoppeld zijn (groene status). U hoeft niets te doen: wanneer onze voorraad verandert, werken wij uw winkel bij.

Om de voorraad nu te versturen, drukt u bovenaan de pagina op <b>Update Stock</b>. Dit stuurt de huidige voorraad van de producten in dit kanaal. Is nog geen enkel product gekoppeld, dan staat er <b>Nothing to update</b>. De knop staat op Shopify-, WooCommerce-, eBay-, TikTok Shop- en Wix-kanalen, niet op Allegro of handmatige kanalen.

U kunt de voorraad begrenzen of verbergen in de kanaalinstellingen. Zie [Waarom een product in mijn winkel als niet op voorraad toont](/docs/out-of-stock-in-my-store).

## Andere knoppen

- <b>Add products</b>, de uploadknop en <b>Clone portfolio from channel:</b>: producten toevoegen. Zie [Producten vinden en toevoegen](/docs/sourcing-products).
- <b>CSV</b>, <b>⋮</b> (<b>Other Export Options</b>) en <b>Images</b>: uw productgegevens en foto's downloaden. Zie [Productgegevens en afbeeldingen exporteren](/docs/exporting-product-data).
- <b>Publish ... drafts</b> (alleen eBay): publiceert de vermeldingen die als concept naar eBay zijn geüpload.
- <b>Update all dimensions</b> (alleen Shopify): stuurt onze actuele afmetingen naar al uw Shopify-producten.

## Het tabblad Logs

Het tabblad <b>Logs</b> toont elke upload, match en voorraadupdate voor dit kanaal: <b>Product Code</b>, <b>Type</b> (<b>upload</b>, <b>match</b> of <b>update-stock</b>), <b>Platform</b>, <b>Status</b> (<b>Done</b>, <b>In progress</b> of <b>Failed</b>), de <b>Response</b> van uw platform en de <b>Date</b>. Kijk hier eerst wanneer een product of zijn voorraad niet is aangekomen.

## Als er iets misgaat

Het rode bericht op de rij, en de <b>Response</b> in <b>Logs</b>, is het antwoord van uw platform. De meest voorkomende:

- **Throttled / too many calls / request timeout / internal error.** Uw platform vroeg ons te vertragen, of antwoordde niet op tijd. Er is niets mis met het product. Probeer het over een paar minuten opnieuw.
- **The store answered with a web page instead of data, or returned 503, timed out or an empty reply** (WooCommerce). Uw eigen website ligt plat, staat in onderhoudsmodus, of de beveiligingsplugin of hosting ervan blokkeert ons. Controleer of uw site online is. Vraag uw hosting onze verbinding toe te staan, en probeer het daarna opnieuw.
- **A product with this SKU already exists in your store / Invalid or duplicated SKU / already present in the lookup table** (WooCommerce). U heeft al een product met die SKU. Gebruik <b>Match</b> in plaats van <b>Create new product</b>. Staat het oude product in de WooCommerce-prullenbak, leeg deze dan eerst.
- **Invalid or duplicated GTIN** (WooCommerce). Een ander product in uw winkel gebruikt die barcode al. Verwijder de barcode van het andere product in WooCommerce, of match ermee.
- **Cannot list more products: your Shop probation tier allows at most 100 total product listings** (TikTok). Dit is een TikTok-limiet voor nieuwe shops, geen probleem met het product. Verwijder vermeldingen die u niet nodig heeft, of vraag TikTok uw tier te verhogen.
- **product_weight received 0 / weight cannot be zero** (TikTok). TikTok heeft een gewicht nodig. U kunt ons productgewicht niet zelf wijzigen: vraag het ons in de chat op onze website, met de productcode.
- **Image must be at least 300:300** (TikTok). Een van onze afbeeldingen is te klein voor TikTok. Vraag het ons in de chat op onze website, met de productcode.
- **Price out of range / incorrect price** (TikTok). TikTok bepaalt het prijsbereik dat uw shop mag gebruiken. Controleer het bereik in TikTok Shop Seller Center. Valt de prijs die wij sturen erbuiten, vraag het ons dan in de chat op onze website, met de productcode.
- **Category qualification / category is restricted** (TikTok). Solliciteer voor de categorie in het Qualification Center van TikTok Shop Seller Center, en upload daarna opnieuw.
- **Requires an active seller account** (TikTok) of **create a seller account** (eBay). Rond eerst uw verkopersaccount op het platform af.
- **The listing would cause you to exceed the amount you can list this month** (eBay). U bereikte uw eBay-verkooplimiet. Vraag eBay deze te verhogen, of wacht tot de volgende maand.
- **Invalid data in the associated fulfilment policy** (eBay). Uw eBay-verzendbeleid (fulfilment policy) heeft een probleem. Herstel het in eBay, en controleer daarna de beleidsregels die in uw kanaalinstellingen gekozen zijn.
- **Item specific Type / Brand missing, or custom values for Size no longer supported** (eBay). eBay wil extra gegevens voor deze categorie. Zie [Producten en bestellingen op eBay beheren](/docs/managing-products-on-ebay).
- **Not allowed to revise an ended item** (eBay). De vermelding is beëindigd op eBay. Ontkoppel het product en maak het opnieuw aan.
- **Overseas Warehouse Block Policy** (eBay). Is uw account geregistreerd in sommige landen, dan toont een rode <b>Important Notice</b> bovenaan. eBay kan vermeldingen die overzee zijn opgeslagen blokkeren. Neem contact op met eBay Support om goedkeuring te vragen.
- **This product line has been discontinued.** Wij verkopen het niet meer. Verwijder het uit uw lijst en uit uw winkel.
