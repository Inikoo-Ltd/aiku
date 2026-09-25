---
title: Ihren WooCommerce-Shop verbinden
summary: Verknüpfen Sie Ihren WooCommerce-Shop mit Ihrem Dropshipping-Konto, beheben Sie die Meldungen, die beim Verbinden erscheinen können, und verbinden Sie einen Shop erneut, der nicht mehr antwortet.
date: 2026-09-25
source_date: 2026-09-25
tags: WooCommerce, WordPress, Vertriebskanal, Verbinden, API-Schlüssel
category: sales-channels
series: woocommerce
order: 1
shops: awd, dssk, dse
---

<aside class="tldr">
Gehen Sie zu <b>Channels</b>, drücken Sie <b>Add Sales Channel</b>, dann <b>Connect</b> auf der Woocommerce-Karte. Geben Sie einen Namen für Ihren Shop ein und drücken Sie <b>Next</b>. Geben Sie Ihre Shop-Adresse ein, drücken Sie <b>Auth Store</b>, genehmigen Sie unsere App in WooCommerce, kommen Sie zurück und drücken Sie <b>Next</b>. Blockiert Ihr Hosting die automatischen Schlüssel, können Sie diese in WooCommerce erstellen und selbst einfügen.
</aside>

## Bevor Sie beginnen

Prüfen Sie dies zuerst auf Ihrer WordPress-Website. Die meisten fehlgeschlagenen Verbindungen liegen an einem dieser Punkte.

- WooCommerce ist installiert und aktiv.
- Ihre Shop-Adresse beginnt mit <b>https://</b>. Wir verbinden uns nicht mit Shops ohne gültiges SSL-Zertifikat.
- In WordPress ist <b>Settings</b>, <b>Permalinks</b> nicht auf <b>Plain</b> gesetzt. Mit Plain-Permalinks kann die WooCommerce-API nicht gefunden werden.
- Ihr Sicherheits-Plugin, Ihre Firewall oder Cloudflare blockiert keine Anfragen an <b>/wp-json/</b>. Über diese Adresse sprechen wir mit Ihrem Shop.
- Sie können sich als Administrator in Ihrem WordPress-Admin anmelden. Das benötigen Sie, um die Verbindung zu genehmigen.
- Optional, aber nützlich: Legen Sie vor dem Verbinden die gewünschte Gewichtseinheit in WooCommerce fest (<b>Settings</b>, <b>Products</b>). Wir lesen sie beim Verbinden aus und senden Produktgewichte in dieser Einheit.

WooCommerce ist auf allen unseren Dropshipping-Websites verfügbar. Verbinden Sie es von der Website aus, auf der Sie Ihr Dropshipping-Konto haben.

## Ihren Shop verbinden

1. Öffnen Sie <b>Channels</b> im Menü. Auf der Seite <b>Sales Channels</b> werden Ihre bereits vorhandenen Kanäle aufgelistet.
2. Drücken Sie <b>Add Sales Channel</b>.
3. Suchen Sie die <b>Woocommerce</b>-Karte und drücken Sie <b>Connect</b>. Ein Fenster öffnet sich.
4. Geben Sie unter <b>Woocommerce Account Name</b> einen Namen für Ihren Shop ein, zum Beispiel Ihren Shop-Namen. Der Name ist erforderlich, und es ist der Name, den Sie in Ihrer Kanalliste sehen werden. Drücken Sie <b>Next</b>.
5. Geben Sie unter <b>Authentication Settings</b> die vollständige Adresse Ihres Shops ein, zum Beispiel <b>https://mystore.com</b>. Drücken Sie <b>Auth Store</b>.
6. Wir prüfen zuerst, ob Ihr Shop antwortet. Ist das der Fall, öffnet sich ein neuer Tab mit Ihrer WordPress-Website. Melden Sie sich an, falls WordPress danach fragt.
7. WooCommerce zeigt an, dass <b>AW Connect</b> um <b>Read/Write</b>-Zugriff bittet. Prüfen Sie, dass Sie beim richtigen Shop angemeldet sind, und drücken Sie dann <b>Approve</b>.
8. Der Tab zeigt eine kurze Meldung und schließt sich von selbst. Gehen Sie zurück zum Fenster in Ihrem Dropshipping-Konto und drücken Sie <b>Next</b>.
9. Sie sehen <b>Connected!</b> Drücken Sie <b>OK</b>.

<!-- screenshot: das Woocommerce-Connect-Fenster, Schritt Authentication Settings mit dem Feld für die Shop-Adresse und der Schaltfläche Auth Store -->

<!-- screenshot: die WooCommerce-Genehmigungsseite, auf der AW Connect um Read/Write-Zugriff bittet, mit der Schaltfläche Approve -->

Schließen Sie alle Schritte innerhalb einer Stunde ab. Danach vergessen wir den Namen und die Schlüssel, mit denen Sie begonnen haben, und Sie müssen erneut bei <b>Connect</b> beginnen.

Blockiert Ihr Browser den neuen Tab, öffnet sich die Genehmigungsseite stattdessen im selben Tab, und Sie verlassen das Connect-Fenster. Erlauben Sie Pop-ups für unsere Website und beginnen Sie dann erneut bei <b>Connect</b>.

## Wenn Ihr Shop uns die Schlüssel nicht senden konnte

Bei der Genehmigung sendet WooCommerce die neuen Schlüssel von Ihrem Hosting an unsere Server. Manche Hosting-Anbieter blockieren dies. Sie sehen dann <b>Your store approved the connection but could not send us the keys</b>, und <b>Next</b> zeigt Ihnen <b>You are not connected yet</b> an.

Sie können sich dennoch verbinden, indem Sie die Schlüssel selbst einfügen:

1. Gehen Sie in WordPress zu <b>WooCommerce</b>, <b>Settings</b>, <b>Advanced</b>, <b>REST API</b>.
2. Fügen Sie einen Schlüssel hinzu. Geben Sie eine beliebige Beschreibung ein, wählen Sie Ihren Administrator-Benutzer, und setzen Sie <b>Permissions</b> auf <b>Read/Write</b>. Erzeugen Sie den Schlüssel.
3. Kopieren Sie den <b>Consumer key</b> (er beginnt mit ck_) und das <b>Consumer secret</b> (es beginnt mit cs_). WooCommerce zeigt das Secret nur einmal an.
4. Stellen Sie im Woocommerce-Fenster in Ihrem Dropshipping-Konto sicher, dass Ihre Shop-Adresse noch im Adressfeld steht.
5. Drücken Sie <b>My store could not send the keys, let me paste them</b>.
6. Fügen Sie den Schlüssel und das Secret ein, und drücken Sie <b>Use these keys</b>.

<!-- screenshot: der Schritt Authentication Settings mit geöffnetem Bereich für manuelle Schlüssel, den Feldern für ck_ und cs_ und der Schaltfläche Use these keys -->

## Nach dem Verbinden

Ihr Shop erscheint nun auf der Seite <b>Sales Channels</b>. Klicken Sie auf seinen Namen, um das Kanal-Dashboard zu öffnen. Dort sehen Sie <b>Orders</b>, <b>Clients</b> und <b>Products</b>. Drücken Sie <b>View all</b> unter <b>Products</b>, um Produkte hinzuzufügen. Siehe [Managing products on WooCommerce](managing-products-on-woocommerce).

Beim Verbinden fügen wir Ihrem Shop außerdem zwei Webhooks hinzu: einen für neue Bestellungen und einen für gelöschte Produkte. Löschen Sie sie nicht in WooCommerce unter <b>Settings</b>, <b>Advanced</b>, <b>Webhooks</b>. Ohne sie erreichen uns neue Bestellungen nicht sofort.

Wir importieren Bestellungen, die bezahlt sind, in WooCommerce den Status <b>Processing</b> haben und ein Lieferland besitzen. Fehlt eine Bestellung, drücken Sie <b>Fetch orders</b> auf dem Kanal-Dashboard. Dies prüft Ihren Shop auf Bestellungen der letzten 14 Tage, die uns noch nicht erreicht haben.

Mit <b>Manage Sales Channel</b> können Sie den Shop-Namen, Ihre Lagerbestandseinstellungen und Ihre Preisregel für neue Produkte ändern.

## Denselben Shop erneut verbinden

Löschen Sie Ihren WooCommerce-Kanal und verbinden später dieselbe Shop-Adresse erneut, stellen wir denselben Kanal mit seinen Produkten und Bestellungen wieder her. Sie beginnen nicht bei null.

## Wenn Ihr Shop nicht mehr antwortet

Wir prüfen Ihren verbundenen Shop regelmäßig. Antwortet Ihr Shop nicht mehr, oder funktionieren die Schlüssel nicht mehr, zeigt der Kanal <b>Your channel is not connected yet to the platform</b>. Darüber sehen Sie eventuell die Fehlermeldung, die Ihr Shop uns gesendet hat. Solange dies angezeigt wird, ist Ihre Produktliste verborgen, und Sie können keine Produkte in Ihren Shop hochladen.

So beheben Sie es:

1. Stellen Sie sicher, dass Ihre Website online ist und Sie sie in Ihrem Browser öffnen können.
2. Drücken Sie auf der Kanalseite <b>Try to reconnect</b>. Ihre WordPress-Website öffnet sich. Melden Sie sich als Administrator an und drücken Sie erneut <b>Approve</b>. Dies erstellt neue Schlüssel.
3. Funktioniert es weiterhin nicht, drücken Sie <b>Test Connection</b>, um die Verbindung erneut zu prüfen.
4. Drücken Sie als letzten Schritt <b>Delete</b> und verbinden Sie den Shop erneut. Ihre Produkte und Bestellungen kommen zurück, wenn Sie dieselbe Shop-Adresse verwenden.

Schlägt Ihr Shop dauerhaft fehl, hören wir auf, ihn zu prüfen. Er funktioniert wieder, sobald Sie ihn erneut verbinden.

<!-- screenshot: die Warnmeldung "not connected" bei einem WooCommerce-Kanal mit den Schaltflächen Try to reconnect, Test Connection und Delete -->

## Wenn etwas schiefgeht

Dies sind die Meldungen, die beim Drücken von <b>Auth Store</b> erscheinen können, und was zu tun ist.

- <b>We could not resolve your store domain</b>: Die Adresse ist falsch geschrieben, oder die Domain ist nicht aktiv. Kopieren Sie die Adresse aus Ihrem Browser, während Ihr Shop geöffnet ist.
- <b>Your store SSL certificate could not be verified</b>: Ihr Zertifikat ist abgelaufen, selbstsigniert oder unvollständig. Bitten Sie Ihren Hosting-Anbieter, es zu erneuern oder zu reparieren.
- <b>Your store refused our connection</b> oder <b>Your store did not answer within 2 minutes</b>: Ihr Hosting oder Ihre Firewall blockiert unsere Server. Die Meldung listet unsere IP-Adressen. Senden Sie sie an Ihren Hosting-Anbieter und bitten Sie ihn, sie zuzulassen.
- <b>Your store redirects to ...</b>: Ihr Shop liegt unter einer anderen Adresse, zum Beispiel mit oder ohne www. Geben Sie die in der Meldung genannte Adresse ein.
- <b>Your store url redirects in a loop</b>: Geben Sie die endgültige Adresse Ihres Shops ein, die Sie im Browser sehen, nachdem die Seite geladen ist.
- <b>We could not find the WooCommerce API on this store</b>: WooCommerce ist nicht aktiv, seine REST-API ist ausgeschaltet, oder Ihre Permalinks sind auf <b>Plain</b> gesetzt. Ändern Sie die Permalinks in WordPress unter <b>Settings</b>, <b>Permalinks</b>.
- <b>Your store answered with 401</b> oder <b>403</b> <b>and blocked our request</b>: Ein Sicherheits-Plugin, eine Firewall oder Cloudflare blockiert uns. Erlauben Sie in diesem Tool Anfragen an <b>/wp-json/</b>.
- <b>Your WooCommerce store returned an error 500</b> (oder eine andere Nummer, die mit 5 beginnt): Ihre Website hat einen Fehler. Prüfen Sie Ihr Hosting-Fehlerprotokoll, oder fragen Sie Ihren Hosting-Anbieter, und versuchen Sie es dann erneut.
- <b>Your store answered with ... but did not return the WooCommerce REST API</b>: Die Adresse zeigt nicht auf Ihre WordPress-Website. Prüfen Sie, dass Sie den Shop selbst eingegeben haben, nicht eine Landingpage oder eine andere Website.
- <b>You are not connected yet, click auth store to connect and follow the instructions</b>: Sie haben <b>Next</b> gedrückt, bevor Sie in WooCommerce genehmigt haben, die Genehmigung hat uns nicht erreicht, oder mehr als eine Stunde ist vergangen. Drücken Sie erneut <b>Auth Store</b>, oder fügen Sie die Schlüssel wie oben gezeigt selbst ein.
- <b>We can't access your store, make sure you already put correct store url</b>: Wir haben die Schlüssel erhalten, konnten sie aber unter dieser Adresse nicht nutzen. Prüfen Sie die Adresse und dass die Schlüssel <b>Read/Write</b>-Berechtigung haben.

Probleme auf Ihrer eigenen Website, zum Beispiel wenn Ihr Shop nicht erreichbar, langsam ist oder uns blockiert, können nur Sie oder Ihr Hosting-Anbieter beheben. Wir können keine Einstellungen auf Ihrer Website ändern.
