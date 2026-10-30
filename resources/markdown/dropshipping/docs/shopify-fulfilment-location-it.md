---
title: La sede di evasione AW su Shopify
summary: Cosa fanno la sede aiku- e il profilo di spedizione AW Connect shipping nel tuo negozio Shopify, quali tariffe di spedizione vedono i tuoi clienti per i nostri prodotti, e cosa fare quando i prodotti risultano esauriti o gli ordini non ci arrivano.
date: 2026-10-30
source_date: 2026-10-30
tags: shopify, sede di evasione, profilo di spedizione, tariffe di spedizione, mercati, scorte, esaurito
category: sales-channels
series: shopify
order: 3
shops: awd, dssk, dse
---

<aside class="tldr">
Quando installi la nostra app, aggiungiamo al tuo negozio Shopify una sede di evasione chiamata <b>aiku-</b> e un profilo di spedizione, <b>AW Connect shipping</b>, con i nostri prodotti e i nostri prezzi di spedizione. Al checkout i tuoi clienti vedono la spedizione <b>AW Standard</b> per i nostri prodotti. Di solito non devi fare nulla.
</aside>

## A cosa serve la sede

Shopify tiene le scorte per sede. I nostri prodotti vengono spediti dal nostro magazzino, quindi aggiungiamo il nostro magazzino al tuo negozio come sede di evasione.

- Il suo nome è <b>aiku-</b>, poi il codice del nostro sito, poi il codice del tuo canale tra parentesi. Per esempio <b>aiku-awd (sho-ab12cd-3e)</b>.
- Le scorte di ogni prodotto che connetti sono tenute in questa sede. Le aggiorniamo noi per te.
- Quando un cliente acquista uno di questi prodotti, Shopify ci invia una richiesta di evasione da questa sede. È così che l'ordine ci arriva.

Non eliminare questa sede e non spostare i nostri prodotti in un'altra sede. Se lo fai, le scorte smettono di aggiornarsi e gli ordini smettono di arrivarci.

## Spedizione configurata per te

All'installazione dell'app aggiungiamo un profilo di spedizione al tuo negozio. Il suo nome è <b>AW Connect shipping</b>, poi il codice del tuo canale tra parentesi, per esempio <b>AW Connect shipping (sho-ab12cd-3e)</b>. Funziona allo stesso modo sia che il tuo negozio usi i profili di spedizione sia la spedizione per mercati.

- Spedisce solo dalla sede <b>aiku-</b>.
- Contiene ogni prodotto che colleghi da noi. Aggiungiamo i prodotti nuovi e togliamo quelli rimossi per te, di solito entro dieci minuti.
- Le sue tariffe sono i nostri prezzi di spedizione, per paese e peso del pacco, nella valuta del tuo negozio. Al checkout appaiono come <b>AW Standard</b>. Quando cambiamo i nostri prezzi, il profilo si aggiorna entro un giorno.
- Dove calcoliamo la spedizione a mano (pacchi molto pesanti, paesi fuori dalle nostre zone), il profilo usa il nostro prezzo più alto per quella zona, così i tuoi clienti possono sempre ordinare. Quello che ti addebitiamo per l'ordine non cambia.
- Shopify non ti permette di modificare queste tariffe. Puoi disattivarle, ma allora i tuoi clienti non vedono nessuna spedizione per i nostri prodotti, a meno che una delle tue tariffe spedisca dalla sede <b>aiku-</b>.

Gli altri tuoi prodotti mantengono le tue impostazioni di spedizione e le tue tariffe. Non le modifichiamo.

## Negozi con spedizione per mercati

Shopify sta passando tutti i negozi dai profili di spedizione alla spedizione per mercati. Un negozio così mostra <b>Shipping has moved to Markets</b> in <b>Settings</b>, <b>Shipping and delivery</b>. Il nostro profilo appare in ogni mercato come un servizio della nostra app, accanto alle tue tariffe.

Se una delle tariffe dei tuoi mercati spedisce dalla sede <b>aiku-</b>, per esempio perché l'hai aggiunta a mano come diceva una versione precedente di questa guida, i tuoi clienti vedono quella tariffa oltre ad <b>AW Standard</b>. Per mostrare solo la nostra, togli la sede <b>aiku-</b> da quella tariffa:

1. Nel tuo pannello Shopify, apri <b>Markets</b> e poi un mercato in cui vendi.
2. In <b>Shipping</b>, accanto a una tariffa, premi <b>...</b> e scegli <b>Edit</b>.
3. In <b>Ships from</b>, togli la sede <b>aiku-</b> e salva.

I nostri prodotti sono venduti solo nei paesi inclusi nei tuoi mercati. Un cliente in un paese che nessuno dei tuoi mercati copre li vede esauriti.

## Le tue scorte dei nostri prodotti

I prodotti nel nostro profilo di spedizione partono solo dalla sede <b>aiku-</b>, quindi Shopify non li spedisce dalle tue sedi. Se hai tu stesso alcuni dei nostri prodotti, per esempio un articolo non usato che un cliente ti ha restituito, mettili in vendita nel tuo negozio come prodotto separato e spedisci tu quegli ordini.

## Controlla tu stesso

Fallo se i nostri prodotti risultano esauriti nel tuo negozio, oppure il checkout non mostra nessuna tariffa di spedizione per essi.

1. Nel tuo pannello Shopify, apri <b>Settings</b>.
2. Apri <b>Shipping and delivery</b>.
3. Cerca <b>AW Connect shipping</b>, sotto la nostra app. Deve spedire dalla sede <b>aiku-</b> e contenere i nostri prodotti.
4. Se manca, o manca un prodotto al suo interno, aspetta un giorno: controlliamo ogni negozio ogni notte. Se non torna, contattaci.

<!-- screenshot: Shopify Shipping and delivery, il profilo di spedizione AW Connect shipping sotto l'app, con la sede aiku-awd -->

Shopify cambia i suoi menu di tanto in tanto, quindi i nomi nel tuo pannello potrebbero essere leggermente diversi.

## Quando qualcosa non va

**I nostri prodotti risultano esauriti su Shopify.** Controlla il profilo di spedizione <b>AW Connect shipping</b> come sopra, e che il paese del cliente sia in uno dei tuoi mercati. Controlla anche che il prodotto sia connesso: in <b>My Products</b> deve mostrare una stretta di mano verde. Vedi [Gestire i prodotti su Shopify](/docs/managing-products-on-shopify).

**Errore di caricamento "No Shopify location, the AW fulfilment service is not installed on this store so stock can not be sent".** Manca la sede <b>aiku-</b>. Apri il canale. Se vedi <b>Click here to install</b>, premilo e installa l'app su Shopify. Se il canale mostra <b>Reset channel</b>, usalo per creare di nuovo la sede.

**Messaggio di log "The specified inventory item is not stocked at the location".** Il prodotto su Shopify non ha scorte nella sede <b>aiku-</b>, per esempio perché è stato spostato in un'altra sede su Shopify. Ricollega il prodotto con <b>Connect with other product</b> in <b>My Products</b>.

**Gli ordini non ci arrivano.** Shopify ci invia solo gli ordini per gli articoli con scorte nella sede <b>aiku-</b>. Se il prodotto aveva scorte nella tua sede, Shopify si aspetta che lo spedisca tu. Vedi [I tuoi ordini Shopify e il loro stato](/docs/shopify-order-status).

<aside class="wayfinder"><strong>Dove cliccare</strong>
<ul>
<li><b>Controlla il nostro profilo di spedizione:</b> pannello Shopify → <b>Settings</b> → <b>Shipping and delivery</b> → <b>AW Connect shipping</b>.</li>
<li><b>Spedizione per mercati:</b> pannello Shopify → <b>Markets</b> → il tuo mercato → <b>Shipping</b>.</li>
<li><b>Controlla il canale:</b> <b>Channels</b> → il tuo negozio Shopify → le tre icone accanto al suo nome.</li>
</ul>
</aside>
