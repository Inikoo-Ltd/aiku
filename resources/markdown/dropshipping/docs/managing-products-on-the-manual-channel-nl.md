---
title: Producten beheren op het Manual/API-kanaal
summary: Voeg de producten die u verkoopt toe aan Mijn producten op een Manual/API-kanaal, importeer ze vanuit een spreadsheet of een ander kanaal, download uw productgegevens en afbeeldingen, en verwijder producten die u niet meer verkoopt.
date: 2026-09-25
source_date: 2026-09-25
tags: handmatig, api, mijn producten, portfolio, producten toevoegen, importeren, csv, afbeeldingen
category: products
series: manual
order: 2
shops: awd, dssk, dse
---

<aside class="tldr">
<b>Mijn producten</b> is de lijst met producten die u in een kanaal verkoopt. Open deze onder uw Manual/API-kanaal en druk op <b>Add products</b> om producten uit onze catalogus te kiezen. Op een Manual/API-kanaal wordt niets ergens geüpload: de lijst is voor uw eigen gebruik en voor de API. U heeft de lijst niet nodig om handmatig te bestellen. Om te stoppen met het verkopen van een product, drukt u op de knop <b>X</b> op de rij ervan.
</aside>

## Mijn producten openen

Ga naar uw Manual/API-kanaal in het menu en open <b>Mijn producten</b>. U kunt ook op <b>View all</b> drukken in het vak <b>Products</b> van de kanaalpagina.

Is de lijst leeg, dan staat er <b>You don't have any items in your portfolio</b> en een knop <b>Add Product</b>.

Elke productrij toont de foto, de naam en code, de voorraad die wij hebben (<b>Stocks:</b>), het gewicht, uw prijs (<b>Price:</b>) en de adviesverkoopprijs (<b>RRP:</b>).

## Producten toevoegen

1. Druk op <b>Add products</b>.
2. Een venster <b>Add products to your product</b> opent.
3. Kies waarop u zoekt: <b>Product</b> zoekt op productnamen en -codes, <b>Department</b>, <b>Sub-department</b> en <b>Family</b> vinden de producten in een groep met die naam.
4. Typ in het zoekvak en vink de producten aan die u wilt.
5. Druk op <b>Add … products and close</b>. Het aantal is hoeveel u heeft aangevinkt.

<!-- screenshot: het venster Add products met de filter Product / Department / Sub-department / Family en de knop Add products and close -->

U ziet <b>Successfully added portfolios</b> en de producten verschijnen in de lijst.

## Meerdere producten tegelijk toevoegen

### Vanuit een spreadsheet

1. Druk op de uploadknop naast <b>Add products</b> (tooltip <b>Import from xlsx file</b>).
2. Druk in het venster <b>Bulk Import Portfolios</b> op <b>Download template (.xlsx)</b>.
3. Vul de kolom <b>sku</b> in met onze productcodes, één per rij. De kolom <b>title</b> is optioneel.
4. Upload het bestand.

Rijen worden overgeslagen wanneer de code niet in onze winkel bestaat of het product niet te koop is. De uploadgeschiedenis toont wat is toegevoegd en wat mislukt is.

### Vanuit een ander kanaal

Heeft u al producten in een ander kanaal, dan kunt u ze kopiëren. Druk op de knop met drie puntjes naast <b>Add products</b>. Kies onder <b>Clone portfolio from channel:</b> het kanaal om vanuit te kopiëren. Het getal tussen haakjes is hoeveel producten het heeft. Het kopiëren gebeurt op de achtergrond en de pagina laadt opnieuw zodra het klaar is.

## Producten in uw lijst vinden

Gebruik het zoekvak, of de filterknoppen boven de lijst:

- <b>Only For Sale</b>: producten die u nu kunt bestellen.
- <b>Not For Sale</b>: producten die wij op dit moment niet verkopen.
- <b>Discontinued</b>: producten die wij niet opnieuw zullen verkopen.
- <b>Out of stock</b>: producten die op dit moment geen voorraad hebben.

Een doorgestreept doosicoontje betekent dat het product stopgezet is. De tooltip zegt <b>This product line has been discontinued. Please remove this item</b>. Een doorgestreept geldicoontje betekent <b>This product line is currently not for sale</b>. Haal deze producten van uw eigen website zodat uw kopers ze niet kunnen bestellen.

## Productgegevens en afbeeldingen voor uw website ophalen

Op een Manual/API-kanaal uploaden wij geen producten naar uw website. Haal de gegevens hier vandaan:

- <b>CSV</b>: downloadt uw productlijst met prijzen, voorraad en beschrijvingen.
- De knop met drie puntjes naast <b>CSV</b> opent <b>Export Options</b>. Kies de kolommen, de <b>Product State</b> en de <b>Product Sale Status</b> die u wilt, en druk dan op <b>Export Extended Properties</b>. Vink <b>Include bundles</b> aan om uw bundels toe te voegen.
- <b>Images</b>: bereidt een download van de foto's van uw producten voor. Zodra het klaar is, drukt u op <b>Download images</b>. De link werkt maar een beperkte tijd, te zien in de tooltip van de knop.
- Via de API kan uw systeem dezelfde lijst uitlezen, en deze als CSV- of JSON-feed downloaden. Zie [Het Manual/API-kanaal](/docs/manual-and-api-channel).

Voorraad en prijzen veranderen. Download de lijst opnieuw, of lees haar via de API, vaak genoeg om uw website actueel te houden.

## Een product verwijderen

Druk op de knop <b>X</b> op de rij van het product (tooltip <b>Remove product from list</b>). Het product verdwijnt uit uw lijst. Reeds geplaatste bestellingen ermee blijven ongewijzigd. U kunt het later opnieuw toevoegen met <b>Add products</b>.

## Als er iets misgaat

- <b>Ik kan een product niet vinden in het venster Add products.</b> Controleer of u in het juiste tabblad zoekt: <b>Product</b> zoekt op productnamen en -codes, <b>Family</b> en <b>Department</b> zoeken op groepsnamen. Het venster toont geen producten die al in uw lijst staan, niet te koop zijn of stopgezet zijn.
- <b>Mijn spreadsheetupload sloeg rijen over met "SKU not found in this shop".</b> De code in de kolom <b>sku</b> is geen van onze productcodes in deze website. Kopieer de code precies zoals op het product staat.
- <b>Mijn spreadsheetupload sloeg rijen over met "Product is not for sale".</b> Wij verkopen dat product op dit moment niet. Laat het weg.
- <b>Een product toont als stopgezet of niet te koop.</b> U kunt het niet bestellen. Verwijder het van uw eigen website en uit <b>Mijn producten</b>.
- <b>Een product is niet op voorraad.</b> Het blijft in uw lijst staan. Gebruik <b>Out of stock</b> om deze producten te vinden en verberg ze op uw website totdat ze er weer zijn.
- <b>De downloadlink voor afbeeldingen werkt niet meer.</b> De link verloopt. Druk opnieuw op <b>Images</b> om een nieuwe te maken.
- <b>Mijn producten staan niet op mijn website.</b> Wij uploaden nooit vanuit een Manual/API-kanaal. Laad ze zelf in met de CSV-download of de API. Verkoopt u op een platform dat op de pagina <b>Add Sales Channel</b> staat, zoals Shopify, WooCommerce, eBay of TikTok Shop, koppel dat platform dan als eigen kanaal en worden producten voor u geüpload.
