---
title: Zahlungskarten und -optionen
summary: Wie Sie Bestellungen bezahlen, welche Zahlungsmethoden die Kasse anbietet, und wie Sie eine Karte speichern, damit Bestellungen aus Ihren verbundenen Shops automatisch bezahlt werden.
date: 2026-09-25
source_date: 2026-09-25
tags: Zahlung, Karte, Gespeicherte Karten, Kasse, PayPal, Apple Pay, Google Pay, Automatische Zahlung
category: payments
shops: awd, dssk, dse
---

<aside class="tldr">
Ihr Guthaben wird immer zuerst verwendet. Was das Guthaben nicht deckt, bezahlen Sie an der Kasse unter <b>Online payments</b>: Karte, Apple Pay, Google Pay, PayPal und weitere Methoden, je nach Land und Gerät. Bestellungen aus Ihren verbundenen Shops werden ohne Ihr Zutun bezahlt: zuerst aus Ihrem Guthaben, dann von einer Karte, die Sie unter <b>Saved Cards</b> gespeichert haben. Speichern Sie eine Karte, oder halten Sie Ihr Guthaben aufgeladen, damit diese Bestellungen nicht unbezahlt bleiben.
</aside>

## Zwei Arten, wie Bestellungen bezahlt werden

- <b>Bestellungen, die Sie selbst anlegen</b> (manuelle Bestellungen): Sie bezahlen an der Kasse, während Sie zusehen.
- <b>Bestellungen aus Ihren verbundenen Shops</b> (Shopify, WooCommerce, eBay, TikTok und weitere, oder über die API): niemand ist an der Kasse, also übernehmen wir die Zahlung selbst. Wir nutzen zuerst Ihr Guthaben, dann Ihre gespeicherten Karten. Siehe [Topping up and paying with balance](/docs/topping-up-and-paying-with-balance).

## An der Kasse bezahlen

1. Drücken Sie auf dem <b>Dashboard</b> unter <b>Quick links (Shortcuts)</b> auf <b>Create manual Order</b>.
2. Wählen Sie unter <b>Select Customer Client</b> die Person, an die Sie versenden, oder drücken Sie <b>Create new client here</b>. Drücken Sie <b>Create Order</b>.
3. Fügen Sie Produkte zum Warenkorb hinzu, prüfen Sie die Adresse und drücken Sie <b>Continue to Checkout</b>. Deckt Ihr Guthaben die gesamte Bestellung, zeigt der Warenkorb stattdessen <b>Place order</b>: drücken Sie darauf, und die Bestellung wird aus Ihrem Guthaben bezahlt.
4. Die Kasse zeigt Ihre <b>Order number</b> und die Zusammenfassung. Haben Sie Guthaben, wird es zuerst verwendet: Sie sehen, wie viel mit Guthaben bezahlt wird, und <b>Please paid the rest with your preferred method below:</b>.
5. Wählen Sie unter <b>Online payments</b>, wie Sie den Rest bezahlen, und folgen Sie den Schritten. Ihre Bank fragt Sie möglicherweise, die Zahlung in ihrer App oder mit einem Code zu bestätigen.
6. Nach der Zahlung sehen Sie <b>Payment done. Waiting for confirmation...</b>. Sobald die Zahlung bestätigt ist, wird die Bestellung an unser Lager gesendet.

Deckt Ihr Guthaben die gesamte Bestellung, gibt es kein Zahlungsformular: Sie sehen nur <b>Place order</b>.

<!-- screenshot: die Kassenseite mit der Bestellzusammenfassung und dem Online-payments-Formular mit Karte, Apple Pay und PayPal -->

## Welche Zahlungsmethoden Sie nutzen können

Das Formular <b>Online payments</b> zeigt die Methoden, die für Ihr Land, Ihre Währung und Ihr Gerät funktionieren. Kunden nutzen:

- Debit- und Kreditkarten
- Apple Pay (auf Apple-Geräten) und Google Pay
- PayPal
- Klarna
- In manchen europäischen Ländern: iDEAL, Przelewy24 und Bancontact

Sehen Sie eine erwartete Methode nicht, ist sie für Ihr Land, Ihre Währung oder Ihr Gerät nicht verfügbar. Banküberweisung und Nachnahme werden an der Dropshipping-Kasse nicht angeboten.

## Eine Karte für automatische Zahlungen speichern

Gespeicherte Karten werden genutzt, um Bestellungen aus Ihren verbundenen Shops zu bezahlen, wenn Ihr Guthaben nicht ausreicht.

Der Punkt <b>Saved Cards</b> erscheint im linken Menü, sobald Sie einen Shop verbunden, ein API-Token erstellt oder eine Karte gespeichert haben. Ein kleiner Punkt daneben bedeutet, dass Sie noch keine gespeicherte Karte haben.

So speichern Sie eine Karte:

1. Drücken Sie <b>Saved Cards</b> im linken Menü. Die Seite heißt <b>Credit Card Dashboard</b>.
2. Drücken Sie oben <b>Save Credit Card</b> (oder <b>Add credit card</b> über Ihrer Kartenliste).
3. Geben Sie Ihre Kartendaten ein. Ihre Bank bittet Sie um eine Bestätigung. Das ist nötig, damit wir die Karte später ohne Ihr Zutun belasten können.
4. Die Karte erscheint in der Liste, die <b>Card type</b>, <b>Expired</b>-Status, <b>Last 4 digits</b> und <b>Added date</b> zeigt.

Hier können nur Karten gespeichert werden. Apple Pay, Google Pay und PayPal können nicht für automatische Zahlungen gespeichert werden.

<!-- screenshot: das Credit Card Dashboard mit einer als Standard markierten Karte und den Schaltflächen „Set as default" und „Unlink" -->

## Mehr als eine Karte

- Die Standardkarte hat ein grünes Häkchen. Drücken Sie <b>Set as default</b> bei einer anderen Karte, um sie zuerst zu nutzen.
- Müssen wir eine Bestellung bezahlen, versuchen wir zuerst die Standardkarte, dann Ihre anderen Karten, eine nach der anderen, bis eine funktioniert.
- Um eine Karte zu entfernen, drücken Sie <b>Unlink</b> und bestätigen.

Prüfen Sie das Ablaufdatum Ihrer Karten. Läuft eine Karte ab, speichern Sie die neue und entfernen Sie die alte.

## Wenn etwas schiefgeht

- <b>Something went wrong</b> / <b>Failed to communicate with the payment service.</b>: Die Zahlung ist nicht gestartet. Aktualisieren Sie die Kassenseite und versuchen Sie es erneut, oder wählen Sie eine andere Methode.
- <b>Payment still processing</b> / <b>Your order will be submitted automatically once the payment is confirmed.</b>: Ihre Bank hat noch nicht bestätigt. Zahlen Sie nicht erneut. Prüfen Sie die Bestellung in ein paar Minuten.
- <b>Order already submitted</b> / <b>This order has already been submitted and cannot be paid again.</b>: Die Bestellung ist bereits bezahlt. Sie werden zur Bestellseite weitergeleitet.
- <b>Online payments are temporarily unavailable</b>: Der Zahlungsdienst antwortet nicht. Versuchen Sie es später erneut, oder laden Sie Ihr Guthaben auf und bezahlen Sie damit.
- <b>Insert file missing</b>: Für ein Einlegeblatt in Ihrer Bestellung fehlt eine Datei. Gehen Sie zurück zum Warenkorb und laden Sie die Datei vor der Kasse hoch.
- <b>We cannot deliver to …</b> oder <b>Your current billing address (…) is marked as forbidden</b>: Wir können für diese Adresse keine Zahlung entgegennehmen. Ändern Sie die Adresse, oder kontaktieren Sie uns im Chat auf unserer Website.
- Eine Bestellung aus Ihrem Shop zeigt <b>Unpaid</b>, und Sie haben eine E-Mail erhalten, dass sie zurückgestellt ist: Ihr Guthaben reichte nicht, und keine gespeicherte Karte hat funktioniert. Laden Sie Ihr Guthaben auf und drücken Sie bei der Bestellung <b>Pay … with balance</b>. Siehe [Topping up and paying with balance](/docs/topping-up-and-paying-with-balance).
- Ihre Karte wurde bei einer automatischen Zahlung abgelehnt: Ihre Bank hat die Belastung zurückgewiesen. Prüfen Sie sie unter <b>Saved Cards</b> — sie könnte abgelehnt oder abgelaufen sein — laden Sie dann Ihr Guthaben auf und zahlen Sie damit, oder speichern Sie eine andere Karte und legen Sie sie als Standard fest.
