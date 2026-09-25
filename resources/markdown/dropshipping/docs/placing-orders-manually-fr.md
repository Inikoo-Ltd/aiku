---
title: Passer des commandes manuellement
summary: Créez une commande pour un client sur un canal Manuel/API, ajoutez des produits, choisissez les options de livraison, payez à la caisse et suivez la commande jusqu'à son expédition.
date: 2026-09-25
source_date: 2026-09-25
tags: manuel, commandes, panier, caisse, paiement, solde, enlèvement, expédition
category: orders
series: manual
order: 4
shops: awd, dssk, dse
---

<aside class="tldr">
Ouvrez le client dans votre canal Manuel/API et appuyez sur <b>Create Order</b>. Un panier s'ouvre : ajoutez des produits avec <b>Add products</b>, choisissez les options de livraison et appuyez sur <b>Continue to Checkout</b>. Nous utilisons d'abord le solde de votre compte, puis vous payez le reste par carte. Une fois la commande payée, elle part à l'entrepôt et vous la suivez dans <b>Orders</b>.
</aside>

## Avant de commencer

- Vous avez besoin d'un canal Manuel/API. Voir [Le canal Manuel/API](/docs/manual-and-api-channel).
- La personne à qui vous expédiez doit être un client de ce canal. Voir [Gérer les clients](/docs/managing-clients).

## Créer la commande

1. Ouvrez votre canal Manuel/API puis <b>Clients</b>.
2. Cliquez sur le nom du client. La page du client s'ouvre.
3. Appuyez sur <b>Create Order</b>.

Le panier de la nouvelle commande s'ouvre. En haut, il montre le client, ses coordonnées et l'adresse de livraison. Vérifiez le nom et l'adresse avant de continuer.

## Ajouter des produits

- <b>Add products</b> ouvre une fenêtre <b>Add products to Order</b>. Cherchez par nom ou code de produit, indiquez la quantité et ajoutez les produits. Vous pouvez choisir n'importe quel produit que nous vendons, pas seulement ceux de <b>My Products</b>.
- <b>Upload products</b> ajoute de nombreux produits depuis un tableur. Téléchargez le modèle (.xlsx) dans la fenêtre, remplissez les colonnes <b>code</b> et <b>quantity</b>, puis importez-le.

Les produits apparaissent dans la liste. Vous pouvez y modifier les quantités, ou supprimer une ligne. Le poids estimé du colis est indiqué à côté de l'adresse.

<!-- screenshot: un panier avec le client et l'adresse en haut, les produits dans la liste, les options de livraison et le bouton Continue to Checkout -->

## Choisir les options de livraison

- <b>Collection</b> : activez-la si vous, ou un coursier que vous réservez, viendrez chercher la commande à notre entrepôt au lieu que nous l'envoyions. Cela entraîne un supplément. Quand c'est désactivé, nous envoyons la commande à l'adresse indiquée. Appuyez sur <b>Edit</b> sous l'adresse pour la modifier pour cette commande.
- Expédition plus rapide : sur AW Dropship UK l'option s'appelle <b>Same Day Dispatch</b>, sur AW Dropship Europe <b>Premium Dispatch</b> et sur AW Dropship España <b>Envío Premium</b>. Cela entraîne un supplément. Lisez l'icône d'information à côté pour connaître les conditions.
- <b>Extra protective packing for fragile items</b> (AW Dropship UK uniquement) : emballage supplémentaire pour les produits fragiles. Cela entraîne un supplément.
- <b>Delivery Instructions</b> : une note pour le coursier. <b>This message will be printed in shipping label</b>, écrivez-la donc pour le coursier, pas pour nous.
- <b>Other Instructions</b> : une note pour notre équipe.

Les frais et le total de la commande se mettent à jour quand vous changez une option.

Voici les frais supplémentaires sur ce site :

{order_charges}

## Payer

Si le solde de votre compte couvre toute la commande, le panier affiche <b>Place order</b> au lieu de <b>Continue to Checkout</b>. Appuyez dessus et la commande est payée avec votre solde. La note indique <b>This is your final confirmation. You can pay totally with your current balance.</b>

Sinon :

1. Appuyez sur <b>Continue to Checkout</b>.
2. La caisse affiche le numéro de commande. Si vous avez du solde, elle indique combien est payé avec le solde et vous demande de payer le reste.
3. Dans <b>Online payments</b>, saisissez les détails de votre carte et confirmez. Votre banque peut vous demander d'approuver le paiement dans son application ou avec un code.
4. Une fois le paiement effectué, la page affiche <b>Payment done. Waiting for confirmation...</b> puis ouvre la commande.

Appuyez sur <b>Back to basket</b> sur la page de caisse pour modifier la commande avant de payer.

## Commandes non terminées : Baskets

Une commande que vous avez créée mais non payée reste dans <b>Baskets</b> sous votre canal. Le chiffre à côté de <b>Baskets</b> dans le menu indique combien vous en avez. Ouvrez-en une pour la terminer, ou appuyez sur <b>Delete</b> sur sa ligne (infobulle <b>Delete basket</b>) pour la supprimer. Un panier n'est envoyé à l'entrepôt qu'une fois payé.

## Suivre vos commandes

Ouvrez <b>Orders</b> sous votre canal. La liste indique le <b>Status</b>, la <b>Reference</b>, le client, la <b>Date</b>, les articles et le total. Cliquez sur une commande pour voir ses produits, ses bons de livraison, ses envois avec les liens de suivi et ses factures.

L'icône de statut indique où en est la commande. Survolez-la pour voir son nom :

- <b>Submitted</b> : nous avons reçu la commande.
- <b>In Warehouse</b>, <b>Picking</b>, <b>Picked</b>, <b>Packing</b>, <b>Packed</b> : notre équipe la prépare.
- <b>Waiting</b> : elle est en attente à l'entrepôt.
- <b>Finalized</b> : prête à partir.
- <b>Dispatched</b> : envoyée. Le lien de suivi est sur la commande.
- <b>Cancelled</b> : la commande a été annulée.

Les icônes en haut d'une commande montrent les options choisies : une étoile pour <b>Premium dispatch</b>, une boîte pour <b>Extra packing</b>.

Si nous ne pouvons pas envoyer certains articles, la commande indique que <b>Some items are not being sent</b>. L'argent de ces articles est remboursé automatiquement.

La page de commande liste ses factures avec un bouton de téléchargement. Toutes vos factures se trouvent aussi sous <b>Invoices</b> dans le menu.

## En cas de problème

- <b>Je ne vois pas Create Order sur la page du client.</b> Le bouton n'existe que pour les clients d'un canal Manuel/API. Sur les canaux connectés, les commandes arrivent depuis votre boutique.
- <b>Le panier indique "We cannot deliver to …".</b> Nous ne livrons pas ce pays. Modifiez l'adresse de livraison, ou activez <b>Collection</b> si vous organisez le transport vous-même.
- <b>Le panier indique que votre adresse de facturation est marquée comme interdite.</b> Mettez à jour l'adresse de facturation dans votre compte, ou contactez-nous via le chat de notre site.
- <b>Continue to Checkout est grisé et me demande de téléverser un fichier.</b> Vous avez choisi un encart imprimé qui nécessite votre visuel. Téléversez le fichier correspondant, ou retirez l'encart, avant de passer à la caisse.
- <b>Le paiement par carte a échoué.</b> La caisse affiche <b>Something went wrong</b>. Vérifiez les détails de la carte et que votre banque a approuvé le paiement, puis réessayez. Vous pouvez aussi recharger votre solde et payer avec.
- <b>La caisse indique "Payment still processing".</b> Ne payez pas à nouveau. La commande est soumise automatiquement une fois le paiement confirmé.
- <b>La caisse indique "Order already submitted".</b> La commande est déjà payée. Ouvrez-la dans <b>Orders</b>.
- <b>Ma commande affiche Unpaid.</b> Le paiement n'a pas couvert la commande. Ajoutez de l'argent à votre solde avec <b>Top Up</b>, ouvrez la commande et appuyez sur <b>Pay … with balance</b>. Le bouton apparaît quand votre solde couvre le montant dû. La commande part alors à l'entrepôt.
- <b>J'ai besoin de modifier ou d'annuler une commande déjà payée.</b> Il n'y a pas de bouton d'annulation. Contactez-nous via le chat de notre site dès que possible. Nous ne pouvons l'annuler ou la modifier qu'avant son expédition. Une fois emballée, il peut être trop tard.
