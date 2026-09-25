---
title: Gestire i clienti
summary: Aggiungi come clienti gli acquirenti a cui spedisci, in un canale Manual/API, uno alla volta o da un foglio di calcolo, e modificali o disattivali più avanti.
date: 2026-09-25
source_date: 2026-09-25
tags: manual, api, clients, customers, delivery address, import
category: orders
series: manual
order: 3
shops: awd, dssk, dse
---

<aside class="tldr">
Un cliente è la persona a cui vendi: inviamo il pacco all'indirizzo del cliente. In un canale Manual/API, apri <b>Clients</b> e premi <b>Create Customer Client</b> per aggiungerne uno, oppure <b>Upload File</b> per aggiungerne molti da un foglio di calcolo. Ogni ordine in un canale Manual/API viene creato dalla pagina di un cliente.
</aside>

## Dove si trovano i clienti

I clienti appartengono a un canale. Apri il tuo canale Manual/API nel menu e poi <b>Clients</b>, oppure premi <b>View all</b> sul riquadro <b>Clients</b> della pagina del canale.

L'elenco mostra <b>Name</b>, <b>Email</b>, <b>phone</b>, <b>location</b> e <b>since</b> (da quando li hai aggiunti). Ha due schede:

- <b>Active</b>: i tuoi clienti attuali.
- <b>Inactive</b>: i clienti che hai disattivato.

Solo i canali Manual/API hanno una pagina <b>Clients</b>. Nei canali collegati, i dati dell'acquirente arrivano con ogni ordine dal tuo negozio.

## Aggiungere un cliente

1. Nella pagina <b>Clients</b> premi <b>Create Customer Client</b>.
2. Si apre il modulo <b>New client</b>. Compila:
   - <b>Company</b>: se il tuo acquirente è un'azienda.
   - <b>Contact name</b>: il nome per l'etichetta di spedizione.
   - <b>Email</b>
   - <b>phone</b>: almeno 6 caratteri, se lo compili.
   - <b>Address</b>: l'indirizzo di consegna. Il paese parte con il paese del nostro negozio. Cambialo se il tuo acquirente vive altrove.
3. Premi <b>Save</b>.

<!-- screenshot: il modulo New client con Company, Contact name, Email, phone e Address -->

Si apre la pagina del cliente. Da qui puoi premere <b>Create Order</b>. Vedi [Creare ordini manualmente](/docs/placing-orders-manually).

L'indirizzo deve essere completo per il paese scelto. Il modulo indica cosa manca, ad esempio <b>The address is required</b>, <b>The town is required</b>, <b>The postal code is required</b> o <b>The province is required</b>. Alcuni paesi non hanno codice postale o città, e in quel caso il modulo non li richiede.

## Aggiungere molti clienti da un foglio di calcolo

1. Nella pagina <b>Clients</b> premi <b>Upload File</b>.
2. Nella finestra <b>Import your clients</b>, scarica il modello.
3. Compila un cliente per riga, con queste colonne: contact_name, company_name, email, phone, address_line_1, address_line_2, postal_code, locality, country_code. Ogni colonna tranne address_line_2 deve essere compilata, e l'email deve essere un indirizzo email valido.
4. Per country_code usa il codice paese di due lettere, ad esempio GB, ES, DE o FR.
5. Carica il file.

## Modificare un cliente

Apri il cliente e premi <b>Edit</b>. La pagina <b>Edit client</b> ti permette di cambiare <b>Company</b>, <b>Contact name</b>, <b>Email</b>, <b>phone</b> e <b>Delivery Address</b>.

Un nuovo indirizzo viene usato per i nuovi ordini. Per un ordine ancora nel carrello, puoi anche cambiare l'indirizzo di consegna nella pagina del carrello con <b>Edit</b> sotto l'indirizzo.

## Disattivare un cliente

Nella pagina <b>Edit client</b>, disattiva <b>status</b>. Il cliente si sposta nella scheda <b>Inactive</b>. I suoi ordini passati restano. Riattiva <b>status</b> per usarli di nuovo.

## Clienti tramite l'API

Il tuo sistema può elencare, creare, modificare e disattivare i clienti tramite l'API, e creare ordini per loro. Vedi [Il canale Manual/API](/docs/manual-and-api-channel).

## Quando qualcosa non funziona

- <b>Non trovo Create Customer Client.</b> Il pulsante è presente solo nei canali Manual/API, e solo mentre il canale è aperto. Gli altri canali non hanno una pagina <b>Clients</b>.
- <b>Il modulo dice che città, codice postale o provincia sono obbligatori.</b> L'indirizzo non è completo per quel paese. Compila il campo indicato. Controlla che il paese sia corretto.
- <b>Il modulo dice che l'email non è valida.</b> Controlla che non ci siano spazi e che non manchi una @ o un punto. Puoi anche lasciare l'email vuota.
- <b>Il mio cliente non è nell'elenco.</b> Guarda nella scheda <b>Inactive</b>. Controlla anche di essere nel canale giusto: i clienti di un canale non compaiono in un altro.
- <b>Il caricamento del mio foglio di calcolo è fallito per alcune righe.</b> Controlla che ogni colonna obbligatoria sia compilata (solo address_line_2 può essere vuota), che l'email sia valida, che country_code sia un codice di due lettere e che l'indirizzo abbia i campi richiesti dal paese.
