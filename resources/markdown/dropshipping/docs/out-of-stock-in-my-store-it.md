---
title: Perché un prodotto risulta esaurito nel mio negozio
summary: Scopri perché il tuo negozio mostra un prodotto come esaurito quando è disponibile da noi, e risolvi il problema su Shopify, WooCommerce, eBay, TikTok Shop e Wix.
date: 2026-09-25
source_date: 2026-09-25
tags: scorte, esaurito, magazzino, shopify, wix, woocommerce, ebay, tiktok, sede
category: troubleshooting
shops: awd, dssk, dse
---

<aside class="tldr">
Inviamo le scorte al tuo negozio solo per i prodotti <b>collegati</b> su <b>My Products</b>, e solo mentre il canale è connesso. I motivi più comuni per cui un prodotto risulta "esaurito" sono: il prodotto non è collegato, è davvero esaurito o non in vendita da noi, le impostazioni del canale nascondono le scorte basse, oppure (su Shopify) le scorte si trovano in un'altra sede. Controlla nell'ordine indicato qui sotto, poi premi <b>Update Stock</b>.
</aside>

## Come le scorte arrivano al tuo negozio

- Inviamo le scorte solo per i prodotti collegati a un'inserzione nel tuo negozio: verde nella colonna <b>Status</b> di <b>My Products</b>.
- Quando le nostre scorte cambiano, aggiorniamo il tuo negozio da soli. Non devi fare nulla.
- Inviamo 0 per i prodotti non in vendita o fuori produzione, anche se restano alcune unità.
- Le impostazioni del tuo canale possono ridurre il numero che inviamo. Vedi il punto 4.

## Controlla questi punti, uno per uno

### 1. Il canale è connesso?

Apri <b>Channels</b> nel menu, clicca sul tuo canale e apri <b>My Products</b>. Se un riquadro rosso dice <b>Your channel is not connected yet to the platform</b>, non possiamo inviare nulla. Riconnetti prima il canale. Su Shopify, assicurati di aver premuto <b>Install</b> su Shopify per completare la connessione.

### 2. Il prodotto è collegato?

Cerca il prodotto su <b>My Products</b>. Su Shopify lo stato deve essere la stretta di mano verde (<b>Product connected to shopify</b>). Sulle altre piattaforme tutti e tre i segni di spunta devono essere verdi.

Se è rosso, per quanto ne sappiamo l'inserzione nel tuo negozio non è la nostra, quindi non ne aggiorniamo mai le scorte. Succede spesso quando hai creato tu il prodotto, o l'hai importato da un'altra app. Collegalo con <b>Match with this product</b>, oppure abbina tutto insieme con <b>Match all with default product</b>. Vedi [Usare My Products](/docs/my-products).

### 3. È disponibile da noi?

Guarda <b>Stocks</b> (su Shopify, <b>Stock</b>) nella riga. Tranne che su Shopify, puoi usare il filtro <b>Out of stock</b> per elencare tutti i prodotti senza scorte. Un simbolo del dollaro barrato significa <b>This product line is currently not for sale</b>, e una scatola barrata significa che è fuori produzione. In tutti questi casi il tuo negozio ha ragione a mostrare "esaurito".

Per essere avvisato quando un prodotto torna disponibile, usa il pulsante a forma di busta sul prodotto sul nostro sito. I tuoi promemoria sono sotto <b>Back In Stock Reminders</b> nel menu.

### 4. Controlla le impostazioni scorte del tuo canale

Sulla pagina del canale premi <b>Manage Sales Channel</b> (o <b>Edit</b>). Sotto <b>Manage Stock</b>:

- <b>Stock Update</b>: quando è disattivato, smettiamo di aggiornare le scorte in automatico. Tienilo attivo.
- <b>Stock Threshold</b>: quando le nostre scorte scendono a questo numero o sotto, inviamo 0. Per esempio, con una soglia di 10, un prodotto con 8 unità risulta esaurito. Lascialo vuoto per inviare le scorte reali.
- <b>Max Quantity To Advertise</b>: il massimo che mostriamo, anche se ne abbiamo di più. Lascialo vuoto per non avere un limite.

<!-- screenshot: la sezione Manage Stock delle impostazioni del canale con Stock Update, Max Quantity To Advertise e Stock Threshold -->

### 5. Invia subito le scorte

Su <b>My Products</b>, premi <b>Update Stock</b> (Shopify, WooCommerce, eBay, TikTok Shop e Wix). Vedrai <b>Stock update started</b>. Può richiedere qualche minuto. Poi apri la scheda <b>Logs</b>: le righe di tipo <b>Update Stock</b> mostrano <b>Done</b> o <b>Failed</b> con la risposta della tua piattaforma.

Se dice <b>Nothing to update</b>, nessuno dei tuoi prodotti è ancora collegato. Torna al punto 2.

## Shopify

Su Shopify le nostre scorte risiedono nella nostra sede di evasione, chiamata <b>aiku-</b> seguito dal codice del nostro negozio e dal codice del tuo canale tra parentesi, ad esempio <b>aiku-awd (my-store)</b>.

1. Su Shopify, apri <b>Products</b> e il prodotto che risulta esaurito.
2. Nella sezione <b>Inventory</b>, controlla che la nostra sede sia presente e abbia scorte.
3. Se le scorte sono in un'altra sede (per esempio il tuo stesso indirizzo del negozio) a 0, è quello il numero che Shopify mostra per quella sede. Le nostre scorte si trovano solo nella nostra sede.

Se Shopify risponde che il prodotto non ha scorte nella nostra sede, lo aggiungiamo noi alla nostra sede, così il prossimo aggiornamento scorte può andare a buon fine. Se la scheda <b>Logs</b> dice <b>No variant on Shopify matches this sku</b>, lo SKU della variante su Shopify non è il nostro codice prodotto. Cambia lo SKU su Shopify con il nostro codice, oppure ricollega il prodotto con <b>Connect with other product</b>.

## Wix

Non inviamo mai scorte per un prodotto Wix non collegato. Se Wix dice che tutti i tuoi prodotti sono esauriti, molto probabilmente i prodotti sono stati aggiunti direttamente su Wix o non sono stati abbinati. Su <b>My Products</b>, usa <b>Match all with default product</b> per collegarli tramite SKU, oppure <b>Create new product</b> per lasciare che li creiamo noi. Poi premi <b>Update Stock</b>.

## eBay

Quando inviamo 0, eBay mostra l'inserzione come esaurita. Se il tuo account eBay non usa l'opzione "esaurito" di eBay, eBay potrebbe terminare l'inserzione invece. Attiva l'opzione nelle tue preferenze di vendita eBay, così le inserzioni restano e tornano disponibili quando abbiamo scorte.

## WooCommerce e TikTok Shop

Controlla la scheda <b>Logs</b>. Su WooCommerce, un aggiornamento scorte <b>Failed</b> con "503", "timed out" o "The store answered with a web page instead of data" significa che il tuo sito non ci ha lasciato entrare. Controlla che il sito sia online e che il tuo hosting o plugin di sicurezza non ci blocchi, poi premi di nuovo <b>Update Stock</b>.

## Quando qualcosa non va

- **"Stock update failed. This channel is not connected to the platform, so stock cannot be updated."** Riconnetti il canale, poi riprova.
- **"Nothing to update".** Nessuno dei tuoi prodotti è collegato. Collegali prima (punto 2).
- **Le scorte sono corrette su My Products ma sbagliate nel mio negozio, e i Logs mostrano Done.** Il tuo negozio potrebbe aggiungere scorte dalle proprie sedi o app. Controlla che nessun'altra app o sede modifichi le scorte di quel prodotto.
- **Il prodotto è tornato disponibile ma il mio negozio mostra ancora 0.** Premi <b>Update Stock</b> e controlla la scheda <b>Logs</b>. Se l'aggiornamento risulta <b>Failed</b>, il messaggio spiega il motivo.
