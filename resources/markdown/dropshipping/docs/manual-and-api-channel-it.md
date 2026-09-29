---
title: Il canale Manual/API
summary: Crea un canale Manual/API per vendere dal tuo sito, marketplace o app, inserire ordini a mano o inviarceli tramite la nostra API.
date: 2026-09-25
source_date: 2026-09-25
tags: manuale, api, canale di vendita, sito personale, token api, integrazione
category: sales-channels
series: manual
order: 1
shops: awd, dssk, dse
---

<aside class="tldr">
Usa un canale <b>Manual/API</b> quando il tuo negozio non è su una delle piattaforme a cui ci colleghiamo, oppure quando vuoi digitare gli ordini tu stesso. Vai su <b>Channels</b>, premi <b>Add Sales Channel</b>, poi <b>Create</b> sulla card <b>Manual/API</b> e dagli un nome. Poi aggiungi prodotti a <b>My Products</b>, aggiungi i tuoi acquirenti come <b>Clients</b> e crei ordini per loro, a mano o tramite l'API.
</aside>

## Quando usare un canale Manual/API

Un canale Manual/API non è collegato a nessun negozio. Non viene caricato nulla su un sito e nessun ordine arriva da solo. Usalo quando:

- Vendi sul tuo sito, su un marketplace a cui non ci colleghiamo, sui social o per telefono, e vuoi inviarci ogni ordine tu stesso.
- Hai un tuo sistema o sviluppatore e vuoi inviarci ordini tramite la nostra API.

Se il tuo negozio è su una piattaforma mostrata nella pagina <b>Add Sales Channel</b>, come Shopify, WooCommerce, eBay o TikTok Shop, collega invece quella piattaforma. Così i prodotti vengono caricati per te e gli ordini arrivano da soli.

Puoi avere più di un canale Manual/API, per esempio uno per ogni sito.

## Creare il canale

1. Apri <b>Channels</b> nel menu. Vedi l'elenco dei tuoi <b>Sales Channels</b>.
2. Premi <b>Add Sales Channel</b>. La pagina mostra <b>Select channel you want to create</b>.
3. Sulla card <b>Manual/API</b> premi <b>Create</b>.
4. Si apre una finestra <b>Create platform manual</b>. Digita un nome per il canale, per esempio il nome del tuo sito. Il nome può avere fino a 28 caratteri.
5. Premi <b>Create</b>.

<!-- screenshot: la pagina Add Sales Channel con la card Manual/API e il suo pulsante Create, e la finestra Create platform manual -->

Vedi il messaggio <b>Your Manual store has been created.</b> e si apre la pagina del canale.

Ogni tuo canale ha bisogno del proprio nome. Se il nome è già usato da un altro dei tuoi canali, la finestra mostra un errore. Scegli un nome diverso.

## La pagina del tuo canale

La pagina del canale ha il nome del tuo canale come titolo e l'intestazione <b>Manual/API order management</b>. Mostra tre riquadri, ciascuno con un link <b>View all</b>:

- <b>Orders</b>: gli ordini che hai inserito in questo canale.
- <b>Clients</b>: le persone a cui invii ordini.
- <b>Products</b>: i prodotti nel tuo elenco <b>My Products</b>.

Nel menu, sotto il nome del canale, trovi:

- <b>Baskets</b>: ordini che hai iniziato e non hai ancora pagato.
- <b>My Products</b>: i prodotti che vendi in questo canale. Vedi [Gestire i prodotti sul canale Manual/API](/docs/managing-products-on-the-manual-channel).
- <b>Clients</b>: i tuoi acquirenti. Vedi [Gestire i clienti](/docs/managing-clients).
- <b>Orders</b>: i tuoi ordini inseriti. Vedi [Inserire ordini manualmente](/docs/placing-orders-manually).
- <b>API</b>: token e documentazione per collegare il tuo sistema.

Il modo abituale di lavorare è:

1. Aggiungi i prodotti che vendi a <b>My Products</b> con <b>Add products</b>. Serve per l'API. Per gli ordini che digiti tu stesso è facoltativo: il carrello ti permette di scegliere qualsiasi prodotto che vendiamo.
2. Quando ricevi un ordine, apri <b>Clients</b>, trova il tuo acquirente o aggiungilo.
3. Sulla pagina del cliente premi <b>Create Order</b>, aggiungi prodotti e quantità e paga.

## Cambiare il nome o chiudere il canale

Per rinominare il canale, premi <b>Edit</b> sulla pagina del canale e cambia <b>Store name</b>.

Per chiudere un canale, vai su <b>Channels</b> e premi il pulsante di chiusura nella colonna <b>Action</b> (tooltip <b>Close channel</b>). La finestra chiede <b>Are you sure you want to close this channel?</b> e avverte <b>This operation is irreversible.</b> Un canale chiuso esce dal menu. I tuoi ordini e fatture passati vengono conservati.

## Collegare il tuo sistema con l'API

L'API permette al tuo sito o alla tua app di fare da sola quello che fai nelle pagine del canale: leggere il nostro catalogo prodotti con prezzi in tempo reale, aggiungere prodotti a <b>My Products</b>, creare e cambiare clienti, creare ordini, aggiungervi prodotti, inviarli e seguirli. Puoi anche scaricare il tuo elenco <b>My Products</b> come feed CSV o JSON per caricare i prodotti nel tuo sito.

Apri <b>API</b> sotto il tuo canale. La pagina ha queste schede:

- <b>Overview</b>: come collegarsi, l'indirizzo base dell'API e il pulsante <b>API documentation</b>. La documentazione elenca ogni endpoint con esempi.
- <b>API tokens</b>: i token di questo canale.
- <b>API calls</b>: le richieste fatte dal tuo sistema.
- <b>History</b>: le modifiche fatte sul tuo account.

### Ottenere un token

1. Premi <b>Generate API token</b>.
2. Se il token serve solo per leggere i dati, spunta <b>Read only (cannot create, change or submit orders)</b>.
3. Premi <b>Click to Generate</b>.
4. Copia il token con l'icona di copia e conservalo al sicuro. La finestra dice <b>Put this token in a safe place, you won't be able to see it again.</b> La breve etichetta nell'elenco token è solo un nome, non il token.

Invia il token con ogni richiesta nell'header <b>Authorization: Bearer</b> seguito dal tuo token. Ogni token appartiene a un canale: prodotti, clienti e ordini che il tuo sistema crea vanno a quel canale. Per far smettere di funzionare un token, eliminalo nella scheda <b>API tokens</b>.

<!-- screenshot: la pagina API, scheda Overview con i pulsanti API documentation e Generate API token -->

### Prova prima su staging

La scheda <b>Overview</b> ha anche <b>Open staging mirror</b>. Staging è una copia separata del sito dove puoi fare test senza ordini o pagamenti reali. Accedi con la stessa email e password. Staging viene reimpostato regolarmente con una copia nuova, il che cancella ciò che hai creato lì. I token del sito reale non funzionano su staging: genera un token separato su staging, e uno nuovo dopo ogni reset. L'indirizzo base di staging è mostrato nella scheda <b>Overview</b>.

### Come vengono pagati gli ordini dall'API

Quando il tuo sistema invia un ordine, lo paghiamo prima dal saldo del tuo account, poi dalle tue carte salvate. Aggiungi una carta prima di iniziare. Una volta che hai un token, il menu mostra <b>Saved Cards</b>. Finché non è salvata nessuna carta, la pagina API mostra <b>You have no cards saved yet.</b> con un pulsante <b>Add card</b>.

Se né il tuo saldo né le tue carte coprono l'ordine, l'ordine viene segnato <b>Unpaid</b> e non passa al magazzino. Ricarica il tuo saldo con <b>Top Up</b>, apri l'ordine e premi <b>Pay … with balance</b>. Il pulsante compare quando il tuo saldo copre l'importo dovuto.

## Quando qualcosa non funziona

- <b>Il nome è già usato quando creo il canale.</b> Un altro dei tuoi canali aperti ha quel nome. Digita un nome diverso. Il nome di un canale chiuso può essere riusato.
- <b>I miei ordini non arrivano da soli.</b> Un canale Manual/API non raccoglie mai ordini da un sito. Creali nella pagina del cliente, oppure inviali dal tuo sistema tramite l'API. Se vendi su una piattaforma mostrata nella pagina <b>Add Sales Channel</b>, collega quella piattaforma come canale a sé.
- <b>I miei prodotti non sono sul mio sito.</b> Non carichiamo nulla da un canale Manual/API. Caricali tu stesso nel tuo sito, con lo scarico CSV in <b>My Products</b> o tramite l'API.
- <b>Ho perso il mio token API.</b> Non può essere mostrato di nuovo. Genera un nuovo token, mettilo nel tuo sistema ed elimina quello vecchio.
- <b>L'API risponde che non posso creare o cambiare ordini.</b> Il token è di sola lettura. Genera un token senza <b>Read only</b> spuntato.
- <b>L'API rifiuta le mie richieste per un po'.</b> Ogni token può fare fino a 120 richieste al minuto. Rallenta il tuo sistema e riprova dopo un minuto.
- <b>L'API dice "This order has no products yet".</b> Aggiungi almeno un prodotto all'ordine prima di inviarlo.
- <b>L'API dice "Unable to find related portfolio item".</b> Tramite l'API aggiungi un prodotto a un ordine tramite il suo elemento <b>My Products</b>, non tramite il prodotto stesso. Aggiungi prima il prodotto a <b>My Products</b> e usa l'id di quell'elemento.
- <b>L'API dice che esiste già un'altra transazione con lo stesso prodotto.</b> Il prodotto è già nell'ordine. Cambia la quantità di quella riga invece di aggiungerlo di nuovo.
- <b>L'API dice che l'ordine "is already in the 'submitted' state and cannot be updated".</b> Gli ordini inviati non possono essere cambiati o eliminati tramite l'API. Chiedici nella chat del nostro sito se l'ordine deve cambiare.
- <b>Il mio ordine API mostra Unpaid.</b> Il tuo saldo e le tue carte salvate non lo coprivano. Ricarica il tuo saldo, apri l'ordine e premi <b>Pay … with balance</b>, e controlla che la tua carta salvata sia ancora valida.
