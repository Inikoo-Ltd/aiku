---
title: Uw WooCommerce-winkel koppelen
summary: Koppel uw WooCommerce-winkel aan uw dropshippingaccount, herstel de berichten die u mogelijk ziet tijdens het koppelen, en verbind een winkel opnieuw die niet meer antwoordt.
date: 2026-09-25
source_date: 2026-09-25
tags: woocommerce, wordpress, verkoopkanaal, verbinden, api-sleutels
category: sales-channels
series: woocommerce
order: 1
shops: awd, dssk, dse
---

<aside class="tldr">
Ga naar <b>Kanalen</b>, druk op <b>Add Sales Channel</b> en dan op <b>Connect</b> bij de Woocommerce-kaart. Typ een naam voor uw winkel en druk op <b>Next</b>. Typ uw winkeladres, druk op <b>Auth Store</b>, keur onze app goed in WooCommerce, kom terug en druk op <b>Next</b>. Blokkeert uw hosting de automatische sleutels, dan kunt u de sleutels in WooCommerce aanmaken en zelf plakken.
</aside>

## Voordat u begint

Controleer eerst het volgende op uw WordPress-site. De meeste mislukte koppelingen komen door een van deze punten.

- WooCommerce is geïnstalleerd en actief.
- Uw winkeladres begint met <b>https://</b>. Wij koppelen niet met winkels zonder geldig SSL-certificaat.
- In WordPress staat <b>Settings</b>, <b>Permalinks</b> niet op <b>Plain</b>. Met Plain-permalinks kan de WooCommerce-API niet gevonden worden.
- Uw beveiligingsplugin, firewall of Cloudflare blokkeert geen verzoeken naar <b>/wp-json/</b>. Wij spreken via dit adres met uw winkel.
- U kunt inloggen bij uw WordPress-beheeromgeving als beheerder. Dit heeft u nodig om de koppeling goed te keuren.
- Optioneel maar handig: stel de gewichtseenheid die u wilt in WooCommerce in (<b>Settings</b>, <b>Products</b>) voordat u koppelt. Wij lezen deze bij het koppelen en sturen productgewichten in die eenheid.

WooCommerce is beschikbaar op al onze dropshippingwebsites. Verbind vanaf de website waar u uw dropshippingaccount heeft.

## Uw winkel koppelen

1. Open <b>Kanalen</b> in het menu. De pagina <b>Sales Channels</b> toont de kanalen die u al heeft.
2. Druk op <b>Add Sales Channel</b>.
3. Zoek de kaart <b>Woocommerce</b> en druk op <b>Connect</b>. Een venster opent.
4. Typ bij <b>Woocommerce Account Name</b> een naam voor uw winkel, bijvoorbeeld uw winkelnaam. De naam is verplicht en is de naam die u in uw kanaallijst ziet. Druk op <b>Next</b>.
5. Typ onder <b>Authentication Settings</b> het volledige adres van uw winkel, bijvoorbeeld <b>https://mystore.com</b>. Druk op <b>Auth Store</b>.
6. Wij controleren eerst of uw winkel antwoordt. Doet ze dat, dan opent een nieuw tabblad op uw WordPress-site. Log in als WordPress hierom vraagt.
7. WooCommerce toont dat <b>AW Connect</b> vraagt om <b>Read/Write</b>-toegang. Controleer of u bent ingelogd bij de juiste winkel, en druk dan op <b>Approve</b>.
8. Het tabblad toont een kort bericht en sluit vanzelf. Ga terug naar het venster in uw dropshippingaccount en druk op <b>Next</b>.
9. U ziet <b>Connected!</b> Druk op <b>OK</b>.

<!-- screenshot: het Woocommerce-verbindingsvenster, stap Authentication Settings met het winkeladresvak en de knop Auth Store -->

<!-- screenshot: de WooCommerce-goedkeuringspagina met AW Connect dat vraagt om Read/Write-toegang en de knop Approve -->

Rond alle stappen binnen een uur af. Daarna vergeten wij de naam en sleutels waarmee u begon, en moet u opnieuw beginnen vanaf <b>Connect</b>.

Blokkeert uw browser het nieuwe tabblad, dan opent de goedkeuringspagina in hetzelfde tabblad en verlaat u het verbindingsvenster. Sta pop-ups toe voor onze website en begin dan opnieuw vanaf <b>Connect</b>.

## Als uw winkel ons de sleutels niet kon sturen

Bij goedkeuring stuurt WooCommerce de nieuwe sleutels vanuit uw hosting naar onze servers. Sommige hostingbedrijven blokkeren dit. U ziet dan <b>Your store approved the connection but could not send us the keys</b>, en <b>Next</b> geeft aan <b>You are not connected yet</b>.

U kunt dan alsnog koppelen door de sleutels zelf te plakken:

1. Ga in WordPress naar <b>WooCommerce</b>, <b>Settings</b>, <b>Advanced</b>, <b>REST API</b>.
2. Voeg een sleutel toe. Geef een willekeurige omschrijving, kies uw beheerdersgebruiker en zet <b>Permissions</b> op <b>Read/Write</b>. Genereer de sleutel.
3. Kopieer de <b>Consumer key</b> (begint met ck_) en de <b>Consumer secret</b> (begint met cs_). WooCommerce toont het geheim maar één keer.
4. Zorg in het Woocommerce-venster in uw dropshippingaccount dat uw winkeladres nog in het adresvak staat.
5. Druk op <b>My store could not send the keys, let me paste them</b>.
6. Plak de sleutel en het geheim, en druk op <b>Use these keys</b>.

<!-- screenshot: de stap Authentication Settings met het geopende gedeelte voor handmatige sleutels, met de vakken ck_ en cs_ en de knop Use these keys -->

## Na het koppelen

Uw winkel verschijnt nu op de pagina <b>Sales Channels</b>. Klik op de naam om het kanaaldashboard te openen. Daar ziet u <b>Orders</b>, <b>Clients</b> en <b>Products</b>. Druk op <b>View all</b> onder <b>Products</b> om producten toe te voegen. Zie [Producten beheren op WooCommerce](managing-products-on-woocommerce).

Bij het koppelen voegen wij ook twee webhooks toe aan uw winkel: één voor nieuwe bestellingen en één voor verwijderde producten. Verwijder ze niet in WooCommerce, <b>Settings</b>, <b>Advanced</b>, <b>Webhooks</b>. Zonder deze bereiken nieuwe bestellingen ons niet meteen.

Wij importeren bestellingen die betaald zijn, in WooCommerce de status <b>Processing</b> hebben en een verzendland hebben. Ontbreekt een bestelling, druk dan op <b>Fetch orders</b> op het kanaaldashboard. Dit controleert uw winkel op bestellingen van de laatste 14 dagen die ons nog niet hebben bereikt.

Met <b>Manage Sales Channel</b> kunt u de winkelnaam, uw voorraadinstellingen en uw prijsregel voor nieuwe producten wijzigen.

## Dezelfde winkel opnieuw koppelen

Verwijdert u uw WooCommerce-kanaal en koppelt u later hetzelfde winkeladres opnieuw, dan brengen wij hetzelfde kanaal terug, met zijn producten en bestellingen. U begint niet vanaf nul.

## Als uw winkel niet meer antwoordt

Wij controleren uw gekoppelde winkel regelmatig. Antwoordt uw winkel niet meer, of werken de sleutels niet meer, dan toont het kanaal <b>Your channel is not connected yet to the platform</b>. Daarboven ziet u mogelijk het foutbericht dat uw winkel ons stuurde. Zolang dit zo blijft, is uw productlijst verborgen en kunt u geen producten naar uw winkel uploaden.

Zo lost u het op:

1. Zorg dat uw website online is en dat u deze in uw browser kunt openen.
2. Druk op de kanaalpagina op <b>Try to reconnect</b>. Uw WordPress-site opent. Log in als beheerder en druk opnieuw op <b>Approve</b>. Dit maakt nieuwe sleutels aan.
3. Werkt het nog niet, druk dan op <b>Test Connection</b> om de verbinding opnieuw te controleren.
4. Als laatste stap drukt u op <b>Delete</b> en koppelt u de winkel opnieuw. Uw producten en bestellingen komen terug wanneer u hetzelfde winkeladres gebruikt.

Blijft uw winkel lange tijd mislukken, dan stoppen wij met controleren. Het werkt weer zodra u opnieuw koppelt.

<!-- screenshot: de waarschuwing "niet verbonden" op een WooCommerce-kanaal met de knoppen Try to reconnect, Test Connection en Delete -->

## Als er iets misgaat

Dit zijn de berichten die u kunt zien wanneer u op <b>Auth Store</b> drukt, en wat u kunt doen.

- <b>We could not resolve your store domain</b>: het adres is verkeerd gespeld of het domein is niet live. Kopieer het adres uit uw browser terwijl uw winkel open staat.
- <b>Your store SSL certificate could not be verified</b>: uw certificaat is verlopen, zelfondertekend of onvolledig. Vraag uw hostingbedrijf het te vernieuwen of te herstellen.
- <b>Your store refused our connection</b> of <b>Your store did not answer within 2 minutes</b>: uw hosting of firewall blokkeert onze servers. Het bericht vermeldt onze IP-adressen. Stuur ze naar uw hostingbedrijf en vraag ze toe te staan.
- <b>Your store redirects to ...</b>: uw winkel staat op een ander adres, bijvoorbeeld met of zonder www. Voer het adres in dat in het bericht genoemd wordt.
- <b>Your store url redirects in a loop</b>: voer het uiteindelijke adres van uw winkel in, het adres dat u in de browser ziet nadat de pagina is geladen.
- <b>We could not find the WooCommerce API on this store</b>: WooCommerce is niet actief, de REST API staat uit, of uw permalinks staan op <b>Plain</b>. Wijzig de permalinks in WordPress, <b>Settings</b>, <b>Permalinks</b>.
- <b>Your store answered with 401</b> of <b>403</b> <b>and blocked our request</b>: een beveiligingsplugin, een firewall of Cloudflare blokkeert ons. Sta verzoeken naar <b>/wp-json/</b> toe in die tool.
- <b>Your WooCommerce store returned an error 500</b> (of een ander nummer dat begint met 5): uw website heeft een fout. Controleer uw hosting-foutlog, of vraag uw hostingbedrijf, en probeer het daarna opnieuw.
- <b>Your store answered with ... but did not return the WooCommerce REST API</b>: het adres verwijst niet naar uw WordPress-site. Controleer of u de winkel zelf heeft ingevoerd, niet een landingspagina of een andere site.
- <b>You are not connected yet, click auth store to connect and follow the instructions</b>: u drukte op <b>Next</b> voordat u had goedgekeurd in WooCommerce, de goedkeuring heeft ons niet bereikt, of er is meer dan een uur verstreken. Druk opnieuw op <b>Auth Store</b>, of plak de sleutels zelf zoals hierboven.
- <b>We can't access your store, make sure you already put correct store url</b>: wij hebben de sleutels ontvangen, maar konden ze niet gebruiken op dat adres. Controleer het adres, en of de sleutels <b>Read/Write</b>-rechten hebben.

Problemen op uw eigen website, zoals uw winkel die plat ligt, traag is of ons blokkeert, kunnen alleen door u of uw hostingbedrijf worden opgelost. Wij kunnen geen instellingen op uw website wijzigen.
