---
title: Consulter vos commandes
summary: Trouvez les commandes de chaque canal de vente, lisez leur statut, voyez ce qui a été envoyé et ce que vous avez payé, et comprenez pourquoi une commande est impayée, annulée ou introuvable.
date: 2026-09-25
source_date: 2026-09-25
tags: commandes, statut de commande, impayé, annulé, liste des commandes
category: orders
shops: awd, dssk, dse
---

<aside class="tldr">
Les commandes sont classées par canal de vente. Dans le menu de gauche, ouvrez votre canal et cliquez sur <b>Orders</b>. Chaque commande affiche son statut, et une étiquette rouge <b>Unpaid</b> quand nous n'avons pas encore pu prendre le paiement. Une commande impayée attend et n'est pas envoyée à l'entrepôt tant qu'elle n'est pas payée. Cliquez sur la référence de la commande pour voir les produits, l'adresse de livraison, le numéro de suivi et la facture.
</aside>

## Où se trouvent vos commandes

Chaque canal que vous avez connecté (Shopify, eBay, TikTok, WooCommerce et les autres, et votre canal manuel) a sa propre liste de commandes.

1. Dans le menu de gauche, trouvez votre canal sous <b>Channels</b>.
2. Cliquez sur <b>Orders</b> sous celui-ci.

La liste comporte ces colonnes : <b>Status</b>, <b>Reference</b>, <b>Client</b>, <b>Date</b>, <b>Items</b> et <b>Total</b>. Les commandes les plus récentes sont en haut. Utilisez la zone de recherche pour trouver une commande par sa référence.

La <b>Reference</b> est notre numéro de commande. Ce n'est pas le numéro de commande de votre boutique et ce n'est pas un numéro de suivi. Pour trouver le numéro de suivi, voir [Trouver le numéro de suivi d'une commande](/docs/tracking-numbers).

Sur un canal Manuel/API, les commandes que vous préparez encore ne figurent pas dans cette liste. Elles se trouvent dans <b>Baskets</b>, sous le même canal, jusqu'à ce que vous les passiez.

Pour télécharger la liste, utilisez le bouton d'export en haut de la page et choisissez <b>Excel</b> ou <b>CSV</b>.

<!-- screenshot: la liste Orders d'un canal, avec la colonne Status, une référence avec l'étiquette rouge Unpaid, et le bouton d'export -->

## Ce que signifie chaque statut

- <b>Submitted</b> : nous avons la commande. Si elle n'est pas payée, elle reste ici jusqu'à son paiement.
- <b>In Warehouse</b> : la commande est payée et attend d'être préparée.
- <b>Picking</b> : l'entrepôt prépare les produits.
- <b>Waiting</b> : la préparation est en pause, par exemple pendant que l'entrepôt vérifie un produit.
- <b>Picked</b>, <b>Packing</b>, <b>Packed</b> : le colis est en préparation.
- <b>Finalized</b> : la commande est facturée et prête à partir.
- <b>Dispatched</b> : le colis a quitté notre entrepôt. Le numéro de suivi est sur la commande.
- <b>Cancelled</b> : la commande ne sera pas envoyée.

À côté de la référence, vous pouvez aussi voir de petites icônes pour <b>Premium dispatch</b>, <b>Extra packing</b> et <b>Insurance</b> quand vous les avez choisies pour cette commande.

## Commandes impayées

Une étiquette rouge <b>Unpaid</b> signifie que nous n'avons pas encore pu prendre le montant total. La commande reste <b>Submitted</b> et n'est pas envoyée à l'entrepôt.

Quand une commande arrive de votre boutique, nous la payons ainsi :

1. D'abord avec votre solde.
2. Si le solde ne suffit pas, avec vos cartes enregistrées, en commençant par votre carte par défaut.

Si aucune des deux ne fonctionne, la commande attend et nous vous envoyons un email indiquant qu'elle est en attente. La plupart du temps, cela arrive parce qu'aucune carte n'est enregistrée pour le canal. Pour éviter que cela se reproduise, enregistrez une carte : voir [Payer vos commandes](/docs/topping-up-and-paying-with-balance).

Pour payer une commande en attente :

1. Rechargez votre solde d'au moins le montant dû. Voir [Payer vos commandes](/docs/topping-up-and-paying-with-balance).
2. Rouvrez la commande. Un encadré jaune indique <b>Order ... is not paid yet</b> et affiche <b>Your balance</b>.
3. Cliquez sur le bouton <b>Pay ... with balance</b>. Il affiche le montant dû.

La commande part alors à l'entrepôt. Le bouton n'apparaît que lorsque votre solde couvre la totalité du montant dû, tant que la commande est <b>Submitted</b> ou <b>Picking</b>.

<b>Remarque :</b> nous ne retentons pas votre carte de nous-mêmes. Une commande en attente le reste tant que vous ne l'avez pas payée.

## À l'intérieur d'une commande

Cliquez sur la référence de la commande pour l'ouvrir. Vous voyez :

- En haut, une chronologie des étapes franchies par la commande, et une étiquette <b>Paid</b> ou <b>Unpaid</b>.
- Votre client : nom, email, téléphone et adresse de livraison.
- <b>Weight</b> : le poids estimé de tous les produits.
- <b>Delivery Notes</b> : les colis, leur statut, et sous <b>Shipments</b> le transporteur et le numéro de suivi. L'icône PDF (<b>Download Picking List</b>) télécharge la liste des produits du colis.
- <b>Invoices</b> : notre facture pour la commande, à ouvrir ou télécharger en PDF. Voir [Vos factures](/docs/invoices).
- Le résumé des prix : <b>Items</b>, frais, <b>Net</b>, taxe et <b>Total</b>.

Voici les frais supplémentaires sur ce site :

{order_charges}

- L'onglet <b>Transactions</b> : chaque produit avec sa <b>Quantity</b>. Quand moins d'articles ont été envoyés que commandés, la quantité envoyée s'affiche en rouge au-dessus de la quantité commandée, qui est barrée.
- <b>Notes from Staff</b>, <b>Delivery Instructions</b> et <b>Other Instructions</b>. Les instructions de livraison sont imprimées sur l'étiquette d'expédition.

<!-- screenshot: une page de commande montrant la chronologie, l'encadré Delivery Notes avec un numéro de suivi et l'encadré Invoices -->

## Quand tout n'a pas été envoyé

Parfois, nous ne pouvons pas envoyer tous les produits, par exemple quand l'un d'eux vient à manquer pendant la préparation. La page de la commande affiche alors <b>Dispatched | Modified</b>, et une icône d'avertissement jaune apparaît à côté du statut dans la liste. L'onglet <b>Transactions</b> indique quels produits n'ont pas été envoyés. L'argent des produits non envoyés retourne à votre solde de lui-même une fois la commande facturée.

## Commandes annulées

Une commande annulée affiche son statut <b>Cancelled</b> en haut, et un encadré rouge <b>Order cancelled</b> quand un motif a été enregistré. L'argent déjà payé pour cette commande retourne à votre solde.

Pour une commande Shopify, il y a aussi un bouton de synchronisation (infobulle <b>Sync order state</b>) en haut. Cliquez dessus pour indiquer à Shopify que la commande a été annulée. Si vous voyez <b>The order state on Shopify is up-to-date</b>, Shopify le sait déjà.

Il n'y a pas de bouton d'annulation. Pour annuler une commande, contactez-nous via le chat de notre site avec la référence de la commande. Nous ne pouvons l'annuler qu'avant son expédition. Une fois la commande emballée, il peut être trop tard.

## Laisser un avis

Sur certains de nos sites, quelque temps après l'expédition d'une commande, un bouton <b>Review</b> apparaît en haut de la commande. Utilisez-le pour noter la commande et les produits.

## En cas de problème

**Une commande de ma boutique n'apparaît pas dans la liste.** Vérifiez ceci, dans cet ordre :

- Seuls les produits présents dans <b>My Products</b> de ce canal remontent. Si aucun des produits de la commande n'est dans <b>My Products</b>, la commande ne s'affiche pas dans <b>Orders</b>, car il n'y a rien que nous puissions envoyer.
- Vérifiez que vous regardez le bon canal. Chaque canal a sa propre liste.
- Le canal doit encore être connecté. Si la page du canal indique qu'il n'est pas connecté, reconnectez-le d'abord.
- **Shopify** : seules les commandes que Shopify envoie à notre emplacement de traitement remontent, et elles arrivent sous forme de demande de traitement. Si un produit de la commande n'est pas dans <b>My Products</b>, cette partie de la demande est refusée dans Shopify et le reste remonte. Quand toute la demande est refusée (aucun des produits n'est dans <b>My Products</b>, ou la commande n'a pas d'adresse de livraison), la commande s'affiche dans <b>Orders</b> comme <b>Cancelled</b>, avec le motif sous <b>Notes from Staff</b>. Corrigez la commande dans Shopify et redemandez le traitement. Une commande traitée par votre propre boutique, déjà traitée, ou dont la demande a été annulée dans Shopify ne remontera pas. C'est la raison la plus fréquente pour laquelle une commande de test n'arrive pas : vérifiez dans Shopify que ses produits sont en stock à notre emplacement.

**Ma demande de traitement Shopify a été acceptée mais je ne vois pas la commande à payer.** Cherchez dans <b>Orders</b> du canal Shopify son statut et une étiquette rouge <b>Unpaid</b>. Si elle n'y est pas, contactez-nous via le chat de notre site avec le numéro de commande Shopify.

**La commande est Submitted depuis longtemps.** Elle est presque toujours impayée. Suivez les étapes de "Commandes impayées" ci-dessus.

**La commande indique "We cannot deliver to ...".** Nous n'expédions pas vers ce pays depuis ce site. Voir [Pays que nous ne pouvons pas livrer](/docs/delivery-restrictions).

**Un produit est arrivé cassé, ou mon acheteur veut retourner quelque chose.** Contactez-nous via le chat de notre site avec la référence de la commande et des photos. Votre acheteur ne doit rien renvoyer avant que cela n'ait été convenu avec nous dans le chat. Les remboursements vont sur votre solde.
