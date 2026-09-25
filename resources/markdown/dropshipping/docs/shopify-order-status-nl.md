---
title: Uw Shopify-bestellingen en hun status
summary: Hoe bestellingen uit uw Shopify-winkel ons bereiken, hoe ze worden betaald, wat elke status betekent, waarom een bestelling als Submitted kan blijven wachten of helemaal niet aankomt, en wat we terugsturen naar Shopify.
date: 2026-09-25
source_date: 2026-09-25
tags: shopify, bestellingen, status, betaling, ingediend, onbetaald, fulfilmentverzoek
category: orders
series: shopify
order: 4
shops: awd, dssk, dse
---

<aside class="tldr">
Koopt een klant een gekoppeld product in uw Shopify-winkel, dan stuurt Shopify ons een fulfilmentverzoek en verschijnt de bestelling onder <b>Orders</b> van uw kanaal. Wij betalen haar uit uw saldo, dan met uw opgeslagen kaart. Een betaalde bestelling gaat vanzelf naar ons magazijn. Een bestelling die wij niet konden betalen blijft <b>Submitted</b> en <b>Unpaid</b> tot u haar betaalt. Versturen wij haar, dan markeren wij haar in Shopify als afgehandeld met het trackingnummer.
</aside>

## Hoe een bestelling ons bereikt

1. Een klant koopt een van uw gekoppelde producten in Shopify.
2. Shopify stuurt een fulfilmentverzoek voor die artikelen naar de <b>aiku-</b>-locatie.
3. Wij accepteren het verzoek en maken de bestelling aan in uw kanaal. U vindt haar onder uw kanaal, <b>Orders</b>.

Alleen producten die gekoppeld zijn in <b>My Products</b> kunnen bij ons terechtkomen. Heeft een bestelling deels onze producten en deels uw eigen producten, dan accepteren wij onze producten en verstuurt u de rest zelf.

## Hoe de bestelling wordt betaald

Wij proberen elke nieuwe bestelling meteen te betalen:

1. Eerst met uw saldo.
2. Is het saldo niet toereikend, dan met de kaarten opgeslagen onder <b>Saved Cards</b>, in uw volgorde van voorkeur.

Lukt de betaling, dan gaat de bestelling vanzelf naar ons magazijn. Lukt het niet, dan wacht de bestelling en sturen wij u een e-mail dat ze on hold staat. Meestal gebeurt dit omdat er geen kaart is opgeslagen. Om dit niet opnieuw te laten gebeuren, slaat u een kaart op: zie [Betaalkaarten en betaalmethoden](/docs/payment-cards-and-options).

## Betaal een bestelling die wacht

Een bestelling die wij niet konden betalen toont <b>Unpaid</b> naast haar nummer en blijft <b>Submitted</b>.

1. Vul uw saldo bij met minstens het verschuldigde bedrag, via <b>Top Up</b> in het menu.
2. Open uw kanaal, <b>Orders</b>, en open de bestelling.
3. Druk op <b>Pay ... with balance</b>. De knop verschijnt alleen wanneer uw saldo het verschuldigde bedrag dekt.

De bestelling gaat dan vanzelf naar ons magazijn.

<!-- screenshot: een onbetaalde bestelling met het label Unpaid en de knop Pay with balance -->

## Wat elke status betekent

- <b>Submitted</b>: wij hebben de bestelling. Toont ze ook <b>Unpaid</b>, dan wacht ze op uw betaling.
- <b>In Warehouse</b>: betaald en wacht om te worden gepickt.
- <b>Handling</b>: wordt gepickt.
- <b>Waiting</b>: het magazijn moest de bestelling even stopzetten voordat ze verder kan.
- <b>Picked</b>, <b>Packing</b>, <b>Packed</b>: het pakket wordt voorbereid.
- <b>Finalized</b>: gefactureerd en klaar om te gaan.
- <b>Dispatched</b>: verstuurd. Konden sommige artikelen niet worden verstuurd, dan ziet u <b>Modified</b> en wordt het geld ervoor automatisch terugbetaald.
- <b>Cancelled</b>: de bestelling wordt niet verstuurd. De reden staat bovenaan de bestelling.

Voor meer over de bestelpagina, zie [Uw bestellingen bekijken](/docs/reviewing-orders).

## Wat wij terugsturen naar Shopify

- Wordt de bestelling verstuurd, dan markeren wij haar in Shopify als afgehandeld met het trackingnummer en de link. Shopify laat het uw klant weten.
- Wordt een bestelling geannuleerd, dan sluiten wij het verzoek in Shopify. Bij een geannuleerde bestelling kunt u op de synchronisatieknop (<b>Sync order state</b>) drukken om de annulering opnieuw naar Shopify te sturen. Is Shopify al bijgewerkt, dan ziet u <b>The order state on Shopify is up-to-date</b>.

## Een bestelling staat niet in mijn Orders

Open het kanaal en druk op <b>Fetch orders</b>. Dit <b>Checks Shopify for orders that have not reached us yet</b>: het kijkt naar recente niet-afgehandelde bestellingen en haalt de bestellingen voor onze locatie binnen. Is er niets nieuws, dan ziet u <b>No new orders</b>. U kunt na een paar minuten opnieuw drukken.

Komt de bestelling nog steeds niet, controleer dan het volgende:

- De producten zijn gekoppeld (groene handdruk) in <b>My Products</b>.
- De artikelen staan op voorraad op de <b>aiku-</b>-locatie in Shopify, en de locatie staat in uw verzendprofiel. Zie [De AW-fulfilmentlocatie in Shopify](/docs/shopify-fulfilment-location).
- De bestelling was nog niet afgehandeld in Shopify, of naar een andere locatie verstuurd.

## Als er iets misgaat

**Een geannuleerde bestelling zegt "Fulfilment request declined: The items can't be fulfilled because you don't have the items in your portfolio."** Geen van de producten in de bestelling is gekoppeld in <b>My Products</b>. Voeg ze toe en koppel ze, en vraag dan opnieuw om fulfilment in Shopify.

**Een geannuleerde bestelling zegt "Fulfilment request declined: Order don't have shipping information".** De bestelling in Shopify heeft geen afleveradres. Voeg het adres toe in Shopify en vraag opnieuw om fulfilment.

**Het fulfilmentverzoek is geaccepteerd in Shopify, maar ik zie de bestelling niet om te betalen.** Open <b>Orders</b> in het kanaal: daar staan bestellingen uit Shopify vermeld. Zoek de bestelling met <b>Unpaid</b>, of druk op <b>Fetch orders</b>.

**Mijn testbestelling op Shopify is niet overgekomen.** Een testbestelling komt alleen bij ons terecht als deze gekoppelde producten bevat die op de <b>aiku-</b>-locatie op voorraad staan. Let op: een bestelling die wel bij ons terechtkomt, is een echte bestelling. Wij betalen en versturen haar. Heeft u er per ongeluk één geplaatst, vraag het ons dan snel in de chat op onze website om haar te annuleren. Wij kunnen alleen annuleren voordat ze verstuurd is.

**De bestelling blijft Submitted en Unpaid.** Er was niet genoeg saldo en geen kaart werkte. Betaal haar zoals getoond in <b>Betaal een bestelling die wacht</b>, en sla een kaart op voor de volgende.

**De bestelling zegt "We cannot deliver to ...".** Wij versturen vanaf deze website niet naar dat land. De bestelling is niet betaald en wordt niet verstuurd. Werk het afleveradres bij, of vraag het ons in de chat op onze website.

**De bestelling is betaald maar heeft al lang niet bewogen.** Vraag het ons in de chat op onze website, met het bestelnummer. Het bestelnummer is de <b>Reference</b> in <b>Orders</b>.

<aside class="wayfinder"><strong>Waar te klikken</strong>
<ul>
<li><b>Bekijk uw Shopify-bestellingen:</b> <b>Channels</b> → uw Shopify-winkel → <b>Orders</b>.</li>
<li><b>Betaal een wachtende bestelling:</b> open de bestelling → <b>Pay ... with balance</b>.</li>
<li><b>Haal een ontbrekende bestelling binnen:</b> open het kanaal → <b>Fetch orders</b>.</li>
<li><b>Sla een kaart op:</b> <b>Saved Cards</b> in het menu.</li>
</ul>
</aside>
