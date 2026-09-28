---
title: Betaalkaarten en betaalmethoden
summary: Hoe u bestellingen betaalt, welke betaalmethoden het afrekenen biedt, en hoe u een kaart opslaat zodat bestellingen uit uw gekoppelde winkels automatisch worden betaald.
date: 2026-09-25
source_date: 2026-09-25
tags: betaling, kaart, opgeslagen kaarten, afrekenen, paypal, apple pay, google pay, automatische betaling
category: payments
shops: awd, dssk, dse
---

<aside class="tldr">
Uw saldo wordt altijd eerst gebruikt. Wat het saldo niet dekt, betaalt u bij het afrekenen onder <b>Online payments</b>: kaart, Apple Pay, Google Pay, PayPal en andere methoden, afhankelijk van uw land en apparaat. Bestellingen die binnenkomen via uw gekoppelde winkels worden zonder tussenkomst betaald: eerst uit uw saldo, dan met een kaart die u heeft opgeslagen onder <b>Saved Cards</b>. Sla een kaart op, of houd uw saldo aangevuld, zodat deze bestellingen niet onbetaald blijven.
</aside>

## Twee manieren waarop bestellingen worden betaald

- <b>Bestellingen die u zelf maakt</b> (handmatige bestellingen): u betaalt bij het afrekenen terwijl u toekijkt.
- <b>Bestellingen uit uw gekoppelde winkels</b> (Shopify, WooCommerce, eBay, TikTok en de andere, of via de API): er is niemand bij het afrekenen, dus nemen wij het geld zelf. Wij gebruiken eerst uw saldo, dan uw opgeslagen kaarten. Zie [Saldo aanvullen en ermee betalen](/docs/topping-up-and-paying-with-balance).

## Betalen bij het afrekenen

1. Druk op het <b>Dashboard</b>, onder <b>Quick links (Shortcuts)</b>, op <b>Create manual Order</b>.
2. Kies onder <b>Select Customer Client</b> de persoon naar wie u verstuurt, of druk op <b>Create new client here</b>. Druk op <b>Create Order</b>.
3. Voeg producten toe aan het mandje, controleer het adres en druk op <b>Continue to Checkout</b>. Dekt uw saldo de hele bestelling, dan toont het mandje in plaats daarvan <b>Place order</b>: druk erop en de bestelling wordt met uw saldo betaald.
4. Bij het afrekenen ziet u uw <b>Order number</b> en de samenvatting. Heeft u geld in uw saldo, dan wordt dit eerst gebruikt: u ziet hoeveel met saldo wordt betaald en <b>Please paid the rest with your preferred method below:</b>.
5. Kies onder <b>Online payments</b> hoe u de rest betaalt en volg de stappen. Uw bank kan u vragen de betaling in de app of met een code te bevestigen.
6. Na het betalen ziet u <b>Payment done. Waiting for confirmation...</b>. Zodra de betaling is bevestigd, wordt de bestelling naar ons magazijn gestuurd.

Dekt uw saldo de hele bestelling, dan is er geen betaalformulier: u ziet alleen <b>Place order</b>.

<!-- screenshot: de afrekenpagina met de bestelsamenvatting en het formulier Online payments met kaart, Apple Pay en PayPal -->

## Welke betaalmethoden u kunt gebruiken

Het formulier <b>Online payments</b> toont de methoden die werken voor uw land, valuta en apparaat. Klanten gebruiken:

- Debet- en creditcards
- Apple Pay (op Apple-apparaten) en Google Pay
- PayPal
- Klarna
- In sommige Europese landen: iDEAL, Przelewy24 en Bancontact

Ziet u een methode niet die u verwacht, dan is deze niet beschikbaar voor uw land, valuta of apparaat. Bankoverschrijving en rembours worden niet aangeboden bij het dropshipping-afrekenen.

## Een kaart opslaan voor automatische betalingen

Opgeslagen kaarten worden gebruikt om bestellingen uit uw gekoppelde winkels te betalen wanneer uw saldo niet toereikend is.

Het item <b>Saved Cards</b> verschijnt in het linkermenu zodra u een winkel heeft gekoppeld, een API-token heeft aangemaakt of een kaart heeft opgeslagen. Een kleine stip erop betekent dat u nog geen opgeslagen kaart heeft.

Om een kaart op te slaan:

1. Druk op <b>Saved Cards</b> in het linkermenu. De pagina heet <b>Credit Card Dashboard</b>.
2. Druk bovenaan op <b>Save Credit Card</b> (of <b>Add credit card</b> boven uw lijst met kaarten).
3. Voer uw kaartgegevens in. Uw bank zal u vragen te bevestigen. Dit is nodig zodat wij de kaart later zonder u kunnen belasten.
4. De kaart verschijnt in de lijst, die zijn <b>Card type</b>, <b>Expired</b>-status, <b>Last 4 digits</b> en <b>Added date</b> toont.

Alleen kaarten kunnen hier worden opgeslagen. Apple Pay, Google Pay en PayPal kunnen niet worden opgeslagen voor automatische betalingen.

<!-- screenshot: het Credit Card Dashboard met één opgeslagen kaart gemarkeerd als standaard en de knoppen Set as default en Unlink -->

## Meer dan één kaart

- De standaardkaart heeft een groen vinkje. Druk op <b>Set as default</b> bij een andere kaart om deze als eerste te gebruiken.
- Wanneer wij een bestelling moeten betalen, proberen wij eerst de standaardkaart, dan uw andere kaarten, één voor één, tot er één werkt.
- Om een kaart te verwijderen, drukt u op <b>Unlink</b> en bevestigt u.

Controleer de vervaldatum van uw kaarten. Verloopt een kaart, sla dan de nieuwe op en ontkoppel de oude.

## Als er iets misgaat

- <b>Something went wrong</b> / <b>Failed to communicate with the payment service.</b>: de betaling is niet gestart. Ververs de afrekenpagina en probeer het opnieuw, of kies een andere methode.
- <b>Payment still processing</b> / <b>Your order will be submitted automatically once the payment is confirmed.</b>: uw bank heeft nog niet bevestigd. Betaal niet opnieuw. Controleer de bestelling over een paar minuten.
- <b>Order already submitted</b> / <b>This order has already been submitted and cannot be paid again.</b>: de bestelling is al betaald. U wordt naar de bestelpagina gebracht.
- <b>Online payments are temporarily unavailable</b>: de betaaldienst reageert niet. Probeer het later opnieuw, of vul uw saldo aan en betaal ermee.
- <b>Insert file missing</b>: een bijsluiter in uw bestelling heeft geen bestand. Ga terug naar het mandje en upload het bestand vóór het afrekenen.
- <b>We cannot deliver to …</b> of <b>Your current billing address (…) is marked as forbidden</b>: wij kunnen geen betaling voor dit adres aannemen. Wijzig het adres, of vraag het ons in de chat op onze website.
- Een bestelling uit uw winkel toont <b>Unpaid</b> en u kreeg een mail dat deze on hold staat: uw saldo was niet toereikend en geen opgeslagen kaart werkte. Vul uw saldo aan en druk op <b>Pay … with balance</b> bij de bestelling. Zie [Saldo aanvullen en ermee betalen](/docs/topping-up-and-paying-with-balance).
- Uw kaart werd geweigerd bij een automatische betaling: uw bank heeft de afschrijving geweigerd. Controleer haar bij <b>Saved Cards</b> — ze kan geweigerd of verlopen zijn — vul dan uw saldo bij en betaal ermee, of sla een andere kaart op en stel deze in als standaard.
