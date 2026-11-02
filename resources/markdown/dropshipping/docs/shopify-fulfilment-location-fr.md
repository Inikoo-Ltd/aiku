---
title: L'emplacement de traitement AW dans Shopify
summary: Ce que font l'emplacement aiku- et le profil d'expédition AW Connect shipping dans votre boutique Shopify, quels tarifs d'expédition vos clients voient pour nos produits, et que faire quand des produits apparaissent épuisés ou que des commandes ne nous parviennent pas.
date: 2026-10-30
source_date: 2026-10-30
tags: shopify, emplacement de traitement, profil d'expédition, tarifs d'expédition, marchés, stock, épuisé
category: sales-channels
series: shopify
order: 3
shops: awd, dssk, dse
---

<aside class="tldr">
Quand vous installez notre application, nous ajoutons à votre boutique Shopify un emplacement de traitement nommé <b>aiku-</b> et un profil d'expédition, <b>AW Connect shipping</b>, avec nos produits et nos prix d'expédition. Au paiement, vos clients voient l'expédition <b>AW Standard</b> pour nos produits. Vous n'avez généralement rien à faire.
</aside>

## À quoi sert l'emplacement

Shopify gère le stock par emplacement. Nos produits sont envoyés depuis notre entrepôt, donc nous ajoutons notre entrepôt à votre boutique comme emplacement de traitement.

- Son nom est <b>aiku-</b>, puis le code de notre site, puis le code de votre canal entre parenthèses. Par exemple <b>aiku-awd (sho-ab12cd-3e)</b>.
- Le stock de chaque produit que vous connectez est conservé dans cet emplacement. Nous le mettons à jour pour vous.
- Quand un client achète l'un de ces produits, Shopify nous envoie une demande de traitement depuis cet emplacement. C'est ainsi que la commande nous parvient.

Ne supprimez pas cet emplacement et ne déplacez pas nos produits vers un autre emplacement. Si vous le faites, le stock arrête de se mettre à jour et les commandes n'arrivent plus jusqu'à nous.

## L'expédition configurée pour vous

À l'installation de l'application, nous ajoutons un profil d'expédition à votre boutique. Son nom est <b>AW Connect shipping</b>, puis le code de votre canal entre parenthèses, par exemple <b>AW Connect shipping (sho-ab12cd-3e)</b>. Si votre boutique utilise encore des profils d'expédition et que l'un d'eux expédie déjà depuis l'emplacement <b>aiku-</b>, nous le laissons tel quel : vos clients continuent de payer vos propres tarifs pour nos produits, et vous ne verrez pas encore ce profil. Nous l'ajoutons quand Shopify fait passer votre boutique à l'expédition par marché.

- Il expédie uniquement depuis l'emplacement <b>aiku-</b>.
- Il contient chaque produit que vous connectez depuis chez nous. Nous ajoutons les nouveaux produits et retirons les produits supprimés pour vous, en général en moins de dix minutes.
- Ses tarifs sont nos prix d'expédition, par pays et poids du colis, dans la devise de votre boutique. Au paiement, ils apparaissent comme <b>AW Standard</b>. Quand nous changeons nos prix, le profil suit en un jour.
- Là où nous calculons l'expédition à la main (colis très lourds, pays hors de nos zones), le profil utilise notre prix le plus élevé pour cette zone, pour que vos clients puissent toujours commander. Ce que nous vous facturons pour la commande ne change pas.
- Shopify ne vous laisse pas modifier ces tarifs. Vous pouvez les désactiver, mais alors vos clients ne voient aucune expédition pour nos produits, sauf si l'un de vos propres tarifs expédie depuis l'emplacement <b>aiku-</b>.

Vos autres produits gardent vos propres paramètres d'expédition et vos tarifs. Nous ne les modifions pas.

## Boutiques avec expédition par marché

Shopify fait passer toutes les boutiques des profils d'expédition à l'expédition par marché. Une telle boutique affiche <b>Shipping has moved to Markets</b> dans <b>Settings</b>, <b>Shipping and delivery</b>. Notre profil apparaît dans chaque marché comme un service de notre application, à côté de vos propres tarifs.

Si l'un des tarifs de vos marchés expédie depuis l'emplacement <b>aiku-</b>, par exemple parce que vous l'avez ajouté à la main comme le disait une ancienne version de ce guide, vos clients voient ce tarif en plus d'<b>AW Standard</b>. Pour n'afficher que le nôtre, retirez l'emplacement <b>aiku-</b> de ce tarif :

1. Dans votre administration Shopify, ouvrez <b>Markets</b>, puis un marché où vous vendez.
2. Dans <b>Shipping</b>, à côté d'un tarif, appuyez sur <b>...</b> et choisissez <b>Edit</b>.
3. Dans <b>Ships from</b>, retirez l'emplacement <b>aiku-</b> et enregistrez.

Nos produits ne sont vendus que vers les pays que vos marchés incluent. Un client dans un pays qu'aucun de vos marchés ne couvre les voit épuisés.

## Votre propre stock de nos produits

Les produits de notre profil d'expédition partent uniquement de l'emplacement <b>aiku-</b>, donc Shopify ne les expédie pas depuis vos propres emplacements. Si vous détenez vous-même certains de nos produits, par exemple un article inutilisé qu'un client vous a renvoyé, mettez-les en vente dans votre boutique comme un produit distinct et expédiez ces commandes vous-même.

## Vérifiez vous-même

Faites cela si nos produits apparaissent épuisés dans votre boutique, ou si le paiement n'affiche aucun tarif d'expédition pour eux.

1. Dans votre administration Shopify, ouvrez <b>Settings</b>.
2. Ouvrez <b>Shipping and delivery</b>.
3. Cherchez <b>AW Connect shipping</b>, sous notre application. Il doit expédier depuis l'emplacement <b>aiku-</b> et contenir nos produits.
4. S'il manque, ou s'il manque un produit dedans, attendez un jour : nous vérifions chaque boutique chaque nuit. S'il ne revient pas, contactez-nous.

<!-- screenshot: Shopify Shipping and delivery, le profil d'expédition AW Connect shipping sous l'application, avec l'emplacement aiku-awd -->

Shopify modifie ses menus de temps en temps, les noms peuvent donc être un peu différents dans votre administration.

## Quand quelque chose ne va pas

**Nos produits apparaissent épuisés dans Shopify.** Vérifiez le profil d'expédition <b>AW Connect shipping</b> comme ci-dessus, et que le pays du client fait partie de l'un de vos marchés. Vérifiez aussi que le produit est connecté : dans <b>Mes produits</b>, il doit afficher une poignée de main verte. Voir [Gérer les produits sur Shopify](/docs/managing-products-on-shopify).

**Erreur d'import « No Shopify location, the AW fulfilment service is not installed on this store so stock can not be sent ».** L'emplacement <b>aiku-</b> est manquant. Ouvrez le canal. Si vous voyez <b>Click here to install</b>, appuyez dessus et installez l'application dans Shopify. Si le canal affiche <b>Reset channel</b>, utilisez-le pour recréer l'emplacement.

**Message de journal « The specified inventory item is not stocked at the location ».** Le produit dans Shopify n'est pas stocké à l'emplacement <b>aiku-</b>, par exemple parce qu'il a été déplacé vers un autre emplacement dans Shopify. Reliez le produit à nouveau avec <b>Connect with other product</b> dans <b>Mes produits</b>.

**Les commandes ne nous parviennent pas.** Shopify ne nous envoie que les commandes des articles stockés à l'emplacement <b>aiku-</b>. Si le produit était stocké dans votre propre emplacement, Shopify s'attend à ce que vous l'envoyiez vous-même. Voir [Vos commandes Shopify et leur statut](/docs/shopify-order-status).

<aside class="wayfinder"><strong>Où cliquer</strong>
<ul>
<li><b>Vérifier notre profil d'expédition :</b> administration Shopify → <b>Settings</b> → <b>Shipping and delivery</b> → <b>AW Connect shipping</b>.</li>
<li><b>Expédition par marché :</b> administration Shopify → <b>Markets</b> → votre marché → <b>Shipping</b>.</li>
<li><b>Vérifier le canal :</b> <b>Channels</b> → votre boutique Shopify → les trois icônes à côté de son nom.</li>
</ul>
</aside>
