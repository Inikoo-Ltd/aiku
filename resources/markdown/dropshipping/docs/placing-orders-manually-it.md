---
title: Inserire ordini manualmente
summary: Crea un ordine per un cliente su un canale Manual/API, aggiungi prodotti, scegli le opzioni di consegna, paga al checkout e segui l'ordine fino alla spedizione.
date: 2026-09-25
source_date: 2026-09-25
tags: manuale, ordini, carrello, checkout, pagamento, saldo, ritiro, spedizione
category: orders
series: manual
order: 4
shops: awd, dssk, dse
---

<aside class="tldr">
Apri il cliente nel tuo canale Manual/API e premi <b>Create Order</b>. Si apre un carrello: aggiungi prodotti con <b>Add products</b>, scegli le opzioni di consegna e premi <b>Continue to Checkout</b>. Usiamo prima il saldo del tuo account e paghi il resto con carta. Quando l'ordine è pagato passa al magazzino e lo segui sotto <b>Orders</b>.
</aside>

## Prima di iniziare

- Ti serve un canale Manual/API. Vedi [Il canale Manual/API](/docs/manual-and-api-channel).
- La persona a cui spedisci deve essere un cliente di quel canale. Vedi [Gestire i clienti](/docs/managing-clients).

## Creare l'ordine

1. Apri il tuo canale Manual/API e poi <b>Clients</b>.
2. Clicca sul nome del cliente. Si apre la pagina del cliente.
3. Premi <b>Create Order</b>.

Si apre il carrello per il nuovo ordine. In cima mostra il cliente, i suoi dati di contatto e l'indirizzo di consegna. Controlla nome e indirizzo prima di proseguire.

## Aggiungere prodotti

- <b>Add products</b> apre una finestra <b>Add products to Order</b>. Cerca per nome o codice del prodotto, digita la quantità e aggiungi i prodotti. Puoi scegliere qualsiasi prodotto che vendiamo, non solo quelli in <b>My Products</b>.
- <b>Upload products</b> aggiunge molti prodotti da un foglio di calcolo. Scarica il modello (.xlsx) nella finestra, compila le colonne <b>code</b> e <b>quantity</b>, e caricalo.

I prodotti compaiono nell'elenco. Puoi cambiare le quantità lì, oppure rimuovere una riga. Il peso stimato del pacco è mostrato accanto all'indirizzo.

<!-- screenshot: un carrello con cliente e indirizzo in cima, prodotti nell'elenco, le opzioni di consegna e il pulsante Continue to Checkout -->

## Scegliere le opzioni di consegna

- <b>Collection</b>: attivalo se tu, o un corriere che prenoti, ritirerete l'ordine dal nostro magazzino invece di spedirlo noi. Ha un costo aggiuntivo. Quando è disattivato, inviamo l'ordine all'indirizzo mostrato. Premi <b>Edit</b> sotto l'indirizzo per cambiarlo per questo ordine.
- Spedizione più veloce: su AW Dropship UK l'opzione si chiama <b>Same Day Dispatch</b>, su AW Dropship Europe <b>Premium Dispatch</b> e su AW Dropship España <b>Envío Premium</b>. Ha un costo aggiuntivo. Leggi l'icona informativa accanto per le condizioni.
- <b>Extra protective packing for fragile items</b> (solo AW Dropship UK): imballaggio extra per prodotti fragili. Ha un costo aggiuntivo.
- <b>Delivery Instructions</b>: una nota per il corriere. <b>This message will be printed in shipping label</b>, quindi scrivila per il corriere, non per noi.
- <b>Other Instructions</b>: una nota per il nostro team.

I costi e il totale dell'ordine si aggiornano quando attivi un'opzione.

Questi sono i costi aggiuntivi su questo sito:

{order_charges}

## Pagare

Se il saldo del tuo account copre l'intero ordine, il carrello mostra <b>Place order</b> invece di <b>Continue to Checkout</b>. Premilo e l'ordine viene pagato dal tuo saldo. La nota dice <b>This is your final confirmation. You can pay totally with your current balance.</b>

Altrimenti:

1. Premi <b>Continue to Checkout</b>.
2. Il checkout mostra il numero d'ordine. Se hai del saldo, ti dice quanto viene pagato con il saldo, e ti chiede di pagare il resto.
3. In <b>Online payments</b>, inserisci i dati della tua carta e conferma. La tua banca potrebbe chiederti di approvare il pagamento nella sua app o con un codice.
4. Quando il pagamento è completato, la pagina dice <b>Payment done. Waiting for confirmation...</b> e poi apre l'ordine.

Premi <b>Back to basket</b> nella pagina di checkout per cambiare l'ordine prima di pagare.

## Ordini non completati: Baskets

Un ordine che hai creato ma non hai pagato resta in <b>Baskets</b> sotto il tuo canale. Il numero accanto a <b>Baskets</b> nel menu mostra quanti ne hai. Aprine uno per completarlo, oppure premi <b>Delete</b> sulla sua riga (tooltip <b>Delete basket</b>) per rimuoverlo. Un carrello non viene inviato al magazzino finché non è pagato.

## Seguire i tuoi ordini

Apri <b>Orders</b> sotto il tuo canale. L'elenco mostra <b>Status</b>, <b>Reference</b>, cliente, <b>Date</b>, articoli e totale. Clicca su un ordine per vedere i suoi prodotti, le note di consegna, le spedizioni con i link di tracking e le fatture.

L'icona di stato indica a che punto è l'ordine. Passaci sopra con il mouse per vederne il nome:

- <b>Submitted</b>: abbiamo ricevuto l'ordine.
- <b>In Warehouse</b>, <b>Picking</b>, <b>Picked</b>, <b>Packing</b>, <b>Packed</b>: il nostro team lo sta preparando.
- <b>Waiting</b>: è in sospeso nel magazzino.
- <b>Finalized</b>: pronto a partire.
- <b>Dispatched</b>: spedito. Il link di tracking è sull'ordine.
- <b>Cancelled</b>: l'ordine è stato annullato.

Le icone in cima a un ordine mostrano le opzioni scelte: una stella per <b>Premium dispatch</b>, una scatola per <b>Extra packing</b>.

Se non possiamo spedire alcuni articoli, l'ordine mostra <b>Some items are not being sent</b>. I soldi per quegli articoli vengono rimborsati automaticamente.

La pagina dell'ordine elenca le sue fatture con un pulsante di download. Tutte le tue fatture sono anche sotto <b>Invoices</b> nel menu.

## Quando qualcosa non funziona

- <b>Non vedo Create Order nella pagina del cliente.</b> Il pulsante è solo sui clienti di un canale Manual/API. Sui canali connessi, gli ordini arrivano dal tuo negozio.
- <b>Il carrello dice "We cannot deliver to …".</b> Non spediamo in quel paese. Cambia l'indirizzo di consegna, oppure attiva <b>Collection</b> se organizzi tu il trasporto.
- <b>Il carrello dice che il tuo indirizzo di fatturazione è segnato come vietato.</b> Aggiorna l'indirizzo di fatturazione nel tuo account, oppure chiedici nella chat del nostro sito.
- <b>Continue to Checkout è disattivato e mi chiede di caricare un file.</b> Hai scelto un inserto stampato che richiede la tua grafica. Carica il file, oppure rimuovi l'inserto, prima di procedere al checkout.
- <b>Il pagamento con carta è fallito.</b> Il checkout dice <b>Something went wrong</b>. Controlla i dati della carta e che la tua banca abbia approvato il pagamento, poi riprova. Puoi anche ricaricare il tuo saldo e pagare con quello.
- <b>Il checkout dice "Payment still processing".</b> Non pagare di nuovo. L'ordine viene inviato automaticamente una volta confermato il pagamento.
- <b>Il checkout dice "Order already submitted".</b> L'ordine è già pagato. Aprilo sotto <b>Orders</b>.
- <b>Il mio ordine mostra Unpaid.</b> Il pagamento non ha coperto l'ordine. Ricarica il tuo saldo con <b>Top Up</b>, apri l'ordine e premi <b>Pay … with balance</b>. Il pulsante compare quando il tuo saldo copre l'importo dovuto. L'ordine passa quindi al magazzino.
- <b>Devo cambiare o annullare un ordine che ho già pagato.</b> Non c'è un pulsante di annullamento. Chiedici nella chat del nostro sito il prima possibile. Possiamo annullarlo o cambiarlo solo prima che sia spedito. Una volta imballato potrebbe essere troppo tardi.
