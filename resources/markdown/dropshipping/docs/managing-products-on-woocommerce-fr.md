---
title: Gérer les produits sur WooCommerce
summary: Ajoutez nos produits à votre canal WooCommerce, créez-les dans votre boutique ou liez-les à des produits que vous vendez déjà, gardez le stock à jour, et réparez les erreurs d'import.
date: 2026-09-25
source_date: 2026-09-25
tags: woocommerce, produits, import, association, sku, stock
category: products
series: woocommerce
order: 2
shops: awd, dssk, dse
---

<aside class="tldr">
Ouvrez votre canal WooCommerce et allez dans <b>Mes produits</b>. Appuyez sur <b>Ajouter des produits</b> et choisissez les produits que vous voulez vendre. Puis envoyez chacun vers votre boutique : <b>Créer un nouveau produit</b> crée un nouveau produit dans WooCommerce, et <b>Match with this product</b> le lie à un produit que vous avez déjà dans votre boutique. Une coche verte signifie que le produit est en ligne et que nous gardons son stock à jour.
</aside>

## Ouvrir votre liste de produits

1. Ouvrez <b>Chaînes</b> dans le menu et cliquez sur le nom de votre boutique WooCommerce.
2. Sur le tableau de bord du canal, appuyez sur <b>Afficher tout</b> sous <b>Products</b>. La page <b>Mes produits</b> s'ouvre.

Si la page affiche <b>Your channel is not connected yet to the platform</b>, réparez d'abord la connexion. Voir [Connecter votre boutique WooCommerce](connecting-woocommerce).

## Ajouter des produits à votre liste

1. Appuyez sur <b>Ajouter des produits</b>. La fenêtre <b>Select products to be added to shop</b> s'ouvre.
2. Recherchez par nom ou par code. Vous pouvez aussi choisir tout un <b>Département</b>, <b>Sous-département</b> ou <b>Famille</b> plutôt que des produits individuels.
3. Cochez les produits voulus. Appuyez sur <b>Ajouter</b>. Le bouton indique combien vous en avez sélectionnés.

Les produits sont maintenant dans votre liste, mais pas encore dans votre boutique WooCommerce. Vous devez d'abord les créer ou les associer.

Vous pouvez aussi ajouter plusieurs produits à la fois depuis un tableur avec le bouton d'import à côté de <b>Ajouter des produits</b> (<b>Importer à partir d'un fichier xlsx</b>). Si vous avez des produits sur un autre canal, le bouton <b>⋮</b> permet de <b>Cloner le portefeuille à partir de la chaîne</b>.

<!-- screenshot: la fenêtre Select products to be added to shop avec quelques produits cochés et le bouton Ajouter -->

## Envoyer les produits vers votre boutique

Chaque produit a une colonne <b>Woo Commerce product</b>. Ce que vous y voyez dépend du produit :

- <b>Créer un nouveau produit</b> : crée un nouveau produit dans votre boutique WooCommerce, avec le nom, la description et le prix de votre liste de produits, et nos images, SKU, code-barres, poids, dimensions et stock.
- <b>Match with this product</b> : nous avons trouvé un produit dans votre boutique avec le même SKU, ou un nom similaire. Vérifiez que c'est le bon, puis appuyez dessus pour lier les deux. Utilisez ceci quand vous vendez déjà le produit et ne voulez pas d'une seconde fiche.
- <b>Choisissez un autre produit dans votre boutique</b> (quand nous avons trouvé une correspondance possible) ou <b>Associez-le à un produit existant dans votre boutique</b> (quand nous n'en avons trouvé aucune) : ouvre une liste des produits de votre boutique. Recherchez le produit, sélectionnez-le et appuyez sur <b>Link ... to selected item on your platform</b>.

Quand cela fonctionne, le produit affiche une coche verte et le nom de votre produit WooCommerce. À partir de là, nous gardons son stock à jour. Pour le lier à un autre produit WooCommerce plus tard, appuyez sur <b>Change linked listing</b>.

Quand vous associez un produit, nous le lions et mettons seulement son stock à jour. Nous ne changeons pas le nom, la description, le prix ou les images que vous avez déjà dans WooCommerce.

<!-- screenshot: lignes de Mes produits montrant Créer un nouveau produit, Match with this product, et une coche verte sur un produit lié -->

### Plusieurs produits à la fois

- Cochez plusieurs produits dans la liste. Des boutons apparaissent au-dessus de la liste : <b>Créer un nouveau</b> les envoie tous comme nouveaux produits, <b>Match</b> les lie aux produits de votre boutique avec le même SKU.
- Si certains produits ne sont pas encore dans votre boutique, vous voyez <b>You have ... products not synced yet</b>. Appuyez sur <b>Upload all as new product</b> pour tous les créer, ou <b>Match all with default product</b> pour lier chaque produit ayant le même SKU dans votre boutique.

Les imports volumineux s'exécutent en arrière-plan et affichent une fenêtre de progression. Vous pouvez continuer à travailler pendant ce temps.

L'association recherche notre SKU, ou notre code produit, dans votre boutique. La casse n'a pas d'importance. Si vos SKU sont différents des nôtres, utilisez <b>Associez-le à un produit existant dans votre boutique</b> et choisissez le produit vous-même.

## Ce que nous envoyons à WooCommerce

- Nom, description et prix de votre liste de produits.
- Nos images, SKU et code-barres (comme GTIN, UPC, EAN ou ISBN).
- Poids dans l'unité utilisée par votre boutique, et dimensions quand nous les avons.
- Pays d'origine et ingrédients comme attributs produit, et liens vers les documents produits dans la description.
- Le stock disponible à la vente. Les produits en vente sont publiés. Les produits en rupture de stock, à venir ou pas encore prêts sont enregistrés comme brouillons.

Nous ne choisissons pas de catégorie pour vous. Les nouveaux produits arrivent sans catégorie, ajoutez donc vos propres catégories dans WooCommerce.

## Stock et prix

Nous envoyons les changements de stock à votre boutique automatiquement. Appuyez sur <b>Update Stock</b> en haut de <b>Mes produits</b> pour envoyer immédiatement le stock actuel de tous vos produits vers ce canal.

Dans <b>Gérer le canal de vente</b>, vous pouvez changer comment le stock est affiché :

- <b>Mise à jour des stocks</b> : activer ou désactiver les mises à jour automatiques de stock.
- <b>Quantité maximale à annoncer</b> : le nombre de stock maximum affiché dans votre boutique, même quand nous en avons plus.
- <b>Seuil de stock</b> : quand notre stock descend à ce nombre, le produit s'affiche en rupture de stock dans votre boutique.

Votre <b>Pricing Policy</b> dans <b>Gérer le canal de vente</b> fixe le prix des produits que vous ajoutez à partir de maintenant. Elle ne change pas les produits déjà dans votre liste. Pour changer leur prix, cochez-les et appuyez sur <b>Edit Price</b>.

## Retirer des produits

Il y a trois façons de retirer un produit. Choisissez avec attention, car le bouton crâne supprime aussi le produit de votre boutique WooCommerce.

- Le bouton crâne sur une ligne de produit demande confirmation, puis retire le produit de votre liste et, s'il est lié, le supprime définitivement de votre boutique WooCommerce. Il ne va pas dans la corbeille WooCommerce.
- <b>Unlink & Delete</b> (après avoir coché des produits) retire les produits cochés de votre liste, mais les garde dans votre boutique WooCommerce. Ils ne sont plus liés, donc nous cessons de mettre à jour leur stock.
- <b>Dissocier</b> (après avoir coché des produits) garde les produits dans votre liste et dans WooCommerce, mais rompt le lien. Nous cessons de mettre à jour leur stock. Vous pouvez les associer à nouveau plus tard.

Si vous supprimez un produit lié directement dans WooCommerce, nous le retirons aussi de votre liste.

Les produits que nous ne vendons plus affichent un signe rouge. <b>Cette gamme de produits n'est plus commercialisée. Veuillez retirer cet article.</b> signifie que vous devez le retirer de votre boutique. <b>Cette gamme de produits n'est actuellement pas disponible à la vente.</b> signifie que vous ne pouvez pas l'importer en ce moment.

## Vérifier ce qui s'est passé

Ouvrez l'onglet <b>Logs</b> (l'icône horloge à droite des onglets) pour voir chaque import, s'il a fonctionné, et le message renvoyé par votre boutique.

La colonne <b>Statut</b> affiche trois coches pour chaque produit : <b>Has valid platform product id</b>, <b>Exist in platform</b> et <b>Platform status</b>. Trois coches vertes signifient que le produit est lié et en ligne.

## En cas de problème

Si un import échoue, la ligne du produit affiche le message de votre boutique et un court conseil. Les plus fréquents :

- <b>The store answered with a web page instead of data</b>, une erreur 503, un délai dépassé, ou une réponse vide : votre site est hors ligne, trop lent, en maintenance, ou nous bloque. C'est le problème d'import le plus fréquent. Vérifiez que votre site s'ouvre dans le navigateur, demandez à votre hébergeur d'autoriser nos serveurs, puis importez à nouveau.
- <b>A product with this SKU already exists in your store</b>, <b>Invalid or duplicated SKU</b>, ou <b>product with SKU ... already present in the lookup table</b> : votre boutique a déjà un produit avec ce SKU. Quand vous appuyez sur <b>Créer un nouveau produit</b>, nous essayons de le lier à ce produit nous-mêmes. Si le message persiste, associez le produit à la main avec <b>Associez-le à un produit existant dans votre boutique</b>. Si vous ne trouvez pas le produit dans votre boutique, regardez dans la corbeille WooCommerce : un produit supprimé garde le SKU jusqu'à sa suppression définitive.
- <b>Invalid or duplicated GTIN</b> : un autre produit de votre boutique a déjà le même code-barres (GTIN, UPC, EAN ou ISBN). Associez avec ce produit, ou retirez le code-barres de l'autre produit dans WooCommerce, puis importez à nouveau.
- <b>Your store could not save the product images</b> : le dossier d'import de WordPress n'est pas accessible en écriture. Demandez à votre hébergeur de corriger les permissions du dossier, puis importez à nouveau.
- <b>The account connected to your store is not allowed to create products</b> (ou de les modifier ou lire) : les clés n'ont pas la permission <b>Read/Write</b>. Reconnectez le canal avec un compte administrateur.
- <b>Your store rejected the credentials</b> : les clés ont été supprimées ou changées dans WooCommerce. Appuyez sur <b>Essayez de vous reconnecter</b> sur la page du canal.
- <b>This product no longer exists in your store</b> : le produit a été supprimé dans WooCommerce. Recréez-le ou associez-le à un autre produit.
- Le bouton <b>Ajouter des produits</b> est absent : votre boutique n'a pas répondu la dernière fois que nous avons essayé de la joindre, donc nous avons mis le canal en pause. Vérifiez que votre site est en ligne. Le bouton revient une fois que nous avons rejoint votre boutique à nouveau.

Les problèmes sur votre propre site, comme un site hors ligne, lent ou qui nous bloque, et les règles de produits que vous définissez dans WooCommerce, ne peuvent être réparés que par vous ou votre hébergeur.
