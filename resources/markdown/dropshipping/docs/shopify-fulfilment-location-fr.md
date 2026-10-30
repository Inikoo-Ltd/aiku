---
title: L'emplacement de traitement AW dans Shopify
summary: Ce que fait l'emplacement aiku- dans votre boutique Shopify, comment il est ajouté à votre profil d'expédition pour vous, et que faire quand des produits apparaissent épuisés ou que des commandes ne nous parviennent pas.
date: 2026-09-25
source_date: 2026-09-25
tags: shopify, emplacement de traitement, profil d'expédition, stock, épuisé
category: sales-channels
series: shopify
order: 3
shops: awd, dssk, dse
---

<aside class="tldr">
Quand vous installez notre application, nous ajoutons un emplacement de traitement à votre boutique Shopify. Son nom commence par <b>aiku-</b>. Nous l'ajoutons à votre profil d'expédition par défaut pour vous, donc vous n'avez généralement rien à faire. Si vous utilisez plusieurs profils d'expédition, vérifiez que l'emplacement <b>aiku-</b> se trouve dans le profil de nos produits, sinon Shopify les affiche comme épuisés et ne nous envoie pas leurs commandes.
Si votre boutique utilise l'expédition par marché, Shopify ne nous laisse pas modifier ses paramètres d'expédition : ajoutez l'emplacement <b>aiku-</b> dans <b>Ships from</b> des tarifs d'expédition de vos marchés, comme expliqué ci-dessous.
</aside>

## À quoi sert l'emplacement

Shopify gère le stock par emplacement. Nos produits sont envoyés depuis notre entrepôt, donc nous ajoutons notre entrepôt à votre boutique comme emplacement de traitement.

- Son nom est <b>aiku-</b>, puis le code de notre site, puis le code de votre canal entre parenthèses. Par exemple <b>aiku-awd (sho-ab12cd-3e)</b>.
- Le stock de chaque produit que vous connectez est conservé dans cet emplacement. Nous le mettons à jour pour vous.
- Quand un client achète l'un de ces produits, Shopify nous envoie une demande de traitement depuis cet emplacement. C'est ainsi que la commande nous parvient.

Ne supprimez pas cet emplacement et ne déplacez pas nos produits vers un autre emplacement. Si vous le faites, le stock arrête de se mettre à jour et les commandes n'arrivent plus jusqu'à nous.

## Ajouté à votre profil d'expédition pour vous

Shopify ne vend que le stock des emplacements présents dans un profil d'expédition. Quand l'application est installée, nous ajoutons l'emplacement <b>aiku-</b> pour vous :

- à votre profil d'expédition par défaut, ou
- si votre boutique a encore un ancien emplacement <b>aiku-dse</b> d'une connexion précédente, à chaque profil d'expédition dans lequel se trouve cet ancien emplacement.

Si l'emplacement est déjà dans l'un de vos profils d'expédition, nous ne changeons rien.

Si votre boutique utilise l'expédition par marché (les nouveaux paramètres d'expédition de Shopify, qui remplacent les profils d'expédition), Shopify ne nous laisse pas modifier vos paramètres d'expédition. Ajoutez vous-même l'emplacement <b>aiku-</b> aux tarifs d'expédition de vos marchés, une seule fois : voir <b>Boutiques avec expédition par marché</b> ci-dessous.

Vous n'avez plus besoin d'ajouter l'emplacement à la main, comme le disaient d'anciens guides.

## Boutiques avec expédition par marché

Shopify fait passer toutes les boutiques des profils d'expédition à l'expédition par marché. Une telle boutique affiche <b>Shipping has moved to Markets</b> dans <b>Settings</b>, <b>Shipping and delivery</b>. Là, chaque tarif d'expédition a sa propre liste d'emplacements d'où il expédie, et Shopify ne vend nos produits que si un tarif expédie depuis l'emplacement <b>aiku-</b>. D'ici là, ils apparaissent épuisés.

1. Dans votre administration Shopify, ouvrez <b>Markets</b>, puis un marché où vous vendez.
2. Dans <b>Shipping</b>, ouvrez la liste des options d'expédition. À côté d'un tarif, appuyez sur <b>...</b> et choisissez <b>Edit</b>.
3. Dans <b>Ships from</b>, ajoutez l'emplacement <b>aiku-</b>. Gardez vos propres emplacements si vous expédiez aussi depuis ceux-ci.
4. Enregistrez. Faites de même pour chaque tarif de chaque marché où vous vendez.

<!-- screenshot: Shopify Markets, un tarif d'expédition dont le Ships from affiche Shop location et aiku-awd -->

L'emplacement <b>aiku-</b> est géré par notre application, il ne peut donc pas être ouvert depuis <b>Settings</b>, <b>Locations</b>. Ajoutez-le depuis le tarif d'expédition comme ci-dessus.

Nos produits ne sont vendus que vers les pays que vos marchés incluent. Un client dans un pays qu'aucun de vos marchés ne couvre les voit épuisés.

## Vérifiez vous-même

Faites cela si nos produits apparaissent épuisés dans votre boutique, ou si le paiement n'affiche aucun tarif d'expédition pour eux.

1. Dans votre administration Shopify, ouvrez <b>Settings</b>.
2. Ouvrez <b>Shipping and delivery</b>.
3. Ouvrez le profil d'expédition dans lequel se trouvent nos produits. Dans la plupart des boutiques, c'est le profil général.
4. Regardez les emplacements depuis lesquels le profil expédie. L'emplacement <b>aiku-</b> doit y figurer.
5. S'il n'y est pas, ajoutez-le au profil et enregistrez.

<!-- capture d'écran : Shopify Shipping and delivery, un profil d'expédition avec l'emplacement aiku-awd dans sa liste d'emplacements -->

Shopify change parfois ses menus, donc les noms peuvent être un peu différents dans votre administration.

Si vous avez créé un profil d'expédition personnalisé pour certains de nos produits, ajoutez aussi l'emplacement <b>aiku-</b> à ce profil. Nous ne l'ajoutons qu'au profil par défaut.

## Quand quelque chose ne va pas

**Nos produits apparaissent épuisés dans Shopify.** Vérifiez le profil d'expédition comme ci-dessus. Avec l'expédition par marché, vérifiez que les tarifs d'expédition de vos marchés expédient depuis l'emplacement <b>aiku-</b>, voir <b>Boutiques avec expédition par marché</b>. Vérifiez aussi que le produit est connecté : dans <b>Mes produits</b>, il doit afficher une poignée de main verte. Voir [Gérer les produits sur Shopify](/docs/managing-products-on-shopify).

**Erreur d'import « No Shopify location, the AW fulfilment service is not installed on this store so stock can not be sent ».** L'emplacement <b>aiku-</b> est manquant. Ouvrez le canal. Si vous voyez <b>Click here to install</b>, appuyez dessus et installez l'application dans Shopify. Si le canal affiche <b>Reset channel</b>, utilisez-le pour recréer l'emplacement.

**Message de journal « The specified inventory item is not stocked at the location ».** Le produit dans Shopify n'est pas stocké à l'emplacement <b>aiku-</b>, par exemple parce qu'il a été déplacé vers un autre emplacement dans Shopify. Reliez le produit à nouveau avec <b>Connect with other product</b> dans <b>Mes produits</b>.

**Les commandes ne nous parviennent pas.** Shopify ne nous envoie que les commandes des articles stockés à l'emplacement <b>aiku-</b>. Si le produit était stocké dans votre propre emplacement, Shopify s'attend à ce que vous l'envoyiez vous-même. Voir [Vos commandes Shopify et leur statut](/docs/shopify-order-status).

<aside class="wayfinder"><strong>Où cliquer</strong>
<ul>
<li><b>Expédition par marché :</b> administration Shopify → <b>Markets</b> → votre marché → <b>Shipping</b> → le tarif → <b>Edit</b> → <b>Ships from</b>.</li>
<li><b>Vérifier l'emplacement dans Shopify :</b> administration Shopify → <b>Settings</b> → <b>Shipping and delivery</b> → votre profil d'expédition.</li>
<li><b>Vérifier le canal :</b> <b>Channels</b> → votre boutique Shopify → les trois icônes à côté de son nom.</li>
</ul>
</aside>
