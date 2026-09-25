---
title: Kunden verwalten
summary: Fügen Sie die Käufer, an die Sie versenden, als Kunden eines Manual/API-Kanals hinzu, einzeln oder aus einer Tabelle, und bearbeiten oder deaktivieren Sie sie später.
date: 2026-09-25
source_date: 2026-09-25
tags: manuell, API, Kunden, Käufer, Lieferadresse, Import
category: orders
series: manual
order: 3
shops: awd, dssk, dse
---

<aside class="tldr">
Ein Kunde ist die Person, an die Sie verkaufen: Wir senden das Paket an die Adresse des Kunden. Öffnen Sie in einem Manual/API-Kanal <b>Clients</b> und drücken Sie <b>Create Customer Client</b>, um einen hinzuzufügen, oder <b>Upload File</b>, um viele aus einer Tabelle hinzuzufügen. Jede Bestellung in einem Manual/API-Kanal wird von einer Kundenseite aus erstellt.
</aside>

## Wo Kunden zu finden sind

Kunden gehören zu einem Kanal. Öffnen Sie im Menü Ihren Manual/API-Kanal und dann <b>Clients</b>, oder drücken Sie <b>View all</b> im Feld <b>Clients</b> der Kanalseite.

Die Liste zeigt <b>Name</b>, <b>Email</b>, <b>phone</b>, <b>location</b> und <b>since</b> (wann Sie sie hinzugefügt haben). Sie hat zwei Tabs:

- <b>Active</b>: Ihre aktuellen Kunden.
- <b>Inactive</b>: Kunden, die Sie ausgeschaltet haben.

Nur Manual/API-Kanäle haben eine Seite <b>Clients</b>. Bei verbundenen Kanälen kommen die Angaben des Käufers mit jeder Bestellung aus Ihrem Shop.

## Einen Kunden hinzufügen

1. Drücken Sie auf der Seite <b>Clients</b> <b>Create Customer Client</b>.
2. Das Formular <b>New client</b> öffnet sich. Füllen Sie aus:
   - <b>Company</b>: falls Ihr Käufer ein Unternehmen ist.
   - <b>Contact name</b>: der Name für das Versandetikett.
   - <b>Email</b>
   - <b>phone</b>: mindestens 6 Zeichen, falls Sie es ausfüllen.
   - <b>Address</b>: die Lieferadresse. Das Land steht zunächst auf dem Land unseres Shops. Ändern Sie es, wenn Ihr Käufer woanders lebt.
3. Drücken Sie <b>Save</b>.

<!-- screenshot: das Formular New client mit Company, Contact name, Email, phone und Address -->

Die Kundenseite öffnet sich. Von hier aus können Sie <b>Create Order</b> drücken. Siehe [Placing orders manually](/docs/placing-orders-manually).

Die Adresse muss für das gewählte Land vollständig sein. Das Formular sagt Ihnen, was fehlt, zum Beispiel <b>The address is required</b>, <b>The town is required</b>, <b>The postal code is required</b> oder <b>The province is required</b>. Manche Länder haben keine Postleitzahl oder keine Stadt, dann fragt das Formular nicht danach.

## Viele Kunden aus einer Tabelle hinzufügen

1. Drücken Sie auf der Seite <b>Clients</b> <b>Upload File</b>.
2. Laden Sie im Fenster <b>Import your clients</b> die Vorlage herunter.
3. Tragen Sie einen Kunden pro Zeile ein, mit diesen Spalten: contact_name, company_name, email, phone, address_line_1, address_line_2, postal_code, locality, country_code. Jede Spalte außer address_line_2 muss ausgefüllt sein, und email muss eine gültige E-Mail-Adresse sein.
4. Verwenden Sie für country_code den zweistelligen Ländercode, zum Beispiel GB, ES, DE oder FR.
5. Laden Sie die Datei hoch.

## Einen Kunden ändern

Öffnen Sie den Kunden und drücken Sie <b>Edit</b>. Auf der Seite <b>Edit client</b> können Sie <b>Company</b>, <b>Contact name</b>, <b>Email</b>, <b>phone</b> und <b>Delivery Address</b> ändern.

Eine neue Adresse wird für neue Bestellungen verwendet. Bei einer Bestellung, die sich noch im Warenkorb befindet, können Sie die Lieferadresse auch auf der Warenkorbseite mit <b>Edit</b> unter der Adresse ändern.

## Einen Kunden deaktivieren

Schalten Sie auf der Seite <b>Edit client</b> <b>status</b> aus. Der Kunde wechselt in den Tab <b>Inactive</b>. Seine vergangenen Bestellungen bleiben erhalten. Schalten Sie <b>status</b> wieder ein, um ihn erneut zu nutzen.

## Kunden über die API

Ihr eigenes System kann Kunden über die API auflisten, erstellen, ändern und deaktivieren sowie Bestellungen für sie anlegen. Siehe [The Manual/API channel](/docs/manual-and-api-channel).

## Wenn etwas schiefgeht

- <b>I cannot find Create Customer Client.</b> Die Schaltfläche gibt es nur bei Manual/API-Kanälen, und nur solange der Kanal geöffnet ist. Andere Kanäle haben keine Seite <b>Clients</b>.
- <b>The form says the town, postal code or province is required.</b> Die Adresse ist für dieses Land nicht vollständig. Füllen Sie das genannte Feld aus. Prüfen Sie, ob das Land korrekt ist.
- <b>The form says the email is not valid.</b> Prüfen Sie auf Leerzeichen und ein fehlendes @ oder einen fehlenden Punkt. Sie können die E-Mail auch leer lassen.
- <b>My client is not in the list.</b> Sehen Sie im Tab <b>Inactive</b> nach. Prüfen Sie außerdem, ob Sie im richtigen Kanal sind: Kunden eines Kanals erscheinen nicht in einem anderen.
- <b>My spreadsheet upload failed for some rows.</b> Prüfen Sie, ob jede erforderliche Spalte ausgefüllt ist (nur address_line_2 darf leer sein), die E-Mail gültig ist, country_code ein zweistelliger Code ist und die Adresse die vom Land benötigten Felder enthält.
