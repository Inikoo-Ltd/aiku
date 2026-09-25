---
title: Produktdaten und Bilder exportieren
summary: Laden Sie die Produkte eines Kanals als CSV-Datei herunter, wählen Sie eigene Spalten und Filter, und laden Sie alle Produktfotos als eine Zip-Datei herunter.
date: 2026-09-25
source_date: 2026-09-25
tags: Produkte, Export, CSV, Bilder, Download, Datenfeed
category: products
shops: awd, dssk, dse
---

<aside class="tldr">
Unter <b>My Products</b> eines Kanals gibt es drei Download-Schaltflächen: <b>CSV</b> liefert die vollständigen Angaben jedes Produkts in der Liste, <b>⋮</b> lässt Sie die Spalten und Filter für eine kleinere CSV auswählen, und <b>Images</b> packt alle Fotos in eine Zip-Datei. Sie können auch ein einzelnes Produkt, eine Familie, Abteilung oder Kollektion von seiner Seite im <b>Catalogue</b> herunterladen.
</aside>

## Wo sich die Schaltflächen befinden

1. Öffnen Sie <b>Channels</b> im Menü, klicken Sie auf Ihren Kanal und öffnen Sie <b>My Products</b>.
2. Oben rechts sehen Sie eine Gruppe von Schaltflächen: <b>CSV</b>, <b>⋮</b> und <b>Images</b>.

Die Schaltflächen werden nur angezeigt, wenn der Kanal Produkte hat und nicht geschlossen ist, und nicht im Tab <b>My Bundles</b>. Die Dateien enthalten nur die Produkte dieses Kanals. Um einen anderen Kanal zu exportieren, öffnen Sie dessen <b>My Products</b>.

<!-- screenshot: die Schaltflächengruppe CSV / ⋮ / Images oben auf My Products -->

## Vollständige Produktangaben (CSV)

Drücken Sie <b>CSV</b>. Die Datei wird sofort heruntergeladen. Öffnen Sie sie in Excel, Google Sheets oder einem beliebigen Tabellenkalkulationsprogramm. Jede Zeile ist ein Produkt, jede Spalte eine Angabe.

Die Spalten sind:

- <b>Status</b>: <b>Active</b>, <b>Discontinuing</b> oder <b>Discontinued</b>.
- <b>Product code</b>, <b>Product user reference</b> (Ihre eigene Referenz für das Produkt, falls Sie eine festgelegt haben).
- <b>Department code</b>, <b>Department</b>, <b>Subdepartment code</b>, <b>Subdepartment</b>, <b>Family code</b>, <b>Family</b>.
- <b>Barcode</b>, <b>CPNP number</b> (EU-Kosmetiknummer, sofern das Produkt eine hat).
- <b>Price</b>: Ihr Preis für eine Außenverpackung (die Gebindegröße, die Sie bestellen). <b>Units per outer</b>, <b>Unit label</b>, <b>Unit price</b>.
- <b>Unit Name</b>: der Produktname.
- <b>Unit RRP</b>: unverbindliche Preisempfehlung für eine Einheit.
- <b>Unit net weight</b> und <b>Package weight (shipping)</b>, in Kilogramm. <b>Unit dimensions</b>.
- <b>Materials/Ingredients</b>.
- <b>Webpage description (html)</b> und <b>Webpage description (plain text)</b>.
- <b>Country of origin</b>, <b>Tariff code</b>, <b>Duty rate</b>, <b>HTS US</b>.
- <b>Stock</b>: eine Lagerbestandsstufe, keine Zahl: <b>Normal</b>, <b>Low</b> (unter 20), <b>VeryLow</b> (unter 5), <b>OutofStock</b>, <b>Discontinuing</b> oder <b>Discontinued</b>.
- <b>Images</b>: Links zu den Fotos in voller Größe, durch Kommas getrennt.
- <b>Data updated</b>, <b>Stock updated</b>, <b>Price updated</b>, <b>Images updated</b>: wann sich der jeweilige Teil zuletzt geändert hat.
- <b>Available Quantity</b>: die Anzahl der Einheiten am Lager. Sie ist 0, wenn das Produkt nicht zum Verkauf steht.
- <b>For sale</b>: <b>Yes</b> oder <b>No</b>.

Sets werden nicht mit einbezogen. Um sie einzubeziehen, öffnen Sie <b>⋮</b> und haken zuerst <b>Include bundles</b> an.

## Eigene Spalten und Filter

Drücken Sie <b>⋮</b> (<b>Other Export Options</b>). Ein Panel öffnet sich:

- <b>Bundles</b>: Haken Sie <b>Include bundles</b> an, um Ihre Sets hinzuzufügen. Standardmäßig ist dies ausgeschaltet und gilt für beide CSV-Downloads.
- <b>Columns to Export</b>: Haken Sie die gewünschten Spalten an. <b>Select All</b> und <b>Deselect All</b> stehen oben. Die Spalten sind die Codes und Namen von Produkt, Abteilung, Unterabteilung und Familie, Barcode, Materialien, Abmessungen, Gewichte, Ursprungs- und Zollcodes, <b>Stock</b> (eine Zahl), <b>Status</b> (<b>In stock</b> oder <b>Out of stock</b>), <b>For sale</b> und <b>Data updated</b>.
- <b>Product State</b>: <b>Active</b>, <b>Discontinuing</b>, <b>Discontinued</b>. Zu Beginn ist nur <b>Active</b> angehakt.
- <b>Product Sale Status</b>: <b>Exclude products that are not for sale</b>, <b>Exclude products that are out of stock</b>, <b>Only products that are not for sale</b>.

Drücken Sie <b>Export Extended Properties</b>. Die Datei öffnet sich in einem neuen Tab und wird heruntergeladen. Diese Datei enthält keine Preise, Beschreibungen oder Bildlinks: Nutzen Sie dafür die vollständige <b>CSV</b>.

<!-- screenshot: das Panel Export Options mit Columns to Export, Product State und Product Sale Status -->

## Alle Produktfotos (Zip)

1. Drücken Sie <b>Images</b>. Ein Fenster zeigt <b>Your download images request is being processed.</b> Wir sammeln die Fotos jedes Produkts in der Liste.
2. Sobald es fertig ist, zeigt das Fenster <b>Your images are ready for download.</b> Drücken Sie <b>Download</b> und speichern Sie die Zip-Datei.
3. Die Schaltfläche zeigt nun <b>Download images</b>. Halten Sie die Maus darüber, um zu sehen, wie lange der Link noch funktioniert. Der Link läuft einen Tag nach seiner Erstellung ab.

Jedes Foto ist mit dem Produktcode und einer Nummer benannt, zum Beispiel <b>abc-01__12345.jpg</b>, damit Sie erkennen, zu welchem Produkt es gehört.

Immer wenn Produkte im Kanal hinzugefügt oder geändert werden, wird die alte Zip-Datei gelöscht. Drücken Sie erneut <b>Images</b>, um eine neue zu erstellen.

Es gibt in diesem Download keine Produktvideos.

## Ein einzelnes Produkt, eine Familie oder eine Kollektion

Öffnen Sie im <b>Catalogue</b> ein Produkt, eine Familie, eine Unterabteilung, eine Abteilung oder eine Kollektion. Oben rechts:

- <b>CSV</b> lädt dessen Produkte mit denselben Spalten wie die vollständige CSV herunter.
- Auf den Seiten von Produkt, Familie und Kollektion drücken Sie <b>⋮</b> und wählen unter <b>Select another download file type</b> die Option <b>images</b>, um deren Fotos als Zip-Datei herunterzuladen.

Auf den Katalogseiten unserer Website hat jede Familie und jedes Produkt in der Liste zwei Download-Symbole: <b>Download products (csv)</b> und <b>Download images (zip)</b>.

## Wenn etwas schiefgeht

- **I do not see the CSV and Images buttons.** Der Kanal hat noch keine Produkte, der Kanal ist geschlossen, oder Sie befinden sich im Tab <b>My Bundles</b>. Fügen Sie zuerst Produkte hinzu, oder gehen Sie zurück zum Tab <b>My Products</b>.
- **The CSV has fewer products than My Products.** Die vollständige <b>CSV</b> lässt Sets aus. Die Datei <b>Export Extended Properties</b> verwendet außerdem die Filter <b>Product State</b> und <b>Product Sale Status</b>: Haken Sie alle Zustände an, um alles zu erhalten.
- **"Select at least one column".** Haken Sie mindestens eine Spalte unter <b>Columns to Export</b> an.
- **The images link says Expired or does not open.** Drücken Sie erneut <b>Images</b>, um eine neue Zip-Datei zu erstellen.
- **The zip only has a file called error.txt.** Keines der Produkte in der Liste hat ein Foto. Prüfen Sie, ob der Kanal Produkte hat.
- **Excel shows strange letters.** Öffnen Sie die Datei mit <b>Data → From Text/CSV</b> und wählen Sie UTF-8, oder öffnen Sie sie in Google Sheets.
- **"The data feed for ... is not available yet, please try again later."** Die Datei für diese Familie oder Abteilung wird noch erstellt. Versuchen Sie es in ein paar Minuten erneut.
