---
title: Cartes et modes de paiement
summary: Comment vous payez vos commandes, quels modes de paiement propose la caisse, et comment enregistrer une carte pour que les commandes de vos boutiques connectées soient payées automatiquement.
date: 2026-09-25
source_date: 2026-09-25
tags: paiement, carte, cartes enregistrées, caisse, paypal, apple pay, google pay, paiement automatique
category: payments
shops: awd, dssk, dse
---

<aside class="tldr">
Votre solde est toujours utilisé en premier. Ce que le solde ne couvre pas, vous le payez à la caisse sous <b>Online payments</b> : carte, Apple Pay, Google Pay, PayPal et d'autres modes, selon votre pays et votre appareil. Les commandes qui arrivent de vos boutiques connectées sont payées sans vous : d'abord avec votre solde, puis avec une carte que vous avez enregistrée dans <b>Saved Cards</b>. Enregistrez une carte, ou gardez votre solde rechargé, pour que ces commandes ne restent pas impayées.
</aside>

## Deux façons dont les commandes sont payées

- <b>Commandes que vous créez vous-même</b> (commandes manuelles) : vous payez à la caisse pendant que vous regardez.
- <b>Commandes de vos boutiques connectées</b> (Shopify, WooCommerce, eBay, TikTok et les autres, ou via l'API) : personne n'est à la caisse, donc nous prenons l'argent nous-mêmes. Nous utilisons d'abord votre solde, puis vos cartes enregistrées. Voir [Recharger et payer avec le solde](/docs/topping-up-and-paying-with-balance).

## Payer à la caisse

1. Sur le <b>Dashboard</b>, sous <b>Quick links (Shortcuts)</b>, appuyez sur <b>Create manual Order</b>.
2. Sous <b>Select Customer Client</b>, choisissez la personne à qui vous envoyez, ou appuyez sur <b>Create new client here</b>. Appuyez sur <b>Create Order</b>.
3. Ajoutez des produits au panier, vérifiez l'adresse, et appuyez sur <b>Continue to Checkout</b>. Si votre solde couvre toute la commande, le panier affiche <b>Place order</b> à la place : appuyez dessus et la commande est payée avec votre solde.
4. La caisse affiche votre <b>Order number</b> et le résumé. Si vous avez de l'argent sur votre solde, il est utilisé en premier : vous voyez combien sera payé avec le solde et <b>Please paid the rest with your preferred method below:</b>.
5. Sous <b>Online payments</b>, choisissez comment payer le reste et suivez les étapes. Votre banque peut vous demander de confirmer le paiement dans son application ou avec un code.
6. Après le paiement, vous voyez <b>Payment done. Waiting for confirmation...</b>. Une fois le paiement confirmé, la commande est envoyée à notre entrepôt.

Si votre solde couvre toute la commande, il n'y a pas de formulaire de paiement : vous voyez seulement <b>Place order</b>.

<!-- screenshot: la page de caisse avec le résumé de commande et le formulaire Online payments montrant carte, Apple Pay et PayPal -->

## Quels modes de paiement vous pouvez utiliser

Le formulaire <b>Online payments</b> affiche les modes qui fonctionnent pour votre pays, votre devise et votre appareil. Les acheteurs utilisent :

- Cartes de débit et de crédit
- Apple Pay (sur les appareils Apple) et Google Pay
- PayPal
- Klarna
- Dans certains pays européens : iDEAL, Przelewy24 et Bancontact

Si vous ne voyez pas un mode que vous attendiez, il n'est pas disponible pour votre pays, votre devise ou votre appareil. Le virement bancaire et le paiement à la livraison ne sont pas proposés à la caisse dropshipping.

## Enregistrer une carte pour les paiements automatiques

Les cartes enregistrées servent à payer les commandes de vos boutiques connectées quand votre solde ne suffit pas.

L'élément <b>Saved Cards</b> apparaît dans le menu de gauche une fois que vous avez connecté une boutique, créé un jeton API ou enregistré une carte. Un petit point dessus signifie que vous n'avez encore aucune carte enregistrée.

Pour enregistrer une carte :

1. Appuyez sur <b>Saved Cards</b> dans le menu de gauche. La page s'appelle <b>Credit Card Dashboard</b>.
2. Appuyez sur <b>Save Credit Card</b> en haut (ou <b>Add credit card</b> au-dessus de votre liste de cartes).
3. Saisissez les détails de votre carte. Votre banque vous demandera de confirmer. Cela est nécessaire pour que nous puissions débiter la carte plus tard sans vous.
4. La carte apparaît dans la liste, qui indique son <b>Card type</b>, son statut <b>Expired</b>, ses <b>Last 4 digits</b> et sa <b>Added date</b>.

Seules les cartes peuvent être enregistrées ici. Apple Pay, Google Pay et PayPal ne peuvent pas être enregistrés pour les paiements automatiques.

<!-- screenshot: le Credit Card Dashboard avec une carte enregistrée marquée par défaut et les boutons Set as default et Unlink -->

## Plusieurs cartes

- La carte par défaut a une coche verte. Appuyez sur <b>Set as default</b> sur une autre carte pour l'utiliser en premier.
- Quand nous devons payer une commande, nous essayons d'abord la carte par défaut, puis vos autres cartes, une par une, jusqu'à ce que l'une d'elles fonctionne.
- Pour retirer une carte, appuyez sur <b>Unlink</b> et confirmez.

Vérifiez la date d'expiration de vos cartes. Quand une carte expire, enregistrez la nouvelle et retirez l'ancienne.

## En cas de problème

- <b>Something went wrong</b> / <b>Failed to communicate with the payment service.</b> : le paiement n'a pas démarré. Actualisez la page de caisse et réessayez, ou choisissez un autre mode.
- <b>Payment still processing</b> / <b>Your order will be submitted automatically once the payment is confirmed.</b> : votre banque n'a pas encore confirmé. Ne payez pas à nouveau. Vérifiez la commande dans quelques minutes.
- <b>Order already submitted</b> / <b>This order has already been submitted and cannot be paid again.</b> : la commande est déjà payée. Vous êtes redirigé vers la page de la commande.
- <b>Online payments are temporarily unavailable</b> : le service de paiement ne répond pas. Réessayez plus tard, ou rechargez votre solde et payez avec.
- <b>Insert file missing</b> : un encart de votre commande n'a pas de fichier. Retournez au panier et téléversez le fichier avant la caisse.
- <b>We cannot deliver to …</b> ou <b>Your current billing address (…) is marked as forbidden</b> : nous ne pouvons pas prendre le paiement pour cette adresse. Modifiez l'adresse, ou contactez-nous via le chat de notre site.
- Une commande de votre boutique affiche <b>Unpaid</b> et vous avez reçu un email indiquant qu'elle est en attente : votre solde n'était pas suffisant et aucune carte enregistrée n'a fonctionné. Rechargez votre solde et appuyez sur <b>Pay … with balance</b> sur la commande. Voir [Recharger et payer avec le solde](/docs/topping-up-and-paying-with-balance).
- Votre carte a été refusée pour un paiement automatique : votre banque a refusé le débit. Vérifiez-la dans <b>Saved Cards</b> — elle peut être refusée ou expirée — puis rechargez votre solde et payez avec, ou enregistrez une autre carte et définissez-la par défaut.
