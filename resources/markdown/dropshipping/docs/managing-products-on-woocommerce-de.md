---
title: Produkte auf WooCommerce verwalten
summary: Fügen Sie unsere Produkte zu Ihrem WooCommerce-Kanal hinzu, erstellen Sie sie in Ihrem Shop, oder verknüpfen Sie sie mit bereits verkauften Produkten, halten Sie den Lagerbestand aktuell, und beheben Sie Upload-Fehler.
date: 2026-09-25
source_date: 2026-09-25
tags: WooCommerce, Produkte, Upload, Verknüpfen, SKU, Lagerbestand
category: products
series: woocommerce
order: 2
shops: awd, dssk, dse
---

<aside class="tldr">
Öffnen Sie Ihren WooCommerce-Kanal und gehen Sie zu <b>My Products</b>. Drücken Sie <b>Add products</b> und wählen Sie die zu verkaufenden Produkte aus. Senden Sie dann jedes an Ihren Shop: <b>Create new product</b> legt ein neues Produkt in WooCommerce an, und <b>Match with this product</b> verknüpft es mit einem bereits in Ihrem Shop vorhandenen Produkt. Ein grünes Häkchen bedeutet, dass das Produkt live ist und wir seinen Lagerbestand aktuell halten.
</aside>

## Ihre Produktliste öffnen

1. Öffnen Sie <b>Channels</b> im Menü und klicken Sie auf den Namen Ihres WooCommerce-Shops.
2. Drücken Sie auf dem Kanal-Dashboard <b>View all</b> unter <b>Products</b>. Die Seite <b>My Products</b> öffnet sich.

Zeigt die Seite <b>Your channel is not connected yet to the platform</b>, beheben Sie zuerst die Verbindung. Siehe [Connecting your WooCommerce store](connecting-woocommerce).

## Produkte zu Ihrer Liste hinzufügen

1. Drücken Sie <b>Add products</b>. Das Fenster <b>Select products to be added to shop</b> öffnet sich.
2. Suchen Sie nach Name oder Code. Sie können statt einzelner Produkte auch eine ganze <b>Department</b>, <b>Sub-department</b> oder <b>Family</b> wählen.
3. Haken Sie die gewünschten Produkte an. Drücken Sie <b>Add</b>. Die Schaltfläche zeigt, wie viele Sie ausgewählt haben.

Die Produkte befinden sich nun in Ihrer Liste, sind aber noch nicht in Ihrem WooCommerce-Shop. Sie müssen sie zuerst anlegen oder verknüpfen.

Sie können auch viele Produkte auf einmal aus einer Tabelle hinzufügen, mit der Upload-Schaltfläche neben <b>Add products</b> (<b>Import from xlsx file</b>). Haben Sie Produkte in einem anderen Kanal, lässt Sie die Schaltfläche <b>⋮</b> ein <b>Clone portfolio from channel</b> ausführen.

<!-- screenshot: das Fenster Select products to be added to shop mit einigen angehakten Produkten und der Schaltfläche Add -->

## Produkte an Ihren Shop senden

Jedes Produkt hat eine Spalte <b>Woo Commerce product</b>. Was Sie dort sehen, hängt vom Produkt ab:

- <b>Create new product</b>: legt ein neues Produkt in Ihrem WooCommerce-Shop an, mit Name, Beschreibung und Preis aus Ihrer Produktliste sowie unseren Bildern, der SKU, dem Barcode, dem Gewicht, den Abmessungen und dem Lagerbestand.
- <b>Match with this product</b>: Wir haben in Ihrem Shop ein Produkt mit derselben SKU oder einem ähnlichen Namen gefunden. Prüfen Sie, ob es das richtige ist, und drücken Sie es dann, um die beiden zu verknüpfen. Nutzen Sie dies, wenn Sie das Produkt bereits verkaufen und kein zweites Exemplar möchten.
- <b>Choose another product from your shop</b> (wenn wir eine mögliche Übereinstimmung gefunden haben) oder <b>Match it with an existing product in your shop</b> (wenn wir keine gefunden haben): öffnet eine Liste der Produkte in Ihrem Shop. Suchen Sie das Produkt, wählen Sie es aus und drücken Sie <b>Link ... to selected item on your platform</b>.

Hat es funktioniert, zeigt das Produkt ein grünes Häkchen und den Namen Ihres WooCommerce-Produkts. Von da an halten wir seinen Lagerbestand aktuell. Um es später mit einem anderen WooCommerce-Produkt zu verknüpfen, drücken Sie <b>Change linked listing</b>.

Wenn Sie ein Produkt verknüpfen, verknüpfen wir es nur und aktualisieren seinen Lagerbestand. Name, Beschreibung, Preis oder Bilder, die Sie bereits in WooCommerce haben, ändern wir nicht.

<!-- screenshot: Zeilen in My Products mit Create new product, Match with this product, und einem grünen Häkchen bei einem verknüpften Produkt -->

### Mehrere Produkte auf einmal

- Haken Sie mehrere Produkte in der Liste an. Über der Liste erscheinen Schaltflächen: <b>Create New</b> sendet sie alle als neue Produkte, <b>Match</b> verknüpft sie mit Produkten in Ihrem Shop mit derselben SKU.
- Sind manche Produkte noch nicht in Ihrem Shop, sehen Sie <b>You have ... products not synced yet</b>. Drücken Sie <b>Upload all as new product</b>, um sie alle anzulegen, oder <b>Match all with default product</b>, um jedes Produkt mit derselben SKU in Ihrem Shop zu verknüpfen.

Große Uploads laufen im Hintergrund und zeigen ein Fortschrittsfenster. Sie können währenddessen weiterarbeiten.

Der Abgleich sucht nach unserer SKU oder unserem Produktcode in Ihrem Shop. Groß- und Kleinschreibung spielt keine Rolle. Weichen Ihre SKUs von unseren ab, nutzen Sie <b>Match it with an existing product in your shop</b> und wählen Sie das Produkt selbst.

## Was wir an WooCommerce senden

- Name, Beschreibung und Preis aus Ihrer Produktliste.
- Unsere Bilder, SKU und Barcode (als GTIN, UPC, EAN oder ISBN).
- Gewicht in der von Ihrem Shop verwendeten Einheit, sowie Abmessungen, sofern vorhanden.
- Ursprungsland und Zutaten als Produktattribute, sowie Links zu Produktdokumenten in der Beschreibung.
- Der Lagerbestand, den Sie verkaufen können. Produkte, die zum Verkauf stehen, werden veröffentlicht. Produkte, die nicht vorrätig, bald verfügbar oder noch nicht bereit sind, werden als Entwürfe gespeichert.

Wir wählen keine Kategorie für Sie aus. Neue Produkte kommen ohne Kategorie an, fügen Sie also Ihre eigenen Kategorien in WooCommerce hinzu.

## Lagerbestand und Preise

Wir senden Lagerbestandsänderungen automatisch an Ihren Shop. Drücken Sie oben in <b>My Products</b> auf <b>Update Stock</b>, um den aktuellen Lagerbestand aller Ihrer Produkte sofort an diesen Kanal zu senden.

In <b>Manage Sales Channel</b> können Sie ändern, wie der Lagerbestand angezeigt wird:

- <b>Stock Update</b>: automatische Lagerbestandsaktualisierungen ein- oder ausschalten.
- <b>Max Quantity To Advertise</b>: die höchste in Ihrem Shop angezeigte Bestandszahl, selbst wenn wir mehr haben.
- <b>Stock Threshold</b>: Fällt unser Lagerbestand auf diese Zahl, zeigt das Produkt in Ihrem Shop als nicht vorrätig an.

Ihre <b>Pricing Policy</b> in <b>Manage Sales Channel</b> legt den Preis von Produkten fest, die Sie ab jetzt hinzufügen. Bereits in Ihrer Liste vorhandene Produkte ändert sie nicht. Um deren Preise zu ändern, haken Sie sie an und drücken Sie <b>Edit Price</b>.

## Produkte entfernen

Es gibt drei Wege, ein Produkt zu entfernen. Wählen Sie sorgfältig, denn die Totenkopf-Schaltfläche löscht das Produkt auch aus Ihrem WooCommerce-Shop.

- Die Totenkopf-Schaltfläche in einer Produktzeile bittet um Bestätigung und entfernt das Produkt dann aus Ihrer Liste sowie, falls verknüpft, dauerhaft aus Ihrem WooCommerce-Shop. Es landet nicht im WooCommerce-Papierkorb.
- <b>Unlink & Delete</b> (nach dem Anhaken von Produkten) entfernt die angehakten Produkte aus Ihrer Liste, behält sie aber in Ihrem WooCommerce-Shop. Sie sind nicht mehr verknüpft, daher aktualisieren wir ihren Lagerbestand nicht mehr.
- <b>Unlink</b> (nach dem Anhaken von Produkten) behält die Produkte in Ihrer Liste und in WooCommerce, hebt aber die Verknüpfung auf. Wir aktualisieren ihren Lagerbestand nicht mehr. Sie können sie später erneut abgleichen.

Löschen Sie ein verknüpftes Produkt selbst in WooCommerce, entfernen wir es auch aus Ihrer Liste.

Produkte, die wir nicht mehr verkaufen, zeigen ein rotes Zeichen. <b>This product line has been discontinued. Please remove this item</b> bedeutet, dass Sie es aus Ihrem Shop entfernen sollten. <b>This product line is currently not for sale</b> bedeutet, dass Sie es derzeit nicht hochladen können.

## Prüfen, was passiert ist

Öffnen Sie den Tab <b>Logs</b> (das Uhr-Symbol rechts neben den Tabs), um jeden Upload zu sehen, ob er funktioniert hat, und die Meldung, die Ihr Shop zurückgesendet hat.

Die Spalte <b>Status</b> zeigt drei Häkchen für jedes Produkt: <b>Has valid platform product id</b>, <b>Exist in platform</b> und <b>Platform status</b>. Drei grüne Häkchen bedeuten, dass das Produkt verknüpft und live ist.

## Wenn etwas schiefgeht

Schlägt ein Upload fehl, zeigt die Produktzeile die Meldung Ihres Shops und einen kurzen Hinweis. Die häufigsten:

- <b>The store answered with a web page instead of data</b>, ein 503-Fehler, eine Zeitüberschreitung, oder eine leere Antwort: Ihre Website ist nicht erreichbar, zu langsam, im Wartungsmodus, oder blockiert uns. Dies ist das häufigste Upload-Problem. Prüfen Sie, ob Ihre Website im Browser öffnet, bitten Sie Ihren Hosting-Anbieter, unsere Server zuzulassen, und laden Sie dann erneut hoch.
- <b>A product with this SKU already exists in your store</b>, <b>Invalid or duplicated SKU</b>, oder <b>product with SKU ... already present in the lookup table</b>: Ihr Shop hat bereits ein Produkt mit dieser SKU. Drücken Sie <b>Create new product</b>, versuchen wir, es selbst mit diesem Produkt zu verknüpfen. Zeigt die Meldung weiterhin, gleichen Sie das Produkt von Hand mit <b>Match it with an existing product in your shop</b> ab. Finden Sie das Produkt in Ihrem Shop nicht, sehen Sie im WooCommerce-Papierkorb nach: Ein gelöschtes Produkt behält die SKU, bis Sie es endgültig löschen.
- <b>Invalid or duplicated GTIN</b>: Ein anderes Produkt in Ihrem Shop hat bereits denselben Barcode (GTIN, UPC, EAN oder ISBN). Verknüpfen Sie mit diesem Produkt, oder entfernen Sie den Barcode vom anderen Produkt in WooCommerce, und laden Sie dann erneut hoch.
- <b>Your store could not save the product images</b>: Der WordPress-Upload-Ordner ist nicht beschreibbar. Bitten Sie Ihren Hosting-Anbieter, die Ordnerberechtigungen zu korrigieren, und laden Sie dann erneut hoch.
- <b>The account connected to your store is not allowed to create products</b> (oder sie zu bearbeiten oder zu lesen): Die Schlüssel haben keine <b>Read/Write</b>-Berechtigung. Verbinden Sie den Kanal mit einem Administrator-Konto erneut.
- <b>Your store rejected the credentials</b>: Die Schlüssel wurden in WooCommerce gelöscht oder geändert. Drücken Sie auf der Kanalseite <b>Try to reconnect</b>.
- <b>This product no longer exists in your store</b>: Das Produkt wurde in WooCommerce gelöscht. Legen Sie es erneut an, oder verknüpfen Sie es mit einem anderen Produkt.
- Die Schaltfläche <b>Add products</b> fehlt: Ihr Shop hat beim letzten Versuch, ihn zu erreichen, nicht geantwortet, daher haben wir den Kanal pausiert. Prüfen Sie, ob Ihre Website online ist. Die Schaltfläche kommt zurück, sobald wir Ihren Shop wieder erreichen.

Probleme auf Ihrer eigenen Website, zum Beispiel wenn sie nicht erreichbar, langsam ist oder uns blockiert, sowie Produktregeln, die Sie in WooCommerce festgelegt haben, können nur Sie oder Ihr Hosting-Anbieter beheben.
