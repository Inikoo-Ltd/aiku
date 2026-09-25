---
title: Esportare i dati e le immagini dei prodotti
summary: Scarica i prodotti di un canale come file CSV, scegli le tue colonne e i tuoi filtri, e scarica tutte le foto dei prodotti come un unico file zip.
date: 2026-09-25
source_date: 2026-09-25
tags: products, export, csv, images, download, data feed
category: products
shops: awd, dssk, dse
---

<aside class="tldr">
Su <b>My Products</b> di un canale ci sono tre pulsanti di download: <b>CSV</b> fornisce i dettagli completi di ogni prodotto nell'elenco, <b>⋮</b> ti permette di scegliere le colonne e i filtri per un CSV più piccolo, e <b>Images</b> mette tutte le foto in un unico file zip. Puoi anche scaricare un singolo prodotto, famiglia, dipartimento o collezione dalla sua pagina in <b>Catalogue</b>.
</aside>

## Dove si trovano i pulsanti

1. Apri <b>Channels</b> nel menu, clicca sul tuo canale e apri <b>My Products</b>.
2. In alto a destra vedi un gruppo di pulsanti: <b>CSV</b>, <b>⋮</b> e <b>Images</b>.

I pulsanti compaiono solo quando il canale ha prodotti e non è chiuso, e non nella scheda <b>My Bundles</b>. I file contengono solo i prodotti di questo canale. Per esportare un altro canale, apri il suo <b>My Products</b>.

<!-- screenshot: il gruppo di pulsanti CSV / ⋮ / Images in alto in My Products -->

## Dettagli completi dei prodotti (CSV)

Premi <b>CSV</b>. Il file si scarica subito. Aprilo in Excel, Google Sheets o qualsiasi programma di fogli di calcolo. Ogni riga è un prodotto, ogni colonna un dettaglio.

Le colonne sono:

- <b>Status</b>: <b>Active</b>, <b>Discontinuing</b> o <b>Discontinued</b>.
- <b>Product code</b>, <b>Product user reference</b> (il tuo riferimento personale per il prodotto, se ne hai impostato uno).
- <b>Department code</b>, <b>Department</b>, <b>Subdepartment code</b>, <b>Subdepartment</b>, <b>Family code</b>, <b>Family</b>.
- <b>Barcode</b>, <b>CPNP number</b> (numero cosmetico UE, quando il prodotto ne ha uno).
- <b>Price</b>: il tuo prezzo per un cartone esterno (la confezione che ordini). <b>Units per outer</b>, <b>Unit label</b>, <b>Unit price</b>.
- <b>Unit Name</b>: il nome del prodotto.
- <b>Unit RRP</b>: prezzo di vendita consigliato per un'unità.
- <b>Unit net weight</b> e <b>Package weight (shipping)</b>, in chilogrammi. <b>Unit dimensions</b>.
- <b>Materials/Ingredients</b>.
- <b>Webpage description (html)</b> e <b>Webpage description (plain text)</b>.
- <b>Country of origin</b>, <b>Tariff code</b>, <b>Duty rate</b>, <b>HTS US</b>.
- <b>Stock</b>: un livello di scorta, non un numero: <b>Normal</b>, <b>Low</b> (sotto 20), <b>VeryLow</b> (sotto 5), <b>OutofStock</b>, <b>Discontinuing</b> o <b>Discontinued</b>.
- <b>Images</b>: link alle foto a dimensione piena, separati da virgole.
- <b>Data updated</b>, <b>Stock updated</b>, <b>Price updated</b>, <b>Images updated</b>: quando ciascuna parte è stata modificata l'ultima volta.
- <b>Available Quantity</b>: il numero di unità in stock. È 0 quando il prodotto non è in vendita.
- <b>For sale</b>: <b>Yes</b> o <b>No</b>.

I bundle sono esclusi. Per includerli, apri <b>⋮</b> e spunta prima <b>Include bundles</b>.

## Le tue colonne e i tuoi filtri

Premi <b>⋮</b> (<b>Other Export Options</b>). Si apre un pannello:

- <b>Bundles</b>: spunta <b>Include bundles</b> per aggiungere i tuoi bundle. È disattivato per impostazione predefinita e si applica a entrambi i download CSV.
- <b>Columns to Export</b>: spunta le colonne che vuoi. <b>Select All</b> e <b>Deselect All</b> sono in alto. Le colonne sono i codici e i nomi di prodotto, dipartimento, sottodipartimento e famiglia, codice a barre, materiali, dimensioni, pesi, codici di origine e doganali, <b>Stock</b> (un numero), <b>Status</b> (<b>In stock</b> o <b>Out of stock</b>), <b>For sale</b> e <b>Data updated</b>.
- <b>Product State</b>: <b>Active</b>, <b>Discontinuing</b>, <b>Discontinued</b>. Solo <b>Active</b> è spuntato all'inizio.
- <b>Product Sale Status</b>: <b>Exclude products that are not for sale</b>, <b>Exclude products that are out of stock</b>, <b>Only products that are not for sale</b>.

Premi <b>Export Extended Properties</b>. Il file si apre in una nuova scheda e si scarica. Questo file non ha prezzi, descrizioni o link alle immagini: usa il <b>CSV</b> completo per quelli.

<!-- screenshot: il pannello Export Options con Columns to Export, Product State e Product Sale Status -->

## Tutte le foto dei prodotti (zip)

1. Premi <b>Images</b>. Una finestra dice <b>Your download images request is being processed.</b> Raccogliamo le foto di ogni prodotto nell'elenco.
2. Quando è pronto, la finestra dice <b>Your images are ready for download.</b> Premi <b>Download</b> e salva il file zip.
3. Il pulsante ora dice <b>Download images</b>. Tieni il mouse sopra per vedere per quanto tempo il link funziona ancora. Il link scade un giorno dopo essere stato creato.

Ogni foto è nominata con il codice prodotto e un numero, ad esempio <b>abc-01__12345.jpg</b>, così puoi vedere a quale prodotto appartiene.

Ogni volta che i prodotti nel canale vengono aggiunti o modificati, il vecchio zip viene eliminato. Premi di nuovo <b>Images</b> per crearne uno nuovo.

Non ci sono video dei prodotti in questo download.

## Un singolo prodotto, famiglia o collezione

In <b>Catalogue</b>, apri un prodotto, una famiglia, un sottodipartimento, un dipartimento o una collezione. In alto a destra:

- <b>CSV</b> scarica i suoi prodotti con le stesse colonne del CSV completo.
- Sulle pagine di prodotto, famiglia e collezione, premi <b>⋮</b> e scegli <b>images</b> sotto <b>Select another download file type</b> per scaricare le loro foto come file zip.

Sulle pagine del catalogo del nostro sito, ogni famiglia e prodotto nell'elenco ha due icone di download: <b>Download products (csv)</b> e <b>Download images (zip)</b>.

## Quando qualcosa non funziona

- **Non vedo i pulsanti CSV e Images.** Il canale non ha ancora prodotti, il canale è chiuso, oppure sei nella scheda <b>My Bundles</b>. Aggiungi prima i prodotti, oppure torna alla scheda <b>My Products</b>.
- **Il CSV ha meno prodotti di My Products.** Il <b>CSV</b> completo esclude i bundle. Il file <b>Export Extended Properties</b> usa anche i filtri <b>Product State</b> e <b>Product Sale Status</b>: spunta tutti gli stati per ottenere tutto.
- **"Select at least one column".** Spunta almeno una colonna sotto <b>Columns to Export</b>.
- **Il link delle immagini dice Expired o non si apre.** Premi di nuovo <b>Images</b> per creare un nuovo zip.
- **Lo zip contiene solo un file chiamato error.txt.** Nessuno dei prodotti nell'elenco ha una foto. Controlla che il canale abbia prodotti.
- **Excel mostra lettere strane.** Apri il file con <b>Data → From Text/CSV</b> e scegli UTF-8, oppure aprilo in Google Sheets.
- **"The data feed for ... is not available yet, please try again later."** Il file per quella famiglia o dipartimento è ancora in preparazione. Riprova tra qualche minuto.
