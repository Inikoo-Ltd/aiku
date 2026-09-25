---
title: Ihren Shopify-Shop verbinden
summary: Verknüpfen Sie Ihren Shopify-Shop über den myshopify.com-Namen mit Ihrem Dropshipping-Konto, installieren Sie unsere App in Shopify, und beheben Sie einen Shop, der weiterhin als nicht verbunden angezeigt wird.
date: 2026-09-25
source_date: 2026-09-25
tags: Shopify, Vertriebskanal, Verbinden, Installieren, myshopify
category: sales-channels
series: shopify
order: 1
shops: awd, dssk, dse
---

<aside class="tldr">
Gehen Sie zu <b>Channels</b>, drücken Sie <b>Add Sales Channel</b>, dann <b>Connect</b> auf der Shopify-Karte. Geben Sie den <b>myshopify.com</b>-Namen Ihres Shops ein, nicht Ihre eigene Domain, und drücken Sie <b>Connect</b>. Shopify öffnet sich in einem neuen Tab: Drücken Sie dort <b>Install</b>. Die Verbindung ist erst abgeschlossen, wenn die App in Shopify installiert ist.
</aside>

## Bevor Sie beginnen

- Sie benötigen den <b>myshopify.com</b>-Namen Ihres Shops. Shopify hat ihn Ihnen bei der Erstellung des Shops mitgeteilt, zum Beispiel <b>mystore.myshopify.com</b> oder einen Code wie <b>ab12cd-3e.myshopify.com</b>. Ihre eigene Domain, wie <b>www.mystore.com</b>, funktioniert nicht.
- Um ihn zu finden, öffnen Sie Ihr Shopify-Admin und gehen Sie zu <b>Settings</b>, <b>Domains</b>. Sie können auch die Adressleiste Ihres Shopify-Admins ansehen: In <b>admin.shopify.com/store/ab12cd-3e</b> ist der Name <b>ab12cd-3e</b>.
- Melden Sie sich im selben Browser bei Ihrem Shopify-Admin an, als Shop-Inhaber oder als Mitarbeiter, der Apps installieren kann.
- Shopify ist auf allen unseren Dropshipping-Websites verfügbar. Verbinden Sie es von der Website aus, auf der Sie Ihr Dropshipping-Konto haben.

## Ihren Shop verbinden

1. Öffnen Sie <b>Channels</b> im Menü. Auf der Seite <b>Sales Channels</b> werden Ihre bereits vorhandenen Kanäle aufgelistet.
2. Drücken Sie <b>Add Sales Channel</b>.
3. Suchen Sie die <b>Shopify</b>-Karte und drücken Sie <b>Connect</b>. Ein Fenster öffnet sich: <b>Please enter your Shopify unique domain name</b>.
4. Geben Sie den Namen Ihres Shops in das Feld ein. Die Endung <b>.myshopify.com</b> ist bereits für Sie eingetragen. Sie können auch die vollständige <b>xxx.myshopify.com</b>-Adresse oder die <b>admin.shopify.com/store/...</b>-Adresse einfügen: Wir behalten nur den Shop-Namen.
5. Drücken Sie <b>Connect</b>.
6. Shopify öffnet sich in einem neuen Tab und bittet Sie, unsere App zu installieren. Drücken Sie <b>Install</b>. Öffnet sich kein neuer Tab, hat Ihr Browser ihn blockiert: Erlauben Sie Pop-ups für unsere Website, oder nutzen Sie <b>Click here to install</b> auf der Kanalseite (siehe unten).
7. Sobald die App installiert ist, zeigt Shopify die App-Seite an. Sie können diesen Tab schließen und zu Ihrem Dropshipping-Konto zurückkehren.

<!-- screenshot: das Shopify-Connect-Fenster mit eingegebenem Shop-Namen und der rechts angezeigten Endung .myshopify.com -->

Der neue Kanal befindet sich jetzt in Ihrer Liste <b>Sales Channels</b>. Öffnen Sie ihn, um sein Dashboard zu sehen.

Sind Sie sich nicht sicher, wie Ihr Shop-Name lautet, drücken Sie im selben Fenster den Link <b>Click here</b> neben <b>Not sure which is your Shopify store name?</b>.

## Was wir in Ihrem Shop einrichten

Sobald die App installiert ist, tun wir Folgendes für Sie in Ihrem Shopify-Shop:

- Wir fügen einen Erfüllungsstandort hinzu, dessen Name mit <b>aiku-</b> beginnt. Der Lagerbestand der von Ihnen verbundenen Produkte wird an diesem Standort geführt, und Shopify sendet uns die Bestellungen für diese Produkte über ihn.
- Wir fügen diesen Standort zu Ihrem Standard-Versandprofil hinzu, damit Shopify von ihm aus verkaufen und versenden kann. Siehe [The AW fulfilment location in Shopify](/docs/shopify-fulfilment-location).
- Wir richten die Nachrichten ein, die Shopify uns bei eingehenden Bestellungen sendet.

Sie müssen nichts davon selbst erledigen.

## Prüfen, ob der Kanal verbunden ist

Öffnen Sie den Kanal über <b>Channels</b>. Sobald unsere App installiert ist, erscheinen drei kleine Symbole neben dem Shop-Namen. Bewegen Sie die Maus darüber, um ihre Namen zu lesen:

- <b>App installed</b>: Unsere App ist installiert, und wir können Ihren Shop lesen.
- <b>Exist in platform</b> und <b>Platform status</b>: Unser Erfüllungsstandort ist in Ihrem Shop eingerichtet.

Zeigen alle drei grüne Häkchen, zeigt das Dashboard die Felder <b>Orders</b> und <b>Products</b> sowie im linken Menü unter Ihrem Kanal <b>My Products</b> und <b>Orders</b>. Jetzt können Sie Produkte hinzufügen: siehe [Managing products on Shopify](/docs/managing-products-on-shopify).

<!-- screenshot: Kanal-Dashboard mit den drei grünen Häkchen, der Schaltfläche Fetch orders und den Feldern Orders und Products -->

## Wenn angezeigt wird, dass der Kanal noch nicht verbunden ist

Sehen Sie <b>Your channel is not connected yet to the platform. Please connect it to be able to synchronize your products.</b>, wurde die App nicht in Shopify installiert. Dies ist das häufigste Problem. Es passiert, wenn der Shopify-Tab geschlossen wurde, bevor <b>Install</b> gedrückt wurde, oder wenn Ihr Browser den neuen Tab blockiert hat.

1. Melden Sie sich im selben Browser bei Ihrem Shopify-Admin an.
2. Klicken Sie auf der Kanalseite auf <b>Click here to install</b>, am Ende von <b>Make sure you click the button "Install" in the Shopify dashboard to finalize the connection.</b>
3. Shopify öffnet sich im selben Tab. Drücken Sie <b>Install</b>.
4. Gehen Sie zurück zur Kanalseite und laden Sie sie neu.

Funktioniert es weiterhin nicht, können Sie <b>Delete</b> neben <b>Or delete the channel and try again</b> drücken und den Shop von vorne verbinden.

## Einen Kanal löschen oder zurücksetzen

- <b>Delete channel</b>: wird bei einem verbundenen Kanal angezeigt. Es erscheint die Frage <b>Are you sure you want to delete channel</b>; bestätigen Sie mit <b>Yes, delete channel</b>. Verbinden Sie denselben Shopify-Shop später erneut, können wir den alten Kanal mit seinen Produkten wiedereröffnen, statt einen neuen zu erstellen.
- <b>Reset channel</b>: wird angezeigt, wenn der Kanal seine Verbindung verloren hat, aber noch Produkte besitzt. Es richtet den Erfüllungsstandort und die Bestellnachrichten erneut ein. Ihre Produkte müssen dann erneut verknüpft werden. Bereits aufgegebene Bestellungen bleiben unverändert.

## Wenn etwas schiefgeht

**"This does not look like a Shopify store name. Use the .myshopify.com name, not your own domain."** Sie haben Ihre eigene Domain eingegeben, wie <b>mystore.com</b>. Geben Sie stattdessen den <b>myshopify.com</b>-Namen ein. Sie finden ihn in Shopify unter <b>Settings</b>, <b>Domains</b>.

**"Shopify shop ... not found".** Es gibt keinen Shopify-Shop mit diesem Namen. Prüfen Sie die Schreibweise. Der Name ist oft ein Code aus Buchstaben und Zahlen, nicht Ihr Shop-Name.

**"Shopify shop ... already exists, please use other name".** Dieser Shop ist bereits mit einem Dropshipping-Konto verbunden. Prüfen Sie Ihre Liste <b>Sales Channels</b>. Ist er mit einem anderen Ihrer Konten verbunden, löschen Sie ihn dort zuerst.

**"Shop name cannot contain spaces".** Der myshopify.com-Name enthält nie Leerzeichen. Kopieren Sie ihn aus Shopify, statt Ihren Shop-Namen einzutippen.

**I connected Shopify but it still says not connected.** Die App wurde nicht installiert. Folgen Sie den Schritten unter <b>If it says the channel is not connected yet</b> oben.

**"Click here to install" shows "Something went wrong".** Der Kanal hat seine Verknüpfung zu Ihrem Shop verloren. Drücken Sie <b>Delete</b> neben <b>Or delete the channel and try again</b> und verbinden Sie den Shop erneut.

**After pressing Connect, no Shopify tab opens.** Ihr Browser hat den neuen Tab blockiert. Erlauben Sie Pop-ups für unsere Website, oder öffnen Sie den Kanal und nutzen Sie <b>Click here to install</b>.

**Products do not sell in Shopify, or show as sold out.** Prüfen Sie, ob sich der Standort <b>aiku-</b> in Ihrem Versandprofil befindet. Siehe [The AW fulfilment location in Shopify](/docs/shopify-fulfilment-location).

Hilft nichts davon, kontaktieren Sie uns im Chat auf unserer Website und nennen Sie Ihren myshopify.com-Namen.

<aside class="wayfinder"><strong>Wo Sie klicken</strong>
<ul>
<li><b>Neuen Shop verbinden:</b> <b>Channels</b> → <b>Add Sales Channel</b> → <b>Shopify</b> → <b>Connect</b>.</li>
<li><b>Eine Installation abschließen:</b> Kanal öffnen → <b>Click here to install</b> → <b>Install</b> in Shopify.</li>
<li><b>Die Verbindung prüfen:</b> Kanal öffnen → die drei Symbole neben seinem Namen.</li>
<li><b>Lagerbestandseinstellungen ändern:</b> Kanal öffnen → <b>Manage Sales Channel</b>.</li>
</ul>
</aside>
