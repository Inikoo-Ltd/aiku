---
title: Handmatig bestellingen plaatsen
summary: Maak een bestelling voor een klant op een Manual/API-kanaal, voeg producten toe, kies leveringsopties, betaal bij het afrekenen en volg de bestelling tot ze wordt verzonden.
date: 2026-09-25
source_date: 2026-09-25
tags: handmatig, bestellingen, mandje, afrekenen, betaling, saldo, ophalen, verzending
category: orders
series: manual
order: 4
shops: awd, dssk, dse
---

<aside class="tldr">
Open de klant in uw Manual/API-kanaal en druk op <b>Create Order</b>. Er opent een mandje: voeg producten toe met <b>Add products</b>, kies de leveringsopties en druk op <b>Continue to Checkout</b>. Wij gebruiken eerst uw accountsaldo en u betaalt de rest met kaart. Zodra de bestelling betaald is, gaat ze naar het magazijn en volgt u haar onder <b>Orders</b>.
</aside>

## Voordat u begint

- U heeft een Manual/API-kanaal nodig. Zie [Het Manual/API-kanaal](/docs/manual-and-api-channel).
- De persoon naar wie u verzendt moet een klant van dat kanaal zijn. Zie [Klanten beheren](/docs/managing-clients).

## Maak de bestelling

1. Open uw Manual/API-kanaal en vervolgens <b>Clients</b>.
2. Klik op de naam van de klant. De klantpagina opent.
3. Druk op <b>Create Order</b>.

Het mandje voor de nieuwe bestelling opent. Bovenaan ziet u de klant, hun contactgegevens en het afleveradres. Controleer de naam en het adres voordat u verdergaat.

## Producten toevoegen

- <b>Add products</b> opent een venster <b>Add products to Order</b>. Zoek op productnaam of code, typ de hoeveelheid en voeg de producten toe. U kunt elk product dat wij verkopen kiezen, niet alleen die in <b>My Products</b>.
- <b>Upload products</b> voegt veel producten toe vanuit een spreadsheet. Download de sjabloon (.xlsx) in het venster, vul de kolommen <b>code</b> en <b>quantity</b> in en upload het.

De producten verschijnen in de lijst. U kunt daar hoeveelheden wijzigen of een regel verwijderen. Het geschatte gewicht van het pakket wordt naast het adres getoond.

<!-- screenshot: een mandje met bovenaan de klant en het adres, producten in de lijst, de leveringsopties en de knop Continue to Checkout -->

## Kies leveringsopties

- <b>Collection</b>: zet dit aan als u, of een koerier die u boekt, de bestelling bij ons magazijn ophaalt in plaats van dat wij haar versturen. Er geldt een extra kost. Staat het uit, dan sturen wij de bestelling naar het getoonde adres. Druk op <b>Edit</b> onder het adres om het voor deze bestelling te wijzigen.
- Snellere verzending: bij AW Dropship UK heet de optie <b>Same Day Dispatch</b>, bij AW Dropship Europe <b>Premium Dispatch</b> en bij AW Dropship España <b>Envío Premium</b>. Er geldt een extra kost. Lees het informatie-icoon ernaast voor de voorwaarden.
- <b>Extra protective packing for fragile items</b> (alleen AW Dropship UK): extra verpakking voor breekbare producten. Er geldt een extra kost.
- <b>Delivery Instructions</b>: een notitie voor de koerier. <b>This message will be printed in shipping label</b>, dus schrijf deze voor de koerier, niet voor ons.
- <b>Other Instructions</b>: een notitie voor ons team.

De kosten en het bestelbedrag worden bijgewerkt wanneer u een optie wijzigt.

Dit zijn de extra kosten op deze website:

{order_charges}

## Betalen

Dekt uw accountsaldo de hele bestelling, dan toont het mandje <b>Place order</b> in plaats van <b>Continue to Checkout</b>. Druk erop en de bestelling wordt met uw saldo betaald. De notitie zegt <b>This is your final confirmation. You can pay totally with your current balance.</b>

Anders:

1. Druk op <b>Continue to Checkout</b>.
2. Het afrekenen toont het bestelnummer. Heeft u wat saldo, dan wordt getoond hoeveel met saldo wordt betaald, en wordt u gevraagd de rest te betalen.
3. Voer onder <b>Online payments</b> uw kaartgegevens in en bevestig. Uw bank kan u vragen de betaling in de app of met een code goed te keuren.
4. Zodra de betaling is voltooid, ziet u <b>Payment done. Waiting for confirmation...</b> en opent daarna de bestelling.

Druk op <b>Back to basket</b> bij het afrekenen om de bestelling te wijzigen voordat u betaalt.

## Onafgeronde bestellingen: Baskets

Een bestelling die u heeft aangemaakt maar niet betaald, blijft staan onder <b>Baskets</b> bij uw kanaal. Het getal naast <b>Baskets</b> in het menu toont hoeveel u er heeft. Open er een om deze af te ronden, of druk op <b>Delete</b> bij de rij (tooltip <b>Delete basket</b>) om deze te verwijderen. Een mandje wordt pas naar het magazijn gestuurd zodra het betaald is.

## Volg uw bestellingen

Open <b>Orders</b> onder uw kanaal. De lijst toont de <b>Status</b>, <b>Reference</b>, klant, <b>Date</b>, artikelen en totaal. Klik op een bestelling om de producten, leveringsbonnen, zendingen met tracking-links en facturen te zien.

Het statusicoon vertelt u waar de bestelling zich bevindt. Beweeg erover voor de naam:

- <b>Submitted</b>: wij hebben de bestelling ontvangen.
- <b>In Warehouse</b>, <b>Picking</b>, <b>Picked</b>, <b>Packing</b>, <b>Packed</b>: ons team bereidt haar voor.
- <b>Waiting</b>: ze staat on hold in het magazijn.
- <b>Finalized</b>: klaar om te vertrekken.
- <b>Dispatched</b>: verzonden. De tracking-link staat bij de bestelling.
- <b>Cancelled</b>: de bestelling is geannuleerd.

Iconen bovenaan een bestelling tonen de opties die u koos: een ster voor <b>Premium dispatch</b>, een doos voor <b>Extra packing</b>.

Kunnen wij sommige artikelen niet versturen, dan toont de bestelling <b>Some items are not being sent</b>. Het geld voor die artikelen wordt automatisch terugbetaald.

De bestelpagina toont de facturen met een downloadknop. Al uw facturen staan ook onder <b>Invoices</b> in het menu.

## Als er iets misgaat

- <b>Ik zie Create Order niet op de klantpagina.</b> De knop staat alleen bij klanten van een Manual/API-kanaal. Bij verbonden kanalen komen bestellingen binnen vanuit uw winkel.
- <b>Het mandje zegt "We cannot deliver to …".</b> Wij versturen niet naar dat land. Wijzig het afleveradres, of zet <b>Collection</b> aan als u het vervoer zelf regelt.
- <b>Het mandje zegt dat mijn factuuradres als verboden is gemarkeerd.</b> Werk het factuuradres bij in uw account, of vraag het ons in de chat op onze website.
- <b>Continue to Checkout is grijs en vraagt mij een bestand te uploaden.</b> U koos een gedrukte bijsluiter waarvoor uw ontwerp nodig is. Upload het bestand ervoor, of verwijder de bijsluiter, voordat u afrekent.
- <b>De kaartbetaling is mislukt.</b> Het afrekenen zegt <b>Something went wrong</b>. Controleer de kaartgegevens en of uw bank de betaling heeft goedgekeurd, en probeer het opnieuw. U kunt ook uw saldo aanvullen en daarmee betalen.
- <b>Het afrekenen zegt "Payment still processing".</b> Betaal niet opnieuw. De bestelling wordt automatisch ingediend zodra de betaling is bevestigd.
- <b>Het afrekenen zegt "Order already submitted".</b> De bestelling is al betaald. Open deze onder <b>Orders</b>.
- <b>Mijn bestelling toont Unpaid.</b> De betaling dekte de bestelling niet. Voeg geld toe aan uw saldo met <b>Top Up</b>, open de bestelling en druk op <b>Pay … with balance</b>. De knop verschijnt zodra uw saldo het verschuldigde bedrag dekt. De bestelling gaat dan naar het magazijn.
- <b>Ik moet een bestelling wijzigen of annuleren die ik al betaald heb.</b> Er is geen annuleerknop. Vraag het ons zo snel mogelijk in de chat op onze website. Wij kunnen alleen annuleren of wijzigen voordat de bestelling verzonden is. Is ze al verpakt, dan kan het te laat zijn.
