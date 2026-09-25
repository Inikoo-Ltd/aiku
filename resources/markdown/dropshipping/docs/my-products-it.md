---
title: Usare My Products
summary: Leggi l'elenco My Products di un canale, invia i prodotti al tuo negozio o collegali a inserzioni che hai già, tieni aggiornato lo stock e leggi gli errori di caricamento.
date: 2026-09-25
source_date: 2026-09-25
tags: prodotti, my products, portfolio, caricamento, abbinamento, sku, stock, log
category: products
shops: awd, dssk, dse
---

<aside class="tldr">
<b>My Products</b> è l'elenco dei nostri prodotti che vendi in un canale. Ogni canale ha il proprio elenco. Da qui invii ogni prodotto al tuo negozio con <b>Create new product</b>, oppure lo colleghi a un'inserzione che hai già con <b>Match</b>. Quando il prodotto è collegato teniamo aggiornato il suo stock, e la scheda <b>Logs</b> mostra ogni caricamento, abbinamento e aggiornamento di stock con la risposta della tua piattaforma.
</aside>

## Aprire My Products

1. Apri <b>Channels</b> nel menu. Ogni canale compare sotto con il suo logo.
2. Clicca sul canale, poi su <b>My Products</b>. Il numero accanto ad esso indica quanti prodotti ci sono nell'elenco.

La pagina ha tre schede: <b>My Products</b>, <b>My Bundles</b> (vedi [Creare bundle](/docs/bundles)) e <b>Logs</b> (l'icona dell'orologio a destra).

Se un riquadro rosso dice <b>Your channel is not connected yet to the platform</b>, la connessione al tuo negozio è interrotta. Non può essere caricato nulla e non viene inviato stock finché non ti ricolleghi. Segui la guida di connessione per la tua piattaforma.

<!-- screenshot: la pagina My Products di un canale Shopify con le schede, i pulsanti in cima e alcune righe -->

## Cosa mostra ogni riga

- **Product**: il nostro codice prodotto (cliccalo per aprire il prodotto), il nome, <b>Stocks</b>, <b>Weight</b> (peso del prodotto / peso con imballaggio), <b>Dimension</b>, il nostro <b>Price</b> (quanto ci paghi) e l'<b>RRP</b>. Se il tuo canale mostra i prezzi con IVA, vedi <b>Price (include VAT)</b> e <b>RRP (include VAT)</b> (non su Shopify).
- **Status**: su Shopify, una stretta di mano verde significa <b>Product connected to shopify</b> e una rossa significa <b>Not connected</b>. Sulle altre piattaforme ci sono tre segni di spunta: <b>Has valid platform product id</b>, <b>Exist in platform</b> e <b>Platform status</b>. Tre segni di spunta verdi significano che il prodotto è attivo e collegato.
- **Message**: un segno di spunta verde quando va tutto bene. Un messaggio rosso quando la tua piattaforma ha rifiutato il prodotto (su Shopify, guarda invece la scheda <b>Logs</b>). Cliccalo per vedere <b>Answer of ...</b> con il testo completo dalla tua piattaforma e, spesso, cosa fare. Un riquadro barrato significa <b>This product line has been discontinued. Please remove this item</b>. Un simbolo del dollaro barrato significa <b>This product line is currently not for sale</b>.
- **La colonna prodotto della tua piattaforma** (per esempio <b>Shopify product</b> o <b>eBay product</b>): a quale inserzione nel tuo negozio è collegato questo prodotto, o i pulsanti per collegarlo.

## Inviare un prodotto al tuo negozio

Per un prodotto non ancora collegato hai due scelte.

**Creare una nuova inserzione.** Premi <b>Create new product</b>. Creiamo il prodotto nel tuo negozio con il nostro nome, descrizione, immagini, prezzo, SKU e stock.

**Collegarlo a un'inserzione che hai già.** Usalo quando vendi già il prodotto e non vuoi una seconda copia.
- Se abbiamo trovato un'inserzione nel tuo negozio con lo stesso SKU, compare nella riga. Premi <b>Match with this product</b>.
- Per sceglierne una diversa, premi <b>Choose another product from your shop</b>, oppure <b>Match it with an existing product in your shop</b> quando non abbiamo trovato nulla. Cerca nel tuo negozio, scegli l'articolo e premi <b>Link ... to selected item on your platform</b>.
- Per cambiare un prodotto già collegato, premi <b>Change linked listing</b> (su Shopify: <b>Connect with other product</b>).

## Fare molti prodotti insieme

Quando alcuni prodotti non sono ancora collegati, una barra gialla dice <b>You have ... products not synced yet</b>. Ha due pulsanti:

- <b>Upload all as new product</b>: li crea tutti nel tuo negozio. Non mostrato su eBay.
- <b>Match all with default product</b>: collega ogni prodotto all'inserzione nel tuo negozio con lo stesso SKU. Confrontiamo lo SKU nel tuo negozio con lo SKU del prodotto in <b>My Products</b> e con il nostro codice prodotto, e maiuscole o minuscole non contano. I prodotti senza un'inserzione con quello SKU vengono lasciati come sono.

Per lavorare solo su alcuni prodotti, spuntali nell'elenco. Compaiono questi pulsanti:

- <b>Create New (...)</b>: crea i prodotti spuntati nel tuo negozio.
- <b>Match (...)</b>: collega i prodotti spuntati per SKU.
- <b>Unlink (...)</b> e <b>Unlink & Delete (...)</b>: vedi [Rimuovere prodotti](/docs/removing-products).
- <b>Edit Price (...)</b>: imposta il tuo prezzo di vendita per i prodotti spuntati su eBay, Shopify, WooCommerce e Wix, come percentuale o importo sopra o sotto l'RRP. Non mostrato quando il tuo canale è impostato per mantenere i propri prezzi.

I lavori grandi vengono eseguiti in background. Una finestra di avanzamento mostra quanti sono stati completati, e la pagina si ricarica da sola.

<!-- screenshot: la barra gialla "products not synced yet" con Upload all as new product e Match all with default product -->

## Trovare prodotti nell'elenco

Usa il campo di ricerca, oppure i pulsanti filtro: <b>Only For Sale</b>, <b>Not For Sale</b>, <b>Discontinued</b> e <b>Out of stock</b>. Su Shopify i filtri sono nel menu <b>Filter</b>: <b>Only For Sale</b>, <b>Not For Sale</b>, <b>Discontinued</b>, <b>Connected to Shopify</b> e <b>Not Connected</b>. Su Shopify non c'è un filtro per il fuori stock.

## Stock

Inviamo lo stock solo per i prodotti collegati (stato verde). Non devi fare nulla: quando il nostro stock cambia, aggiorniamo il tuo negozio.

Per inviare subito lo stock, premi <b>Update Stock</b> in cima alla pagina. Invia lo stock attuale dei prodotti di questo canale. Se nessuno dei tuoi prodotti è ancora collegato, dice <b>Nothing to update</b>. Il pulsante è presente sui canali Shopify, WooCommerce, eBay, TikTok Shop e Wix, non su Allegro o sui canali manuali.

Puoi limitare o nascondere lo stock nelle impostazioni del canale. Vedi [Perché un prodotto risulta esaurito nel mio negozio](/docs/out-of-stock-in-my-store).

## Altri pulsanti

- <b>Add products</b>, il pulsante di caricamento e <b>Clone portfolio from channel:</b>: aggiungono prodotti. Vedi [Trovare e aggiungere prodotti](/docs/sourcing-products).
- <b>CSV</b>, <b>⋮</b> (<b>Other Export Options</b>) e <b>Images</b>: scaricano i dati e le foto dei tuoi prodotti. Vedi [Esportare i dati e le immagini dei prodotti](/docs/exporting-product-data).
- <b>Publish ... drafts</b> (solo eBay): pubblica le inserzioni caricate su eBay come bozze.
- <b>Update all dimensions</b> (solo Shopify): invia le nostre dimensioni attuali a tutti i tuoi prodotti Shopify.

## La scheda Logs

La scheda <b>Logs</b> elenca ogni caricamento, abbinamento e aggiornamento di stock per questo canale: <b>Product Code</b>, <b>Type</b> (<b>upload</b>, <b>match</b> o <b>update-stock</b>), <b>Platform</b>, <b>Status</b> (<b>Done</b>, <b>In progress</b> o <b>Failed</b>), la <b>Response</b> dalla tua piattaforma e la <b>Date</b>. Guarda qui per primo quando un prodotto o il suo stock non sono arrivati.

## Quando qualcosa non funziona

Il messaggio rosso sulla riga, e la <b>Response</b> in <b>Logs</b>, è la risposta della tua piattaforma. I più comuni:

- **Throttled / too many calls / request timeout / internal error.** La tua piattaforma ci ha chiesto di rallentare, oppure non ha risposto in tempo. Non c'è nulla che non va nel prodotto. Riprova tra qualche minuto.
- **The store answered with a web page instead of data, or returned 503, timed out or an empty reply** (WooCommerce). Il tuo sito è offline, in modalità manutenzione, oppure il suo plugin di sicurezza o l'hosting ci blocca. Controlla che il tuo sito sia online. Chiedi al tuo hosting di consentire la nostra connessione, poi riprova.
- **A product with this SKU already exists in your store / Invalid or duplicated SKU / already present in the lookup table** (WooCommerce). Hai già un prodotto con quello SKU. Usa <b>Match</b> invece di <b>Create new product</b>. Se il vecchio prodotto è nel cestino di WooCommerce, svuota prima il cestino.
- **Invalid or duplicated GTIN** (WooCommerce). Un altro prodotto nel tuo negozio usa già quel codice a barre. Rimuovi il codice a barre dall'altro prodotto in WooCommerce, oppure abbinati ad esso.
- **Cannot list more products: your Shop probation tier allows at most 100 total product listings** (TikTok). È un limite TikTok per i nuovi negozi, non un problema del prodotto. Rimuovi le inserzioni che non ti servono, oppure chiedi a TikTok di alzare il tuo livello.
- **product_weight received 0 / weight cannot be zero** (TikTok). TikTok richiede un peso. Non puoi cambiare tu stesso il peso del nostro prodotto: chiedici nella chat del nostro sito, indicando il codice prodotto.
- **Image must be at least 300:300** (TikTok). Una delle nostre immagini è troppo piccola per TikTok. Chiedici nella chat del nostro sito, indicando il codice prodotto.
- **Price out of range / incorrect price** (TikTok). TikTok decide l'intervallo di prezzo che il tuo negozio può usare. Controlla l'intervallo nel Seller Center di TikTok Shop. Se il prezzo che inviamo è fuori da esso, chiedici nella chat del nostro sito, indicando il codice prodotto.
- **Category qualification / category is restricted** (TikTok). Fai la richiesta per la categoria nel Qualification Center del Seller Center di TikTok Shop, poi carica di nuovo.
- **Requires an active seller account** (TikTok) o **create a seller account** (eBay). Completa prima il tuo account venditore sulla piattaforma.
- **The listing would cause you to exceed the amount you can list this month** (eBay). Hai raggiunto il tuo limite di vendita eBay. Chiedi a eBay di alzarlo, oppure aspetta il mese successivo.
- **Invalid data in the associated fulfilment policy** (eBay). La tua policy di spedizione (fulfilment) eBay ha un problema. Correggila su eBay, poi controlla le policy scelte nelle impostazioni del tuo canale.
- **Item specific Type / Brand missing, or custom values for Size no longer supported** (eBay). eBay richiede dettagli extra per quella categoria. Vedi [Gestire prodotti e ordini su eBay](/docs/managing-products-on-ebay).
- **Not allowed to revise an ended item** (eBay). L'inserzione è terminata su eBay. Scollega il prodotto e crealo di nuovo.
- **Overseas Warehouse Block Policy** (eBay). Se il tuo account è registrato in alcuni paesi, in cima compare un <b>Important Notice</b> rosso. eBay può bloccare le inserzioni conservate all'estero. Contatta l'assistenza eBay per chiedere l'approvazione.
- **This product line has been discontinued.** Non lo vendiamo più. Rimuovilo dal tuo elenco e dal tuo negozio.
