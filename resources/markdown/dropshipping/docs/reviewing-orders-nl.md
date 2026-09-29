---
title: Uw bestellingen bekijken
summary: Vind de bestellingen van elk verkoopkanaal, lees hun status, zie wat er is verstuurd en wat u heeft betaald, en begrijp waarom een bestelling onbetaald, geannuleerd of helemaal niet aanwezig is.
date: 2026-09-25
source_date: 2026-09-25
tags: bestellingen, bestelstatus, onbetaald, geannuleerd, bestellijst
category: orders
shops: awd, dssk, dse
---

<aside class="tldr">
Bestellingen worden per verkoopkanaal bijgehouden. Open in het linkermenu uw kanaal en klik op <b>Orders</b>. Elke bestelling toont zijn status, en een rode label <b>Unpaid</b> wanneer we het geld nog niet konden afschrijven. Een onbetaalde bestelling wacht en wordt pas naar het magazijn gestuurd zodra ze betaald is. Klik op de bestelreferentie om de producten, het afleveradres, het trackingnummer en de factuur te zien.
</aside>

## Waar uw bestellingen staan

Elk kanaal dat u heeft gekoppeld (Shopify, eBay, TikTok, WooCommerce en de andere, en uw handmatige kanaal) heeft zijn eigen lijst met bestellingen.

1. Zoek in het linkermenu uw kanaal onder <b>Channels</b>.
2. Klik op <b>Orders</b> eronder.

De lijst heeft deze kolommen: <b>Status</b>, <b>Reference</b>, <b>Client</b>, <b>Date</b>, <b>Items</b> en <b>Total</b>. De nieuwste bestellingen staan bovenaan. Gebruik het zoekvak om een bestelling op referentie te vinden.

De <b>Reference</b> is ons bestelnummer. Het is niet het bestelnummer in uw winkel en het is geen trackingnummer. Om het trackingnummer te vinden, zie [Het trackingnummer van een bestelling vinden](/docs/tracking-numbers).

Op een Manual/API-kanaal staan bestellingen die u nog aan het voorbereiden bent niet in deze lijst. Ze staan onder <b>Baskets</b>, bij hetzelfde kanaal, totdat u ze plaatst.

Gebruik de exportknop bovenaan de pagina om de lijst te downloaden en kies <b>Excel</b> of <b>CSV</b>.

<!-- screenshot: de bestellijst van één kanaal, met de kolom Status, een referentie met de rode label Unpaid, en de exportknop -->

## Wat elke status betekent

- <b>Submitted</b>: wij hebben de bestelling. Is ze niet betaald, dan blijft ze hier staan tot ze betaald is.
- <b>In Warehouse</b>: de bestelling is betaald en wacht om te worden gepickt.
- <b>Picking</b>: het magazijn pickt de producten.
- <b>Waiting</b>: het picken is gepauzeerd, bijvoorbeeld terwijl het magazijn een product controleert.
- <b>Picked</b>, <b>Packing</b>, <b>Packed</b>: het pakket wordt voorbereid.
- <b>Finalized</b>: de bestelling is gefactureerd en klaar om te vertrekken.
- <b>Dispatched</b>: het pakket heeft ons magazijn verlaten. Het trackingnummer staat bij de bestelling.
- <b>Cancelled</b>: de bestelling wordt niet verstuurd.

Naast de referentie ziet u mogelijk ook kleine iconen voor <b>Premium dispatch</b>, <b>Extra packing</b> en <b>Insurance</b> wanneer u die voor die bestelling koos.

## Onbetaalde bestellingen

Een rode label <b>Unpaid</b> betekent dat wij het volledige bedrag nog niet konden afschrijven. De bestelling blijft <b>Submitted</b> en wordt niet naar het magazijn gestuurd.

Wanneer een bestelling uit uw winkel binnenkomt, betalen wij haar zo:

1. Eerst met uw saldo.
2. Is het saldo niet toereikend, dan met uw opgeslagen kaarten, te beginnen met uw standaardkaart.

Werkt geen van beide, dan wacht de bestelling en sturen wij u een e-mail dat ze on hold staat. Meestal gebeurt dit omdat er geen kaart is opgeslagen voor het kanaal. Om dit niet opnieuw te laten gebeuren, slaat u een kaart op: zie [Betalen voor uw bestellingen](/docs/topping-up-and-paying-with-balance).

Om een bestelling die wacht te betalen:

1. Vul uw saldo bij met minstens het verschuldigde bedrag. Zie [Betalen voor uw bestellingen](/docs/topping-up-and-paying-with-balance).
2. Open de bestelling opnieuw. Een geel vak zegt <b>Order ... is not paid yet</b> en toont <b>Your balance</b>.
3. Klik op de knop <b>Pay ... with balance</b>. Deze toont het verschuldigde bedrag.

De bestelling gaat dan naar het magazijn. De knop verschijnt alleen wanneer uw saldo het hele verschuldigde bedrag dekt, terwijl de bestelling <b>Submitted</b> of <b>Picking</b> is.

<b>Let op:</b> wij proberen uw kaart niet zelf opnieuw. Een wachtende bestelling blijft wachten tot u haar betaalt.

## Binnen een bestelling

Klik op de bestelreferentie om deze te openen. U ziet:

- Bovenaan een tijdlijn met de stappen die de bestelling heeft doorlopen, en een label <b>Paid</b> of <b>Unpaid</b>.
- Uw klant: naam, e-mail, telefoon en afleveradres.
- <b>Weight</b>: het geschatte gewicht van alle producten.
- <b>Delivery Notes</b>: de pakketten, hun status, en onder <b>Shipments</b> de koerier en het trackingnummer. Het PDF-icoon (<b>Download Picking List</b>) downloadt de lijst met producten in het pakket.
- <b>Invoices</b>: onze factuur voor de bestelling, om te openen of als PDF te downloaden. Zie [Uw facturen](/docs/invoices).
- De prijssamenvatting: <b>Items</b>, kosten, <b>Net</b>, belasting en <b>Total</b>.

Dit zijn de extra kosten op deze website:

{order_charges}

- Het tabblad <b>Transactions</b>: elk product met zijn <b>Quantity</b>. Zijn er minder verstuurd dan besteld, dan wordt de verstuurde hoeveelheid rood boven de bestelde hoeveelheid getoond, die is doorgestreept.
- <b>Notes from Staff</b>, <b>Delivery Instructions</b> en <b>Other Instructions</b>. Leveringsinstructies worden op het verzendlabel afgedrukt.

<!-- screenshot: een bestelpagina met de tijdlijn, het vak Delivery Notes met een trackingnummer en het vak Invoices -->

## Wanneer niet alles is verstuurd

Soms kunnen wij niet elk product versturen, bijvoorbeeld wanneer er één op raakt terwijl we picken. De bestelpagina toont dan <b>Dispatched | Modified</b>, en in de lijst verschijnt een geel waarschuwingsicoon naast de status. Het tabblad <b>Transactions</b> toont welke producten niet zijn verstuurd. Het geld voor de producten die wij niet hebben verstuurd, gaat vanzelf terug naar uw saldo zodra de bestelling gefactureerd is.

## Geannuleerde bestellingen

Een geannuleerde bestelling toont bovenaan de status <b>Cancelled</b>, en een rood vak <b>Order cancelled</b> wanneer er een reden is vastgelegd. Geld dat u er al voor betaalde, gaat terug naar uw saldo.

Bij een Shopify-bestelling staat bovenaan ook een synchronisatieknop (tooltip <b>Sync order state</b>). Klik erop om Shopify te laten weten dat de bestelling is geannuleerd. Ziet u <b>The order state on Shopify is up-to-date</b>, dan weet Shopify het al.

Er is geen annuleerknop. Om een bestelling te annuleren, vraag het ons in de chat op onze website met de bestelreferentie. Wij kunnen haar alleen annuleren voordat ze verzonden is. Zodra de bestelling verpakt is, kan het te laat zijn.

## Een beoordeling achterlaten

Op sommige van onze websites verschijnt, een tijdje nadat een bestelling is verzonden, een knop <b>Review</b> bovenaan de bestelling. Gebruik deze om de bestelling en de producten te beoordelen.

## Als er iets misgaat

**Een bestelling uit mijn winkel staat niet in de lijst.** Controleer dit, in deze volgorde:

- Alleen producten die in <b>My Products</b> van dat kanaal staan, komen over. Staat geen enkel product van de bestelling in <b>My Products</b>, dan verschijnt de bestelling niet in <b>Orders</b>, omdat er niets is dat we kunnen versturen.
- Controleer of u naar het juiste kanaal kijkt. Elk kanaal heeft zijn eigen lijst.
- Het kanaal moet nog verbonden zijn. Zegt de kanaalpagina dat het niet verbonden is, verbind het dan eerst opnieuw.
- **Shopify**: alleen bestellingen die Shopify naar onze fulfilmentlocatie stuurt, komen over, en ze komen binnen als een fulfilmentverzoek. Staat een product in de bestelling niet in <b>My Products</b>, dan wordt dat deel van het verzoek in Shopify geweigerd en komt de rest over. Wordt het hele verzoek geweigerd (geen van de producten staat in <b>My Products</b>, of de bestelling heeft geen afleveradres), dan toont de bestelling in <b>Orders</b> als <b>Cancelled</b>, met de reden onder <b>Notes from Staff</b>. Herstel de bestelling in Shopify en vraag opnieuw om fulfilment. Een bestelling die door uw eigen winkel wordt afgehandeld, al is afgehandeld, of waarvan het verzoek in Shopify is geannuleerd, komt niet over. Dit is meestal de reden dat een testbestelling niet aankomt: controleer in Shopify of de producten ervan op onze locatie op voorraad staan.

**Mijn Shopify-fulfilmentverzoek is geaccepteerd maar ik zie de bestelling niet om te betalen.** Kijk in <b>Orders</b> van het Shopify-kanaal naar de status en een rode label <b>Unpaid</b>. Staat ze er niet, vraag het ons dan in de chat op onze website met het Shopify-bestelnummer.

**De bestelling staat al lang op Submitted.** Ze is bijna altijd onbetaald. Volg de stappen onder "Onbetaalde bestellingen" hierboven.

**De bestelling zegt "We cannot deliver to ...".** Wij versturen vanaf deze website niet naar dat land. Zie [Landen waar wij niet naartoe kunnen leveren](/docs/delivery-restrictions).

**Een product kwam kapot aan, of mijn koper wil iets terugsturen.** Vraag het ons in de chat op onze website met de bestelreferentie en foto's. Uw koper mag niets terugsturen voordat het is afgesproken met ons in de chat. Terugbetalingen gaan naar uw saldo.
