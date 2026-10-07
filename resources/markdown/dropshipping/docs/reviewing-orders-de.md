---
title: Ihre Bestellungen überprüfen
summary: Finden Sie die Bestellungen jedes Verkaufskanals, lesen Sie ihren Status, sehen Sie, was gesendet wurde und was Sie bezahlt haben, und verstehen Sie, warum eine Bestellung unbezahlt, storniert oder gar nicht vorhanden ist.
date: 2026-09-25
source_date: 2026-09-25
tags: Bestellungen, Bestellstatus, Unbezahlt, Storniert, Bestellliste
category: orders
shops: awd, dssk, dse
---

<aside class="tldr">
Bestellungen werden je Verkaufskanal geführt. Öffnen Sie im linken Menü Ihren Kanal und klicken Sie auf <b>Orders</b>. Jede Bestellung zeigt ihren Status und ein rotes <b>Unpaid</b>-Label, wenn wir das Geld noch nicht entgegennehmen konnten. Eine unbezahlte Bestellung wartet und wird erst ans Lager gesendet, wenn sie bezahlt ist. Klicken Sie auf die Bestellreferenz, um Produkte, Lieferadresse, Tracking-Nummer und Rechnung zu sehen.
</aside>

## Wo Ihre Bestellungen sind

Jeder von Ihnen verbundene Kanal (Shopify, eBay, TikTok, WooCommerce und weitere, sowie Ihr manueller Kanal) hat seine eigene Bestellliste.

1. Suchen Sie im linken Menü Ihren Kanal unter <b>Channels</b>.
2. Klicken Sie darunter auf <b>Orders</b>.

Die Liste hat diese Spalten: <b>Status</b>, <b>Reference</b>, <b>Client</b>, <b>Date</b>, <b>Items</b> und <b>Total</b>. Die neuesten Bestellungen stehen oben. Nutzen Sie das Suchfeld, um eine Bestellung anhand ihrer Referenz zu finden.

Die <b>Reference</b> ist unsere Bestellnummer. Es ist nicht die Bestellnummer in Ihrem Shop und keine Tracking-Nummer. Um die Tracking-Nummer zu finden, siehe [Finding the tracking number of an order](/docs/tracking-numbers).

Bei einem Manual/API-Kanal stehen Bestellungen, die Sie noch vorbereiten, nicht in dieser Liste. Sie stehen unter demselben Kanal in <b>Baskets</b>, bis Sie sie aufgeben.

Um die Liste herunterzuladen, nutzen Sie die Export-Schaltfläche oben auf der Seite und wählen <b>Excel</b> oder <b>CSV</b>.

<!-- screenshot: die Orders-Liste eines Kanals mit der Status-Spalte, einer Referenz mit dem roten Unpaid-Label und der Export-Schaltfläche -->

## Was jeder Status bedeutet

- <b>Submitted</b>: Wir haben die Bestellung. Ist sie nicht bezahlt, bleibt sie hier, bis sie bezahlt ist.
- <b>In Warehouse</b>: Die Bestellung ist bezahlt und wartet darauf, kommissioniert zu werden.
- <b>Picking</b>: Das Lager kommissioniert die Produkte.
- <b>Waiting</b>: Die Kommissionierung ist pausiert, zum Beispiel während das Lager ein Produkt prüft.
- <b>Picked</b>, <b>Packing</b>, <b>Packed</b>: Das Paket wird vorbereitet.
- <b>Finalized</b>: Die Bestellung ist fakturiert und versandbereit.
- <b>Dispatched</b>: Das Paket hat unser Lager verlassen. Die Tracking-Nummer steht bei der Bestellung.
- <b>Cancelled</b>: Die Bestellung wird nicht gesendet.

Neben der Referenz sehen Sie manchmal auch kleine Symbole für <b>Premium dispatch</b>, <b>Extra packing</b> und <b>Insurance</b>, wenn Sie diese für diese Bestellung gewählt haben.

## Unbezahlte Bestellungen

Ein rotes <b>Unpaid</b>-Label bedeutet, dass wir den vollen Betrag noch nicht entgegennehmen konnten. Die Bestellung bleibt <b>Submitted</b> und wird nicht ans Lager gesendet.

Wenn eine Bestellung aus Ihrem Shop eingeht, bezahlen wir sie so:

1. Zuerst mit Ihrem Guthaben.
2. Reicht das Guthaben nicht, mit Ihren gespeicherten Karten, beginnend mit Ihrer Standardkarte.

Klappt keines von beiden, wartet die Bestellung, und wir senden Ihnen eine E-Mail, dass sie zurückgestellt ist. Meist passiert das, weil für den Kanal keine Karte gespeichert ist. Damit das nicht wieder passiert, speichern Sie eine Karte: siehe [Topping up and paying with balance](/docs/topping-up-and-paying-with-balance).

Um eine wartende Bestellung zu bezahlen:

1. Laden Sie Ihr Guthaben mit mindestens dem fälligen Betrag auf. Siehe [Topping up and paying with balance](/docs/topping-up-and-paying-with-balance).
2. Öffnen Sie die Bestellung erneut. Ein gelbes Feld zeigt <b>Order ... is not paid yet</b> und <b>Your balance</b>.
3. Klicken Sie auf die Schaltfläche <b>Pay ... with balance</b>. Sie zeigt den fälligen Betrag.

Die Bestellung geht dann ins Lager. Die Schaltfläche erscheint nur, wenn Ihr Guthaben den gesamten fälligen Betrag deckt, während die Bestellung <b>Submitted</b> oder <b>Picking</b> ist.

<b>Note:</b> Wir versuchen Ihre Karte nicht von selbst erneut. Eine wartende Bestellung bleibt wartend, bis Sie sie bezahlen.

## In einer Bestellung

Klicken Sie auf die Bestellreferenz, um sie zu öffnen. Sie sehen:

- Oben eine Zeitleiste mit den Schritten, die die Bestellung durchlaufen hat, und ein Label <b>Paid</b> oder <b>Unpaid</b>.
- Ihren Kunden: Name, E-Mail, Telefon und Lieferadresse.
- <b>Weight</b>: das geschätzte Gewicht aller Produkte.
- <b>Delivery Notes</b>: die Pakete, ihren Status, und unter <b>Shipments</b> den Kurier und die Tracking-Nummer. Das PDF-Symbol (<b>Download Picking List</b>) lädt die Produktliste des Pakets herunter.
- <b>Invoices</b>: unsere Rechnung für die Bestellung, zum Öffnen oder als PDF zum Herunterladen. Siehe [Your invoices](/docs/invoices).
- Die Preiszusammenfassung: <b>Items</b>, Gebühren, <b>Net</b>, Steuer und <b>Total</b>.

Dies sind die Zusatzgebühren auf dieser Website:

{order_charges}

- Den Tab <b>Transactions</b>: jedes Produkt mit seiner <b>Quantity</b>. Wurden weniger gesendet als bestellt, wird die gesendete Menge rot über der bestellten Menge angezeigt, die durchgestrichen ist.
- <b>Notes from Staff</b>, <b>Delivery Instructions</b> und <b>Other Instructions</b>. Lieferanweisungen werden auf dem Versandetikett gedruckt.

<!-- screenshot: eine Bestellseite mit der Zeitleiste, dem Delivery-Notes-Feld mit einer Tracking-Nummer und dem Invoices-Feld -->

## Wenn nicht alles gesendet wurde

Manchmal können wir nicht jedes Produkt senden, zum Beispiel wenn eines während der Kommissionierung ausgeht. Die Bestellseite zeigt dann <b>Dispatched | Modified</b>, und in der Liste erscheint ein gelbes Warnsymbol neben dem Status. Der Tab <b>Transactions</b> zeigt, welche Produkte nicht gesendet wurden. Das Geld für die nicht gesendeten Produkte geht von selbst auf Ihr Guthaben zurück, sobald die Bestellung fakturiert ist.

## Stornierte Bestellungen

Eine stornierte Bestellung zeigt oben ihren Status <b>Cancelled</b> und ein rotes Feld <b>Order cancelled</b>, wenn ein Grund vermerkt wurde. Geld, das Sie bereits dafür bezahlt haben, geht auf Ihr Guthaben zurück.

Bei einer Shopify-Bestellung gibt es oben auch eine Sync-Schaltfläche (Tooltip <b>Sync order state</b>). Klicken Sie darauf, um Shopify mitzuteilen, dass die Bestellung storniert wurde. Sehen Sie <b>The order state on Shopify is up-to-date</b>, weiß Shopify bereits Bescheid.

Es gibt keine Stornieren-Schaltfläche. Um eine Bestellung zu stornieren, kontaktieren Sie uns im Chat auf unserer Website mit der Bestellreferenz. Wir können nur stornieren, bevor sie versendet ist. Ist die Bestellung erst einmal verpackt, kann es zu spät sein.

## Eine Bewertung hinterlassen

Auf manchen unserer Websites erscheint eine Weile nach Versand einer Bestellung oben eine <b>Review</b>-Schaltfläche. Nutzen Sie sie, um die Bestellung und die Produkte zu bewerten.

## Wenn etwas schiefgeht

**An order from my store is not in the list.** Prüfen Sie dies in dieser Reihenfolge:

- Nur Produkte, die in <b>My Products</b> dieses Kanals sind, kommen zu uns. Ist keines der Produkte in der Bestellung in <b>My Products</b>, erscheint die Bestellung nicht unter <b>Orders</b>, weil es nichts gibt, das wir senden könnten.
- Prüfen Sie, ob Sie den richtigen Kanal ansehen. Jeder Kanal hat seine eigene Liste.
- Der Kanal muss noch verbunden sein. Zeigt die Kanalseite, dass er nicht verbunden ist, verbinden Sie ihn zuerst neu.
- **Shopify**: Nur Bestellungen, die Shopify an unseren Fulfilment-Standort sendet, kommen zu uns, und sie kommen als Fulfilment-Anfrage an. Ist ein Produkt der Bestellung nicht in <b>My Products</b>, wird dieser Teil der Anfrage in Shopify abgelehnt, und der Rest kommt an. Wird die gesamte Anfrage abgelehnt (keines der Produkte ist in <b>My Products</b>, oder die Bestellung hat keine Lieferadresse), erscheint die Bestellung unter <b>Orders</b> als <b>Cancelled</b>, mit dem Grund unter <b>Notes from Staff</b>. Beheben Sie die Bestellung in Shopify und fordern Sie die Fulfilment erneut an. Eine Bestellung, die von Ihrem eigenen Shop erfüllt wird, bereits erfüllt ist, oder deren Anfrage in Shopify storniert wurde, kommt nicht zu uns. Das ist meist der Grund, warum eine Testbestellung nicht ankommt: Prüfen Sie in Shopify, ob ihre Produkte an unserem Standort gelagert sind.

**My Shopify fulfilment request was accepted but I cannot see the order to pay.** Suchen Sie unter <b>Orders</b> des Shopify-Kanals nach ihrem Status und einem roten <b>Unpaid</b>-Label. Ist sie nicht da, kontaktieren Sie uns im Chat auf unserer Website mit der Shopify-Bestellnummer.

**The order has been Submitted for a long time.** Fast immer ist sie unbezahlt. Folgen Sie den Schritten oben unter „Unbezahlte Bestellungen".

**The order says "We cannot deliver to ...".** Wir liefern von dieser Website nicht in dieses Land. Siehe [Countries we cannot deliver to](/docs/delivery-restrictions).

**A product arrived broken, or my buyer wants to return something.** Kontaktieren Sie uns im Chat auf unserer Website mit der Bestellreferenz und Fotos. Ihr Käufer darf nichts zurückschicken, bevor es mit uns im Chat vereinbart wurde. Rückerstattungen gehen auf Ihr Guthaben.
