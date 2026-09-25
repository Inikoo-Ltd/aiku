---
title: Collegare il tuo negozio Shopify
summary: Collega il tuo negozio Shopify al tuo account dropshipping con il suo nome myshopify.com, installa la nostra app in Shopify, e risolvi un negozio che dice ancora di non essere connesso.
date: 2026-09-25
source_date: 2026-09-25
tags: shopify, canale di vendita, connessione, installazione, myshopify
category: sales-channels
series: shopify
order: 1
shops: awd, dssk, dse
---

<aside class="tldr">
Vai su <b>Channels</b>, premi <b>Add Sales Channel</b>, poi <b>Connect</b> sulla card Shopify. Digita il nome <b>myshopify.com</b> del tuo negozio, non il tuo dominio personale, e premi <b>Connect</b>. Shopify si apre in una nuova scheda: premi <b>Install</b> lì. La connessione è completa solo quando l'app è installata in Shopify.
</aside>

## Prima di iniziare

- Ti serve il nome <b>myshopify.com</b> del tuo negozio. Shopify te lo ha assegnato quando hai creato il negozio, per esempio <b>mystore.myshopify.com</b> oppure un codice come <b>ab12cd-3e.myshopify.com</b>. Il tuo dominio personale, come <b>www.mystore.com</b>, non funziona.
- Per trovarlo, apri il tuo pannello di amministrazione Shopify e vai su <b>Settings</b>, <b>Domains</b>. Puoi anche guardare la barra degli indirizzi del tuo pannello Shopify: in <b>admin.shopify.com/store/ab12cd-3e</b>, il nome è <b>ab12cd-3e</b>.
- Accedi al tuo pannello Shopify nello stesso browser, come proprietario del negozio o come membro dello staff che può installare app.
- Shopify è disponibile su tutti i nostri siti di dropshipping. Collegalo dal sito dove hai il tuo account dropshipping.

## Collegare il tuo negozio

1. Apri <b>Channels</b> nel menu. La pagina <b>Sales Channels</b> elenca i canali che hai già.
2. Premi <b>Add Sales Channel</b>.
3. Trova la card <b>Shopify</b> e premi <b>Connect</b>. Si apre una finestra: <b>Please enter your Shopify unique domain name</b>.
4. Digita il nome del tuo negozio nel campo. La parte finale, <b>.myshopify.com</b>, è già scritta per te. Puoi anche incollare l'indirizzo completo <b>xxx.myshopify.com</b> o l'indirizzo <b>admin.shopify.com/store/...</b>: teniamo solo il nome del negozio.
5. Premi <b>Connect</b>.
6. Shopify si apre in una nuova scheda e ti chiede di installare la nostra app. Premi <b>Install</b>. Se non si apre nessuna nuova scheda, il tuo browser l'ha bloccata: consenti i pop-up per il nostro sito, oppure usa <b>Click here to install</b> nella pagina del canale (vedi sotto).
7. Quando l'app è installata, Shopify mostra la pagina dell'app. Puoi chiudere quella scheda e tornare al tuo account dropshipping.

<!-- screenshot: la finestra di connessione Shopify con un nome negozio digitato e la desinenza .myshopify.com mostrata a destra -->

Il nuovo canale è ora nel tuo elenco <b>Sales Channels</b>. Aprilo per vedere la sua dashboard.

Se non sei sicuro di quale sia il nome del tuo negozio, premi il link <b>Click here</b> accanto a <b>Not sure which is your Shopify store name?</b> nella stessa finestra.

## Cosa configuriamo nel tuo negozio

Quando l'app è installata facciamo queste cose nel tuo negozio Shopify per te:

- Aggiungiamo una sede di evasione (fulfilment location) il cui nome inizia con <b>aiku-</b>. Lo stock dei prodotti che colleghi viene tenuto in questa sede, e Shopify ci invia gli ordini di quei prodotti tramite essa.
- Aggiungiamo questa sede al tuo profilo di spedizione predefinito, così Shopify può vendere e spedire da lì. Vedi [La sede di evasione AW in Shopify](/docs/shopify-fulfilment-location).
- Configuriamo i messaggi che Shopify ci invia quando arrivano gli ordini.

Non devi fare nulla di questo a mano.

## Verificare che il canale sia connesso

Apri il canale da <b>Channels</b>. Quando la nostra app è installata, compaiono tre piccole icone accanto al nome del negozio. Sposta il mouse su di esse per leggerne i nomi:

- <b>App installed</b>: la nostra app è installata e possiamo leggere il tuo negozio.
- <b>Exist in platform</b> e <b>Platform status</b>: la nostra sede di evasione è configurata nel tuo negozio.

Quando tutti e tre sono segni di spunta verdi, la dashboard mostra i riquadri <b>Orders</b> e <b>Products</b> e, nel menu a sinistra sotto il tuo canale, <b>My Products</b> e <b>Orders</b>. Ora puoi aggiungere prodotti: vedi [Gestire prodotti su Shopify](/docs/managing-products-on-shopify).

<!-- screenshot: la dashboard del canale con i tre segni di spunta verdi, il pulsante Fetch orders e i riquadri Orders e Products -->

## Se dice che il canale non è ancora connesso

Se vedi <b>Your channel is not connected yet to the platform. Please connect it to be able to synchronize your products.</b>, l'app non è stata installata in Shopify. Questo è il problema più comune. Succede quando la scheda Shopify è stata chiusa prima di premere <b>Install</b>, oppure quando il tuo browser ha bloccato la nuova scheda.

1. Accedi al tuo pannello Shopify nello stesso browser.
2. Sulla pagina del canale, clicca su <b>Click here to install</b>, alla fine di <b>Make sure you click the button "Install" in the Shopify dashboard to finalize the connection.</b>
3. Shopify si apre nella stessa scheda. Premi <b>Install</b>.
4. Torna alla pagina del canale e ricaricala.

Se ancora non funziona, puoi premere <b>Delete</b> accanto a <b>Or delete the channel and try again</b>, poi collegare di nuovo il negozio dall'inizio.

## Eliminare o reimpostare un canale

- <b>Delete channel</b>: mostrato su un canale connesso. Chiede <b>Are you sure you want to delete channel</b>; premi <b>Yes, delete channel</b> per confermare. Se colleghi di nuovo lo stesso negozio Shopify in seguito, possiamo riaprire il vecchio canale con i suoi prodotti invece di crearne uno nuovo.
- <b>Reset channel</b>: mostrato quando il canale ha perso la connessione ma ha ancora prodotti. Configura di nuovo la sede di evasione e i messaggi d'ordine. I tuoi prodotti dovranno poi essere ricollegati. Gli ordini già effettuati non vengono modificati.

## Quando qualcosa non funziona

**"This does not look like a Shopify store name. Use the .myshopify.com name, not your own domain."** Hai digitato il tuo dominio personale, come <b>mystore.com</b>. Digita invece il nome <b>myshopify.com</b>. Lo trovi in Shopify sotto <b>Settings</b>, <b>Domains</b>.

**"Shopify shop ... not found".** Nessun negozio Shopify ha quel nome. Controlla l'ortografia. Il nome è spesso un codice di lettere e numeri, non il nome del tuo negozio.

**"Shopify shop ... already exists, please use other name".** Questo negozio è già collegato a un account dropshipping. Controlla il tuo elenco <b>Sales Channels</b>. Se è collegato a un altro dei tuoi account, eliminalo lì prima.

**"Shop name cannot contain spaces".** Il nome myshopify.com non ha mai spazi. Copialo da Shopify invece di digitare il nome del tuo negozio.

**Ho collegato Shopify ma dice ancora non connesso.** L'app non è stata installata. Segui i passaggi in <b>Se dice che il canale non è ancora connesso</b> sopra.

**"Click here to install" mostra "Something went wrong".** Il canale ha perso il collegamento al tuo negozio. Premi <b>Delete</b> accanto a <b>Or delete the channel and try again</b>, poi collega di nuovo il negozio.

**Dopo aver premuto Connect, non si apre nessuna scheda Shopify.** Il tuo browser ha bloccato la nuova scheda. Consenti i pop-up per il nostro sito, oppure apri il canale e usa <b>Click here to install</b>.

**I prodotti non si vendono in Shopify, o risultano esauriti.** Controlla che la sede <b>aiku-</b> sia nel tuo profilo di spedizione. Vedi [La sede di evasione AW in Shopify](/docs/shopify-fulfilment-location).

Se nulla di questo aiuta, chiedici nella chat del nostro sito e dicci il tuo nome myshopify.com.

<aside class="wayfinder"><strong>Dove cliccare</strong>
<ul>
<li><b>Collegare un nuovo negozio:</b> <b>Channels</b> → <b>Add Sales Channel</b> → <b>Shopify</b> → <b>Connect</b>.</li>
<li><b>Completare un'installazione:</b> apri il canale → <b>Click here to install</b> → <b>Install</b> in Shopify.</li>
<li><b>Controllare la connessione:</b> apri il canale → le tre icone accanto al suo nome.</li>
<li><b>Cambiare le impostazioni di stock:</b> apri il canale → <b>Manage Sales Channel</b>.</li>
</ul>
</aside>
