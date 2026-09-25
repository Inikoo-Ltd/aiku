---
title: Produkte und Bestellungen auf eBay verwalten
summary: Listen Sie unsere Produkte auf eBay auf oder verknüpfen Sie sie mit bereits vorhandenen Listings, steuern Sie Preise und Lagerbestand, und verstehen Sie, wie eBay-Bestellungen bei uns eingehen und als versendet markiert werden.
date: 2026-09-25
source_date: 2026-09-25
tags: eBay, Produkte, Listings, Verknüpfen, SKU, Preise, Lagerbestand, Bestellungen, Fehler
category: products
series: ebay
order: 2
shops: awd, dssk, dse
---

<aside class="tldr">
Fügen Sie Produkte zu Ihrem eBay-Kanal hinzu, öffnen Sie dann <b>My Products</b> und drücken Sie <b>Create new product</b>, um sie bei eBay zu listen, oder <b>Match with this product</b>, um ein bereits vorhandenes Listing mit derselben SKU zu verknüpfen. Wir halten Lagerbestand und, falls gewünscht, Preise bei eBay aktuell. Bestellt ein Käufer ein verknüpftes Produkt, erreicht uns die Bestellung, wird von Ihrem Guthaben bezahlt, und wir senden das Tracking bei Versand an eBay.
</aside>

## Produkte zu Ihrem Kanal hinzufügen

Öffnen Sie Ihren eBay-Kanal und gehen Sie zu <b>My Products</b>. Drücken Sie <b>Add products</b> und wählen Sie die Produkte, die Sie verkaufen möchten. Sie erscheinen in der Liste, sind aber noch nicht auf eBay.

Oben in der Liste sehen Sie „You have … products not synced yet", solange manche Produkte noch nicht mit einem eBay-Listing verknüpft sind.

## Produkte bei eBay listen

Für ein einzelnes Produkt drücken Sie <b>Create new product</b> in seiner Zeile. Für mehrere haken Sie sie an und drücken <b>Create New (…)</b>. Ein Fenster zeigt den Upload-Fortschritt. Sie können es schließen; der Upload läuft weiter.

Was wir an eBay senden:

- Der Produktname als Titel. eBay erlaubt 80 Zeichen, längere Namen werden gekürzt.
- Die Beschreibung, die Bilder, die SKU sowie Gewicht und Größe.
- Eine von eBay vorgeschlagene Kategorie für das Produkt und die von dieser Kategorie benötigten Artikelmerkmale, ausgefüllt aus den Produktdaten.
- Der Preis aus Ihrer Preisrichtlinie und der Lagerbestand, den wir haben.

Ist <b>Upload as draft</b> in <b>Manage Sales Channel</b> aktiviert, wird das Produkt bei eBay angelegt, aber nicht veröffentlicht. Seine Zeile zeigt „Draft: uploaded to eBay but not published yet" und eine Schaltfläche <b>Publish on eBay</b>. Um alle Entwürfe auf einmal zu veröffentlichen, drücken Sie oben in der Liste <b>Publish … drafts</b>.

Ein grünes Häkchen in der Statusspalte bedeutet, dass das Produkt bei eBay live und verknüpft ist.

<!-- screenshot: My Products in einem eBay-Kanal, eine Zeile mit Create new product und eine mit dem grünen Häkchen -->

## Bereits vorhandene Listings verknüpfen (Abgleich nach SKU)

Verkaufen Sie unsere Produkte bereits bei eBay, verknüpfen Sie sie, statt ein zweites Listing anzulegen.

- Hat ein eBay-Listing dieselbe SKU wie das Produkt, zeigt seine Zeile dieses Listing. Drücken Sie <b>Match with this product</b>.
- Um ein anderes Listing zu verknüpfen, drücken Sie <b>Choose another product from your shop</b> oder <b>Match it with an existing product in your shop</b>, durchsuchen Sie Ihre eBay-Listings und drücken Sie <b>Link … to selected item on your platform</b>.
- Um viele auf einmal zu verknüpfen, haken Sie sie an und drücken Sie <b>Match (…)</b>, oder drücken Sie in der Meldung „not synced yet" auf <b>Match all with default product</b>. Beide gleichen nach SKU ab.
- Bei einem verknüpften Produkt verknüpft <b>Change linked listing</b> es mit einem anderen eBay-Listing.

Schlägt der Abgleich fehl, zeigt die Zeile „Your product is not in the listing yet": Wir konnten kein veröffentlichtes eBay-Listing dafür finden. Der Abgleich findet nur Listings, die eBay mit einer SKU in seinem Bestandssystem führt, zum Beispiel von uns oder einem anderen Listing-Tool erstellte Listings. Ein von Hand bei eBay eingegebenes Listing wird möglicherweise nicht gefunden. Nutzen Sie dann <b>Create new product</b>, und beenden Sie das alte Listing bei eBay.

## Preise

Ihre <b>Pricing Policy</b> in <b>Manage Sales Channel</b> legt den eBay-Preis jedes Produkts anhand seiner aktuellen UVP fest:

- <b>± % over live RRP</b> oder <b>± £ over live RRP</b> (€ in den Shops Europe und España): Wir legen den Preis fest und halten ihn synchron, wenn sich die UVP ändert. Speichern Sie eine neue Regel, werden Sie gefragt: <b>Reprice every product?</b>. Drücken Sie <b>Save and reprice</b>, um alle Preise bei eBay zu aktualisieren. Produkte, bei denen Sie einen eigenen Preis festgelegt haben, bleiben unberührt, außer Sie haken zusätzlich das Kästchen zum Zurücksetzen an.
- <b>Do not follow RRP</b>: Sie legen die Preise bei eBay selbst fest. Wir laden sie nie hoch und überschreiben sie nicht.

Um einigen Produkten einen eigenen Preis zu geben, haken Sie sie an und drücken Sie <b>Edit Price (…)</b>. Um Titel, Beschreibung oder Preis eines einzelnen Produkts zu ändern, drücken Sie die Bearbeiten-Schaltfläche in seiner Zeile. Das Fenster <b>Edit Product</b> hat <b>Title</b>, <b>Price Mapping</b> und <b>Description</b>. Drücken Sie <b>Save & Publish</b>, um die Änderungen sofort an eBay zu senden, oder <b>Save as Draft</b>. Der Preis muss über null bleiben.

## Lagerbestand

Mit aktiviertem <b>Stock Update</b> senden wir unseren Lagerbestand automatisch an eBay. In <b>Manage Sales Channel</b> können Sie festlegen:

- <b>Max Quantity To Advertise</b>: die höchste bei eBay angezeigte Menge, selbst wenn wir mehr haben.
- <b>Stock Threshold</b>: Fällt unser Lagerbestand auf diese Zahl, zeigt eBay das Produkt als nicht vorrätig an.

Ist ein Produkt nicht vorrätig, nicht zum Verkauf oder eingestellt, senden wir eine Menge von 0. Die Schaltfläche <b>Update Stock</b> oben in <b>My Products</b> sendet den aktuellen Lagerbestand aller Produkte im Kanal sofort.

<b>Tip:</b> eBay kann ein Listing beenden, dessen Menge 0 erreicht. Um das Listing zu behalten und es nur zu verbergen, bis der Lagerbestand wieder da ist, schalten Sie die Option für nicht vorrätige Artikel in Ihren eBay-Verkaufseinstellungen ein.

## Produkte entfernen

- Die Papierkorb-Schaltfläche in einer Zeile entfernt das Produkt aus Ihrem Kanal und beendet sein eBay-Listing.
- <b>Unlink (…)</b> behält das Produkt in Ihrer Liste und das Listing bei eBay, hebt aber die Verknüpfung auf. Wir aktualisieren dieses Listing nicht mehr, und seine Bestellungen erreichen uns nicht mehr.
- <b>Unlink & Delete (…)</b> entfernt die ausgewählten Produkte aus Ihrer Liste. Ist ein Listing danach bei eBay noch live, beenden Sie es bei eBay.

## Produktdaten und Bilder herunterladen

Öffnen Sie in <b>My Products</b> die Exportoptionen. Sie können eine CSV Ihrer Produkte mit den gewählten Spalten exportieren und mit <b>Download images</b> die Produktbilder herunterladen.

## Wie eBay-Bestellungen uns erreichen

- Wir prüfen Ihr eBay-Konto regelmäßig auf neue Bestellungen, die noch nicht versendet und nicht storniert sind. Um sofort zu prüfen, drücken Sie auf der Kanalseite <b>Fetch orders</b>.
- Es werden nur Produkte importiert, die in <b>My Products</b> verknüpft sind. Andere Artikel derselben eBay-Bestellung bleiben außen vor, diese versenden Sie selbst. Eine Bestellung ohne verknüpfte Produkte wird nicht importiert.
- Die Bestellung wird zuerst von Ihrem Guthaben, dann von Ihren gespeicherten Karten bezahlt. Schlägt die Zahlung fehl, schreiben wir Ihnen eine E-Mail, und die Bestellung wartet, bis sie bezahlt ist.
- Sie sehen die Bestellungen auf der Seite <b>Orders</b> des Kanals.
- Sobald wir die Bestellung versenden, senden wir Tracking-Nummer und Spediteur an eBay, und eBay markiert sie als versendet.

Eine Rücksendung oder Stornierung durch den Käufer bei eBay ändert die Bestellung bei uns nicht automatisch.

- Es gibt keine Stornieren-Schaltfläche. Um zu stornieren, kontaktieren Sie uns im Chat auf unserer Website. Dies ist nur möglich, bevor die Bestellung versendet ist. Ist sie erst einmal verpackt, kann es zu spät sein.
- Ihr Käufer darf nichts zurückschicken, bevor die Rücksendung mit uns im Chat auf unserer Website vereinbart wurde.
- Rückerstattungen gehen auf Ihr Guthaben.

## Rechnungen und Nachweis der Lieferung

Unsere Rechnungen sind an Sie ausgestellt, nicht an Ihren Käufer. Sie finden sie unter <b>Invoices</b>, sobald die Bestellung versendet ist. Fordert eBay einen Nachweis der Lieferung, können Sie diese Rechnungen vorlegen.

Sie stellen Ihrem Käufer selbst eine Rechnung aus. Wir stellen Ihrem Käufer nie eine Rechnung aus.

Fordert eBay einen Nachweis, dass Sie unsere Produkte weiterverkaufen dürfen, fragen Sie uns im Chat auf unserer Website nach einer Vollmacht.

## Wenn etwas schiefgeht

Der Grund für einen fehlgeschlagenen Upload wird in der Produktzeile angezeigt, der vollständige Verlauf im Tab <b>Logs</b> (das Uhr-Symbol) von <b>My Products</b>.

- <b>"This listing would cause you to exceed the amount you can list … this month"</b> oder <b>"… the number of items you can list"</b>: Dies sind Ihre eBay-Verkaufslimits. Laden Sie weniger oder günstigere Produkte hoch, bitten Sie eBay, Ihre Limits anzuheben, oder warten Sie den nächsten Monat ab. Nur eBay kann diese ändern.
- <b>"invalid data in the associated fulfilment policy … add at least one valid postage service"</b>: Ihre Versandrichtlinie bei eBay hat keine gültige Versandart. Fügen Sie dieser Richtlinie bei eBay eine hinzu und laden Sie dann erneut hoch.
- <b>"eBay will not list anything until your seller account is finished"</b> oder <b>eBay has not finished setting up your seller account</b>: Melden Sie sich bei eBay an, schließen Sie Ihre Verkäuferregistrierung ab, und laden Sie dann erneut hoch.
- <b>"not allowed to revise an ended item"</b>: Das Listing wurde bei eBay beendet. Das Produkt wird wieder als nicht verknüpft angezeigt. Drücken Sie <b>Create new product</b>, um es erneut zu listen.
- <b>"This Offer is not available"</b>: Das Listing existiert bei eBay nicht mehr. Drücken Sie <b>Create new product</b>, um es erneut zu listen.
- <b>My eBay items all ended suddenly</b>: Listings enden bei eBay, wenn die Menge ohne die Option für nicht vorrätige Artikel 0 erreicht, wenn Sie sie bei eBay löschen, oder wenn eBay sie entfernt. Schalten Sie die Option für nicht vorrätige Artikel ein und drücken Sie dann bei den gewünschten Produkten <b>Create new product</b>.
- <b>"The title or description may contain improper words, or the listing or seller may be in violation of eBay policy"</b>: eBay hat das Listing nach eigenen Regeln abgewiesen. Lesen Sie die Meldungen von eBay in Ihrem eBay-Konto. Dies kann nur eBay prüfen.
- <b>"The item specific Brand is missing"</b> (oder Type, Item Length, Item Width), oder <b>"custom values for Size are no longer supported"</b>: Die eBay-Kategorie benötigt einen Wert, den wir aus dem Produkt nicht ausfüllen konnten. Kontaktieren Sie uns im Chat auf unserer Website, mit dem Produktcode.
- <b>Listings blocked under the Overseas Warehouse Block Policy</b>: eBay blockiert Listings von Waren, die in einem anderen Land gelagert werden, für Verkäufer, die in bestimmten Ländern registriert sind. Wenden Sie sich an den eBay Customer Support, um eine Genehmigung zu erbitten.
- <b>"A system error has occurred"</b>, <b>"Unable to process your request"</b>, <b>"Too many requests"</b>, oder eine Zeitüberschreitung: Bei eBay gab es ein Problem auf deren Seite. Mit Ihrem Produkt stimmt nichts nicht. Versuchen Sie es später erneut.
- <b>Products are not syncing to eBay</b>: Prüfen Sie, ob der Kanal verbunden ist (keine Schaltfläche <b>Reconnect</b>), ob <b>Stock Update</b> eingeschaltet ist und ob das Produkt das grüne Häkchen zeigt. Ein Produkt ohne Häkchen ist nicht verknüpft.
- <b>An eBay order did not come to us</b>: Prüfen Sie, ob dessen Produkte in <b>My Products</b> verknüpft sind, und drücken Sie dann <b>Fetch orders</b>.
