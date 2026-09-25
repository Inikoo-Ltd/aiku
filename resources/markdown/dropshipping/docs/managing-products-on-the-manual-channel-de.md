---
title: Produkte im Manual/API-Kanal verwalten
summary: Fügen Sie die von Ihnen verkauften Produkte zu My Products in einem Manual/API-Kanal hinzu, importieren Sie sie aus einer Tabelle oder einem anderen Kanal, laden Sie Ihre Produktdaten und Bilder herunter, und entfernen Sie Produkte, die Sie nicht mehr verkaufen.
date: 2026-09-25
source_date: 2026-09-25
tags: manuell, API, Meine Produkte, Portfolio, Produkte hinzufügen, Import, CSV, Bilder
category: products
series: manual
order: 2
shops: awd, dssk, dse
---

<aside class="tldr">
<b>My Products</b> ist die Liste der Produkte, die Sie in einem Kanal verkaufen. Öffnen Sie sie unter Ihrem Manual/API-Kanal und drücken Sie <b>Add products</b>, um Produkte aus unserem Katalog auszuwählen. Bei einem Manual/API-Kanal wird nichts irgendwohin hochgeladen: Die Liste dient Ihrem eigenen Gebrauch und der API. Sie brauchen sie nicht, um manuell Bestellungen aufzugeben. Um ein Produkt nicht mehr zu verkaufen, drücken Sie die Schaltfläche <b>X</b> in seiner Zeile.
</aside>

## My Products öffnen

Gehen Sie im Menü zu Ihrem Manual/API-Kanal und öffnen Sie <b>My Products</b>. Sie können auch <b>View all</b> im Feld <b>Products</b> der Kanalseite drücken.

Ist die Liste leer, zeigt die Seite <b>You don't have any items in your portfolio</b> und eine Schaltfläche <b>Add Product</b>.

Jede Produktzeile zeigt das Bild, Name und Code, unseren Lagerbestand (<b>Stocks:</b>), das Gewicht, Ihren Preis (<b>Price:</b>) und die unverbindliche Preisempfehlung (<b>RRP:</b>).

## Produkte hinzufügen

1. Drücken Sie <b>Add products</b>.
2. Ein Fenster <b>Add products to your product</b> öffnet sich.
3. Wählen Sie, wonach Sie suchen möchten: <b>Product</b> durchsucht Produktnamen und -codes, <b>Department</b>, <b>Sub-department</b> und <b>Family</b> finden die Produkte in einer Gruppe mit diesem Namen.
4. Geben Sie etwas in das Suchfeld ein und haken Sie die gewünschten Produkte an.
5. Drücken Sie <b>Add … products and close</b>. Die Zahl zeigt, wie viele Sie angehakt haben.

<!-- screenshot: das Fenster Add products mit dem Filter Product / Department / Sub-department / Family und der Schaltfläche Add products and close -->

Sie sehen <b>Successfully added portfolios</b>, und die Produkte erscheinen in der Liste.

## Viele Produkte auf einmal hinzufügen

### Aus einer Tabelle

1. Drücken Sie die Upload-Schaltfläche neben <b>Add products</b> (Tooltip <b>Import from xlsx file</b>).
2. Drücken Sie im Fenster <b>Bulk Import Portfolios</b> auf <b>Download template (.xlsx)</b>.
3. Füllen Sie die Spalte <b>sku</b> mit unseren Produktcodes aus, eine pro Zeile. Die Spalte <b>title</b> ist optional.
4. Laden Sie die Datei hoch.

Zeilen werden übersprungen, wenn der Code nicht in unserem Shop existiert oder das Produkt nicht zum Verkauf steht. Der Upload-Verlauf zeigt, was hinzugefügt wurde und was fehlgeschlagen ist.

### Aus einem anderen Kanal

Haben Sie bereits Produkte in einem anderen Kanal, können Sie diese kopieren. Drücken Sie die Schaltfläche mit den drei Punkten neben <b>Add products</b>. Wählen Sie unter <b>Clone portfolio from channel:</b> den Kanal, aus dem kopiert werden soll. Die Zahl in Klammern zeigt, wie viele Produkte er hat. Das Kopieren läuft im Hintergrund, und die Seite lädt neu, sobald es fertig ist.

## Produkte in Ihrer Liste finden

Nutzen Sie das Suchfeld oder die Filterschaltflächen über der Liste:

- <b>Only For Sale</b>: Produkte, die Sie jetzt bestellen können.
- <b>Not For Sale</b>: Produkte, die wir derzeit nicht verkaufen.
- <b>Discontinued</b>: Produkte, die wir nicht mehr verkaufen werden.
- <b>Out of stock</b>: Produkte, die derzeit nicht vorrätig sind.

Ein durchgestrichenes Kästchen-Symbol bedeutet, dass das Produkt eingestellt ist. Sein Tooltip sagt <b>This product line has been discontinued. Please remove this item</b>. Ein durchgestrichenes Geld-Symbol bedeutet <b>This product line is currently not for sale</b>. Entfernen Sie diese Produkte von Ihrer eigenen Website, damit Ihre Käufer sie nicht bestellen können.

## Produktdaten und Bilder für Ihre Website erhalten

Bei einem Manual/API-Kanal laden wir keine Produkte auf Ihre Website hoch. Holen Sie sich die Daten hier:

- <b>CSV</b>: lädt Ihre Produktliste mit Preisen, Lagerbestand und Beschreibungen herunter.
- Die Schaltfläche mit den drei Punkten neben <b>CSV</b> öffnet <b>Export Options</b>. Wählen Sie die Spalten, den <b>Product State</b> und den <b>Product Sale Status</b>, und drücken Sie dann <b>Export Extended Properties</b>. Haken Sie <b>Include bundles</b> an, um Ihre Sets hinzuzufügen.
- <b>Images</b>: bereitet einen Download der Bilder Ihrer Produkte vor. Sobald er fertig ist, drücken Sie <b>Download images</b>. Der Link funktioniert nur für eine begrenzte Zeit, die im Tooltip der Schaltfläche angezeigt wird.
- Über die API kann Ihr System dieselbe Liste lesen und als CSV- oder JSON-Feed herunterladen. Siehe [The Manual/API channel](/docs/manual-and-api-channel).

Lagerbestand und Preise ändern sich. Laden Sie die Liste erneut herunter, oder lesen Sie sie über die API, ausreichend oft, damit Ihre Website aktuell bleibt.

## Ein Produkt entfernen

Drücken Sie die Schaltfläche <b>X</b> in der Zeile des Produkts (Tooltip <b>Remove product from list</b>). Das Produkt verschwindet aus Ihrer Liste. Bereits mit ihm aufgegebene Bestellungen bleiben unverändert. Sie können es später mit <b>Add products</b> wieder hinzufügen.

## Wenn etwas schiefgeht

- <b>I cannot find a product in the Add products window.</b> Prüfen Sie, ob Sie im richtigen Tab suchen: <b>Product</b> sucht nach Produktnamen und -codes, <b>Family</b> und <b>Department</b> suchen nach Gruppennamen. Das Fenster zeigt keine Produkte, die bereits in Ihrer Liste sind, nicht zum Verkauf stehen oder eingestellt sind.
- <b>My spreadsheet upload skipped rows with "SKU not found in this shop".</b> Der Code in der Spalte <b>sku</b> ist auf dieser Website keiner unserer Produktcodes. Kopieren Sie den Code genau so, wie er am Produkt angezeigt wird.
- <b>My spreadsheet upload skipped rows with "Product is not for sale".</b> Wir verkaufen dieses Produkt derzeit nicht. Lassen Sie es aus.
- <b>A product shows as discontinued or not for sale.</b> Sie können es nicht bestellen. Entfernen Sie es von Ihrer eigenen Website und aus <b>My Products</b>.
- <b>A product is out of stock.</b> Es bleibt in Ihrer Liste. Nutzen Sie <b>Out of stock</b>, um diese Produkte zu finden und sie bis zur Wiederverfügbarkeit auf Ihrer Website zu verbergen.
- <b>The image download link does not work any more.</b> Der Link ist abgelaufen. Drücken Sie erneut <b>Images</b>, um einen neuen zu erstellen.
- <b>My products are not on my website.</b> Von einem Manual/API-Kanal laden wir nie hoch. Laden Sie sie selbst mit dem CSV-Download oder der API. Verkaufen Sie auf einer auf der Seite <b>Add Sales Channel</b> gezeigten Plattform, wie Shopify, WooCommerce, eBay oder TikTok Shop, verbinden Sie diese Plattform als eigenen Kanal, und Produkte werden für Sie hochgeladen.
