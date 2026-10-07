---
title: Klanten beheren
summary: Voeg de kopers waar u naar verzendt toe als klanten van een Manual/API-kanaal, één voor één of vanuit een spreadsheet, en wijzig of deactiveer ze later.
date: 2026-09-25
source_date: 2026-09-25
tags: handmatig, api, klanten, afnemers, afleveradres, importeren
category: orders
series: manual
order: 3
shops: awd, dssk, dse
---

<aside class="tldr">
Een klant is degene aan wie u verkoopt: wij sturen het pakket naar het adres van de klant. Open in een Manual/API-kanaal <b>Clients</b> en druk op <b>Create Customer Client</b> om er een toe te voegen, of op <b>Upload File</b> om er meerdere vanuit een spreadsheet toe te voegen. Elke bestelling in een Manual/API-kanaal wordt aangemaakt vanaf een klantpagina.
</aside>

## Waar klanten staan

Klanten horen bij één kanaal. Open uw Manual/API-kanaal in het menu en dan <b>Clients</b>, of druk op <b>View all</b> in het vak <b>Clients</b> van de kanaalpagina.

De lijst toont <b>Name</b>, <b>Email</b>, <b>phone</b>, <b>location</b> en <b>since</b> (wanneer u ze toevoegde). Er zijn twee tabbladen:

- <b>Active</b>: uw huidige klanten.
- <b>Inactive</b>: klanten die u heeft uitgeschakeld.

Alleen Manual/API-kanalen hebben een pagina <b>Clients</b>. Bij gekoppelde kanalen komen de gegevens van de koper met elke bestelling uit uw winkel binnen.

## Een klant toevoegen

1. Druk op de pagina <b>Clients</b> op <b>Create Customer Client</b>.
2. Het formulier <b>New client</b> opent. Vul in:
   - <b>Company</b>: als uw koper een bedrijf is.
   - <b>Contact name</b>: de naam voor het verzendlabel.
   - <b>Email</b>
   - <b>phone</b>: minstens 6 tekens als u dit invult.
   - <b>Address</b>: het afleveradres. Het land begint als het land van onze winkel. Wijzig dit als uw koper elders woont.
3. Druk op <b>Save</b>.

<!-- screenshot: het formulier New client met Company, Contact name, Email, phone en Address -->

De klantpagina opent. Van hieruit kunt u op <b>Create Order</b> drukken. Zie [Handmatig bestellingen plaatsen](/docs/placing-orders-manually).

Het adres moet volledig zijn voor het gekozen land. Het formulier laat weten wat ontbreekt, bijvoorbeeld <b>The address is required</b>, <b>The town is required</b>, <b>The postal code is required</b> of <b>The province is required</b>. Sommige landen hebben geen postcode of geen plaats, en dan vraagt het formulier daar niet naar.

## Meerdere klanten toevoegen vanuit een spreadsheet

1. Druk op de pagina <b>Clients</b> op <b>Upload File</b>.
2. Download in het venster <b>Import your clients</b> de sjabloon.
3. Vul één klant per rij in, met deze kolommen: contact_name, company_name, email, phone, address_line_1, address_line_2, postal_code, locality, country_code. Elke kolom behalve address_line_2 moet ingevuld zijn, en email moet een geldig e-mailadres zijn.
4. Gebruik voor country_code de tweeletterige landcode, bijvoorbeeld GB, ES, DE of FR.
5. Upload het bestand.

## Een klant wijzigen

Open de klant en druk op <b>Edit</b>. Op de pagina <b>Edit client</b> kunt u <b>Company</b>, <b>Contact name</b>, <b>Email</b>, <b>phone</b> en <b>Delivery Address</b> wijzigen.

Een nieuw adres wordt gebruikt voor nieuwe bestellingen. Voor een bestelling die nog in het mandje zit, kunt u het afleveradres ook wijzigen op de mandjepagina met <b>Edit</b> onder het adres.

## Een klant deactiveren

Zet op de pagina <b>Edit client</b> <b>status</b> uit. De klant verhuist naar het tabblad <b>Inactive</b>. Eerdere bestellingen blijven staan. Zet <b>status</b> weer aan om de klant opnieuw te gebruiken.

## Klanten via de API

Uw eigen systeem kan klanten via de API tonen, aanmaken, wijzigen en deactiveren, en er bestellingen voor aanmaken. Zie [Het Manual/API-kanaal](/docs/manual-and-api-channel).

## Als er iets misgaat

- <b>Ik kan Create Customer Client niet vinden.</b> De knop staat alleen bij Manual/API-kanalen, en alleen zolang het kanaal open is. Andere kanalen hebben geen pagina <b>Clients</b>.
- <b>Het formulier zegt dat plaats, postcode of provincie verplicht is.</b> Het adres is niet volledig voor dat land. Vul het genoemde veld in. Controleer of het land klopt.
- <b>Het formulier zegt dat de e-mail niet geldig is.</b> Controleer op spaties en een ontbrekende @ of punt. U kunt de e-mail ook leeg laten.
- <b>Mijn klant staat niet in de lijst.</b> Kijk in het tabblad <b>Inactive</b>. Controleer ook of u in het juiste kanaal zit: klanten van het ene kanaal verschijnen niet in een ander.
- <b>Mijn spreadsheetupload is voor sommige rijen mislukt.</b> Controleer of elke verplichte kolom is ingevuld (alleen address_line_2 mag leeg zijn), de e-mail geldig is, de country_code een tweeletterige code is en het adres de velden heeft die het land nodig heeft.
