---
title: Paesi in cui non possiamo consegnare
summary: Cosa significa il messaggio "We cannot deliver to" su un carrello o un ordine, e come risolvere i checkout Shopify che rifiutano di spedire i nostri prodotti verso un paese.
date: 2026-09-25
source_date: 2026-09-25
tags: consegna, paesi, spedizione, shopify, profilo di spedizione, indirizzo vietato
category: orders
shops: awd, dssk, dse
---

<aside class="tldr">
Ogni nostro sito ha un elenco di paesi in cui non consegna. Quando l'indirizzo di consegna di un ordine è in uno di essi, vedi <b>We cannot deliver to ...</b>, non puoi pagare, e l'ordine non passa al magazzino. Cambia l'indirizzo mentre l'ordine è ancora un carrello, oppure chiedici nella chat del nostro sito. Un problema diverso è un checkout Shopify che non spedirà i nostri prodotti verso un paese: quella è un'impostazione di spedizione nel tuo negozio Shopify.
</aside>

## "We cannot deliver to ..." sul tuo carrello o ordine

Se l'indirizzo di consegna è in un paese in cui il sito non consegna, vedi questo in rosso:

<b>We cannot deliver to (country). Please update the address or contact support.</b>

Cosa succede poi:

- In un carrello, i pulsanti <b>Continue to Checkout</b> e <b>Place order</b> sono nascosti.
- Un ordine che arriva dal tuo negozio non viene pagato e resta <b>Submitted</b>. Non viene inviato al magazzino.
- Sulla pagina dell'ordine, il riquadro giallo che chiede di ricaricare e il pulsante <b>Pay ... with balance</b> sono nascosti, perché l'ordine non può essere spedito. L'ordine mostra comunque <b>Unpaid</b>.

Cosa fare:

- **Carrello (canale Manual/API)**: clicca <b>Edit</b> accanto all'indirizzo di consegna e cambialo, se l'indirizzo era sbagliato.
- **Ordine dal tuo negozio**: non puoi cambiare l'indirizzo sull'ordine. Chiedici nella chat del nostro sito con il riferimento dell'ordine.

Alcuni paesi sono bloccati solo per parte del territorio, per codice postale. Viene mostrato lo stesso messaggio.

L'elenco è diverso per ogni sito. Questo sito non consegna in questi paesi:

{blocked_delivery_countries}

## "Your current billing address is marked as forbidden"

Questo messaggio riguarda il tuo indirizzo di fatturazione, non quello del tuo acquirente. Aggiorna l'indirizzo nel tuo account, oppure chiedici nella chat del nostro sito.

## Shopify: "unable to deliver" al checkout del tuo negozio

Questo succede nel tuo negozio Shopify, prima che l'ordine arrivi a noi. Shopify blocca il checkout quando non ha una tariffa di spedizione dalla sede del prodotto al paese dell'acquirente. I prodotti che hai creato tu stesso potrebbero comunque funzionare, perché usano una sede diversa.

I nostri prodotti hanno stock in Shopify nella nostra sede di evasione. Il suo nome è <b>aiku-</b> seguito dal codice del sito, poi il codice del tuo canale tra parentesi, per esempio <b>aiku-awd (my-store)</b>. Vedi [La sede di evasione AW in Shopify](/docs/shopify-fulfilment-location). Controlla queste impostazioni nel tuo pannello Shopify:

1. **Locations** (Settings → Locations): la nostra sede deve essere attiva. Rimuovi le sedi dropshipping vecchie o duplicate che non usi più.
2. **Shipping profile** (Settings → Shipping and delivery): apri il profilo che contiene i nostri prodotti e controlla che la nostra sede sia inclusa.
3. **Zones and rates**: in quel profilo, il paese dell'acquirente deve essere in una zona di spedizione, e la zona ha bisogno di almeno una tariffa (a pagamento o gratuita).
4. **Product**: apri il prodotto che fallisce e controlla quale profilo di spedizione usa. Spostalo nel profilo del passo 2 se necessario.

<!-- screenshot: profilo di spedizione Shopify con la sede aiku- e una zona che contiene il paese dell'acquirente -->

Se tutti e quattro i punti sono corretti e il checkout fallisce ancora, contatta l'assistenza Shopify. Le zone e le tariffe di spedizione sono impostate nel tuo negozio, quindi non possiamo cambiarle per te.

Anche quando Shopify consente il checkout, possiamo spedire l'ordine solo se il paese non è nel nostro elenco sopra.

## Quando qualcosa non funziona

**Il mio ordine è Submitted da giorni e non c'è il pulsante per pagare.** Apri l'ordine. Se vedi <b>We cannot deliver to ...</b>, il paese è bloccato. Chiedici nella chat del nostro sito con il riferimento dell'ordine.

**L'acquirente ha indicato un paese sbagliato per errore.** Chiedici nella chat del nostro sito con il riferimento dell'ordine e l'indirizzo corretto.
