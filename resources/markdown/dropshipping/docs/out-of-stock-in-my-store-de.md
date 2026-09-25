---
title: Warum ein Produkt in meinem Shop als nicht vorrätig angezeigt wird
summary: Finden Sie heraus, warum Ihr Shop ein Produkt als nicht vorrätig anzeigt, obwohl es bei uns vorrätig ist, und beheben Sie es auf Shopify, WooCommerce, eBay, TikTok Shop und Wix.
date: 2026-09-25
source_date: 2026-09-25
tags: Lagerbestand, nicht vorrätig, Bestand, shopify, wix, woocommerce, ebay, tiktok, Standort
category: troubleshooting
shops: awd, dssk, dse
---

<aside class="tldr">
Wir senden Lagerbestand an Ihren Shop nur für Produkte, die unter <b>Meine Produkte</b> <b>verknüpft</b> sind, und nur solange Ihr Kanal verbunden ist. Die üblichen Gründe für „nicht vorrätig" sind: das Produkt ist nicht verknüpft, es ist wirklich nicht vorrätig oder bei uns nicht verkäuflich, Ihre Kanaleinstellungen verbergen einen niedrigen Bestand, oder (bei Shopify) der Bestand liegt an einem anderen Standort. Prüfen Sie diese Punkte in der Reihenfolge unten und drücken Sie dann <b>Update Stock</b>.
</aside>

## Wie der Lagerbestand Ihren Shop erreicht

- Wir senden Lagerbestand nur für Produkte, die mit einem Eintrag in Ihrem Shop verknüpft sind: grün in der Spalte <b>Status</b> von <b>Meine Produkte</b>.
- Wenn sich unser Lagerbestand ändert, aktualisieren wir Ihren Shop von selbst. Sie müssen nichts tun.
- Wir senden 0 für Produkte, die nicht verkäuflich oder eingestellt sind, auch wenn noch Einheiten übrig sind.
- Ihre Kanaleinstellungen können die gesendete Zahl verringern. Siehe Schritt 4.

## Prüfen Sie dies der Reihe nach

### 1. Ist der Kanal verbunden?

Öffnen Sie <b>Channels</b> im Menü, klicken Sie auf Ihren Kanal und öffnen Sie <b>Meine Produkte</b>. Wenn ein rotes Feld <b>Your channel is not connected yet to the platform</b> anzeigt, können wir nichts senden. Verbinden Sie den Kanal zuerst neu. Stellen Sie bei Shopify sicher, dass Sie in Shopify auf <b>Install</b> gedrückt haben, um die Verbindung abzuschließen.

### 2. Ist das Produkt verknüpft?

Suchen Sie das Produkt in <b>Meine Produkte</b>. Bei Shopify muss der Status der grüne Handschlag sein (<b>Product connected to shopify</b>). Bei anderen Plattformen müssen alle drei Häkchen grün sein.

Ist es rot, ist der Eintrag in Ihrem Shop unseres Wissens nicht unserer, sodass wir seinen Lagerbestand nie aktualisieren. Das passiert oft, wenn Sie das Produkt selbst angelegt oder aus einer anderen App importiert haben. Verknüpfen Sie es mit <b>Match with this product</b>, oder verknüpfen Sie alle auf einmal mit <b>Match all with default product</b>. Siehe [Meine Produkte verwenden](/docs/my-products).

### 3. Ist es bei uns vorrätig?

Sehen Sie sich <b>Stocks</b> (bei Shopify <b>Stock</b>) in der Zeile an. Außer bei Shopify können Sie den Filter <b>Out of stock</b> nutzen, um alle Produkte ohne Bestand aufzulisten. Ein durchgestrichenes Dollarzeichen bedeutet <b>This product line is currently not for sale</b>, und ein durchgestrichenes Kästchen bedeutet, dass es eingestellt ist. In all diesen Fällen zeigt Ihr Shop zu Recht „nicht vorrätig" an.

Um benachrichtigt zu werden, wenn ein Produkt wieder verfügbar ist, nutzen Sie die Umschlag-Schaltfläche beim Produkt auf unserer Website. Ihre Erinnerungen finden Sie unter <b>Back In Stock Reminders</b> im Menü.

### 4. Prüfen Sie Ihre Kanal-Bestandseinstellungen

Drücken Sie auf der Kanalseite <b>Manage Sales Channel</b> (oder <b>Edit</b>). Unter <b>Manage Stock</b>:

- <b>Stock Update</b>: Ist dies deaktiviert, aktualisieren wir den Bestand nicht mehr automatisch. Lassen Sie es aktiviert.
- <b>Stock Threshold</b>: Fällt unser Bestand auf diese Zahl oder darunter, senden wir 0. Bei einer Schwelle von 10 zeigt ein Produkt mit 8 Einheiten also „nicht vorrätig". Lassen Sie das Feld leer, um den tatsächlichen Bestand zu senden.
- <b>Max Quantity To Advertise</b>: die höchste angezeigte Menge, selbst wenn wir mehr haben. Lassen Sie das Feld leer für keine Obergrenze.

<!-- screenshot: Abschnitt „Manage Stock" der Kanaleinstellungen mit Stock Update, Max Quantity To Advertise und Stock Threshold -->

### 5. Bestand jetzt übertragen

Drücken Sie in <b>Meine Produkte</b> auf <b>Update Stock</b> (Shopify, WooCommerce, eBay, TikTok Shop und Wix). Sie sehen <b>Stock update started</b>. Es kann ein paar Minuten dauern. Öffnen Sie dann den Tab <b>Logs</b>: Zeilen vom Typ <b>Update Stock</b> zeigen <b>Done</b> oder <b>Failed</b> mit der Antwort Ihrer Plattform.

Steht dort <b>Nothing to update</b>, ist noch keines Ihrer Produkte verknüpft. Gehen Sie zurück zu Schritt 2.

## Shopify

Bei Shopify liegt unser Bestand an unserem eigenen Fulfilment-Standort, benannt <b>aiku-</b> gefolgt von unserem Shop-Code und Ihrem Kanal-Code in Klammern, zum Beispiel <b>aiku-awd (my-store)</b>.

1. Öffnen Sie in Shopify <b>Products</b> und das Produkt, das als nicht vorrätig angezeigt wird.
2. Prüfen Sie im Abschnitt <b>Inventory</b>, ob unser Standort aufgeführt ist und Bestand hat.
3. Liegt der Bestand an einem anderen Standort (zum Beispiel Ihrer eigenen Shop-Adresse) bei 0, ist das die Zahl, die Shopify für diesen Standort anzeigt. Unser Bestand liegt immer nur an unserem Standort.

Wenn Shopify meldet, dass das Produkt an unserem Standort nicht gelagert wird, fügen wir es selbst zu unserem Standort hinzu, damit die nächste Bestandsaktualisierung durchgehen kann. Steht im Tab <b>Logs</b> <b>No variant on Shopify matches this sku</b>, entspricht die SKU der Shopify-Variante nicht unserem Produktcode. Ändern Sie die SKU in Shopify auf unseren Code, oder verknüpfen Sie das Produkt erneut mit <b>Connect with other product</b>.

## Wix

Wir senden nie Lagerbestand für ein Wix-Produkt, das nicht verknüpft ist. Zeigt Wix alle Ihre Produkte als nicht vorrätig an, wurden die Produkte höchstwahrscheinlich direkt in Wix angelegt oder nicht zugeordnet. Nutzen Sie in <b>Meine Produkte</b> <b>Match all with default product</b>, um sie per SKU zu verknüpfen, oder <b>Create new product</b>, damit wir sie erstellen. Drücken Sie danach <b>Update Stock</b>.

## eBay

Wenn wir 0 senden, zeigt eBay den Eintrag als nicht vorrätig an. Nutzt Ihr eBay-Konto die eBay-Option für „nicht vorrätig" nicht, kann eBay den Eintrag stattdessen beenden. Aktivieren Sie diese Option in Ihren eBay-Verkaufseinstellungen, damit Einträge bestehen bleiben und wiederkommen, sobald wir Bestand haben.

## WooCommerce und TikTok Shop

Prüfen Sie den Tab <b>Logs</b>. Bei WooCommerce bedeutet eine <b>Failed</b>-Bestandsaktualisierung mit „503", „timed out" oder „The store answered with a web page instead of data", dass Ihre Website uns nicht hereingelassen hat. Prüfen Sie, ob Ihre Seite online ist und ob Ihr Hosting oder Sicherheits-Plugin uns nicht blockiert, und drücken Sie dann erneut <b>Update Stock</b>.

## Wenn etwas schiefgeht

- **„Stock update failed. This channel is not connected to the platform, so stock cannot be updated."** Verbinden Sie den Kanal neu und versuchen Sie es erneut.
- **„Nothing to update".** Keines Ihrer Produkte ist verknüpft. Verknüpfen Sie sie zuerst (Schritt 2).
- **Der Bestand ist bei Meine Produkte richtig, aber in meinem Shop falsch, und Logs zeigt Done.** Ihr Shop übernimmt möglicherweise Bestand aus eigenen Standorten oder Apps. Prüfen Sie, ob keine andere App oder kein anderer Standort den Bestand dieses Produkts ändert.
- **Das Produkt ist wieder vorrätig, aber mein Shop zeigt immer noch 0.** Drücken Sie <b>Update Stock</b> und prüfen Sie den Tab <b>Logs</b>. Zeigt die Aktualisierung <b>Failed</b>, steht dort der Grund.
