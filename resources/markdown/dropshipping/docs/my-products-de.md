---
title: My Products nutzen
summary: Lesen Sie die Liste My Products eines Kanals, senden Sie Produkte an Ihren Shop oder verknüpfen Sie sie mit bereits vorhandenen Listings, halten Sie den Lagerbestand aktuell, und lesen Sie die Upload-Fehler.
date: 2026-09-25
source_date: 2026-09-25
tags: Produkte, My Products, Portfolio, Upload, Verknüpfen, SKU, Lagerbestand, Protokolle
category: products
shops: awd, dssk, dse
---

<aside class="tldr">
<b>My Products</b> ist die Liste unserer Produkte, die Sie in einem Kanal verkaufen. Jeder Kanal hat seine eigene Liste. Von hier aus senden Sie jedes Produkt mit <b>Create new product</b> an Ihren Shop, oder verknüpfen es mit <b>Match</b> mit einem bereits vorhandenen Listing. Ist das Produkt verknüpft, halten wir seinen Lagerbestand aktuell, und der Tab <b>Logs</b> zeigt jeden Upload, Abgleich und jede Lagerbestandsaktualisierung mit der Antwort Ihrer Plattform.
</aside>

## My Products öffnen

1. Öffnen Sie <b>Channels</b> im Menü. Jeder Kanal wird darunter mit seinem Logo angezeigt.
2. Klicken Sie auf den Kanal, dann auf <b>My Products</b>. Die Zahl daneben zeigt, wie viele Produkte in der Liste sind.

Die Seite hat drei Tabs: <b>My Products</b>, <b>My Bundles</b> (siehe [Creating bundles](/docs/bundles)) und <b>Logs</b> (das Uhr-Symbol rechts).

Zeigt ein rotes Feld <b>Your channel is not connected yet to the platform</b>, ist die Verbindung zu Ihrem Shop unterbrochen. Es kann nichts hochgeladen und kein Lagerbestand gesendet werden, bis Sie sich erneut verbinden. Folgen Sie der Verbindungsanleitung für Ihre Plattform.

<!-- screenshot: die Seite My Products eines Shopify-Kanals mit den Tabs, den Schaltflächen oben und einigen Zeilen -->

## Was jede Zeile zeigt

- **Product**: unser Produktcode (klicken Sie darauf, um das Produkt zu öffnen), der Name, <b>Stocks</b>, <b>Weight</b> (Produktgewicht / Gewicht mit Verpackung), <b>Dimension</b>, unser <b>Price</b> (was Sie uns bezahlen) und die <b>RRP</b>. Zeigt Ihr Kanal Preise mit Mehrwertsteuer, sehen Sie <b>Price (include VAT)</b> und <b>RRP (include VAT)</b> (nicht bei Shopify).
- **Status**: Bei Shopify bedeutet ein grüner Handschlag <b>Product connected to shopify</b> und ein roter <b>Not connected</b>. Bei anderen Plattformen gibt es drei Häkchen: <b>Has valid platform product id</b>, <b>Exist in platform</b> und <b>Platform status</b>. Drei grüne Häkchen bedeuten, dass das Produkt live und verknüpft ist.
- **Message**: ein grünes Häkchen, wenn alles in Ordnung ist. Eine rote Meldung, wenn Ihre Plattform das Produkt abgewiesen hat (bei Shopify sehen Sie stattdessen im Tab <b>Logs</b> nach). Klicken Sie darauf, um <b>Answer of ...</b> mit dem vollständigen Text Ihrer Plattform zu sehen und, oft, was zu tun ist. Ein durchgestrichenes Kästchen bedeutet <b>This product line has been discontinued. Please remove this item</b>. Ein durchgestrichenes Dollar-Symbol bedeutet <b>This product line is currently not for sale</b>.
- **Produktspalte Ihrer Plattform** (zum Beispiel <b>Shopify product</b> oder <b>eBay product</b>): mit welchem Listing in Ihrem Shop dieses Produkt verknüpft ist, oder die Schaltflächen, um es zu verknüpfen.

## Ein Produkt an Ihren Shop senden

Für ein noch nicht verknüpftes Produkt haben Sie zwei Möglichkeiten.

**Ein neues Listing anlegen.** Drücken Sie <b>Create new product</b>. Wir legen das Produkt in Ihrem Shop an, mit unserem Namen, unserer Beschreibung, unseren Bildern, dem Preis, der SKU und dem Lagerbestand.

**Mit einem bereits vorhandenen Listing verknüpfen.** Nutzen Sie dies, wenn Sie das Produkt bereits verkaufen und kein zweites Exemplar möchten.
- Haben wir in Ihrem Shop ein Listing mit derselben SKU gefunden, erscheint es in der Zeile. Drücken Sie <b>Match with this product</b>.
- Um ein anderes auszuwählen, drücken Sie <b>Choose another product from your shop</b>, oder <b>Match it with an existing product in your shop</b>, wenn wir nichts gefunden haben. Durchsuchen Sie Ihren Shop, wählen Sie den Artikel und drücken Sie <b>Link ... to selected item on your platform</b>.
- Um ein bereits verknüpftes Produkt zu ändern, drücken Sie <b>Change linked listing</b> (bei Shopify: <b>Connect with other product</b>).

## Mehrere Produkte auf einmal bearbeiten

Sind manche Produkte noch nicht verknüpft, zeigt ein gelber Balken <b>You have ... products not synced yet</b>. Er hat zwei Schaltflächen:

- <b>Upload all as new product</b>: legt sie alle in Ihrem Shop an. Nicht bei eBay angezeigt.
- <b>Match all with default product</b>: verknüpft jedes Produkt mit dem Listing in Ihrem Shop mit derselben SKU. Wir vergleichen die SKU in Ihrem Shop mit der SKU des Produkts in <b>My Products</b> und mit unserem Produktcode, Groß- und Kleinschreibung spielt keine Rolle. Produkte ohne Listing dieser SKU bleiben unverändert.

Um nur an manchen Produkten zu arbeiten, haken Sie sie in der Liste an. Diese Schaltflächen erscheinen:

- <b>Create New (...)</b>: legt die angehakten Produkte in Ihrem Shop an.
- <b>Match (...)</b>: verknüpft die angehakten Produkte per SKU.
- <b>Unlink (...)</b> und <b>Unlink & Delete (...)</b>: siehe [Removing products](/docs/removing-products).
- <b>Edit Price (...)</b>: legt Ihren Verkaufspreis für die angehakten Produkte bei eBay, Shopify, WooCommerce und Wix fest, als Prozentsatz oder Betrag über oder unter der UVP. Wird nicht angezeigt, wenn Ihr Kanal auf eigene Preise eingestellt ist.

Große Vorgänge laufen im Hintergrund. Ein Fortschrittsfenster zeigt, wie viele fertig sind, und die Seite lädt von selbst neu.

<!-- screenshot: der gelbe Balken "products not synced yet" mit Upload all as new product und Match all with default product -->

## Produkte in der Liste finden

Nutzen Sie das Suchfeld oder die Filterschaltflächen: <b>Only For Sale</b>, <b>Not For Sale</b>, <b>Discontinued</b> und <b>Out of stock</b>. Bei Shopify befinden sich die Filter im Menü <b>Filter</b>: <b>Only For Sale</b>, <b>Not For Sale</b>, <b>Discontinued</b>, <b>Connected to Shopify</b> und <b>Not Connected</b>. Bei Shopify gibt es keinen Filter für nicht vorrätige Produkte.

## Lagerbestand

Wir senden Lagerbestand nur für verknüpfte Produkte (grüner Status). Sie müssen nichts tun: Ändert sich unser Lagerbestand, aktualisieren wir Ihren Shop.

Um den Lagerbestand sofort zu senden, drücken Sie oben auf der Seite <b>Update Stock</b>. Dies sendet den aktuellen Lagerbestand der Produkte dieses Kanals. Ist noch kein Produkt verknüpft, zeigt es <b>Nothing to update</b>. Die Schaltfläche gibt es bei Shopify-, WooCommerce-, eBay-, TikTok-Shop- und Wix-Kanälen, nicht bei Allegro- oder manuellen Kanälen.

Sie können den Lagerbestand in den Kanaleinstellungen begrenzen oder verbergen. Siehe [Why a product shows out of stock in my store](/docs/out-of-stock-in-my-store).

## Weitere Schaltflächen

- <b>Add products</b>, die Upload-Schaltfläche und <b>Clone portfolio from channel:</b>: Produkte hinzufügen. Siehe [Finding and adding products](/docs/sourcing-products).
- <b>CSV</b>, <b>⋮</b> (<b>Other Export Options</b>) und <b>Images</b>: Ihre Produktdaten und Fotos herunterladen. Siehe [Exporting product data and images](/docs/exporting-product-data).
- <b>Publish ... drafts</b> (nur eBay): veröffentlicht die als Entwürfe zu eBay hochgeladenen Listings.
- <b>Update all dimensions</b> (nur Shopify): sendet unsere aktuellen Abmessungen an alle Ihre Shopify-Produkte.

## Der Tab Logs

Der Tab <b>Logs</b> listet jeden Upload, Abgleich und jede Lagerbestandsaktualisierung dieses Kanals auf: <b>Product Code</b>, <b>Type</b> (<b>upload</b>, <b>match</b> oder <b>update-stock</b>), <b>Platform</b>, <b>Status</b> (<b>Done</b>, <b>In progress</b> oder <b>Failed</b>), die <b>Response</b> Ihrer Plattform und das <b>Date</b>. Sehen Sie hier zuerst nach, wenn ein Produkt oder sein Lagerbestand nicht angekommen ist.

## Wenn etwas schiefgeht

Die rote Meldung in der Zeile und die <b>Response</b> in <b>Logs</b> ist die Antwort Ihrer Plattform. Die häufigsten:

- **Throttled / too many calls / request timeout / internal error.** Ihre Plattform hat uns gebeten, langsamer zu senden, oder hat nicht rechtzeitig geantwortet. Mit dem Produkt stimmt nichts nicht. Versuchen Sie es in ein paar Minuten erneut.
- **The store answered with a web page instead of data, or returned 503, timed out or an empty reply** (WooCommerce). Ihre eigene Website ist nicht erreichbar, im Wartungsmodus, oder ihr Sicherheits-Plugin oder Hosting blockiert uns. Prüfen Sie, ob Ihre Website online ist. Bitten Sie Ihr Hosting, unsere Verbindung zuzulassen, und versuchen Sie es dann erneut.
- **A product with this SKU already exists in your store / Invalid or duplicated SKU / already present in the lookup table** (WooCommerce). Sie haben bereits ein Produkt mit dieser SKU. Nutzen Sie <b>Match</b> statt <b>Create new product</b>. Befindet sich das alte Produkt im WooCommerce-Papierkorb, leeren Sie ihn zuerst.
- **Invalid or duplicated GTIN** (WooCommerce). Ein anderes Produkt in Ihrem Shop nutzt diesen Barcode bereits. Entfernen Sie den Barcode vom anderen Produkt in WooCommerce, oder verknüpfen Sie damit.
- **Cannot list more products: your Shop probation tier allows at most 100 total product listings** (TikTok). Dies ist ein TikTok-Limit für neue Shops, kein Problem mit dem Produkt. Entfernen Sie nicht benötigte Listings, oder bitten Sie TikTok, Ihre Stufe anzuheben.
- **product_weight received 0 / weight cannot be zero** (TikTok). TikTok benötigt ein Gewicht. Sie können unser Produktgewicht nicht selbst ändern: Kontaktieren Sie uns im Chat auf unserer Website, mit dem Produktcode.
- **Image must be at least 300:300** (TikTok). Eines unserer Bilder ist für TikTok zu klein. Kontaktieren Sie uns im Chat auf unserer Website, mit dem Produktcode.
- **Price out of range / incorrect price** (TikTok). TikTok bestimmt die Preisspanne, die Ihr Shop nutzen darf. Prüfen Sie die Spanne im TikTok Shop Seller Center. Liegt der von uns gesendete Preis außerhalb, kontaktieren Sie uns im Chat auf unserer Website, mit dem Produktcode.
- **Category qualification / category is restricted** (TikTok). Bewerben Sie sich für die Kategorie im Qualification Center des TikTok Shop Seller Centers und laden Sie dann erneut hoch.
- **Requires an active seller account** (TikTok) oder **create a seller account** (eBay). Schließen Sie zuerst Ihr Verkäuferkonto auf der Plattform ab.
- **The listing would cause you to exceed the amount you can list this month** (eBay). Sie haben Ihr eBay-Verkaufslimit erreicht. Bitten Sie eBay, es anzuheben, oder warten Sie den nächsten Monat ab.
- **Invalid data in the associated fulfilment policy** (eBay). Ihre Versandrichtlinie (Fulfilment Policy) bei eBay hat ein Problem. Beheben Sie es bei eBay und prüfen Sie dann die in Ihren Kanaleinstellungen gewählten Richtlinien.
- **Item specific Type / Brand missing, or custom values for Size no longer supported** (eBay). eBay verlangt zusätzliche Angaben für diese Kategorie. Siehe [Managing products on eBay](/docs/managing-products-on-ebay).
- **Not allowed to revise an ended item** (eBay). Das Listing wurde bei eBay beendet. Trennen Sie die Verknüpfung des Produkts und legen Sie es erneut an.
- **Overseas Warehouse Block Policy** (eBay). Ist Ihr Konto in bestimmten Ländern registriert, erscheint oben ein rotes <b>Important Notice</b>. eBay kann im Ausland gelagerte Listings blockieren. Wenden Sie sich an den eBay-Support, um eine Genehmigung zu erbitten.
- **This product line has been discontinued.** Wir verkaufen es nicht mehr. Entfernen Sie es aus Ihrer Liste und aus Ihrem Shop.
