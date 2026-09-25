---
title: Carte e opzioni di pagamento
summary: Come paghi gli ordini, quali metodi di pagamento offre il checkout, e come salvare una carta perché gli ordini dai tuoi negozi collegati vengano pagati automaticamente.
date: 2026-09-25
source_date: 2026-09-25
tags: pagamento, carta, carte salvate, checkout, paypal, apple pay, google pay, pagamento automatico
category: payments
shops: awd, dssk, dse
---

<aside class="tldr">
Il tuo saldo viene sempre usato per primo. Quello che il saldo non copre lo paghi al checkout sotto <b>Online payments</b>: carta, Apple Pay, Google Pay, PayPal e altri metodi, a seconda del tuo paese e dispositivo. Gli ordini che arrivano dai tuoi negozi collegati vengono pagati senza di te: prima dal tuo saldo, poi da una carta che hai salvato in <b>Saved Cards</b>. Salva una carta, oppure tieni il saldo ricaricato, così questi ordini non restano non pagati.
</aside>

## Due modi in cui gli ordini vengono pagati

- <b>Ordini che crei tu stesso</b> (ordini manuali): paghi al checkout mentre guardi.
- <b>Ordini dai tuoi negozi collegati</b> (Shopify, WooCommerce, eBay, TikTok e gli altri, o tramite l'API): nessuno è al checkout, quindi prendiamo noi i soldi. Usiamo prima il tuo saldo, poi le tue carte salvate. Vedi [Ricaricare e pagare con il saldo](/docs/topping-up-and-paying-with-balance).

## Pagare al checkout

1. Sulla <b>Dashboard</b>, sotto <b>Quick links (Shortcuts)</b>, premi <b>Create manual Order</b>.
2. Sotto <b>Select Customer Client</b>, scegli la persona a cui invii, oppure premi <b>Create new client here</b>. Premi <b>Create Order</b>.
3. Aggiungi prodotti al carrello, controlla l'indirizzo, e premi <b>Continue to Checkout</b>. Se il tuo saldo copre l'intero ordine, il carrello mostra invece <b>Place order</b>: premilo e l'ordine viene pagato dal tuo saldo.
4. Il checkout mostra il tuo <b>Order number</b> e il riepilogo. Se hai soldi nel saldo, vengono usati per primi: vedi quanto verrà pagato con il saldo e <b>Please paid the rest with your preferred method below:</b>.
5. Sotto <b>Online payments</b>, scegli come pagare il resto e segui i passaggi. La tua banca potrebbe chiederti di confermare il pagamento nella sua app o con un codice.
6. Dopo aver pagato, vedi <b>Payment done. Waiting for confirmation...</b>. Quando il pagamento è confermato, l'ordine viene inviato al nostro magazzino.

Se il tuo saldo copre l'intero ordine, non c'è nessun modulo di pagamento: vedi solo <b>Place order</b>.

<!-- screenshot: la pagina di checkout con il riepilogo dell'ordine e il modulo Online payments che mostra carta, Apple Pay e PayPal -->

## Quali metodi di pagamento puoi usare

Il modulo <b>Online payments</b> mostra i metodi disponibili per il tuo paese, la tua valuta e il tuo dispositivo. I clienti usano:

- Carte di debito e di credito
- Apple Pay (su dispositivi Apple) e Google Pay
- PayPal
- Klarna
- In alcuni paesi europei: iDEAL, Przelewy24 e Bancontact

Se non vedi un metodo che ti aspetti, non è disponibile per il tuo paese, la tua valuta o il tuo dispositivo. Il bonifico bancario e il contrassegno non sono offerti al checkout del dropshipping.

## Salvare una carta per i pagamenti automatici

Le carte salvate vengono usate per pagare gli ordini dai tuoi negozi collegati quando il tuo saldo non basta.

La voce <b>Saved Cards</b> compare nel menu a sinistra una volta che hai collegato un negozio, creato un token API o salvato una carta. Un piccolo punto su di essa significa che non hai ancora nessuna carta salvata.

Per salvare una carta:

1. Premi <b>Saved Cards</b> nel menu a sinistra. La pagina si chiama <b>Credit Card Dashboard</b>.
2. Premi <b>Save Credit Card</b> in cima (oppure <b>Add credit card</b> sopra il tuo elenco carte).
3. Inserisci i dati della tua carta. La tua banca ti chiederà di confermare. Serve per poter addebitare la carta in seguito senza di te.
4. La carta compare nell'elenco, che mostra il suo <b>Card type</b>, lo stato <b>Expired</b>, le <b>Last 4 digits</b> e la <b>Added date</b>.

Qui possono essere salvate solo le carte. Apple Pay, Google Pay e PayPal non possono essere salvati per i pagamenti automatici.

<!-- screenshot: il Credit Card Dashboard con una carta salvata segnata come predefinita e i pulsanti Set as default e Unlink -->

## Più di una carta

- La carta predefinita ha un segno di spunta verde. Premi <b>Set as default</b> su un'altra carta per usarla per prima.
- Quando dobbiamo pagare un ordine, proviamo prima la carta predefinita, poi le tue altre carte, una per una, finché una funziona.
- Per rimuovere una carta, premi <b>Unlink</b> e conferma.

Controlla la data di scadenza delle tue carte. Quando una carta scade, salva quella nuova e scollega quella vecchia.

## Quando qualcosa non funziona

- <b>Something went wrong</b> / <b>Failed to communicate with the payment service.</b>: il pagamento non è partito. Aggiorna la pagina di checkout e riprova, oppure scegli un altro metodo.
- <b>Payment still processing</b> / <b>Your order will be submitted automatically once the payment is confirmed.</b>: la tua banca non ha ancora confermato. Non pagare di nuovo. Controlla l'ordine tra qualche minuto.
- <b>Order already submitted</b> / <b>This order has already been submitted and cannot be paid again.</b>: l'ordine è già pagato. Vieni portato alla pagina dell'ordine.
- <b>Online payments are temporarily unavailable</b>: il servizio di pagamento non risponde. Riprova più tardi, oppure ricarica il tuo saldo e paga con quello.
- <b>Insert file missing</b>: un inserto nel tuo ordine non ha un file. Torna al carrello e carica il file prima del checkout.
- <b>We cannot deliver to …</b> oppure <b>Your current billing address (…) is marked as forbidden</b>: non possiamo prelevare il pagamento per questo indirizzo. Cambia l'indirizzo, oppure chiedici nella chat del nostro sito.
- Un ordine dal tuo negozio mostra <b>Unpaid</b> e hai ricevuto un'email che dice che è in sospeso: il tuo saldo non bastava e nessuna carta salvata ha funzionato. Ricarica il tuo saldo e premi <b>Pay … with balance</b> sull'ordine. Vedi [Ricaricare e pagare con il saldo](/docs/topping-up-and-paying-with-balance).
- La tua carta è stata rifiutata per un pagamento automatico: la tua banca ha rifiutato l'addebito. Controllala in <b>Saved Cards</b> — potrebbe essere rifiutata o scaduta — poi ricarica il tuo saldo e paga con quello, oppure salva un'altra carta e impostala come predefinita.
