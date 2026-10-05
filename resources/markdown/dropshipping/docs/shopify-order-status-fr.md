---
title: Vos commandes Shopify et leur statut
summary: Comment les commandes de votre boutique Shopify nous parviennent, comment elles sont payées, ce que signifie chaque statut, pourquoi une commande peut rester Submitted ou ne pas arriver du tout, et ce que nous renvoyons à Shopify.
date: 2026-09-25
source_date: 2026-09-25
tags: shopify, commandes, statut, paiement, submitted, non payé, demande de traitement
category: orders
series: shopify
order: 4
shops: awd, dssk, dse
---

<aside class="tldr">
Quand un client achète un produit connecté dans votre boutique Shopify, Shopify nous envoie une demande de traitement et la commande apparaît dans <b>Orders</b> de votre canal. Nous la payons depuis votre solde, puis depuis votre carte enregistrée. Une commande payée part vers notre entrepôt d'elle-même. Une commande que nous n'avons pas pu payer reste <b>Submitted</b> et <b>Unpaid</b> jusqu'à ce que vous la payiez. Quand nous l'envoyons, nous la marquons traitée dans Shopify avec le numéro de suivi.
</aside>

## Comment une commande nous parvient

1. Un client achète l'un de vos produits connectés sur Shopify.
2. Shopify envoie une demande de traitement pour ces articles à l'emplacement <b>aiku-</b>.
3. Nous acceptons la demande et créons la commande dans votre canal. Vous la trouvez sous votre canal, <b>Orders</b>.

Seuls les produits connectés dans <b>My Products</b> peuvent nous parvenir. Si une commande contient certains de nos produits et certains des vôtres, nous acceptons nos produits et vous envoyez le reste vous-même.

## Comment la commande est payée

Nous essayons de payer chaque nouvelle commande tout de suite :

1. D'abord avec votre solde.
2. Si le solde ne suffit pas, avec les cartes enregistrées sous <b>Saved Cards</b>, selon votre ordre de priorité.

Si le paiement fonctionne, la commande part vers notre entrepôt d'elle-même. Sinon, la commande attend et nous vous envoyons un e-mail indiquant qu'elle est en attente. C'est le plus souvent parce qu'aucune carte n'est enregistrée. Pour éviter que cela se reproduise, enregistrez une carte : voir [Cartes de paiement et options](/docs/payment-cards-and-options).

## Payer une commande en attente

Une commande que nous n'avons pas pu payer affiche <b>Unpaid</b> à côté de son numéro et reste <b>Submitted</b>.

1. Rechargez votre solde d'au moins le montant dû, depuis <b>Top Up</b> dans le menu.
2. Ouvrez votre canal, <b>Orders</b>, et ouvrez la commande.
3. Appuyez sur <b>Pay ... with balance</b>. Le bouton ne s'affiche que quand votre solde couvre le montant dû.

La commande part alors vers notre entrepôt d'elle-même.

<!-- screenshot: une commande non payée avec le libellé Unpaid et le bouton Pay with balance -->

## Ce que signifie chaque statut

- <b>Submitted</b> : nous avons la commande. Si elle affiche aussi <b>Unpaid</b>, elle attend votre paiement.
- <b>In Warehouse</b> : payée et en attente de préparation.
- <b>Handling</b> : en cours de préparation.
- <b>Waiting</b> : l'entrepôt a dû suspendre la commande un moment avant de pouvoir continuer.
- <b>Picked</b>, <b>Packing</b>, <b>Packed</b> : le colis est en cours de préparation.
- <b>Finalized</b> : facturée et prête à partir.
- <b>Dispatched</b> : envoyée. Si certains articles n'ont pas pu être envoyés, vous voyez <b>Modified</b> et l'argent correspondant est remboursé automatiquement.
- <b>Cancelled</b> : la commande ne sera pas envoyée. La raison est affichée en haut de la commande.

Pour en savoir plus sur la page de commande, voir [Consulter vos commandes](/docs/reviewing-orders).

## Ce que nous renvoyons à Shopify

- Quand la commande est expédiée, nous la marquons traitée dans Shopify avec le numéro de suivi et le lien. Shopify prévient votre client.
- Quand une commande est annulée, nous fermons la demande dans Shopify. Sur une commande annulée, vous pouvez appuyer sur le bouton de synchronisation (<b>Sync order state</b>) pour renvoyer l'annulation à Shopify. Si Shopify est déjà à jour, vous voyez <b>The order state on Shopify is up-to-date</b>.

## Une commande n'est pas dans mes Orders

Ouvrez le canal et appuyez sur <b>Fetch orders</b>. Cela <b>Checks Shopify for orders that have not reached us yet</b> : cela regarde les commandes récentes non traitées et rapatrie celles pour notre emplacement. S'il n'y a rien de nouveau, vous voyez <b>No new orders</b>. Vous pouvez réessayer après quelques minutes.

Si la commande ne remonte toujours pas, vérifiez ces points :

- Les produits sont connectés (poignée de main verte) dans <b>My Products</b>.
- Les articles sont stockés à l'emplacement <b>aiku-</b> dans Shopify, et cet emplacement figure dans votre profil d'expédition. Voir [L'emplacement de traitement AW dans Shopify](/docs/shopify-fulfilment-location).
- La commande n'a pas déjà été traitée dans Shopify, ou envoyée vers un autre emplacement.

## En cas de problème

**Une commande annulée indique « Fulfilment request declined: The items can't be fulfilled because you don't have the items in your portfolio. »** Aucun produit de la commande n'est connecté dans <b>My Products</b>. Ajoutez-les et connectez-les, puis redemandez le traitement dans Shopify.

**Une commande annulée indique « Fulfilment request declined: Order don't have shipping information ».** La commande dans Shopify n'a pas d'adresse de livraison. Ajoutez l'adresse dans Shopify et redemandez le traitement.

**La demande de traitement a été acceptée dans Shopify, mais je ne vois pas la commande à payer.** Ouvrez <b>Orders</b> dans le canal : c'est là que les commandes venant de Shopify sont listées. Cherchez la commande avec <b>Unpaid</b>, ou appuyez sur <b>Fetch orders</b>.

**Ma commande de test sur Shopify n'est pas remontée.** Une commande de test ne nous parvient que si elle contient des produits connectés stockés à l'emplacement <b>aiku-</b>. Attention : une commande qui nous parvient est une vraie commande. Nous la payons et l'envoyons. Si vous en avez fait une par erreur, demandez-nous rapidement dans le chat de notre site de l'annuler. Nous ne pouvons annuler qu'avant son expédition.

**La commande reste Submitted et Unpaid.** Il n'y avait pas assez de solde et aucune carte n'a fonctionné. Payez-la comme indiqué dans <b>Payer une commande en attente</b>, et enregistrez une carte pour les prochaines.

**La commande indique « We cannot deliver to ... ».** Nous ne livrons pas ce pays depuis ce site. La commande n'est pas payée et n'est pas envoyée. Mettez à jour l'adresse de livraison, ou demandez-nous dans le chat de notre site.

**La commande est payée mais n'a pas bougé depuis longtemps.** Demandez-nous dans le chat de notre site, avec le numéro de commande. Le numéro de commande est la <b>Reference</b> dans <b>Orders</b>.

<aside class="wayfinder"><strong>Où cliquer</strong>
<ul>
<li><b>Voir vos commandes Shopify :</b> <b>Chaînes</b> → votre boutique Shopify → <b>Orders</b>.</li>
<li><b>Payer une commande en attente :</b> ouvrez la commande → <b>Pay ... with balance</b>.</li>
<li><b>Rapatrier une commande manquante :</b> ouvrez le canal → <b>Fetch orders</b>.</li>
<li><b>Enregistrer une carte :</b> <b>Saved Cards</b> dans le menu.</li>
</ul>
</aside>
