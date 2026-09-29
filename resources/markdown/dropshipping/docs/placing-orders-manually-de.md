---
title: Bestellungen manuell aufgeben
summary: Legen Sie für einen Kunden auf einem Manual/API-Kanal eine Bestellung an, fügen Sie Produkte hinzu, wählen Sie die Versandoptionen, bezahlen Sie an der Kasse und verfolgen Sie die Bestellung bis zum Versand.
date: 2026-09-25
source_date: 2026-09-25
tags: Manuell, Bestellungen, Warenkorb, Kasse, Zahlung, Guthaben, Abholung, Versand
category: orders
series: manual
order: 4
shops: awd, dssk, dse
---

<aside class="tldr">
Öffnen Sie den Kunden in Ihrem Manual/API-Kanal und drücken Sie <b>Create Order</b>. Ein Warenkorb öffnet sich: Fügen Sie mit <b>Add products</b> Produkte hinzu, wählen Sie die Versandoptionen und drücken Sie <b>Continue to Checkout</b>. Wir nutzen zuerst Ihr Kontoguthaben, den Rest bezahlen Sie per Karte. Sobald die Bestellung bezahlt ist, geht sie ins Lager, und Sie verfolgen sie unter <b>Orders</b>.
</aside>

## Bevor Sie beginnen

- Sie brauchen einen Manual/API-Kanal. Siehe [The Manual/API channel](/docs/manual-and-api-channel).
- Die Person, an die Sie versenden, muss ein Kunde dieses Kanals sein. Siehe [Managing clients](/docs/managing-clients).

## Die Bestellung anlegen

1. Öffnen Sie Ihren Manual/API-Kanal und dann <b>Clients</b>.
2. Klicken Sie auf den Namen des Kunden. Die Kundenseite öffnet sich.
3. Drücken Sie <b>Create Order</b>.

Der Warenkorb für die neue Bestellung öffnet sich. Oben stehen der Kunde, seine Kontaktdaten und die Lieferadresse. Prüfen Sie Name und Adresse, bevor Sie fortfahren.

## Produkte hinzufügen

- <b>Add products</b> öffnet ein Fenster <b>Add products to Order</b>. Suchen Sie nach Produktname oder -code, geben Sie die Menge ein und fügen Sie die Produkte hinzu. Sie können jedes Produkt wählen, das wir verkaufen, nicht nur die in <b>My Products</b>.
- <b>Upload products</b> fügt viele Produkte aus einer Tabelle hinzu. Laden Sie die Vorlage (.xlsx) im Fenster herunter, füllen Sie die Spalten <b>code</b> und <b>quantity</b> aus und laden Sie sie hoch.

Die Produkte erscheinen in der Liste. Dort können Sie Mengen ändern oder eine Zeile entfernen. Das geschätzte Gewicht des Pakets wird neben der Adresse angezeigt.

<!-- screenshot: ein Warenkorb mit Kunde und Adresse oben, Produkten in der Liste, den Versandoptionen und der Continue-to-Checkout-Schaltfläche -->

## Versandoptionen wählen

- <b>Collection</b>: Aktivieren Sie dies, wenn Sie oder ein von Ihnen gebuchter Kurier die Bestellung selbst aus unserem Lager abholt, statt dass wir sie versenden. Es fällt eine Abholgebühr an. Ist es deaktiviert, senden wir die Bestellung an die angezeigte Adresse. Drücken Sie <b>Edit</b> unter der Adresse, um sie für diese Bestellung zu ändern.
- Schnellerer Versand: bei AW Dropship UK heißt die Option <b>Same Day Dispatch</b>, bei AW Dropship Europe <b>Premium Dispatch</b> und bei AW Dropship España <b>Envío Premium</b>. Es fällt ein Zuschlag an. Lesen Sie das Info-Symbol daneben für die Bedingungen.
- <b>Extra protective packing for fragile items</b> (nur AW Dropship UK): zusätzliche Verpackung für zerbrechliche Produkte. Es fällt ein Zuschlag an.
- <b>Delivery Instructions</b>: eine Notiz für den Kurier. <b>This message will be printed in shipping label</b>, schreiben Sie sie also für den Kurier, nicht für uns.
- <b>Other Instructions</b>: eine Notiz für unser Team.

Die Gebühren und die Gesamtsumme der Bestellung aktualisieren sich, wenn Sie eine Option umschalten.

Dies sind die Zusatzgebühren auf dieser Website:

{order_charges}

## Bezahlen

Deckt Ihr Kontoguthaben die gesamte Bestellung, zeigt der Warenkorb <b>Place order</b> statt <b>Continue to Checkout</b>. Drücken Sie darauf, und die Bestellung wird aus Ihrem Guthaben bezahlt. Der Hinweis lautet <b>This is your final confirmation. You can pay totally with your current balance.</b>

Andernfalls:

1. Drücken Sie <b>Continue to Checkout</b>.
2. Die Kasse zeigt die Bestellnummer. Haben Sie etwas Guthaben, wird Ihnen gesagt, wie viel damit bezahlt wird, und Sie werden gebeten, den Rest zu bezahlen.
3. Geben Sie unter <b>Online payments</b> Ihre Kartendaten ein und bestätigen Sie. Ihre Bank kann Sie bitten, die Zahlung in ihrer App oder mit einem Code zu genehmigen.
4. Ist die Zahlung erfolgt, zeigt die Seite <b>Payment done. Waiting for confirmation...</b> und öffnet dann die Bestellung.

Drücken Sie an der Kasse <b>Back to basket</b>, um die Bestellung vor der Zahlung zu ändern.

## Unfertige Bestellungen: Baskets

Eine Bestellung, die Sie angelegt, aber nicht bezahlt haben, bleibt unter Ihrem Kanal in <b>Baskets</b>. Die Zahl neben <b>Baskets</b> im Menü zeigt, wie viele Sie haben. Öffnen Sie eine, um sie fertigzustellen, oder drücken Sie <b>Delete</b> bei ihrer Zeile (Tooltip <b>Delete basket</b>), um sie zu entfernen. Ein Warenkorb wird erst ans Lager gesendet, wenn er bezahlt ist.

## Ihre Bestellungen verfolgen

Öffnen Sie <b>Orders</b> unter Ihrem Kanal. Die Liste zeigt <b>Status</b>, <b>Reference</b>, Kunde, <b>Date</b>, Artikel und Summe. Klicken Sie auf eine Bestellung, um ihre Produkte, Lieferscheine, Sendungen mit Tracking-Links und Rechnungen zu sehen.

Das Statussymbol zeigt, wo sich die Bestellung befindet. Fahren Sie darüber, um den Namen zu sehen:

- <b>Submitted</b>: Wir haben die Bestellung erhalten.
- <b>In Warehouse</b>, <b>Picking</b>, <b>Picked</b>, <b>Packing</b>, <b>Packed</b>: Unser Team bereitet sie vor.
- <b>Waiting</b>: Sie ist im Lager zurückgestellt.
- <b>Finalized</b>: versandbereit.
- <b>Dispatched</b>: versendet. Der Tracking-Link steht bei der Bestellung.
- <b>Cancelled</b>: Die Bestellung wurde storniert.

Symbole oben bei einer Bestellung zeigen die von Ihnen gewählten Optionen: ein Stern für <b>Premium dispatch</b>, ein Kasten für <b>Extra packing</b>.

Können wir manche Artikel nicht senden, zeigt die Bestellung <b>Some items are not being sent</b>. Das Geld für diese Artikel wird automatisch erstattet.

Die Bestellseite listet ihre Rechnungen mit einer Download-Schaltfläche. Alle Ihre Rechnungen finden Sie auch unter <b>Invoices</b> im Menü.

## Wenn etwas schiefgeht

- <b>I cannot see Create Order on the client page.</b> Die Schaltfläche gibt es nur bei Kunden eines Manual/API-Kanals. Bei verbundenen Kanälen kommen Bestellungen aus Ihrem Shop.
- <b>The basket says "We cannot deliver to …".</b> Wir liefern nicht in dieses Land. Ändern Sie die Lieferadresse, oder aktivieren Sie <b>Collection</b>, wenn Sie den Transport selbst organisieren.
- <b>The basket says your billing address is marked as forbidden.</b> Aktualisieren Sie die Rechnungsadresse in Ihrem Konto, oder kontaktieren Sie uns im Chat auf unserer Website.
- <b>Continue to Checkout is greyed out and asks me to upload a file.</b> Sie haben ein Druckeinlegeblatt gewählt, das Ihre Grafikdatei braucht. Laden Sie die Datei dafür hoch, oder entfernen Sie das Einlegeblatt, bevor Sie zur Kasse gehen.
- <b>The card payment failed.</b> Die Kasse zeigt <b>Something went wrong</b>. Prüfen Sie die Kartendaten und ob Ihre Bank die Zahlung genehmigt hat, und versuchen Sie es erneut. Sie können auch Ihr Guthaben aufladen und damit bezahlen.
- <b>The checkout says "Payment still processing".</b> Zahlen Sie nicht erneut. Die Bestellung wird automatisch übermittelt, sobald die Zahlung bestätigt ist.
- <b>The checkout says "Order already submitted".</b> Die Bestellung ist bereits bezahlt. Öffnen Sie sie unter <b>Orders</b>.
- <b>My order shows Unpaid.</b> Die Zahlung hat die Bestellung nicht gedeckt. Fügen Sie mit <b>Top Up</b> Geld zu Ihrem Guthaben hinzu, öffnen Sie die Bestellung und drücken Sie <b>Pay … with balance</b>. Die Schaltfläche erscheint, wenn Ihr Guthaben den fälligen Betrag deckt. Die Bestellung geht dann ins Lager.
- <b>I need to change or cancel an order I already paid.</b> Es gibt keine Stornieren-Schaltfläche. Kontaktieren Sie uns so schnell wie möglich im Chat auf unserer Website. Wir können nur stornieren oder ändern, bevor sie versendet ist. Ist sie erst einmal verpackt, kann es zu spät sein.
