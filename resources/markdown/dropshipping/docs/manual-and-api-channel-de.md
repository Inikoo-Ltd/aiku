---
title: Der Manual/API-Kanal
summary: Erstellen Sie einen Manual/API-Kanal, um von Ihrer eigenen Website, einem Marktplatz oder einer App aus zu verkaufen, Bestellungen von Hand aufzugeben oder sie über unsere API an uns zu senden.
date: 2026-09-25
source_date: 2026-09-25
tags: Manuell, API, Vertriebskanal, Eigene Website, API-Token, Integration
category: sales-channels
series: manual
order: 1
shops: awd, dssk, dse
---

<aside class="tldr">
Nutzen Sie einen <b>Manual/API</b>-Kanal, wenn Ihr Shop nicht auf einer der von uns unterstützten Plattformen läuft, oder wenn Sie Bestellungen selbst eintippen möchten. Gehen Sie zu <b>Channels</b>, drücken Sie <b>Add Sales Channel</b>, dann <b>Create</b> auf der Karte <b>Manual/API</b> und geben Sie ihm einen Namen. Danach fügen Sie Produkte zu <b>My Products</b> hinzu, tragen Ihre Käufer als <b>Clients</b> ein und erstellen Bestellungen für sie, von Hand oder über die API.
</aside>

## Wann einen Manual/API-Kanal nutzen

Ein Manual/API-Kanal ist mit keinem Shop verknüpft. Es wird nichts auf eine Website hochgeladen, und keine Bestellungen kommen von selbst herein. Nutzen Sie ihn, wenn:

- Sie auf Ihrer eigenen Website, auf einem von uns nicht unterstützten Marktplatz, in sozialen Medien oder telefonisch verkaufen und jede Bestellung selbst an uns senden möchten.
- Sie ein eigenes System oder einen Entwickler haben und Bestellungen über unsere API an uns senden möchten.

Läuft Ihr Shop auf einer auf der Seite <b>Add Sales Channel</b> gezeigten Plattform, wie Shopify, WooCommerce, eBay oder TikTok Shop, verbinden Sie stattdessen diese Plattform. Dann werden Produkte für Sie hochgeladen, und Bestellungen kommen von selbst herein.

Sie können mehr als einen Manual/API-Kanal haben, zum Beispiel einen pro Website.

## Den Kanal erstellen

1. Öffnen Sie <b>Channels</b> im Menü. Sie sehen die Liste Ihrer <b>Sales Channels</b>.
2. Drücken Sie <b>Add Sales Channel</b>. Die Seite zeigt <b>Select channel you want to create</b>.
3. Drücken Sie auf der Karte <b>Manual/API</b> <b>Create</b>.
4. Ein Fenster <b>Create platform manual</b> öffnet sich. Geben Sie einen Namen für den Kanal ein, zum Beispiel den Namen Ihrer Website. Der Name darf bis zu 28 Zeichen lang sein.
5. Drücken Sie <b>Create</b>.

<!-- screenshot: die Seite Add Sales Channel mit der Karte Manual/API und ihrer Schaltfläche Create, sowie das Fenster Create platform manual -->

Sie sehen die Meldung <b>Your Manual store has been created.</b>, und die Kanalseite öffnet sich.

Jeder Ihrer Kanäle braucht einen eigenen Namen. Wird der Name bereits von einem anderen Ihrer Kanäle verwendet, zeigt das Fenster einen Fehler. Wählen Sie einen anderen Namen.

## Ihre Kanalseite

Die Kanalseite trägt den Namen Ihres Kanals als Titel und die Überschrift <b>Manual/API order management</b>. Sie zeigt drei Felder, jedes mit einem Link <b>View all</b>:

- <b>Orders</b>: die von Ihnen in diesem Kanal aufgegebenen Bestellungen.
- <b>Clients</b>: die Personen, an die Sie Bestellungen senden.
- <b>Products</b>: die Produkte in Ihrer Liste <b>My Products</b>.

Im Menü, unter dem Kanalnamen, finden Sie:

- <b>Baskets</b>: begonnene, noch nicht bezahlte Bestellungen.
- <b>My Products</b>: die von Ihnen in diesem Kanal verkauften Produkte. Siehe [Managing products on the Manual/API channel](/docs/managing-products-on-the-manual-channel).
- <b>Clients</b>: Ihre Käufer. Siehe [Managing clients](/docs/managing-clients).
- <b>Orders</b>: Ihre aufgegebenen Bestellungen. Siehe [Placing orders manually](/docs/placing-orders-manually).
- <b>API</b>: Tokens und Dokumentation, um Ihr eigenes System zu verbinden.

Der übliche Arbeitsweg ist:

1. Fügen Sie mit <b>Add products</b> die von Ihnen verkauften Produkte zu <b>My Products</b> hinzu. Dies wird für die API benötigt. Für selbst eingetippte Bestellungen ist es optional: Der Warenkorb lässt Sie jedes von uns verkaufte Produkt auswählen.
2. Erhalten Sie eine Bestellung, öffnen Sie <b>Clients</b>, suchen Sie Ihren Käufer oder fügen Sie ihn hinzu.
3. Drücken Sie auf der Kundenseite <b>Create Order</b>, fügen Sie Produkte und Mengen hinzu und bezahlen Sie.

## Den Namen ändern oder den Kanal schließen

Um den Kanal umzubenennen, drücken Sie auf der Kanalseite <b>Edit</b> und ändern Sie <b>Store name</b>.

Um einen Kanal zu schließen, gehen Sie zu <b>Channels</b> und drücken Sie die Schließen-Schaltfläche in der Spalte <b>Action</b> (Tooltip <b>Close channel</b>). Das Fenster fragt <b>Are you sure you want to close this channel?</b> und warnt <b>This operation is irreversible.</b> Ein geschlossener Kanal verschwindet aus dem Menü. Ihre vergangenen Bestellungen und Rechnungen bleiben erhalten.

## Ihr eigenes System mit der API verbinden

Die API lässt Ihre Website oder App selbst tun, was Sie auf den Kanalseiten tun: unseren Produktkatalog mit aktuellen Preisen lesen, Produkte zu <b>My Products</b> hinzufügen, Kunden anlegen und ändern, Bestellungen erstellen, ihnen Produkte hinzufügen, sie absenden und verfolgen. Sie können auch Ihre Liste <b>My Products</b> als CSV- oder JSON-Feed herunterladen, um Produkte in Ihre eigene Website zu laden.

Öffnen Sie <b>API</b> unter Ihrem Kanal. Die Seite hat diese Tabs:

- <b>Overview</b>: wie Sie sich verbinden, die Basisadresse der API und die Schaltfläche <b>API documentation</b>. Die Dokumentation listet jeden Endpunkt mit Beispielen.
- <b>API tokens</b>: die Tokens für diesen Kanal.
- <b>API calls</b>: die von Ihrem System gestellten Anfragen.
- <b>History</b>: Änderungen an Ihrem Konto.

### Ein Token erhalten

1. Drücken Sie <b>Generate API token</b>.
2. Soll das Token nur zum Lesen von Daten dienen, haken Sie <b>Read only (cannot create, change or submit orders)</b> an.
3. Drücken Sie <b>Click to Generate</b>.
4. Kopieren Sie das Token mit dem Kopiersymbol und bewahren Sie es sicher auf. Das Fenster sagt <b>Put this token in a safe place, you won't be able to see it again.</b> Die kurze Bezeichnung in der Token-Liste ist nur ein Name, nicht das Token.

Senden Sie das Token bei jeder Anfrage im Header <b>Authorization: Bearer</b>, gefolgt von Ihrem Token. Jedes Token gehört zu einem Kanal: Produkte, Kunden und Bestellungen, die Ihr System anlegt, gehen an diesen Kanal. Um ein Token unbrauchbar zu machen, löschen Sie es im Tab <b>API tokens</b>.

<!-- screenshot: der Tab Overview der API-Seite mit den Schaltflächen API documentation und Generate API token -->

### Zuerst auf Staging testen

Der Tab <b>Overview</b> hat außerdem <b>Open staging mirror</b>. Staging ist eine separate Kopie der Website, auf der Sie ohne echte Bestellungen oder Zahlungen testen können. Melden Sie sich mit derselben E-Mail-Adresse und demselben Passwort an. Staging wird regelmäßig mit einer frischen Kopie zurückgesetzt, wodurch Ihre dortigen Änderungen gelöscht werden. Tokens der echten Website funktionieren auf Staging nicht: Erzeugen Sie ein separates Token auf Staging, und nach jedem Reset ein neues. Die Basisadresse für Staging steht im Tab <b>Overview</b>.

### Wie API-Bestellungen bezahlt werden

Sobald Ihr System eine Bestellung absendet, bezahlen wir sie zuerst von Ihrem Kontoguthaben, dann von Ihren gespeicherten Karten. Fügen Sie eine Karte hinzu, bevor Sie beginnen. Sobald Sie ein Token haben, zeigt das Menü <b>Saved Cards</b>. Solange keine Karte gespeichert ist, zeigt die API-Seite <b>You have no cards saved yet.</b> mit einer Schaltfläche <b>Add card</b>.

Decken weder Ihr Guthaben noch Ihre Karten die Bestellung, wird sie als <b>Unpaid</b> markiert und geht nicht ans Lager. Laden Sie Ihr Guthaben mit <b>Top Up</b> auf, öffnen Sie die Bestellung und drücken Sie <b>Pay … with balance</b>. Die Schaltfläche erscheint, sobald Ihr Guthaben den fälligen Betrag deckt.

## Wenn etwas schiefgeht

- <b>The name is already taken when I create the channel.</b> Einer Ihrer geöffneten Kanäle trägt diesen Namen bereits. Geben Sie einen anderen Namen ein. Der Name eines geschlossenen Kanals kann erneut verwendet werden.
- <b>My orders are not arriving by themselves.</b> Ein Manual/API-Kanal sammelt nie Bestellungen von einer Website. Erstellen Sie sie auf der Kundenseite, oder senden Sie sie über die API von Ihrem System aus. Verkaufen Sie auf einer auf der Seite <b>Add Sales Channel</b> gezeigten Plattform, verbinden Sie diese Plattform als eigenen Kanal.
- <b>My products are not on my website.</b> Von einem Manual/API-Kanal laden wir nichts hoch. Laden Sie sie selbst in Ihre Website, mit dem CSV-Download unter <b>My Products</b> oder über die API.
- <b>I lost my API token.</b> Es kann nicht erneut angezeigt werden. Erzeugen Sie ein neues Token, tragen Sie es in Ihr System ein und löschen Sie das alte.
- <b>The API answers that I cannot create or change orders.</b> Das Token ist nur lesend. Erzeugen Sie ein Token ohne angehaktes <b>Read only</b>.
- <b>The API refuses my requests for a short while.</b> Jedes Token kann bis zu 120 Anfragen pro Minute stellen. Verlangsamen Sie Ihr System und versuchen Sie es nach einer Minute erneut.
- <b>The API says "This order has no products yet".</b> Fügen Sie der Bestellung mindestens ein Produkt hinzu, bevor Sie sie absenden.
- <b>The API says "Unable to find related portfolio item".</b> Über die API fügen Sie einer Bestellung ein Produkt über sein <b>My Products</b>-Element hinzu, nicht über das Produkt selbst. Fügen Sie das Produkt zuerst zu <b>My Products</b> hinzu und verwenden Sie die ID dieses Elements.
- <b>The API says another transaction with the same product already exists.</b> Das Produkt befindet sich bereits auf der Bestellung. Ändern Sie stattdessen die Menge dieser Zeile, statt sie erneut hinzuzufügen.
- <b>The API says the order "is already in the 'submitted' state and cannot be updated".</b> Abgesendete Bestellungen können über die API nicht geändert oder gelöscht werden. Kontaktieren Sie uns im Chat auf unserer Website, wenn die Bestellung geändert werden muss.
- <b>My API order shows Unpaid.</b> Ihr Guthaben und Ihre gespeicherten Karten haben sie nicht gedeckt. Laden Sie Ihr Guthaben auf, öffnen Sie die Bestellung und drücken Sie <b>Pay … with balance</b>, und prüfen Sie, ob Ihre gespeicherte Karte noch gültig ist.
