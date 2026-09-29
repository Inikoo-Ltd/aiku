---
title: Gestire i prodotti sul canale Manual/API
summary: Aggiungi i prodotti che vendi a My Products su un canale Manual/API, importali da un foglio di calcolo o da un altro canale, scarica i tuoi dati e le tue immagini prodotto, e rimuovi i prodotti che non vendi più.
date: 2026-09-25
source_date: 2026-09-25
tags: manual, api, my products, portfolio, add products, import, csv, images
category: products
series: manual
order: 2
shops: awd, dssk, dse
---

<aside class="tldr">
<b>My Products</b> è l'elenco dei prodotti che vendi in un canale. Aprilo sotto il tuo canale Manual/API e premi <b>Add products</b> per scegliere prodotti dal nostro catalogo. Su un canale Manual/API non viene caricato nulla da nessuna parte: l'elenco è per il tuo uso personale e per l'API. Non ti serve per inserire ordini a mano. Per smettere di vendere un prodotto, premi il pulsante <b>X</b> sulla sua riga.
</aside>

## Aprire My Products

Vai al tuo canale Manual/API nel menu e apri <b>My Products</b>. Puoi anche premere <b>View all</b> sul riquadro <b>Products</b> della pagina del canale.

Se l'elenco è vuoto, la pagina dice <b>You don't have any items in your portfolio</b> e mostra un pulsante <b>Add Product</b>.

Ogni riga prodotto mostra l'immagine, il nome e il codice, la scorta disponibile (<b>Stocks:</b>), il peso, il tuo prezzo (<b>Price:</b>) e il prezzo di vendita consigliato (<b>RRP:</b>).

## Aggiungere prodotti

1. Premi <b>Add products</b>.
2. Si apre una finestra <b>Add products to your product</b>.
3. Scegli come cercare: <b>Product</b> cerca nomi e codici prodotto, <b>Department</b>, <b>Sub-department</b> e <b>Family</b> trovano i prodotti in un gruppo con quel nome.
4. Digita nel campo di ricerca e spunta i prodotti che vuoi.
5. Premi <b>Add … products and close</b>. Il numero indica quanti ne hai spuntati.

<!-- screenshot: la finestra Add products con il filtro Product / Department / Sub-department / Family e il pulsante Add products and close -->

Vedi <b>Successfully added portfolios</b> e i prodotti compaiono nell'elenco.

## Aggiungere molti prodotti insieme

### Da un foglio di calcolo

1. Premi il pulsante di caricamento accanto a <b>Add products</b> (tooltip <b>Import from xlsx file</b>).
2. Nella finestra <b>Bulk Import Portfolios</b>, premi <b>Download template (.xlsx)</b>.
3. Compila la colonna <b>sku</b> con i nostri codici prodotto, uno per riga. La colonna <b>title</b> è facoltativa.
4. Carica il file.

Le righe vengono saltate quando il codice non esiste nel nostro negozio o il prodotto non è in vendita. La cronologia del caricamento mostra cosa è stato aggiunto e cosa è fallito.

### Da un altro canale

Se hai già prodotti in un altro canale, puoi copiarli. Premi il pulsante con i tre puntini accanto a <b>Add products</b>. Sotto <b>Clone portfolio from channel:</b> scegli il canale da cui copiare. Il numero tra parentesi indica quanti prodotti ha. La copia viene eseguita in background e la pagina si ricarica quando è terminata.

## Trovare prodotti nel tuo elenco

Usa il campo di ricerca, o i pulsanti filtro sopra l'elenco:

- <b>Only For Sale</b>: prodotti che puoi ordinare ora.
- <b>Not For Sale</b>: prodotti che non stiamo vendendo al momento.
- <b>Discontinued</b>: prodotti che non venderemo più.
- <b>Out of stock</b>: prodotti senza scorta al momento.

Un'icona a scatola barrata significa che il prodotto è fuori produzione. Il suo tooltip dice <b>This product line has been discontinued. Please remove this item</b>. Un'icona di denaro barrata significa <b>This product line is currently not for sale</b>. Togli questi prodotti dal tuo sito web, così i tuoi acquirenti non possono ordinarli.

## Ottenere dati e immagini dei prodotti per il tuo sito

Su un canale Manual/API non carichiamo prodotti sul tuo sito. Prendi i dati da qui:

- <b>CSV</b>: scarica il tuo elenco prodotti con prezzi, scorte e descrizioni.
- Il pulsante con i tre puntini accanto a <b>CSV</b> apre <b>Export Options</b>. Scegli le colonne, il <b>Product State</b> e il <b>Product Sale Status</b> che vuoi, poi premi <b>Export Extended Properties</b>. Spunta <b>Include bundles</b> per aggiungere i tuoi bundle.
- <b>Images</b>: prepara un download delle immagini dei tuoi prodotti. Quando è pronto, premi <b>Download images</b>. Il link funziona solo per un tempo limitato, indicato nel tooltip del pulsante.
- Tramite l'API, il tuo sistema può leggere lo stesso elenco, e scaricarlo come feed CSV o JSON. Vedi [Il canale Manual/API](/docs/manual-and-api-channel).

Le scorte e i prezzi cambiano. Scarica di nuovo l'elenco, o leggilo tramite l'API, abbastanza spesso da mantenere corretto il tuo sito web.

## Rimuovere un prodotto

Premi il pulsante <b>X</b> sulla riga del prodotto (tooltip <b>Remove product from list</b>). Il prodotto lascia il tuo elenco. Gli ordini già effettuati con esso non vengono modificati. Puoi aggiungerlo di nuovo più avanti con <b>Add products</b>.

## Quando qualcosa non funziona

- <b>Non trovo un prodotto nella finestra Add products.</b> Controlla di cercare nella scheda giusta: <b>Product</b> cerca nomi e codici prodotto, <b>Family</b> e <b>Department</b> cercano nomi di gruppo. La finestra non mostra i prodotti già presenti nel tuo elenco, i prodotti non in vendita e i prodotti fuori produzione.
- <b>Il caricamento del mio foglio di calcolo ha saltato righe con "SKU not found in this shop".</b> Il codice nella colonna <b>sku</b> non è uno dei nostri codici prodotto su questo sito. Copia il codice esattamente come mostrato sul prodotto.
- <b>Il caricamento del mio foglio di calcolo ha saltato righe con "Product is not for sale".</b> Non vendiamo quel prodotto al momento. Lascialo fuori.
- <b>Un prodotto risulta fuori produzione o non in vendita.</b> Non puoi ordinarlo. Rimuovilo dal tuo sito web e da <b>My Products</b>.
- <b>Un prodotto è esaurito.</b> Resta nel tuo elenco. Usa <b>Out of stock</b> per trovare questi prodotti e nasconderli sul tuo sito finché non tornano disponibili.
- <b>Il link di download delle immagini non funziona più.</b> Il link scade. Premi di nuovo <b>Images</b> per crearne uno nuovo.
- <b>I miei prodotti non sono sul mio sito.</b> Non carichiamo mai nulla da un canale Manual/API. Caricali tu stesso con il download CSV o l'API. Se vendi su una piattaforma mostrata nella pagina <b>Add Sales Channel</b>, come Shopify, WooCommerce, eBay o TikTok Shop, collega quella piattaforma come proprio canale e i prodotti vengono caricati per te.
