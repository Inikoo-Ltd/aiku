---
title: La sede di evasione AW su Shopify
summary: Cosa fa la sede aiku- nel tuo negozio Shopify, come viene aggiunta per te al tuo profilo di spedizione, e cosa fare quando i prodotti risultano esauriti o gli ordini non ci arrivano.
date: 2026-09-25
source_date: 2026-09-25
tags: shopify, sede di evasione, profilo di spedizione, scorte, esaurito
category: sales-channels
series: shopify
order: 3
shops: awd, dssk, dse
---

<aside class="tldr">
Quando installi la nostra app, aggiungiamo una sede di evasione al tuo negozio Shopify. Il suo nome inizia con <b>aiku-</b>. La aggiungiamo per te al tuo profilo di spedizione predefinito, quindi di solito non devi fare nulla. Se usi più di un profilo di spedizione, controlla che la sede <b>aiku-</b> sia nel profilo dei nostri prodotti, altrimenti Shopify li mostra come esauriti e non ci invia i loro ordini.
Se il tuo negozio usa la spedizione per mercati, Shopify non ci permette di modificarne le impostazioni di spedizione: aggiungi la sede <b>aiku-</b> in <b>Ships from</b> delle tariffe di spedizione dei tuoi mercati, come spiegato sotto.
</aside>

## A cosa serve la sede

Shopify tiene le scorte per sede. I nostri prodotti vengono spediti dal nostro magazzino, quindi aggiungiamo il nostro magazzino al tuo negozio come sede di evasione.

- Il suo nome è <b>aiku-</b>, poi il codice del nostro sito, poi il codice del tuo canale tra parentesi. Per esempio <b>aiku-awd (sho-ab12cd-3e)</b>.
- Le scorte di ogni prodotto che connetti sono tenute in questa sede. Le aggiorniamo noi per te.
- Quando un cliente acquista uno di questi prodotti, Shopify ci invia una richiesta di evasione da questa sede. È così che l'ordine ci arriva.

Non eliminare questa sede e non spostare i nostri prodotti in un'altra sede. Se lo fai, le scorte smettono di aggiornarsi e gli ordini smettono di arrivarci.

## Aggiunta al tuo profilo di spedizione per te

Shopify vende solo le scorte dalle sedi presenti in un profilo di spedizione. Quando l'app viene installata, aggiungiamo la sede <b>aiku-</b> per te:

- al tuo profilo di spedizione predefinito, oppure
- se il tuo negozio ha ancora una sede <b>aiku-dse</b> più vecchia da una connessione precedente, a ogni profilo di spedizione in cui si trova quella sede più vecchia.

Se la sede è già in uno dei tuoi profili di spedizione, non cambiamo nulla.

Se il tuo negozio usa la spedizione per mercati (le nuove impostazioni di spedizione di Shopify, che sostituiscono i profili di spedizione), Shopify non ci permette di modificare le tue impostazioni di spedizione. Aggiungi tu la sede <b>aiku-</b> alle tariffe di spedizione dei tuoi mercati, una sola volta: vedi <b>Negozi con spedizione per mercati</b> più sotto.

Non devi più aggiungere la sede a mano, come dicevano le guide più vecchie.

## Negozi con spedizione per mercati

Shopify sta passando tutti i negozi dai profili di spedizione alla spedizione per mercati. Un negozio così mostra <b>Shipping has moved to Markets</b> in <b>Settings</b>, <b>Shipping and delivery</b>. Lì ogni tariffa di spedizione ha la sua lista di sedi da cui spedisce, e Shopify vende i nostri prodotti solo se una tariffa spedisce dalla sede <b>aiku-</b>. Fino ad allora risultano esauriti.

1. Nel tuo pannello Shopify, apri <b>Markets</b> e poi un mercato in cui vendi.
2. In <b>Shipping</b>, apri l'elenco delle opzioni di spedizione. Accanto a una tariffa, premi <b>...</b> e scegli <b>Edit</b>.
3. In <b>Ships from</b>, aggiungi la sede <b>aiku-</b>. Tieni le tue sedi se spedisci anche da lì.
4. Salva. Fai lo stesso per ogni tariffa di ogni mercato in cui vendi.

<!-- screenshot: Shopify Markets, una tariffa di spedizione il cui Ships from mostra Shop location e aiku-awd -->

La sede <b>aiku-</b> è gestita dalla nostra app, quindi non si può aprire da <b>Settings</b>, <b>Locations</b>. Aggiungila dalla tariffa di spedizione come sopra.

I nostri prodotti sono venduti solo nei paesi inclusi nei tuoi mercati. Un cliente in un paese che nessuno dei tuoi mercati copre li vede esauriti.

## Controlla tu stesso

Fallo se i nostri prodotti risultano esauriti nel tuo negozio, oppure il checkout non mostra nessuna tariffa di spedizione per essi.

1. Nel tuo pannello Shopify, apri <b>Settings</b>.
2. Apri <b>Shipping and delivery</b>.
3. Apri il profilo di spedizione in cui si trovano i nostri prodotti. Nella maggior parte dei negozi è il profilo generale.
4. Guarda le sedi da cui spedisce il profilo. La sede <b>aiku-</b> deve essere presente.
5. Se non c'è, aggiungila al profilo e salva.

<!-- screenshot: Shopify Shipping and delivery, un profilo di spedizione con la sede aiku-awd nel suo elenco di sedi -->

Shopify cambia i suoi menu di tanto in tanto, quindi i nomi potrebbero essere leggermente diversi nel tuo pannello.

Se hai creato un profilo di spedizione personalizzato per alcuni dei nostri prodotti, aggiungi la sede <b>aiku-</b> anche a quel profilo. Noi la aggiungiamo solo al profilo predefinito.

## Quando qualcosa non va

**I nostri prodotti risultano esauriti su Shopify.** Controlla il profilo di spedizione come sopra. Con la spedizione per mercati, controlla che le tariffe di spedizione dei tuoi mercati spediscano dalla sede <b>aiku-</b>, vedi <b>Negozi con spedizione per mercati</b>. Controlla anche che il prodotto sia connesso: in <b>My Products</b> deve mostrare una stretta di mano verde. Vedi [Gestire i prodotti su Shopify](/docs/managing-products-on-shopify).

**Errore di caricamento "No Shopify location, the AW fulfilment service is not installed on this store so stock can not be sent".** Manca la sede <b>aiku-</b>. Apri il canale. Se vedi <b>Click here to install</b>, premilo e installa l'app su Shopify. Se il canale mostra <b>Reset channel</b>, usalo per creare di nuovo la sede.

**Messaggio di log "The specified inventory item is not stocked at the location".** Il prodotto su Shopify non ha scorte nella sede <b>aiku-</b>, per esempio perché è stato spostato in un'altra sede su Shopify. Ricollega il prodotto con <b>Connect with other product</b> in <b>My Products</b>.

**Gli ordini non ci arrivano.** Shopify ci invia solo gli ordini per gli articoli con scorte nella sede <b>aiku-</b>. Se il prodotto aveva scorte nella tua sede, Shopify si aspetta che lo spedisca tu. Vedi [I tuoi ordini Shopify e il loro stato](/docs/shopify-order-status).

<aside class="wayfinder"><strong>Dove cliccare</strong>
<ul>
<li><b>Spedizione per mercati:</b> pannello Shopify → <b>Markets</b> → il tuo mercato → <b>Shipping</b> → la tariffa → <b>Edit</b> → <b>Ships from</b>.</li>
<li><b>Controlla la sede su Shopify:</b> pannello Shopify → <b>Settings</b> → <b>Shipping and delivery</b> → il tuo profilo di spedizione.</li>
<li><b>Controlla il canale:</b> <b>Channels</b> → il tuo negozio Shopify → le tre icone accanto al suo nome.</li>
</ul>
</aside>
