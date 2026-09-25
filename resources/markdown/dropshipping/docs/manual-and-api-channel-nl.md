---
title: Het Manual/API-kanaal
summary: Maak een Manual/API-kanaal aan om vanaf uw eigen website, marktplaats of app te verkopen, plaats bestellingen met de hand of stuur ze naar ons via onze API.
date: 2026-09-25
source_date: 2026-09-25
tags: handmatig, api, verkoopkanaal, eigen website, api-token, integratie
category: sales-channels
series: manual
order: 1
shops: awd, dssk, dse
---

<aside class="tldr">
Gebruik een <b>Manual/API</b>-kanaal wanneer uw shop niet op een van de platforms staat waarmee wij koppelen, of wanneer u bestellingen zelf wilt intypen. Ga naar <b>Channels</b>, druk op <b>Add Sales Channel</b>, en dan op <b>Create</b> bij de kaart <b>Manual/API</b> en geef het een naam. U voegt dan producten toe aan <b>My Products</b>, voegt uw kopers toe als <b>Clients</b> en maakt bestellingen voor hen aan, met de hand of via de API.
</aside>

## Wanneer u een Manual/API-kanaal gebruikt

Een Manual/API-kanaal is niet gekoppeld aan een winkel. Er wordt niets naar een website geüpload en er komen geen bestellingen vanzelf binnen. Gebruik het wanneer:

- U verkoopt op uw eigen website, op een marktplaats waarmee wij niet koppelen, op sociale media of telefonisch, en u elke bestelling zelf naar ons wilt sturen.
- U heeft uw eigen systeem of ontwikkelaar en wilt bestellingen via onze API naar ons sturen.

Staat uw shop op een platform dat op de pagina <b>Add Sales Channel</b> staat, zoals Shopify, WooCommerce, eBay of TikTok Shop, koppel dat platform dan in plaats daarvan. Dan worden producten voor u geüpload en komen bestellingen vanzelf binnen.

U kunt meer dan één Manual/API-kanaal hebben, bijvoorbeeld één per website.

## Het kanaal aanmaken

1. Open <b>Channels</b> in het menu. U ziet de lijst met uw <b>Sales Channels</b>.
2. Druk op <b>Add Sales Channel</b>. De pagina toont <b>Select channel you want to create</b>.
3. Druk op de kaart <b>Manual/API</b> op <b>Create</b>.
4. Een venster <b>Create platform manual</b> opent. Typ een naam voor het kanaal, bijvoorbeeld de naam van uw website. De naam mag tot 28 tekens lang zijn.
5. Druk op <b>Create</b>.

<!-- screenshot: de pagina Add Sales Channel met de kaart Manual/API en de knop Create, en het venster Create platform manual -->

U ziet het bericht <b>Your Manual store has been created.</b> en de kanaalpagina opent.

Elk van uw kanalen heeft zijn eigen naam nodig. Wordt de naam al gebruikt door een ander van uw kanalen, dan toont het venster een fout. Kies een andere naam.

## Uw kanaalpagina

De kanaalpagina heeft de naam van uw kanaal als titel en de kop <b>Manual/API order management</b>. Er staan drie vakken, elk met een link <b>View all</b>:

- <b>Orders</b>: de bestellingen die u in dit kanaal plaatste.
- <b>Clients</b>: de mensen naar wie u bestellingen stuurt.
- <b>Products</b>: de producten in uw lijst <b>My Products</b>.

In het menu, onder de kanaalnaam, vindt u:

- <b>Baskets</b>: bestellingen die u begon en nog niet betaalde.
- <b>My Products</b>: de producten die u in dit kanaal verkoopt. Zie [Producten beheren op het Manual/API-kanaal](/docs/managing-products-on-the-manual-channel).
- <b>Clients</b>: uw kopers. Zie [Klanten beheren](/docs/managing-clients).
- <b>Orders</b>: uw geplaatste bestellingen. Zie [Handmatig bestellingen plaatsen](/docs/placing-orders-manually).
- <b>API</b>: tokens en documentatie om uw eigen systeem te koppelen.

De gebruikelijke manier van werken is:

1. Voeg de producten die u verkoopt toe aan <b>My Products</b> met <b>Add products</b>. Dit is nodig voor de API. Voor bestellingen die u zelf intypt, is dit optioneel: het mandje laat u elk product kiezen dat wij verkopen.
2. Krijgt u een bestelling, open dan <b>Clients</b>, zoek uw koper op of voeg hem toe.
3. Druk op de klantpagina op <b>Create Order</b>, voeg de producten en aantallen toe en betaal.

## De naam wijzigen of het kanaal sluiten

Om het kanaal te hernoemen, drukt u op de kanaalpagina op <b>Edit</b> en wijzigt u <b>Store name</b>.

Om een kanaal te sluiten, gaat u naar <b>Channels</b> en drukt u op de sluitknop in de kolom <b>Action</b> (tooltip <b>Close channel</b>). Het venster vraagt <b>Are you sure you want to close this channel?</b> en waarschuwt <b>This operation is irreversible.</b> Een gesloten kanaal verdwijnt uit het menu. Uw eerdere bestellingen en facturen blijven bewaard.

## Uw eigen systeem koppelen met de API

De API laat uw website of app zelf doen wat u op de kanaalpagina's doet: onze productcatalogus met actuele prijzen lezen, producten aan <b>My Products</b> toevoegen, klanten aanmaken en wijzigen, bestellingen aanmaken, er producten aan toevoegen, ze indienen en volgen. U kunt uw lijst <b>My Products</b> ook als CSV- of JSON-feed downloaden om producten in uw eigen website te laden.

Open <b>API</b> onder uw kanaal. De pagina heeft deze tabbladen:

- <b>Overview</b>: hoe u koppelt, het basisadres van de API en de knop <b>API documentation</b>. De documentatie somt elk eindpunt op met voorbeelden.
- <b>API tokens</b>: de tokens voor dit kanaal.
- <b>API calls</b>: de verzoeken die uw systeem deed.
- <b>History</b>: wijzigingen aan uw account.

### Een token krijgen

1. Druk op <b>Generate API token</b>.
2. Is het token alleen voor het lezen van gegevens, vink dan <b>Read only (cannot create, change or submit orders)</b> aan.
3. Druk op <b>Click to Generate</b>.
4. Kopieer het token met het kopieerpictogram en bewaar het veilig. Het venster zegt <b>Put this token in a safe place, you won't be able to see it again.</b> Het korte label in de tokenlijst is alleen een naam, niet het token.

Stuur het token met elk verzoek mee in de header <b>Authorization: Bearer</b> gevolgd door uw token. Elk token hoort bij één kanaal: producten, klanten en bestellingen die uw systeem aanmaakt, gaan naar dat kanaal. Om een token te laten stoppen werken, verwijdert u het in het tabblad <b>API tokens</b>.

<!-- screenshot: het tabblad Overview van de API-pagina met de knoppen API documentation en Generate API token -->

### Eerst testen op staging

Het tabblad <b>Overview</b> heeft ook <b>Open staging mirror</b>. Staging is een aparte kopie van de site waar u kunt testen zonder echte bestellingen of betalingen. Log in met hetzelfde e-mailadres en wachtwoord. Staging wordt regelmatig teruggezet met een verse kopie, waardoor wat u daar aanmaakte verdwijnt. Tokens van de echte site werken niet op staging: genereer een apart token op staging, en een nieuw na elke reset. Het basisadres van staging staat op het tabblad <b>Overview</b>.

### Hoe API-bestellingen worden betaald

Wanneer uw systeem een bestelling indient, betalen wij deze eerst van uw accountsaldo, dan van uw opgeslagen kaarten. Voeg een kaart toe voordat u begint. Zodra u een token heeft, toont het menu <b>Saved Cards</b>. Zolang er geen kaart is opgeslagen, toont de API-pagina <b>You have no cards saved yet.</b> met een knop <b>Add card</b>.

Dekken noch uw saldo noch uw kaarten de bestelling, dan wordt deze gemarkeerd als <b>Unpaid</b> en gaat ze niet naar het magazijn. Voeg geld toe aan uw saldo met <b>Top Up</b>, open de bestelling en druk op <b>Pay … with balance</b>. De knop verschijnt zodra uw saldo het verschuldigde bedrag dekt.

## Als er iets misgaat

- <b>De naam is al bezet wanneer ik het kanaal aanmaak.</b> Een van uw open kanalen heeft die naam al. Typ een andere naam. De naam van een gesloten kanaal kan opnieuw gebruikt worden.
- <b>Mijn bestellingen komen niet vanzelf binnen.</b> Een Manual/API-kanaal haalt nooit bestellingen op van een website. Maak ze aan op de klantpagina, of stuur ze vanuit uw systeem via de API. Verkoopt u op een platform dat op de pagina <b>Add Sales Channel</b> staat, koppel dat platform dan als eigen kanaal.
- <b>Mijn producten staan niet op mijn website.</b> Wij uploaden niets vanuit een Manual/API-kanaal. Laad ze zelf in uw website in, met de CSV-download op <b>My Products</b> of via de API.
- <b>Ik ben mijn API-token kwijt.</b> Het kan niet opnieuw getoond worden. Genereer een nieuw token, zet het in uw systeem en verwijder het oude.
- <b>De API antwoordt dat ik geen bestellingen kan aanmaken of wijzigen.</b> Het token is alleen-lezen. Genereer een token zonder <b>Read only</b> aangevinkt.
- <b>De API weigert mijn verzoeken voor een korte tijd.</b> Elk token kan tot 120 verzoeken per minuut doen. Vertraag uw systeem en probeer het na een minuut opnieuw.
- <b>De API zegt "This order has no products yet".</b> Voeg minstens één product aan de bestelling toe voordat u deze indient.
- <b>De API zegt "Unable to find related portfolio item".</b> Via de API voegt u een product aan een bestelling toe met zijn <b>My Products</b>-item, niet met het product zelf. Voeg het product eerst toe aan <b>My Products</b> en gebruik het id van dat item.
- <b>De API zegt dat er al een andere transactie met hetzelfde product bestaat.</b> Het product staat al op de bestelling. Wijzig de hoeveelheid van die regel in plaats van het opnieuw toe te voegen.
- <b>De API zegt dat de bestelling "is already in the 'submitted' state and cannot be updated".</b> Ingediende bestellingen kunnen niet via de API gewijzigd of verwijderd worden. Vraag het ons in de chat op onze website als de bestelling moet wijzigen.
- <b>Mijn API-bestelling toont Unpaid.</b> Uw saldo en opgeslagen kaarten dekten haar niet. Waardeer uw saldo op, open de bestelling en druk op <b>Pay … with balance</b>, en controleer of uw opgeslagen kaart nog geldig is.
