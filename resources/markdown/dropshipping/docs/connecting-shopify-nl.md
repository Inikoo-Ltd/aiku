---
title: Uw Shopify-winkel koppelen
summary: Koppel uw Shopify-winkel aan uw dropshippingaccount met de myshopify.com-naam, installeer onze app in Shopify, en herstel een winkel die nog steeds aangeeft niet verbonden te zijn.
date: 2026-09-25
source_date: 2026-09-25
tags: shopify, verkoopkanaal, verbinden, installeren, myshopify
category: sales-channels
series: shopify
order: 1
shops: awd, dssk, dse
---

<aside class="tldr">
Ga naar <b>Channels</b>, druk op <b>Add Sales Channel</b>, dan op <b>Connect</b> bij de Shopify-kaart. Typ de <b>myshopify.com</b>-naam van uw winkel, niet uw eigen domein, en druk op <b>Connect</b>. Shopify opent in een nieuw tabblad: druk daar op <b>Install</b>. De koppeling is pas afgerond zodra de app in Shopify is geïnstalleerd.
</aside>

## Voordat u begint

- U heeft de <b>myshopify.com</b>-naam van uw winkel nodig. Shopify gaf u deze toen u de winkel aanmaakte, bijvoorbeeld <b>mystore.myshopify.com</b> of een code zoals <b>ab12cd-3e.myshopify.com</b>. Uw eigen domein, zoals <b>www.mystore.com</b>, werkt niet.
- Om deze te vinden, opent u uw Shopify-beheeromgeving en gaat u naar <b>Settings</b>, <b>Domains</b>. U kunt ook naar de adresbalk van uw Shopify-beheeromgeving kijken: bij <b>admin.shopify.com/store/ab12cd-3e</b> is de naam <b>ab12cd-3e</b>.
- Log in bij uw Shopify-beheeromgeving in dezelfde browser, als winkeleigenaar of als medewerker die apps mag installeren.
- Shopify is beschikbaar op al onze dropshippingwebsites. Verbind vanaf de website waar u uw dropshippingaccount heeft.

## Uw winkel koppelen

1. Open <b>Channels</b> in het menu. De pagina <b>Sales Channels</b> toont de kanalen die u al heeft.
2. Druk op <b>Add Sales Channel</b>.
3. Zoek de kaart <b>Shopify</b> en druk op <b>Connect</b>. Een venster opent: <b>Please enter your Shopify unique domain name</b>.
4. Typ de naam van uw winkel in het vak. Het einde, <b>.myshopify.com</b>, staat er al voor u. U kunt ook het volledige adres <b>xxx.myshopify.com</b> of <b>admin.shopify.com/store/...</b> plakken: wij houden alleen de winkelnaam over.
5. Druk op <b>Connect</b>.
6. Shopify opent in een nieuw tabblad en vraagt u onze app te installeren. Druk op <b>Install</b>. Opent er geen nieuw tabblad, dan blokkeert uw browser dit: sta pop-ups toe voor onze website, of gebruik <b>Click here to install</b> op de kanaalpagina (zie hieronder).
7. Wanneer de app is geïnstalleerd, toont Shopify de app-pagina. U kunt dat tabblad sluiten en teruggaan naar uw dropshippingaccount.

<!-- screenshot: het Shopify-verbindingsvenster met een ingetypte winkelnaam en de uitgang .myshopify.com rechts -->

Het nieuwe kanaal staat nu in uw lijst <b>Sales Channels</b>. Open het om het dashboard te zien.

Weet u niet zeker welke uw winkelnaam is, druk dan op de link <b>Click here</b> naast <b>Not sure which is your Shopify store name?</b> in datzelfde venster.

## Wat wij in uw winkel instellen

Zodra de app geïnstalleerd is, doen wij het volgende voor u in uw Shopify-winkel:

- Wij voegen een fulfilmentlocatie toe waarvan de naam begint met <b>aiku-</b>. De voorraad van de producten die u koppelt, wordt op deze locatie bijgehouden, en via deze locatie stuurt Shopify ons de bestellingen voor die producten.
- Wij voegen deze locatie toe aan uw standaard verzendprofiel, zodat Shopify vanaf deze locatie kan verkopen en verzenden. Zie [De AW-fulfilmentlocatie in Shopify](/docs/shopify-fulfilment-location).
- Wij stellen de berichten in die Shopify ons stuurt wanneer bestellingen binnenkomen.

U hoeft dit niet zelf te doen.

## Controleren of het kanaal verbonden is

Open het kanaal vanuit <b>Channels</b>. Wanneer onze app geïnstalleerd is, verschijnen drie kleine icoontjes naast de winkelnaam. Beweeg uw muis erover om hun naam te lezen:

- <b>App installed</b>: onze app is geïnstalleerd en wij kunnen uw winkel lezen.
- <b>Exist in platform</b> en <b>Platform status</b>: onze fulfilmentlocatie is ingesteld in uw winkel.

Zijn alle drie groene vinkjes, dan toont het dashboard de vakken <b>Orders</b> en <b>Products</b> en, in het linkermenu onder uw kanaal, <b>My Products</b> en <b>Orders</b>. Nu kunt u producten toevoegen: zie [Producten beheren op Shopify](/docs/managing-products-on-shopify).

<!-- screenshot: kanaaldashboard met de drie groene vinkjes, de knop Fetch orders en de vakken Orders en Products -->

## Als er staat dat het kanaal nog niet verbonden is

Ziet u <b>Your channel is not connected yet to the platform. Please connect it to be able to synchronize your products.</b>, dan is de app niet in Shopify geïnstalleerd. Dit is het meest voorkomende probleem. Het gebeurt wanneer het Shopify-tabblad werd gesloten voordat op <b>Install</b> werd gedrukt, of wanneer uw browser het nieuwe tabblad blokkeerde.

1. Log in bij uw Shopify-beheeromgeving in dezelfde browser.
2. Klik op de kanaalpagina op <b>Click here to install</b>, aan het eind van <b>Make sure you click the button "Install" in the Shopify dashboard to finalize the connection.</b>
3. Shopify opent in hetzelfde tabblad. Druk op <b>Install</b>.
4. Ga terug naar de kanaalpagina en laad deze opnieuw.

Werkt het nog steeds niet, dan kunt u op <b>Delete</b> drukken naast <b>Or delete the channel and try again</b>, en de winkel opnieuw vanaf het begin koppelen.

## Een kanaal verwijderen of resetten

- <b>Delete channel</b>: getoond bij een verbonden kanaal. Er wordt gevraagd <b>Are you sure you want to delete channel</b>; druk op <b>Yes, delete channel</b> om te bevestigen. Koppelt u dezelfde Shopify-winkel later opnieuw, dan kunnen we het oude kanaal met zijn producten heropenen in plaats van een nieuwe aan te maken.
- <b>Reset channel</b>: getoond wanneer het kanaal zijn verbinding is kwijtgeraakt maar nog producten heeft. Dit stelt de fulfilmentlocatie en de bestelberichten opnieuw in. Uw producten moeten dan opnieuw gekoppeld worden. Al geplaatste bestellingen worden niet gewijzigd.

## Als er iets misgaat

**"This does not look like a Shopify store name. Use the .myshopify.com name, not your own domain."** U typte uw eigen domein, zoals <b>mystore.com</b>. Typ in plaats daarvan de <b>myshopify.com</b>-naam. U vindt deze in Shopify onder <b>Settings</b>, <b>Domains</b>.

**"Shopify shop ... not found".** Er bestaat geen Shopify-winkel met die naam. Controleer de spelling. De naam is vaak een code van letters en cijfers, niet uw winkelnaam.

**"Shopify shop ... already exists, please use other name".** Deze winkel is al gekoppeld aan een dropshippingaccount. Controleer uw lijst <b>Sales Channels</b>. Is de winkel gekoppeld aan een ander account van uzelf, verwijder de koppeling daar dan eerst.

**"Shop name cannot contain spaces".** De myshopify.com-naam heeft nooit spaties. Kopieer de naam uit Shopify in plaats van uw winkelnaam te typen.

**Ik heb Shopify gekoppeld maar het geeft nog steeds aan niet verbonden te zijn.** De app is niet geïnstalleerd. Volg de stappen hierboven onder <b>If it says the channel is not connected yet</b>.

**"Click here to install" toont "Something went wrong".** Het kanaal is de koppeling met uw winkel kwijt. Druk op <b>Delete</b> naast <b>Or delete the channel and try again</b>, en koppel de winkel opnieuw.

**Na het drukken op Connect opent er geen Shopify-tabblad.** Uw browser blokkeerde het nieuwe tabblad. Sta pop-ups toe voor onze website, of open het kanaal en gebruik <b>Click here to install</b>.

**Producten verkopen niet in Shopify, of tonen als uitverkocht.** Controleer of de locatie <b>aiku-</b> in uw verzendprofiel staat. Zie [De AW-fulfilmentlocatie in Shopify](/docs/shopify-fulfilment-location).

Helpt niets hiervan, vraag het ons dan in de chat op onze website en vermeld uw myshopify.com-naam.

<aside class="wayfinder"><strong>Waar te klikken</strong>
<ul>
<li><b>Een nieuwe winkel koppelen:</b> <b>Channels</b> → <b>Add Sales Channel</b> → <b>Shopify</b> → <b>Connect</b>.</li>
<li><b>Een installatie afronden:</b> open het kanaal → <b>Click here to install</b> → <b>Install</b> in Shopify.</li>
<li><b>De koppeling controleren:</b> open het kanaal → de drie icoontjes naast de naam.</li>
<li><b>Voorraadinstellingen wijzigen:</b> open het kanaal → <b>Manage Sales Channel</b>.</li>
</ul>
</aside>
