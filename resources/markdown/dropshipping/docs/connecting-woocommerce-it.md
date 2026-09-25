---
title: Collegare il tuo negozio WooCommerce
summary: Collega il tuo negozio WooCommerce al tuo account dropshipping, risolvi i messaggi che potresti vedere durante il collegamento e ricollega un negozio che ha smesso di rispondere.
date: 2026-09-25
source_date: 2026-09-25
tags: woocommerce, wordpress, sales channel, connect, api keys
category: sales-channels
series: woocommerce
order: 1
shops: awd, dssk, dse
---

<aside class="tldr">
Vai su <b>Channels</b>, premi <b>Add Sales Channel</b>, poi <b>Connect</b> sulla scheda Woocommerce. Digita un nome per il tuo negozio e premi <b>Next</b>. Digita l'indirizzo del tuo negozio, premi <b>Auth Store</b>, approva la nostra app su WooCommerce, torna indietro e premi <b>Next</b>. Se il tuo hosting blocca le chiavi automatiche, puoi crearle su WooCommerce e incollarle tu stesso.
</aside>

## Prima di iniziare

Controlla prima queste cose sul tuo sito WordPress. La maggior parte delle connessioni fallite dipende da una di esse.

- WooCommerce è installato e attivo.
- L'indirizzo del tuo negozio inizia con <b>https://</b>. Non ci colleghiamo a negozi senza un certificato SSL valido.
- Su WordPress, <b>Settings</b>, <b>Permalinks</b> non è impostato su <b>Plain</b>. Con i permalink Plain l'API di WooCommerce non può essere trovata.
- Il tuo plugin di sicurezza, il firewall o Cloudflare non bloccano le richieste a <b>/wp-json/</b>. Comunichiamo con il tuo negozio tramite questo indirizzo.
- Puoi accedere al tuo pannello WordPress come amministratore. Ti serve per approvare la connessione.
- Facoltativo ma utile: imposta l'unità di peso che vuoi in WooCommerce (<b>Settings</b>, <b>Products</b>) prima di collegarti. La leggiamo quando ti colleghi e inviamo i pesi dei prodotti in quell'unità.

WooCommerce è disponibile su tutti i nostri siti dropshipping. Collegalo dal sito dove hai il tuo account dropshipping.

## Collegare il tuo negozio

1. Apri <b>Channels</b> nel menu. La pagina <b>Sales Channels</b> elenca i canali che hai già.
2. Premi <b>Add Sales Channel</b>.
3. Trova la scheda <b>Woocommerce</b> e premi <b>Connect</b>. Si apre una finestra.
4. In <b>Woocommerce Account Name</b>, digita un nome per il tuo negozio, ad esempio il nome del tuo negozio. Il nome è obbligatorio, ed è il nome che vedrai nel tuo elenco di canali. Premi <b>Next</b>.
5. Sotto <b>Authentication Settings</b>, digita l'indirizzo completo del tuo negozio, ad esempio <b>https://mystore.com</b>. Premi <b>Auth Store</b>.
6. Verifichiamo prima che il tuo negozio risponda. Se risponde, si apre una nuova scheda sul tuo sito WordPress. Accedi se WordPress te lo chiede.
7. WooCommerce mostra che <b>AW Connect</b> richiede l'accesso <b>Read/Write</b>. Controlla di essere connesso al negozio giusto, poi premi <b>Approve</b>.
8. La scheda mostra un breve messaggio e si chiude da sola. Torna alla finestra nel tuo account dropshipping e premi <b>Next</b>.
9. Vedi <b>Connected!</b> Premi <b>OK</b>.

<!-- screenshot: la finestra di connessione Woocommerce, passo Authentication Settings con il campo dell'indirizzo del negozio e il pulsante Auth Store -->

<!-- screenshot: la pagina di approvazione WooCommerce con AW Connect che richiede l'accesso Read/Write e il pulsante Approve -->

Completa tutti i passaggi entro un'ora. Dopo, dimentichiamo il nome e le chiavi con cui hai iniziato, e devi ricominciare da <b>Connect</b>.

Se il tuo browser blocca la nuova scheda, la pagina di approvazione si apre nella stessa scheda e lasci la finestra di connessione. Consenti i popup per il nostro sito, poi ricomincia da <b>Connect</b>.

## Se il tuo negozio non è riuscito a inviarci le chiavi

Quando approvi, WooCommerce invia le nuove chiavi dal tuo hosting ai nostri server. Alcune società di hosting lo bloccano. Vedi allora <b>Your store approved the connection but could not send us the keys</b>, e <b>Next</b> mostra <b>You are not connected yet</b>.

Puoi comunque collegarti incollando le chiavi tu stesso:

1. Su WordPress, vai su <b>WooCommerce</b>, <b>Settings</b>, <b>Advanced</b>, <b>REST API</b>.
2. Aggiungi una chiave. Dalle una descrizione qualsiasi, scegli il tuo utente amministratore e imposta <b>Permissions</b> su <b>Read/Write</b>. Genera la chiave.
3. Copia la <b>Consumer key</b> (inizia con ck_) e il <b>Consumer secret</b> (inizia con cs_). WooCommerce mostra il secret solo una volta.
4. Nella finestra Woocommerce nel tuo account dropshipping, assicurati che l'indirizzo del tuo negozio sia ancora nel campo dell'indirizzo.
5. Premi <b>My store could not send the keys, let me paste them</b>.
6. Incolla la chiave e il secret, e premi <b>Use these keys</b>.

<!-- screenshot: il passo Authentication Settings con la sezione delle chiavi manuali aperta, che mostra i campi ck_ e cs_ e il pulsante Use these keys -->

## Dopo il collegamento

Il tuo negozio ora appare nella pagina <b>Sales Channels</b>. Clicca sul suo nome per aprire la dashboard del canale. Lì vedi <b>Orders</b>, <b>Clients</b> e <b>Products</b>. Premi <b>View all</b> sotto <b>Products</b> per aggiungere prodotti. Vedi [Gestire i prodotti su WooCommerce](managing-products-on-woocommerce).

Quando ti colleghi, aggiungiamo anche due webhook al tuo negozio: uno per i nuovi ordini e uno per i prodotti eliminati. Non eliminarli su WooCommerce, <b>Settings</b>, <b>Advanced</b>, <b>Webhooks</b>. Senza di essi, i nuovi ordini non ci arrivano subito.

Importiamo gli ordini pagati, con lo stato <b>Processing</b> su WooCommerce e con un paese di spedizione. Se un ordine manca, premi <b>Fetch orders</b> sulla dashboard del canale. Controlla il tuo negozio per gli ordini degli ultimi 14 giorni che non ci sono ancora arrivati.

Con <b>Manage Sales Channel</b> puoi cambiare il nome del negozio, le tue impostazioni di scorta e la tua regola di prezzo per i nuovi prodotti.

## Collegare di nuovo lo stesso negozio

Se elimini il tuo canale WooCommerce e più avanti colleghi di nuovo lo stesso indirizzo di negozio, riportiamo indietro lo stesso canale, con i suoi prodotti e ordini. Non riparti da zero.

## Quando il tuo negozio smette di rispondere

Controlliamo regolarmente il tuo negozio collegato. Se il tuo negozio smette di rispondere, o le chiavi smettono di funzionare, il canale mostra <b>Your channel is not connected yet to the platform</b>. Sopra, potresti vedere il messaggio di errore inviato dal tuo negozio. Mentre questo è visibile, il tuo elenco prodotti è nascosto e non puoi caricare prodotti sul tuo negozio.

Per risolvere:

1. Assicurati che il tuo sito web sia online e che tu possa aprirlo nel browser.
2. Sulla pagina del canale, premi <b>Try to reconnect</b>. Si apre il tuo sito WordPress. Accedi come amministratore e premi di nuovo <b>Approve</b>. Questo crea nuove chiavi.
3. Se ancora non funziona, premi <b>Test Connection</b> per controllare di nuovo la connessione.
4. Come ultimo passo, premi <b>Delete</b> e collega di nuovo il negozio. I tuoi prodotti e ordini tornano quando usi lo stesso indirizzo di negozio.

Se il tuo negozio continua a fallire a lungo, smettiamo di controllarlo. Ricomincia a funzionare quando lo ricolleghi.

<!-- screenshot: l'avviso di non collegato su un canale WooCommerce con i pulsanti Try to reconnect, Test Connection e Delete -->

## Quando qualcosa non funziona

Questi sono i messaggi che potresti vedere quando premi <b>Auth Store</b>, e cosa fare.

- <b>We could not resolve your store domain</b>: l'indirizzo è scritto male o il dominio non è attivo. Copia l'indirizzo dal tuo browser quando il tuo negozio è aperto.
- <b>Your store SSL certificate could not be verified</b>: il tuo certificato è scaduto, autofirmato o incompleto. Chiedi alla tua società di hosting di rinnovarlo o correggerlo.
- <b>Your store refused our connection</b> oppure <b>Your store did not answer within 2 minutes</b>: il tuo hosting o firewall blocca i nostri server. Il messaggio elenca i nostri indirizzi IP. Inviali alla tua società di hosting e chiedi loro di consentirli.
- <b>Your store redirects to ...</b>: il tuo negozio si trova a un indirizzo diverso, ad esempio con o senza www. Inserisci l'indirizzo indicato nel messaggio.
- <b>Your store url redirects in a loop</b>: inserisci l'indirizzo finale del tuo negozio, quello che vedi nel browser dopo che la pagina si è caricata.
- <b>We could not find the WooCommerce API on this store</b>: WooCommerce non è attivo, la sua REST API è disattivata, oppure i tuoi permalink sono impostati su <b>Plain</b>. Cambia i permalink su WordPress, <b>Settings</b>, <b>Permalinks</b>.
- <b>Your store answered with 401</b> o <b>403</b> <b>and blocked our request</b>: un plugin di sicurezza, un firewall o Cloudflare ci blocca. Consenti le richieste a <b>/wp-json/</b> in quello strumento.
- <b>Your WooCommerce store returned an error 500</b> (o un altro numero che inizia con 5): il tuo sito ha un errore. Controlla il tuo log degli errori di hosting, o chiedi alla tua società di hosting, poi riprova.
- <b>Your store answered with ... but did not return the WooCommerce REST API</b>: l'indirizzo non punta al tuo sito WordPress. Controlla di aver inserito il negozio stesso, non una landing page o un altro sito.
- <b>You are not connected yet, click auth store to connect and follow the instructions</b>: hai premuto <b>Next</b> prima di approvare su WooCommerce, l'approvazione non ci è arrivata, oppure è passata più di un'ora. Premi di nuovo <b>Auth Store</b>, oppure incolla tu stesso le chiavi come mostrato sopra.
- <b>We can't access your store, make sure you already put correct store url</b>: abbiamo ricevuto le chiavi, ma non siamo riusciti a usarle su quell'indirizzo. Controlla l'indirizzo, e che le chiavi abbiano il permesso <b>Read/Write</b>.

I problemi sul tuo sito web, come il negozio non disponibile, lento o che ci blocca, possono essere risolti solo da te o dalla tua società di hosting. Non possiamo cambiare le impostazioni sul tuo sito web.
