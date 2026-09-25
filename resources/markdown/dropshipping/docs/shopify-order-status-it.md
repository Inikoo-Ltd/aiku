---
title: I tuoi ordini Shopify e il loro stato
summary: Come gli ordini dal tuo negozio Shopify arrivano a noi, come vengono pagati, cosa significa ogni stato, perché un ordine può restare in Submitted o non arrivare affatto, e cosa rimandiamo a Shopify.
date: 2026-09-25
source_date: 2026-09-25
tags: shopify, ordini, stato, pagamento, submitted, non pagato, richiesta di evasione
category: orders
series: shopify
order: 4
shops: awd, dssk, dse
---

<aside class="tldr">
Quando un cliente acquista un prodotto collegato nel tuo negozio Shopify, Shopify ci invia una richiesta di evasione e l'ordine compare in <b>Orders</b> del tuo canale. Lo paghiamo dal tuo saldo, poi dalla tua carta salvata. Un ordine pagato passa da solo al nostro magazzino. Un ordine che non siamo riusciti a pagare resta <b>Submitted</b> e <b>Unpaid</b> finché non lo paghi. Quando lo spediamo, lo segniamo evaso in Shopify con il numero di tracking.
</aside>

## Come un ordine arriva a noi

1. Un cliente acquista uno dei tuoi prodotti collegati in Shopify.
2. Shopify invia una richiesta di evasione per quegli articoli alla sede <b>aiku-</b>.
3. Accettiamo la richiesta e creiamo l'ordine nel tuo canale. Lo trovi sotto il tuo canale, <b>Orders</b>.

Possono arrivare a noi solo i prodotti collegati in <b>My Products</b>. Se un ordine contiene alcuni dei nostri prodotti e alcuni tuoi, accettiamo i nostri e tu spedisci il resto tu stesso.

## Come viene pagato l'ordine

Proviamo a pagare subito ogni nuovo ordine:

1. Prima con il tuo saldo.
2. Se il saldo non basta, con le carte salvate in <b>Saved Cards</b>, nel tuo ordine di priorità.

Se il pagamento funziona, l'ordine passa da solo al nostro magazzino. Se non funziona, l'ordine resta in attesa e ti inviamo un'email che dice che è in sospeso. Il più delle volte succede perché non c'è nessuna carta salvata. Per evitare che succeda di nuovo, salva una carta: vedi [Carte e opzioni di pagamento](/docs/payment-cards-and-options).

## Pagare un ordine in attesa

Un ordine che non siamo riusciti a pagare mostra <b>Unpaid</b> accanto al suo numero e resta <b>Submitted</b>.

1. Ricarica il tuo saldo con almeno l'importo dovuto, da <b>Top Up</b> nel menu.
2. Apri il tuo canale, <b>Orders</b>, e apri l'ordine.
3. Premi <b>Pay ... with balance</b>. Il pulsante compare solo quando il tuo saldo copre l'importo dovuto.

L'ordine passa quindi da solo al nostro magazzino.

<!-- screenshot: un ordine non pagato con l'etichetta Unpaid e il pulsante Pay with balance -->

## Cosa significa ogni stato

- <b>Submitted</b>: abbiamo l'ordine. Se mostra anche <b>Unpaid</b>, aspetta il tuo pagamento.
- <b>In Warehouse</b>: pagato e in attesa di essere prelevato.
- <b>Handling</b>: in fase di prelievo.
- <b>Waiting</b>: il magazzino ha dovuto fermare l'ordine per un momento prima di poter proseguire.
- <b>Picked</b>, <b>Packing</b>, <b>Packed</b>: il pacco è in preparazione.
- <b>Finalized</b>: fatturato e pronto a partire.
- <b>Dispatched</b>: spedito. Se alcuni articoli non potevano essere spediti, vedi <b>Modified</b> e i relativi soldi vengono rimborsati automaticamente.
- <b>Cancelled</b>: l'ordine non verrà spedito. Il motivo è mostrato in cima all'ordine.

Per saperne di più sulla pagina dell'ordine, vedi [Controllare i tuoi ordini](/docs/reviewing-orders).

## Cosa rimandiamo a Shopify

- Quando l'ordine viene spedito, lo segniamo evaso in Shopify con il numero e il link di tracking. Shopify lo comunica al tuo cliente.
- Quando un ordine viene annullato, chiudiamo la richiesta in Shopify. Su un ordine annullato puoi premere il pulsante di sincronizzazione (<b>Sync order state</b>) per inviare di nuovo l'annullamento a Shopify. Se Shopify è già aggiornato vedi <b>The order state on Shopify is up-to-date</b>.

## Un ordine non è nei miei Orders

Apri il canale e premi <b>Fetch orders</b>. <b>Controlla Shopify per gli ordini che non ci sono ancora arrivati</b>: guarda gli ordini recenti non evasi e importa quelli per la nostra sede. Se non c'è nulla di nuovo vedi <b>No new orders</b>. Puoi premerlo di nuovo dopo un paio di minuti.

Se l'ordine ancora non arriva, controlla questi punti:

- I prodotti sono collegati (stretta di mano verde) in <b>My Products</b>.
- Gli articoli hanno stock nella sede <b>aiku-</b> in Shopify, e la sede è nel tuo profilo di spedizione. Vedi [La sede di evasione AW in Shopify](/docs/shopify-fulfilment-location).
- L'ordine non era già evaso in Shopify, o inviato a un'altra sede.

## Quando qualcosa non funziona

**Un ordine annullato dice "Fulfilment request declined: The items can't be fulfilled because you don't have the items in your portfolio."** Nessuno dei prodotti dell'ordine è collegato in <b>My Products</b>. Aggiungili e collegali, poi richiedi di nuovo l'evasione in Shopify.

**Un ordine annullato dice "Fulfilment request declined: Order don't have shipping information".** L'ordine in Shopify non ha un indirizzo di consegna. Aggiungi l'indirizzo in Shopify e richiedi di nuovo l'evasione.

**La richiesta di evasione è stata accettata in Shopify, ma non riesco a vedere l'ordine da pagare.** Apri <b>Orders</b> nel canale: è lì che sono elencati gli ordini da Shopify. Cerca l'ordine con <b>Unpaid</b>, oppure premi <b>Fetch orders</b>.

**Il mio ordine di prova su Shopify non è arrivato.** Un ordine di prova arriva a noi solo se contiene prodotti collegati con stock nella sede <b>aiku-</b>. Attenzione: un ordine che ci arriva è un ordine vero. Lo paghiamo e lo spediamo. Se ne hai fatto uno per sbaglio, chiedici rapidamente nella chat del nostro sito di annullarlo. Possiamo annullarlo solo prima che sia spedito.

**L'ordine resta Submitted e Unpaid.** Non c'era abbastanza saldo e nessuna carta ha funzionato. Pagalo come mostrato in <b>Pagare un ordine in attesa</b>, e salva una carta per i prossimi.

**L'ordine dice "We cannot deliver to ...".** Non spediamo in quel paese da questo sito. L'ordine non è pagato e non viene spedito. Aggiorna l'indirizzo di consegna, oppure chiedici nella chat del nostro sito.

**L'ordine è pagato ma non si muove da molto tempo.** Chiedici nella chat del nostro sito, indicando il numero d'ordine. Il numero d'ordine è il <b>Reference</b> in <b>Orders</b>.

<aside class="wayfinder"><strong>Dove cliccare</strong>
<ul>
<li><b>Vedere i tuoi ordini Shopify:</b> <b>Channels</b> → il tuo negozio Shopify → <b>Orders</b>.</li>
<li><b>Pagare un ordine in attesa:</b> apri l'ordine → <b>Pay ... with balance</b>.</li>
<li><b>Importare un ordine mancante:</b> apri il canale → <b>Fetch orders</b>.</li>
<li><b>Salvare una carta:</b> <b>Saved Cards</b> nel menu.</li>
</ul>
</aside>
