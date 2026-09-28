---
title: Länder, in die wir nicht liefern können
summary: Was die Meldung "We cannot deliver to" bei einem Warenkorb oder einer Bestellung bedeutet, und wie Sie Shopify-Checkouts beheben, die sich weigern, unsere Produkte in ein Land zu versenden.
date: 2026-09-25
source_date: 2026-09-25
tags: Lieferung, Länder, Versand, Shopify, Versandprofil, Verbotene Adresse
category: orders
shops: awd, dssk, dse
---

<aside class="tldr">
Jede unserer Websites hat eine Liste von Ländern, in die sie nicht liefert. Liegt die Lieferadresse einer Bestellung in einem dieser Länder, sehen Sie <b>We cannot deliver to ...</b>, Sie können nicht bezahlen, und die Bestellung geht nicht an das Lager. Ändern Sie die Adresse, solange die Bestellung noch ein Warenkorb ist, oder kontaktieren Sie uns im Chat auf unserer Website. Ein anderes Problem ist ein Shopify-Checkout, der sich weigert, unsere Produkte in ein Land zu versenden: Das ist eine Versandeinstellung in Ihrem Shopify-Shop.
</aside>

## "We cannot deliver to ..." bei Ihrem Warenkorb oder Ihrer Bestellung

Liegt die Lieferadresse in einem Land, in das die Website nicht liefert, sehen Sie dies in Rot:

<b>We cannot deliver to (country). Please update the address or contact support.</b>

Was dann passiert:

- In einem Warenkorb werden die Schaltflächen <b>Continue to Checkout</b> und <b>Place order</b> ausgeblendet.
- Eine Bestellung, die aus Ihrem Shop eingeht, wird nicht bezahlt und bleibt <b>Submitted</b>. Sie wird nicht an das Lager gesendet.
- Auf der Bestellseite werden das gelbe Feld, das Sie zum Aufladen auffordert, und die Schaltfläche <b>Pay ... with balance</b> ausgeblendet, da die Bestellung nicht versendet werden kann. Die Bestellung zeigt weiterhin <b>Unpaid</b> an.

Was zu tun ist:

- **Warenkorb (Manual/API-Kanal)**: Klicken Sie neben der Lieferadresse auf <b>Edit</b> und ändern Sie sie, falls die Adresse falsch war.
- **Bestellung aus Ihrem Shop**: Sie können die Adresse an der Bestellung nicht ändern. Kontaktieren Sie uns im Chat auf unserer Website mit der Bestellreferenz.

Manche Länder sind nur teilweise gesperrt, nach Postleitzahl. Es wird dieselbe Meldung angezeigt.

Die Liste ist für jede Website unterschiedlich. Diese Website liefert nicht in diese Länder:

{blocked_delivery_countries}

## "Your current billing address is marked as forbidden"

Diese Meldung betrifft Ihre eigene Rechnungsadresse, nicht die Ihres Käufers. Aktualisieren Sie die Adresse in Ihrem Konto, oder kontaktieren Sie uns im Chat auf unserer Website.

## Shopify: "unable to deliver" an der Kasse Ihres Shops

Dies passiert in Ihrem Shopify-Shop, bevor die Bestellung uns erreicht. Shopify blockiert den Checkout, wenn es keinen Versandtarif vom Standort des Produkts zum Land des Käufers hat. Produkte, die Sie selbst erstellt haben, funktionieren möglicherweise noch, da sie einen anderen Standort verwenden.

Unsere Produkte werden in Shopify an unserem Erfüllungsstandort geführt. Sein Name ist <b>aiku-</b> gefolgt vom Website-Code, dann dem Code Ihres Kanals in Klammern, zum Beispiel <b>aiku-awd (my-store)</b>. Siehe [The AW fulfilment location in Shopify](/docs/shopify-fulfilment-location). Prüfen Sie diese Einstellungen in Ihrem Shopify-Admin:

1. **Locations** (Settings → Locations): Unser Standort muss aktiv sein. Entfernen Sie alte oder doppelte Dropshipping-Standorte, die Sie nicht mehr nutzen.
2. **Shipping profile** (Settings → Shipping and delivery): Öffnen Sie das Profil, das unsere Produkte enthält, und prüfen Sie, ob unser Standort darin enthalten ist.
3. **Zones and rates**: In diesem Profil muss das Land des Käufers in einer Versandzone liegen, und die Zone braucht mindestens einen Tarif (kostenpflichtig oder kostenlos).
4. **Product**: Öffnen Sie das fehlschlagende Produkt und prüfen Sie, welches Versandprofil es nutzt. Verschieben Sie es bei Bedarf in das Profil aus Schritt 2.

<!-- screenshot: Shopify-Versandprofil mit dem aiku--Standort und einer Zone, die das Land des Käufers enthält -->

Sind alle vier Punkte korrekt und der Checkout schlägt weiterhin fehl, kontaktieren Sie den Shopify-Support. Versandzonen und -tarife werden in Ihrem Shop festgelegt, wir können sie daher nicht für Sie ändern.

Auch wenn Shopify den Checkout zulässt, können wir die Bestellung nur senden, wenn das Land nicht auf unserer obigen Liste steht.

## Wenn etwas schiefgeht

**My order has been Submitted for days and there is no pay button.** Öffnen Sie die Bestellung. Sehen Sie <b>We cannot deliver to ...</b>, ist das Land gesperrt. Kontaktieren Sie uns im Chat auf unserer Website mit der Bestellreferenz.

**The buyer gave a wrong country by mistake.** Kontaktieren Sie uns im Chat auf unserer Website mit der Bestellreferenz und der richtigen Adresse.
