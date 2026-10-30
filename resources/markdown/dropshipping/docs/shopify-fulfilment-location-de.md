---
title: Der AW-Fulfilment-Standort in Shopify
summary: Was der aiku--Standort und das Versandprofil AW Connect shipping in Ihrem Shopify-Shop bewirken, welche Versandtarife Ihre Kunden für unsere Produkte sehen, und was zu tun ist, wenn Produkte als ausverkauft angezeigt werden oder Bestellungen uns nicht erreichen.
date: 2026-10-30
source_date: 2026-10-30
tags: shopify, Fulfilment-Standort, Versandprofil, Versandtarife, Märkte, Lagerbestand, ausverkauft
category: sales-channels
series: shopify
order: 3
shops: awd, dssk, dse
---

<aside class="tldr">
Wenn Sie unsere App installieren, fügen wir Ihrem Shopify-Shop einen Fulfilment-Standort namens <b>aiku-</b> hinzu und ein Versandprofil, <b>AW Connect shipping</b>, mit unseren Produkten und unseren Versandpreisen. An der Kasse sehen Ihre Kunden für unsere Produkte den Versand <b>AW Standard</b>. Sie müssen meist nichts tun.
</aside>

## Wofür der Standort da ist

Shopify führt den Lagerbestand nach Standort. Unsere Produkte werden aus unserem Lager versendet, daher fügen wir unser Lager als Fulfilment-Standort zu Ihrem Shop hinzu.

- Sein Name ist <b>aiku-</b>, dann der Code unserer Website, dann der Code Ihres Kanals in Klammern. Zum Beispiel <b>aiku-awd (sho-ab12cd-3e)</b>.
- Der Bestand jedes von Ihnen verbundenen Produkts wird an diesem Standort geführt. Wir aktualisieren ihn für Sie.
- Kauft ein Kunde eines dieser Produkte, sendet Shopify uns von diesem Standort aus eine Fulfilment-Anfrage. So erreicht uns die Bestellung.

Löschen Sie diesen Standort nicht und verschieben Sie unsere Produkte nicht zu einem anderen Standort. Tun Sie es doch, stoppt die Bestandsaktualisierung, und Bestellungen erreichen uns nicht mehr.

## Versand für Sie eingerichtet

Bei der App-Installation fügen wir Ihrem Shop ein Versandprofil hinzu. Sein Name ist <b>AW Connect shipping</b>, dann der Code Ihres Kanals in Klammern, zum Beispiel <b>AW Connect shipping (sho-ab12cd-3e)</b>. Es funktioniert gleich, ob Ihr Shop Versandprofile oder den Versand nach Märkten nutzt.

- Es versendet nur vom <b>aiku-</b>-Standort.
- Es enthält jedes Produkt, das Sie von uns verbinden. Neue Produkte fügen wir hinzu und entfernte nehmen wir heraus, meist innerhalb von zehn Minuten.
- Seine Tarife sind unsere Versandpreise, nach Land und Paketgewicht, in der Währung Ihres Shops. An der Kasse erscheinen sie als <b>AW Standard</b>. Ändern wir unsere Preise, folgt das Profil innerhalb eines Tages.
- Wo wir den Versand von Hand berechnen (sehr schwere Pakete, Länder außerhalb unserer Zonen), nutzt das Profil unseren höchsten Preis für diese Zone, damit Ihre Kunden immer bestellen können. Was wir Ihnen für die Bestellung berechnen, ändert sich nicht.
- Shopify lässt Sie diese Tarife nicht bearbeiten. Sie können sie ausschalten, dann sehen Ihre Kunden aber keinen Versand für unsere Produkte, außer einer Ihrer eigenen Tarife versendet vom <b>aiku-</b>-Standort.

Ihre anderen Produkte behalten Ihre eigenen Versandeinstellungen und Tarife. Wir ändern sie nicht.

## Shops mit Versand nach Märkten

Shopify stellt alle Shops von Versandprofilen auf den Versand nach Märkten um. Ein solcher Shop zeigt unter <b>Settings</b>, <b>Shipping and delivery</b> den Hinweis <b>Shipping has moved to Markets</b>. Unser Profil erscheint in jedem Markt als Dienst unserer App, neben Ihren eigenen Tarifen.

Versendet einer Ihrer Markttarife vom <b>aiku-</b>-Standort, zum Beispiel weil Sie ihn nach einer älteren Version dieser Anleitung von Hand hinzugefügt haben, sehen Ihre Kunden diesen Tarif zusätzlich zu <b>AW Standard</b>. Damit nur unserer erscheint, nehmen Sie den <b>aiku-</b>-Standort aus diesem Tarif heraus:

1. Öffnen Sie in Ihrem Shopify-Adminbereich <b>Markets</b> und dann einen Markt, in den Sie verkaufen.
2. Drücken Sie unter <b>Shipping</b> neben einem Tarif auf <b>...</b> und wählen Sie <b>Edit</b>.
3. Entfernen Sie unter <b>Ships from</b> den <b>aiku-</b>-Standort und speichern Sie.

Unsere Produkte werden nur in die Länder verkauft, die Ihre Märkte enthalten. Ein Kunde in einem Land, das keiner Ihrer Märkte abdeckt, sieht sie als ausverkauft.

## Ihr eigener Bestand unserer Produkte

Produkte in unserem Versandprofil versenden nur vom <b>aiku-</b>-Standort, Shopify verschickt sie also nicht von Ihren eigenen Standorten. Haben Sie selbst einige unserer Produkte, zum Beispiel einen unbenutzten Artikel, den ein Kunde an Sie zurückgeschickt hat, bieten Sie sie in Ihrem Shop als eigenes Produkt an und versenden Sie diese Bestellungen selbst.

## Selbst prüfen

Tun Sie dies, wenn unsere Produkte in Ihrem Shop als ausverkauft angezeigt werden, oder die Kasse für sie keinen Versandtarif zeigt.

1. Öffnen Sie in Ihrem Shopify-Adminbereich <b>Settings</b>.
2. Öffnen Sie <b>Shipping and delivery</b>.
3. Suchen Sie <b>AW Connect shipping</b> unter unserer App. Es muss vom <b>aiku-</b>-Standort versenden und unsere Produkte enthalten.
4. Fehlt es, oder fehlt ein Produkt darin, warten Sie einen Tag: Wir prüfen jeden Shop jede Nacht. Kommt es nicht zurück, kontaktieren Sie uns.

<!-- screenshot: Shopify Shipping and delivery, das Versandprofil AW Connect shipping unter der App, mit dem aiku-awd-Standort -->

Shopify ändert seine Menüs von Zeit zu Zeit, daher können die Namen in Ihrem Adminbereich etwas anders sein.

## Wenn etwas schiefgeht

**Unsere Produkte werden in Shopify als ausverkauft angezeigt.** Prüfen Sie das Versandprofil <b>AW Connect shipping</b> wie oben, und ob das Land des Kunden in einem Ihrer Märkte ist. Prüfen Sie auch, ob das Produkt verbunden ist: In <b>Meine Produkte</b> muss ein grüner Handschlag angezeigt werden. Siehe [Produkte auf Shopify verwalten](/docs/managing-products-on-shopify).

**Upload-Fehler „No Shopify location, the AW fulfilment service is not installed on this store so stock can not be sent".** Der <b>aiku-</b>-Standort fehlt. Öffnen Sie den Kanal. Sehen Sie <b>Click here to install</b>, drücken Sie darauf und installieren Sie die App in Shopify. Zeigt der Kanal <b>Reset channel</b>, nutzen Sie es, um den Standort erneut anzulegen.

**Log-Nachricht „The specified inventory item is not stocked at the location".** Das Produkt in Shopify ist nicht am <b>aiku-</b>-Standort gelagert, zum Beispiel weil es in Shopify zu einem anderen Standort verschoben wurde. Verknüpfen Sie das Produkt erneut mit <b>Connect with other product</b> in <b>Meine Produkte</b>.

**Bestellungen erreichen uns nicht.** Shopify sendet uns nur Bestellungen für Artikel, die am <b>aiku-</b>-Standort gelagert sind. War das Produkt in Ihrem eigenen Standort gelagert, erwartet Shopify, dass Sie es selbst versenden. Siehe [Ihre Shopify-Bestellungen und ihr Status](/docs/shopify-order-status).

<aside class="wayfinder"><strong>Wo Sie klicken</strong>
<ul>
<li><b>Unser Versandprofil prüfen:</b> Shopify-Adminbereich → <b>Settings</b> → <b>Shipping and delivery</b> → <b>AW Connect shipping</b>.</li>
<li><b>Versand nach Märkten:</b> Shopify-Adminbereich → <b>Markets</b> → Ihr Markt → <b>Shipping</b>.</li>
<li><b>Den Kanal prüfen:</b> <b>Channels</b> → Ihr Shopify-Shop → die drei Symbole neben seinem Namen.</li>
</ul>
</aside>
