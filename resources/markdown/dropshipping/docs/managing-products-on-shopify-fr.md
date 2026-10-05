---
title: Gérer les produits sur Shopify
summary: Ajoutez nos produits à votre canal Shopify, créez-les dans votre boutique ou liez-les par SKU à des produits que vous vendez déjà, tenez le stock à jour, déliez-les ou retirez-les, et corrigez les erreurs d'import.
date: 2026-09-25
source_date: 2026-09-25
tags: shopify, produits, portefeuille, correspondance, sku, import, stock
category: products
series: shopify
order: 2
shops: awd, dssk, dse
---

<aside class="tldr">
Ouvrez votre canal Shopify et allez dans <b>My Products</b>. Appuyez sur <b>Add products</b>, cochez les produits voulus et ajoutez-les. Puis, pour chaque produit, appuyez soit sur <b>Create new product</b> pour le créer dans Shopify, soit liez-le à un produit que vous vendez déjà avec <b>Match with this product</b>. Une poignée de main verte signifie que le produit est connecté : nous tenons son stock à jour et vous envoyons ses commandes.
</aside>

## Avant de commencer

Votre canal Shopify doit être connecté, avec l'application installée. Voir [Connecter votre boutique Shopify](/docs/connecting-shopify). En attendant, <b>My Products</b> affiche <b>Click here to install</b> au lieu de vos produits.

## Ajouter des produits à votre canal

1. Ouvrez <b>Chaînes</b> et cliquez sur votre boutique Shopify, ou choisissez-la dans le menu de gauche.
2. Ouvrez <b>My Products</b>. Vous pouvez aussi cliquer sur <b>View all</b> sur l'encadré <b>Products</b> du tableau de bord du canal.
3. Appuyez sur <b>Add products</b>. Une fenêtre s'ouvre : <b>Select products to be added to shop</b>.
4. Tapez dans la zone de recherche. Utilisez les boutons <b>Product</b>, <b>Department</b>, <b>Sub-department</b> ou <b>Family</b> en dessous pour choisir ce que la liste affiche.
5. Cochez les produits voulus et appuyez sur <b>Add</b>. Le bouton indique combien vous en avez coché.
6. La fenêtre se ferme et les produits sont ajoutés à votre liste.

<!-- screenshot: la fenêtre Select products to be added to shop avec quelques produits cochés -->

Les produits sont maintenant dans <b>My Products</b>. Ils ne sont pas encore dans Shopify : ils affichent une poignée de main rouge, <b>Not connected</b>.

## Connecter chaque produit : créer ou faire correspondre

Chaque produit doit être connecté à un produit de votre boutique Shopify. Dans la liste, vous avez deux moyens.

**Create new product.** Nous créons un nouveau produit dans Shopify avec notre nom, description, images, SKU, code-barres, poids et prix. Utilisez-le pour les produits que vous ne vendez pas encore. Voir [Ce qu'il advient de vos descriptions de produits](/docs/product-descriptions-after-connecting).

**Match.** Utilisez-le quand vous vendez déjà ce produit dans Shopify, pour éviter un doublon. Nous cherchons un produit dans votre boutique avec le même SKU ou code-barres.

- Si nous en avons trouvé un, vous le voyez avec son image et son nom. Appuyez sur <b>Match with this product</b>.
- Si ce n'est pas le bon, appuyez sur <b>Choose another product from your shop</b>.
- Si nous n'avons rien trouvé, appuyez sur <b>Match it with an existing product in your shop</b>. Une fenêtre liste les produits de votre boutique. Cherchez-y, choisissez le produit et appuyez sur <b>Link ... to selected item on your platform</b>.

Quand cela fonctionne, la poignée de main devient verte : <b>Product connected to shopify</b>. Vous voyez le nom et l'image du produit Shopify à côté.

<!-- screenshot: une ligne de produit avec la correspondance suggérée, le bouton Match with this product et le bouton Create new product -->

Pour changer le lien plus tard, appuyez sur <b>Connect with other product</b>.

## Plusieurs produits à la fois

Quand certains produits ne sont pas connectés, vous voyez <b>You have ... products not synced yet</b> au-dessus de la liste, avec deux boutons :

- <b>Upload all as new product</b> : crée tous les produits dans Shopify. Une fenêtre affiche la progression.
- <b>Match all with default product</b> : lie chaque produit dont le SKU est déjà dans votre boutique Shopify. Les produits dont le SKU n'est pas dans votre boutique sont laissés tels quels.

Vous pouvez aussi cocher des produits dans la liste. Des boutons apparaissent au-dessus :

- <b>Create New (...)</b> : crée les produits cochés dans Shopify. Il s'affiche quand au moins un produit coché n'est pas encore connecté.
- <b>Match (...)</b> : lie les produits cochés à vos produits Shopify ayant le même SKU.
- <b>Edit Price (...)</b> : change le prix. Lisez d'abord l'avertissement dans [Ce qu'il advient de vos descriptions de produits](/docs/product-descriptions-after-connecting).
- <b>Unlink (...)</b> et <b>Unlink & Delete (...)</b> : voir ci-dessous.

<b>Astuce :</b> commencez avec quelques produits. Vérifiez-les dans Shopify, puis faites le reste.

## Faire correspondre par SKU plutôt que créer de nouveaux produits

Si vous vendez déjà nos produits dans Shopify, donnez à chaque produit (ou variante) Shopify le même SKU que notre code produit avant de faire correspondre. Utilisez ensuite <b>Match all with default product</b>. Cela lie vos annonces existantes et n'en crée pas de nouvelles.

Si vous vendez plusieurs de nos produits comme les variantes d'un seul produit Shopify, par exemple un produit avec une variante par couleur, chaque variante doit avoir son propre SKU. Demandez-nous dans le chat de notre site d'activer la liaison aux variantes existantes pour votre canal. Vous ne pouvez pas l'activer vous-même. La correspondance lie ensuite chaque produit à la variante portant son SKU, et nous ne changeons jamais le prix, le SKU ou le code-barres de vos variantes.

## Trouver des produits dans la liste

Appuyez sur <b>Filter</b> au-dessus de la liste pour n'afficher que les produits <b>Only For Sale</b>, <b>Not For Sale</b>, <b>Discontinued</b>, <b>Connected to Shopify</b> ou <b>Not Connected</b>.

Chaque ligne affiche notre stock, le poids, la taille, le prix et le PVC. Un signe rouge sur la ligne signifie que le produit n'est pas en vente en ce moment, ou qu'il est arrêté. Les produits qui ne sont pas en vente ne peuvent pas être créés ou liés. Retirez les produits arrêtés de votre canal et de votre boutique Shopify.

## Stock, poids et tailles

- Stock : tant que <b>Stock Update</b> est activé, nous envoyons le stock de chaque produit connecté vers l'emplacement <b>aiku-</b> dans Shopify. Pour le changer, ouvrez le canal et appuyez sur <b>Manage Sales Channel</b>. Vous pouvez aussi y régler <b>Max Quantity To Advertise</b> et <b>Stock Threshold</b>.
- Poids et tailles : appuyez sur <b>Update all dimensions</b> au-dessus de la liste pour renvoyer le poids et la taille de tous vos produits vers Shopify.

## Délier ou retirer un produit

- <b>Unlink</b> (l'icône de chaîne brisée sur un produit connecté) : le produit reste dans votre liste mais n'est plus connecté. Sa poignée de main devient rouge. Nous cessons de mettre à jour son stock dans Shopify, donc le dernier stock envoyé y reste.
- <b>Remove product</b> (l'icône de crâne sur un produit non connecté) : le retire de <b>My Products</b>.
- <b>Unlink & Delete (...)</b> : fait les deux pour les produits cochés.

Le produit lui-même reste dans votre boutique Shopify. Si vous ne voulez plus le vendre, archivez-le ou supprimez-le vous-même dans Shopify.

## En cas de problème

Pour voir ce qui s'est passé pour chaque import, ouvrez l'onglet <b>Logs</b> dans <b>My Products</b> : l'icône horloge à droite, à côté de l'onglet <b>My Products</b>.

**La poignée de main reste rouge après « Create new product ».** Ouvrez l'onglet <b>Logs</b> et lisez le message. Les plus fréquents sont ci-dessous.

**A product with the same SKU already exists in my store.** Ne le créez pas à nouveau. Utilisez plutôt <b>Match with this product</b> ou <b>Match it with an existing product in your shop</b>. Ou changez le SKU du produit dans Shopify.

**« No Shopify location, the AW fulfilment service is not installed on this store so stock can not be sent ».** L'application n'est pas complètement installée. Ouvrez le canal et utilisez <b>Click here to install</b>. Voir [L'emplacement de traitement AW dans Shopify](/docs/shopify-fulfilment-location).

**« No variant on Shopify matches this sku ».** Le produit est lié, mais nous ne trouvons plus son SKU dans Shopify. Le SKU a été changé, ou le produit ou la variante a été supprimé dans Shopify. Remettez le SKU en place, ou reliez le produit avec <b>Connect with other product</b>.

**« You need to add option values for Colour »** (ou une autre option). Vous avez fait correspondre avec un produit Shopify ayant des options, comme des couleurs. Donnez à chaque variante son propre SKU et demandez-nous dans le chat de notre site d'activer la liaison aux variantes existantes, ou faites correspondre avec un produit sans options.

**« None of the variants of this Shopify product has the sku ... ».** La liaison aux variantes est activée, mais aucune variante ne porte notre SKU. Réglez le SKU sur la variante voulue et refaites correspondre.

**« More than one variant of this Shopify product has the sku ... ».** Deux variantes ont le même SKU. Donnez à chaque variante son propre SKU.

**« Throttled ».** Shopify nous a demandé de ralentir parce que beaucoup de changements ont été envoyés d'un coup. Attendez quelques minutes et réessayez.

**« Error in API response: HTTP 502 » ou « 504 ».** Shopify n'a pas répondu à temps. C'est du côté de Shopify. Réessayez plus tard.

**« Could not check whether this product is already in Shopify, nothing was created to avoid a duplicate ».** Nous n'avons pas pu atteindre votre boutique à ce moment-là, donc nous n'avons rien créé. Réessayez plus tard.

**Les descriptions Shopify ne changent pas quand les nôtres changent.** C'est normal. Voir [Ce qu'il advient de vos descriptions de produits](/docs/product-descriptions-after-connecting).

<aside class="wayfinder"><strong>Où cliquer</strong>
<ul>
<li><b>Ajouter des produits :</b> votre canal → <b>My Products</b> → <b>Add products</b>.</li>
<li><b>Créer dans Shopify :</b> <b>My Products</b> → <b>Create new product</b>, ou cochez des produits → <b>Create New (...)</b>.</li>
<li><b>Lier à un produit que vous vendez déjà :</b> <b>My Products</b> → <b>Match with this product</b>, ou <b>Match all with default product</b>.</li>
<li><b>Voir les erreurs d'import :</b> <b>My Products</b> → <b>Logs</b>.</li>
<li><b>Paramètres de stock :</b> votre canal → <b>Manage Sales Channel</b>.</li>
</ul>
</aside>
