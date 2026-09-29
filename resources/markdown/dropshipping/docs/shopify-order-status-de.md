---
title: Ihre Shopify-Bestellungen und ihr Status
summary: Wie Bestellungen aus Ihrem Shopify-Shop uns erreichen, wie sie bezahlt werden, was jeder Status bedeutet, warum eine Bestellung als Submitted warten kann oder gar nicht ankommt, und was wir an Shopify zurücksenden.
date: 2026-09-25
source_date: 2026-09-25
tags: Shopify, Bestellungen, Status, Zahlung, Submitted, Unbezahlt, Fulfilment-Anfrage
category: orders
series: shopify
order: 4
shops: awd, dssk, dse
---

<aside class="tldr">
Kauft ein Kunde ein verbundenes Produkt in Ihrem Shopify-Shop, sendet Shopify uns eine Fulfilment-Anfrage, und die Bestellung erscheint unter <b>Orders</b> Ihres Kanals. Wir bezahlen sie aus Ihrem Guthaben, dann von Ihrer gespeicherten Karte. Eine bezahlte Bestellung geht von selbst an unser Lager. Eine Bestellung, die wir nicht bezahlen konnten, bleibt <b>Submitted</b> und <b>Unpaid</b>, bis Sie sie bezahlen. Wenn wir sie senden, markieren wir sie in Shopify mit der Tracking-Nummer als erfüllt.
</aside>

## Wie eine Bestellung uns erreicht

1. Ein Kunde kauft eines Ihrer verbundenen Produkte in Shopify.
2. Shopify sendet für diese Artikel eine Fulfilment-Anfrage an den <b>aiku-</b>-Standort.
3. Wir akzeptieren die Anfrage und legen die Bestellung in Ihrem Kanal an. Sie finden sie unter Ihrem Kanal, <b>Orders</b>.

Nur Produkte, die in <b>My Products</b> verbunden sind, können zu uns kommen. Enthält eine Bestellung sowohl unsere als auch Ihre eigenen Produkte, akzeptieren wir unsere Produkte, und Sie senden den Rest selbst.

## Wie die Bestellung bezahlt wird

Wir versuchen, jede neue Bestellung sofort zu bezahlen:

1. Zuerst mit Ihrem Guthaben.
2. Reicht das Guthaben nicht, mit den unter <b>Saved Cards</b> gespeicherten Karten, in Ihrer Prioritätsreihenfolge.

Klappt die Zahlung, geht die Bestellung von selbst an unser Lager. Wenn nicht, wartet die Bestellung, und wir senden Ihnen eine E-Mail, dass sie zurückgestellt ist. Meist passiert das, weil keine Karte gespeichert ist. Damit das nicht wieder passiert, speichern Sie eine Karte: siehe [Payment cards and options](/docs/payment-cards-and-options).

## Eine wartende Bestellung bezahlen

Eine Bestellung, die wir nicht bezahlen konnten, zeigt neben ihrer Nummer <b>Unpaid</b> und bleibt <b>Submitted</b>.

1. Laden Sie Ihr Guthaben mit mindestens dem fälligen Betrag im Menü unter <b>Top Up</b> auf.
2. Öffnen Sie Ihren Kanal, <b>Orders</b>, und öffnen Sie die Bestellung.
3. Drücken Sie <b>Pay ... with balance</b>. Die Schaltfläche erscheint nur, wenn Ihr Guthaben den fälligen Betrag deckt.

Die Bestellung geht dann von selbst an unser Lager.

<!-- screenshot: eine unbezahlte Bestellung mit dem Unpaid-Label und der Pay-with-balance-Schaltfläche -->

## Was jeder Status bedeutet

- <b>Submitted</b>: Wir haben die Bestellung. Zeigt sie auch <b>Unpaid</b>, wartet sie auf Ihre Zahlung.
- <b>In Warehouse</b>: bezahlt und wartet darauf, kommissioniert zu werden.
- <b>Handling</b>: wird kommissioniert.
- <b>Waiting</b>: Das Lager musste die Bestellung kurz stoppen, bevor es weitergehen kann.
- <b>Picked</b>, <b>Packing</b>, <b>Packed</b>: Das Paket wird vorbereitet.
- <b>Finalized</b>: fakturiert und versandbereit.
- <b>Dispatched</b>: versendet. Konnten manche Artikel nicht gesendet werden, sehen Sie <b>Modified</b>, und das Geld dafür wird automatisch erstattet.
- <b>Cancelled</b>: Die Bestellung wird nicht gesendet. Der Grund steht oben bei der Bestellung.

Mehr zur Bestellseite finden Sie unter [Reviewing your orders](/docs/reviewing-orders).

## Was wir an Shopify zurücksenden

- Wird die Bestellung versendet, markieren wir sie in Shopify mit Tracking-Nummer und Link als erfüllt. Shopify informiert Ihren Kunden.
- Wird eine Bestellung storniert, schließen wir die Anfrage in Shopify. Bei einer stornierten Bestellung können Sie die Sync-Schaltfläche (<b>Sync order state</b>) drücken, um die Stornierung erneut an Shopify zu senden. Ist Shopify bereits aktuell, sehen Sie <b>The order state on Shopify is up-to-date</b>.

## Eine Bestellung fehlt in meinen Orders

Öffnen Sie den Kanal und drücken Sie <b>Fetch orders</b>. Es <b>Checks Shopify for orders that have not reached us yet</b>: Es schaut sich kürzliche, nicht erfüllte Bestellungen an und holt die für unseren Standort. Gibt es nichts Neues, sehen Sie <b>No new orders</b>. Sie können es nach ein paar Minuten erneut drücken.

Kommt die Bestellung immer noch nicht, prüfen Sie Folgendes:

- Die Produkte sind (grüner Handschlag) in <b>My Products</b> verbunden.
- Die Artikel sind am <b>aiku-</b>-Standort in Shopify gelagert, und der Standort ist in Ihrem Versandprofil. Siehe [The AW fulfilment location in Shopify](/docs/shopify-fulfilment-location).
- Die Bestellung wurde in Shopify nicht bereits erfüllt oder an einen anderen Standort gesendet.

## Wenn etwas schiefgeht

**A cancelled order says "Fulfilment request declined: The items can't be fulfilled because you don't have the items in your portfolio."** Keines der Produkte in der Bestellung ist in <b>My Products</b> verbunden. Fügen Sie sie hinzu und verbinden Sie sie, fordern Sie dann in Shopify die Fulfilment erneut an.

**A cancelled order says "Fulfilment request declined: Order don't have shipping information".** Die Bestellung in Shopify hat keine Lieferadresse. Fügen Sie die Adresse in Shopify hinzu und fordern Sie die Fulfilment erneut an.

**The fulfilment request was accepted in Shopify, but I cannot see the order to pay.** Öffnen Sie <b>Orders</b> im Kanal: Dort werden Bestellungen aus Shopify aufgelistet. Suchen Sie die Bestellung mit <b>Unpaid</b>, oder drücken Sie <b>Fetch orders</b>.

**My test order on Shopify did not come over.** Eine Testbestellung erreicht uns nur, wenn sie verbundene Produkte enthält, die am <b>aiku-</b>-Standort gelagert sind. Achtung: Eine Bestellung, die uns erreicht, ist eine echte Bestellung. Wir bezahlen und versenden sie. Haben Sie versehentlich eine gemacht, kontaktieren Sie uns schnell im Chat auf unserer Website, um sie zu stornieren. Wir können nur stornieren, bevor sie versendet ist.

**The order stays Submitted and Unpaid.** Es gab nicht genug Guthaben, und keine Karte hat funktioniert. Bezahlen Sie sie wie unter „Eine wartende Bestellung bezahlen" beschrieben, und speichern Sie eine Karte für die nächsten Bestellungen.

**The order says "We cannot deliver to ...".** Wir liefern von dieser Website nicht in dieses Land. Die Bestellung ist nicht bezahlt und wird nicht gesendet. Aktualisieren Sie die Lieferadresse, oder kontaktieren Sie uns im Chat auf unserer Website.

**The order is paid but has not moved for a long time.** Kontaktieren Sie uns im Chat auf unserer Website, mit der Bestellnummer. Die Bestellnummer ist die <b>Reference</b> in <b>Orders</b>.

<aside class="wayfinder"><strong>Wo Sie klicken</strong>
<ul>
<li><b>Ihre Shopify-Bestellungen ansehen:</b> <b>Channels</b> → Ihr Shopify-Shop → <b>Orders</b>.</li>
<li><b>Eine wartende Bestellung bezahlen:</b> Bestellung öffnen → <b>Pay ... with balance</b>.</li>
<li><b>Eine fehlende Bestellung holen:</b> Kanal öffnen → <b>Fetch orders</b>.</li>
<li><b>Eine Karte speichern:</b> <b>Saved Cards</b> im Menü.</li>
</ul>
</aside>
