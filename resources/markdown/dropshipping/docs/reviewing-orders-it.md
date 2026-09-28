---
title: Controllare i tuoi ordini
summary: Trova gli ordini di ogni canale di vendita, leggi il loro stato, vedi cosa è stato spedito e cosa hai pagato, e capisci perché un ordine è non pagato, annullato o non compare affatto.
date: 2026-09-25
source_date: 2026-09-25
tags: ordini, stato ordine, non pagato, annullato, elenco ordini
category: orders
shops: awd, dssk, dse
---

<aside class="tldr">
Gli ordini sono conservati per ogni canale di vendita. Nel menu a sinistra apri il tuo canale e clicca <b>Orders</b>. Ogni ordine mostra il suo stato, e un'etichetta rossa <b>Unpaid</b> quando non siamo ancora riusciti a prelevare i soldi. Un ordine non pagato resta in attesa e non viene inviato al magazzino finché non è pagato. Clicca sul riferimento dell'ordine per vedere i prodotti, l'indirizzo di consegna, il numero di tracking e la fattura.
</aside>

## Dove sono i tuoi ordini

Ogni canale che hai collegato (Shopify, eBay, TikTok, WooCommerce e gli altri, e il tuo canale manuale) ha il proprio elenco di ordini.

1. Nel menu a sinistra, trova il tuo canale sotto <b>Channels</b>.
2. Clicca <b>Orders</b> sotto di esso.

L'elenco ha queste colonne: <b>Status</b>, <b>Reference</b>, <b>Client</b>, <b>Date</b>, <b>Items</b> e <b>Total</b>. Gli ordini più recenti sono in cima. Usa il campo di ricerca per trovare un ordine tramite il suo riferimento.

Il <b>Reference</b> è il nostro numero d'ordine. Non è il numero d'ordine nel tuo negozio e non è un numero di tracking. Per trovare il numero di tracking, vedi [Trovare il numero di tracking di un ordine](/docs/tracking-numbers).

Su un canale Manual/API, gli ordini che stai ancora preparando non sono in questo elenco. Sono in <b>Baskets</b>, sotto lo stesso canale, finché non li inserisci.

Per scaricare l'elenco, usa il pulsante di esportazione in cima alla pagina e scegli <b>Excel</b> o <b>CSV</b>.

<!-- screenshot: l'elenco Orders di un canale, con la colonna Status, un riferimento con l'etichetta rossa Unpaid e il pulsante di esportazione -->

## Cosa significa ogni stato

- <b>Submitted</b>: abbiamo l'ordine. Se non è pagato, resta qui finché non viene pagato.
- <b>In Warehouse</b>: l'ordine è pagato e in attesa di essere prelevato.
- <b>Picking</b>: il magazzino sta prelevando i prodotti.
- <b>Waiting</b>: il prelievo è in pausa, per esempio mentre il magazzino controlla un prodotto.
- <b>Picked</b>, <b>Packing</b>, <b>Packed</b>: il pacco è in preparazione.
- <b>Finalized</b>: l'ordine è fatturato e pronto a partire.
- <b>Dispatched</b>: il pacco ha lasciato il nostro magazzino. Il numero di tracking è sull'ordine.
- <b>Cancelled</b>: l'ordine non verrà spedito.

Accanto al riferimento potresti anche vedere piccole icone per <b>Premium dispatch</b>, <b>Extra packing</b> e <b>Insurance</b> quando le hai scelte per quell'ordine.

## Ordini non pagati

Un'etichetta rossa <b>Unpaid</b> significa che non siamo ancora riusciti a prelevare l'intero importo. L'ordine resta <b>Submitted</b> e non viene inviato al magazzino.

Quando un ordine arriva dal tuo negozio, lo paghiamo così:

1. Prima con il tuo saldo.
2. Se il saldo non basta, con le tue carte salvate, a partire dalla tua carta predefinita.

Se nessuno dei due funziona, l'ordine resta in attesa e ti inviamo un'email che dice che è in sospeso. Il più delle volte succede perché non c'è nessuna carta salvata per il canale. Per evitare che succeda di nuovo, salva una carta: vedi [Pagare i tuoi ordini](/docs/topping-up-and-paying-with-balance).

Per pagare un ordine in attesa:

1. Ricarica il tuo saldo con almeno l'importo dovuto. Vedi [Pagare i tuoi ordini](/docs/topping-up-and-paying-with-balance).
2. Apri di nuovo l'ordine. Un riquadro giallo dice <b>Order ... is not paid yet</b> e mostra <b>Your balance</b>.
3. Clicca il pulsante <b>Pay ... with balance</b>. Mostra l'importo dovuto.

L'ordine passa quindi al magazzino. Il pulsante compare solo quando il tuo saldo copre l'intero importo dovuto, mentre l'ordine è <b>Submitted</b> o <b>Picking</b>.

<b>Nota:</b> non riproviamo la tua carta da soli. Un ordine in attesa resta in attesa finché non lo paghi.

## Dentro un ordine

Clicca sul riferimento dell'ordine per aprirlo. Vedi:

- In cima, una linea temporale con i passaggi che l'ordine ha superato, e un'etichetta <b>Paid</b> o <b>Unpaid</b>.
- Il tuo cliente: nome, email, telefono e indirizzo di consegna.
- <b>Weight</b>: il peso stimato di tutti i prodotti.
- <b>Delivery Notes</b>: i pacchi, il loro stato, e sotto <b>Shipments</b> il corriere e il numero di tracking. L'icona PDF (<b>Download Picking List</b>) scarica l'elenco dei prodotti nel pacco.
- <b>Invoices</b>: la nostra fattura per l'ordine, da aprire o scaricare come PDF. Vedi [Le tue fatture](/docs/invoices).
- Il riepilogo prezzi: <b>Items</b>, costi, <b>Net</b>, tasse e <b>Total</b>.

Questi sono i costi aggiuntivi su questo sito:

{order_charges}

- La scheda <b>Transactions</b>: ogni prodotto con la sua <b>Quantity</b>. Quando ne sono stati spediti meno di quelli ordinati, la quantità spedita è mostrata in rosso sopra la quantità ordinata, che è barrata.
- <b>Notes from Staff</b>, <b>Delivery Instructions</b> e <b>Other Instructions</b>. Le istruzioni di consegna sono stampate sull'etichetta di spedizione.

<!-- screenshot: una pagina d'ordine che mostra la linea temporale, il riquadro Delivery Notes con un numero di tracking e il riquadro Invoices -->

## Quando non tutto è stato spedito

A volte non possiamo spedire ogni prodotto, per esempio quando uno si esaurisce mentre prepariamo l'ordine. La pagina dell'ordine mostra allora <b>Dispatched | Modified</b>, e nell'elenco compare un'icona di avviso gialla accanto allo stato. La scheda <b>Transactions</b> mostra quali prodotti non sono stati spediti. I soldi per i prodotti non spediti tornano al tuo saldo da soli quando l'ordine viene fatturato.

## Ordini annullati

Un ordine annullato mostra il suo stato <b>Cancelled</b> in cima, e un riquadro rosso <b>Order cancelled</b> quando è stato registrato un motivo. I soldi già pagati per esso tornano al tuo saldo.

Per un ordine Shopify c'è anche un pulsante di sincronizzazione (tooltip <b>Sync order state</b>) in cima. Cliccalo per dire a Shopify che l'ordine è stato annullato. Se vedi <b>The order state on Shopify is up-to-date</b>, Shopify lo sa già.

Non c'è un pulsante di annullamento. Per annullare un ordine, chiedici nella chat del nostro sito con il riferimento dell'ordine. Possiamo annullarlo solo prima che sia spedito. Una volta imballato l'ordine potrebbe essere troppo tardi.

## Lasciare una recensione

Su alcuni dei nostri siti, un po' di tempo dopo che un ordine è stato spedito, compare un pulsante <b>Review</b> in cima all'ordine. Usalo per valutare l'ordine e i prodotti.

## Quando qualcosa non funziona

**Un ordine dal mio negozio non è nell'elenco.** Controlla questi punti, in quest'ordine:

- Arrivano solo i prodotti presenti in <b>My Products</b> di quel canale. Se nessuno dei prodotti dell'ordine è in <b>My Products</b>, l'ordine non compare in <b>Orders</b>, perché non c'è nulla che possiamo spedire.
- Controlla di stare guardando il canale giusto. Ogni canale ha il proprio elenco.
- Il canale deve essere ancora connesso. Se la pagina del canale dice che non è connesso, ricollegalo prima.
- **Shopify**: arrivano solo gli ordini che Shopify invia alla nostra sede di evasione, e arrivano come richiesta di evasione. Se un prodotto dell'ordine non è in <b>My Products</b>, quella parte della richiesta viene rifiutata in Shopify e il resto arriva. Quando l'intera richiesta viene rifiutata (nessuno dei prodotti è in <b>My Products</b>, oppure l'ordine non ha un indirizzo di spedizione), l'ordine compare in <b>Orders</b> come <b>Cancelled</b>, con il motivo sotto <b>Notes from Staff</b>. Correggi l'ordine in Shopify e richiedi di nuovo l'evasione. Un ordine evaso dal tuo stesso negozio, già evaso, o la cui richiesta è stata annullata in Shopify non arriverà. Questo è il motivo più comune per cui un ordine di prova non arriva: controlla in Shopify che i suoi prodotti abbiano stock nella nostra sede.

**La mia richiesta di evasione Shopify è stata accettata ma non riesco a vedere l'ordine da pagare.** Guarda in <b>Orders</b> del canale Shopify il suo stato e un'etichetta rossa <b>Unpaid</b>. Se non c'è, chiedici nella chat del nostro sito con il numero d'ordine Shopify.

**L'ordine è Submitted da molto tempo.** Quasi sempre è non pagato. Segui i passaggi in "Ordini non pagati" sopra.

**L'ordine dice "We cannot deliver to ...".** Non spediamo in quel paese da questo sito. Vedi [Paesi in cui non possiamo consegnare](/docs/delivery-restrictions).

**Un prodotto è arrivato rotto, o il mio acquirente vuole restituire qualcosa.** Chiedici nella chat del nostro sito con il riferimento dell'ordine e delle foto. Il tuo acquirente non deve rispedire nulla finché non è stato concordato con noi nella chat. I rimborsi vanno sul tuo saldo.
