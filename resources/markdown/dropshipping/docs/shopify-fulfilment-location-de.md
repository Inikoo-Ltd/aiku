---
title: Der AW-Fulfilment-Standort in Shopify
summary: Was der aiku--Standort in Ihrem Shopify-Shop bewirkt, wie er für Sie zu Ihrem Versandprofil hinzugefügt wird, und was zu tun ist, wenn Produkte als ausverkauft angezeigt werden oder Bestellungen uns nicht erreichen.
date: 2026-09-25
source_date: 2026-09-25
tags: shopify, Fulfilment-Standort, Versandprofil, Lagerbestand, ausverkauft
category: sales-channels
series: shopify
order: 3
shops: awd, dssk, dse
---

<aside class="tldr">
Wenn Sie unsere App installieren, fügen wir Ihrem Shopify-Shop einen Fulfilment-Standort hinzu. Sein Name beginnt mit <b>aiku-</b>. Wir fügen ihn für Sie zu Ihrem Standard-Versandprofil hinzu, Sie müssen also meist nichts tun. Nutzen Sie mehr als ein Versandprofil, prüfen Sie, ob der <b>aiku-</b>-Standort im Profil unserer Produkte ist, sonst zeigt Shopify sie als ausverkauft an und sendet uns ihre Bestellungen nicht.
</aside>

## Wofür der Standort da ist

Shopify führt den Lagerbestand nach Standort. Unsere Produkte werden aus unserem Lager versendet, daher fügen wir unser Lager als Fulfilment-Standort zu Ihrem Shop hinzu.

- Sein Name ist <b>aiku-</b>, dann der Code unserer Website, dann der Code Ihres Kanals in Klammern. Zum Beispiel <b>aiku-awd (sho-ab12cd-3e)</b>.
- Der Bestand jedes von Ihnen verbundenen Produkts wird an diesem Standort geführt. Wir aktualisieren ihn für Sie.
- Kauft ein Kunde eines dieser Produkte, sendet Shopify uns von diesem Standort aus eine Fulfilment-Anfrage. So erreicht uns die Bestellung.

Löschen Sie diesen Standort nicht und verschieben Sie unsere Produkte nicht zu einem anderen Standort. Tun Sie es doch, stoppt die Bestandsaktualisierung, und Bestellungen erreichen uns nicht mehr.

## Für Sie zu Ihrem Versandprofil hinzugefügt

Shopify verkauft Bestand nur aus Standorten, die in einem Versandprofil sind. Bei der App-Installation fügen wir den <b>aiku-</b>-Standort für Sie hinzu:

- zu Ihrem Standard-Versandprofil, oder
- wenn Ihr Shop noch einen älteren <b>aiku-dse</b>-Standort aus einer früheren Verbindung hat, zu jedem Versandprofil, in dem dieser ältere Standort ist.

Ist der Standort bereits in einem Ihrer Versandprofile, ändern wir nichts.

Sie müssen den Standort nicht mehr von Hand hinzufügen, wie ältere Anleitungen sagten.

## Selbst prüfen

Tun Sie dies, wenn unsere Produkte in Ihrem Shop als ausverkauft angezeigt werden, oder die Kasse für sie keinen Versandtarif zeigt.

1. Öffnen Sie in Ihrem Shopify-Adminbereich <b>Settings</b>.
2. Öffnen Sie <b>Shipping and delivery</b>.
3. Öffnen Sie das Versandprofil, in dem unsere Produkte sind. In den meisten Shops ist das das allgemeine Profil.
4. Sehen Sie sich die Standorte an, von denen das Profil versendet. Der <b>aiku-</b>-Standort muss dort sein.
5. Ist er es nicht, fügen Sie ihn zum Profil hinzu und speichern Sie.

<!-- screenshot: Shopify Shipping and delivery, ein Versandprofil mit dem aiku-awd-Standort in seiner Standortliste -->

Shopify ändert seine Menüs von Zeit zu Zeit, die Namen in Ihrem Adminbereich können also etwas anders sein.

Haben Sie ein eigenes Versandprofil für manche unserer Produkte erstellt, fügen Sie den <b>aiku-</b>-Standort auch zu diesem Profil hinzu. Wir fügen ihn nur zum Standardprofil hinzu.

## Wenn etwas schiefgeht

**Unsere Produkte werden in Shopify als ausverkauft angezeigt.** Prüfen Sie das Versandprofil wie oben. Prüfen Sie auch, ob das Produkt verbunden ist: In <b>Meine Produkte</b> muss ein grüner Handschlag angezeigt werden. Siehe [Produkte auf Shopify verwalten](/docs/managing-products-on-shopify).

**Upload-Fehler „No Shopify location, the AW fulfilment service is not installed on this store so stock can not be sent".** Der <b>aiku-</b>-Standort fehlt. Öffnen Sie den Kanal. Sehen Sie <b>Click here to install</b>, drücken Sie darauf und installieren Sie die App in Shopify. Zeigt der Kanal <b>Reset channel</b>, nutzen Sie es, um den Standort erneut anzulegen.

**Log-Nachricht „The specified inventory item is not stocked at the location".** Das Produkt in Shopify ist nicht am <b>aiku-</b>-Standort gelagert, zum Beispiel weil es in Shopify zu einem anderen Standort verschoben wurde. Verknüpfen Sie das Produkt erneut mit <b>Connect with other product</b> in <b>Meine Produkte</b>.

**Bestellungen erreichen uns nicht.** Shopify sendet uns nur Bestellungen für Artikel, die am <b>aiku-</b>-Standort gelagert sind. War das Produkt in Ihrem eigenen Standort gelagert, erwartet Shopify, dass Sie es selbst versenden. Siehe [Ihre Shopify-Bestellungen und ihr Status](/docs/shopify-order-status).

<aside class="wayfinder"><strong>Wo Sie klicken</strong>
<ul>
<li><b>Den Standort in Shopify prüfen:</b> Shopify-Adminbereich → <b>Settings</b> → <b>Shipping and delivery</b> → Ihr Versandprofil.</li>
<li><b>Den Kanal prüfen:</b> <b>Channels</b> → Ihr Shopify-Shop → die drei Symbole neben seinem Namen.</li>
</ul>
</aside>
