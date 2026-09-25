---
title: Gestire i prodotti su WooCommerce
summary: Aggiungi i nostri prodotti al tuo canale WooCommerce, creali nel tuo negozio o collegali a prodotti che vendi già, mantieni le scorte aggiornate, e risolvi gli errori di caricamento.
date: 2026-09-25
source_date: 2026-09-25
tags: woocommerce, products, upload, match, sku, stock
category: products
series: woocommerce
order: 2
shops: awd, dssk, dse
---

<aside class="tldr">
Apri il tuo canale WooCommerce e vai su <b>My Products</b>. Premi <b>Add products</b> e scegli i prodotti che vuoi vendere. Poi invia ciascuno al tuo negozio: <b>Create new product</b> crea un nuovo prodotto su WooCommerce, e <b>Match with this product</b> lo collega a un prodotto che hai già nel tuo negozio. Un segno di spunta verde significa che il prodotto è attivo e manteniamo aggiornata la sua scorta.
</aside>

## Aprire il tuo elenco prodotti

1. Apri <b>Channels</b> nel menu e clicca sul nome del tuo negozio WooCommerce.
2. Sulla dashboard del canale, premi <b>View all</b> sotto <b>Products</b>. Si apre la pagina <b>My Products</b>.

Se la pagina mostra <b>Your channel is not connected yet to the platform</b>, risolvi prima la connessione. Vedi [Collegare il tuo negozio WooCommerce](connecting-woocommerce).

## Aggiungere prodotti al tuo elenco

1. Premi <b>Add products</b>. Si apre la finestra <b>Select products to be added to shop</b>.
2. Cerca per nome o codice. Puoi anche scegliere un intero <b>Department</b>, <b>Sub-department</b> o <b>Family</b> invece di singoli prodotti.
3. Spunta i prodotti che vuoi. Premi <b>Add</b>. Il pulsante mostra quanti ne hai selezionati.

I prodotti sono ora nel tuo elenco, ma non sono ancora nel tuo negozio WooCommerce. Devi prima crearli o abbinarli.

Puoi anche aggiungere molti prodotti insieme da un foglio di calcolo con il pulsante di caricamento accanto a <b>Add products</b> (<b>Import from xlsx file</b>). Se hai prodotti in un altro canale, il pulsante <b>⋮</b> ti permette di fare <b>Clone portfolio from channel</b>.

<!-- screenshot: la finestra Select products to be added to shop con alcuni prodotti spuntati e il pulsante Add -->

## Inviare i prodotti al tuo negozio

Ogni prodotto ha una colonna <b>Woo Commerce product</b>. Cosa vedi lì dipende dal prodotto:

- <b>Create new product</b>: crea un nuovo prodotto nel tuo negozio WooCommerce, con nome, descrizione e prezzo dal tuo elenco prodotti, e le nostre immagini, SKU, codice a barre, peso e dimensioni e scorta.
- <b>Match with this product</b>: abbiamo trovato un prodotto nel tuo negozio con lo stesso SKU, o un nome simile. Controlla che sia quello giusto, poi premilo per collegare i due. Usalo quando vendi già il prodotto e non vuoi una seconda copia.
- <b>Choose another product from your shop</b> (quando abbiamo trovato un possibile abbinamento) oppure <b>Match it with an existing product in your shop</b> (quando non ne abbiamo trovato nessuno): apre un elenco dei prodotti nel tuo negozio. Cerca il prodotto, selezionalo e premi <b>Link ... to selected item on your platform</b>.

Quando funziona, il prodotto mostra un segno di spunta verde e il nome del tuo prodotto WooCommerce. Da quel momento manteniamo aggiornata la sua scorta. Per collegarlo a un prodotto WooCommerce diverso più avanti, premi <b>Change linked listing</b>.

Quando abbini un prodotto, lo colleghiamo soltanto e aggiorniamo la sua scorta. Non cambiamo il nome, la descrizione, il prezzo o le immagini che hai già su WooCommerce.

<!-- screenshot: righe di My Products che mostrano Create new product, Match with this product, e un segno di spunta verde su un prodotto collegato -->

### Molti prodotti insieme

- Spunta diversi prodotti nell'elenco. Compaiono pulsanti sopra l'elenco: <b>Create New</b> li invia tutti come nuovi prodotti, <b>Match</b> li collega ai prodotti nel tuo negozio con lo stesso SKU.
- Se alcuni prodotti non sono ancora nel tuo negozio, vedi <b>You have ... products not synced yet</b>. Premi <b>Upload all as new product</b> per crearli tutti, oppure <b>Match all with default product</b> per collegare ogni prodotto che ha lo stesso SKU nel tuo negozio.

I caricamenti di grandi dimensioni vengono eseguiti in background e mostrano una finestra di avanzamento. Puoi continuare a lavorare mentre sono in corso.

L'abbinamento cerca il nostro SKU, o il nostro codice prodotto, nel tuo negozio. Maiuscole e minuscole non contano. Se i tuoi SKU sono diversi dai nostri, usa <b>Match it with an existing product in your shop</b> e scegli tu il prodotto.

## Cosa inviamo a WooCommerce

- Nome, descrizione e prezzo dal tuo elenco prodotti.
- Le nostre immagini, SKU e codice a barre (come GTIN, UPC, EAN o ISBN).
- Il peso nell'unità usata dal tuo negozio, e le dimensioni quando le abbiamo.
- Paese di origine e ingredienti come attributi del prodotto, e link ai documenti del prodotto nella descrizione.
- La scorta disponibile per la vendita. I prodotti in vendita vengono pubblicati. I prodotti esauriti, in arrivo o non ancora pronti vengono salvati come bozze.

Non scegliamo una categoria per te. I nuovi prodotti arrivano senza categoria, quindi aggiungi tu le tue categorie su WooCommerce.

## Scorte e prezzi

Inviamo automaticamente le variazioni di scorta al tuo negozio. Premi <b>Update Stock</b> in cima a <b>My Products</b> per inviare subito al canale la scorta attuale di tutti i tuoi prodotti.

In <b>Manage Sales Channel</b> puoi cambiare come viene mostrata la scorta:

- <b>Stock Update</b>: attiva o disattiva gli aggiornamenti automatici delle scorte.
- <b>Max Quantity To Advertise</b>: il numero massimo di scorta che mostriamo nel tuo negozio, anche quando ne abbiamo di più.
- <b>Stock Threshold</b>: quando la nostra scorta scende a questo numero, il prodotto risulta esaurito nel tuo negozio.

La tua <b>Pricing Policy</b> in <b>Manage Sales Channel</b> imposta il prezzo dei prodotti che aggiungi da ora in poi. Non cambia i prodotti già nel tuo elenco. Per cambiarne il prezzo, spuntali e premi <b>Edit Price</b>.

## Rimuovere i prodotti

Ci sono tre modi per rimuovere un prodotto. Scegli con attenzione, perché il pulsante a teschio elimina anche il prodotto dal tuo negozio WooCommerce.

- Il pulsante a teschio su una riga chiede conferma, poi rimuove il prodotto dal tuo elenco e, se è collegato, lo elimina definitivamente dal tuo negozio WooCommerce. Non finisce nel cestino di WooCommerce.
- <b>Unlink & Delete</b> (dopo aver spuntato i prodotti) rimuove i prodotti spuntati dal tuo elenco, ma li mantiene nel tuo negozio WooCommerce. Non sono più collegati, quindi smettiamo di aggiornarne la scorta.
- <b>Unlink</b> (dopo aver spuntato i prodotti) mantiene i prodotti nel tuo elenco e su WooCommerce, ma interrompe il collegamento. Smettiamo di aggiornarne la scorta. Puoi abbinarli di nuovo più avanti.

Se elimini tu stesso un prodotto collegato su WooCommerce, lo rimuoviamo anche dal tuo elenco.

I prodotti che non vendiamo più mostrano un segnale rosso. <b>This product line has been discontinued. Please remove this item</b> significa che dovresti rimuoverlo dal tuo negozio. <b>This product line is currently not for sale</b> significa che non puoi caricarlo al momento.

## Controllare cosa è successo

Apri la scheda <b>Logs</b> (l'icona dell'orologio a destra delle schede) per vedere ogni caricamento, se ha funzionato, e il messaggio inviato dal tuo negozio.

La colonna <b>Status</b> mostra tre segni di spunta per ogni prodotto: <b>Has valid platform product id</b>, <b>Exist in platform</b> e <b>Platform status</b>. Tre segni di spunta verdi significano che il prodotto è collegato e attivo.

## Quando qualcosa non funziona

Se un caricamento fallisce, la riga del prodotto mostra il messaggio del tuo negozio e un breve suggerimento. I più comuni:

- <b>The store answered with a web page instead of data</b>, un errore 503, un timeout, o una risposta vuota: il tuo sito è offline, troppo lento, in manutenzione, o ci blocca. È il problema di caricamento più comune. Controlla che il tuo sito si apra nel browser, chiedi alla tua società di hosting di consentire i nostri server, poi carica di nuovo.
- <b>A product with this SKU already exists in your store</b>, <b>Invalid or duplicated SKU</b>, o <b>product with SKU ... already present in the lookup table</b>: il tuo negozio ha già un prodotto con questo SKU. Quando premi <b>Create new product</b> proviamo a collegarci da soli a quel prodotto. Se il messaggio persiste, abbina il prodotto a mano con <b>Match it with an existing product in your shop</b>. Se non trovi il prodotto nel tuo negozio, guarda nel cestino di WooCommerce: un prodotto eliminato mantiene lo SKU finché non lo elimini definitivamente.
- <b>Invalid or duplicated GTIN</b>: un altro prodotto nel tuo negozio ha già lo stesso codice a barre (GTIN, UPC, EAN o ISBN). Abbina a quel prodotto, oppure rimuovi il codice a barre dall'altro prodotto su WooCommerce, poi carica di nuovo.
- <b>Your store could not save the product images</b>: la cartella di caricamento di WordPress non è scrivibile. Chiedi alla tua società di hosting di correggere i permessi della cartella, poi carica di nuovo.
- <b>The account connected to your store is not allowed to create products</b> (o modificarli o leggerli): le chiavi non hanno il permesso <b>Read/Write</b>. Ricollega il canale con un account amministratore.
- <b>Your store rejected the credentials</b>: le chiavi sono state eliminate o cambiate su WooCommerce. Premi <b>Try to reconnect</b> sulla pagina del canale.
- <b>This product no longer exists in your store</b>: il prodotto è stato eliminato su WooCommerce. Crealo di nuovo o abbinalo a un altro prodotto.
- Il pulsante <b>Add products</b> è mancante: il tuo negozio non ha risposto l'ultima volta che abbiamo provato a raggiungerlo, quindi abbiamo messo in pausa il canale. Controlla che il tuo sito sia online. Il pulsante torna dopo che raggiungiamo di nuovo il tuo negozio.

I problemi sul tuo sito web, come essere offline, lento o bloccarci, e le regole sui prodotti che imposti su WooCommerce, possono essere risolti solo da te o dalla tua società di hosting.
