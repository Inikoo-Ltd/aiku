---
title: Gérer les produits sur le canal Manuel/API
summary: Ajoutez les produits que vous vendez à Mes produits sur un canal Manuel/API, importez-les depuis un tableur ou un autre canal, téléchargez vos données et images produits, et retirez les produits que vous ne vendez plus.
date: 2026-09-25
source_date: 2026-09-25
tags: manuel, api, mes produits, portefeuille, ajouter des produits, import, csv, images
category: products
series: manual
order: 2
shops: awd, dssk, dse
---

<aside class="tldr">
<b>Mes produits</b> est la liste des produits que vous vendez sur un canal. Ouvrez-la sous votre canal Manuel/API et appuyez sur <b>Ajouter des produits</b> pour choisir des produits dans notre catalogue. Sur un canal Manuel/API, rien n'est importé nulle part : la liste sert à votre propre usage et à l'API. Vous n'en avez pas besoin pour passer des commandes à la main. Pour arrêter de vendre un produit, appuyez sur le bouton <b>X</b> de sa ligne.
</aside>

## Ouvrir Mes produits

Allez sur votre canal Manuel/API dans le menu et ouvrez <b>Mes produits</b>. Vous pouvez aussi appuyer sur <b>Afficher tout</b> sur l'encadré <b>Products</b> de la page du canal.

Si la liste est vide, la page indique <b>Vous n'avez aucun article dans votre portefeuille</b> et affiche un bouton <b>Ajouter un produit</b>.

Chaque ligne de produit affiche la photo, le nom et le code, le stock dont nous disposons (<b>Stocks:</b>), le poids, votre prix (<b>Price:</b>) et le prix de vente conseillé (<b>RRP:</b>).

## Ajouter des produits

1. Appuyez sur <b>Ajouter des produits</b>.
2. Une fenêtre <b>Ajoutez des produits à votre produit</b> s'ouvre.
3. Choisissez le critère de recherche : <b>Produit</b> recherche les noms et codes produits, <b>Département</b>, <b>Sous-département</b> et <b>Famille</b> trouvent les produits d'un groupe portant ce nom.
4. Tapez dans le champ de recherche et cochez les produits voulus.
5. Appuyez sur <b>Add … products and close</b>. Le nombre correspond à ceux que vous avez cochés.

<!-- screenshot: la fenêtre Ajouter des produits avec le filtre Produit / Département / Sous-département / Famille et le bouton Ajouter les produits et fermer -->

Vous voyez <b>Portefeuilles ajoutés avec succès</b> et les produits apparaissent dans la liste.

## Ajouter plusieurs produits à la fois

### Depuis un tableur

1. Appuyez sur le bouton d'import à côté de <b>Ajouter des produits</b> (infobulle <b>Importer à partir d'un fichier xlsx</b>).
2. Dans la fenêtre <b>Portefeuilles d'importation en gros</b>, appuyez sur <b>Download template (.xlsx)</b>.
3. Remplissez la colonne <b>sku</b> avec nos codes produits, un par ligne. La colonne <b>title</b> est facultative.
4. Importez le fichier.

Les lignes sont ignorées quand le code n'existe pas sur notre site ou que le produit n'est pas en vente. L'historique d'import montre ce qui a été ajouté et ce qui a échoué.

### Depuis un autre canal

Si vous avez déjà des produits sur un autre canal, vous pouvez les copier. Appuyez sur le bouton à trois points à côté de <b>Ajouter des produits</b>. Sous <b>Cloner le portefeuille à partir de la chaîne :</b>, choisissez le canal source. Le nombre entre parenthèses indique combien de produits il a. La copie s'exécute en arrière-plan et la page se recharge une fois terminée.

## Trouver des produits dans votre liste

Utilisez le champ de recherche, ou les boutons de filtre au-dessus de la liste :

- <b>En vente uniquement</b> : produits que vous pouvez commander maintenant.
- <b>Pas à vendre</b> : produits que nous ne vendons pas actuellement.
- <b>Arrêté</b> : produits que nous ne revendrons plus.
- <b>En rupture de stock</b> : produits sans stock en ce moment.

Une icône de boîte barrée signifie que le produit est arrêté. Son infobulle indique <b>Cette gamme de produits n'est plus commercialisée. Veuillez retirer cet article.</b> Une icône d'argent barré signifie <b>Cette gamme de produits n'est actuellement pas disponible à la vente.</b> Retirez ces produits de votre propre site pour que vos acheteurs ne puissent pas les commander.

## Obtenir les données et images produits pour votre site

Sur un canal Manuel/API, nous n'importons pas les produits vers votre site. Récupérez les données ici :

- <b>CSV</b> : télécharge votre liste de produits avec prix, stock et descriptions.
- Le bouton à trois points à côté de <b>CSV</b> ouvre <b>Options d'exportation</b>. Choisissez les colonnes, l'<b>État du produit</b> et l'<b>État des ventes du produit</b> voulus, puis appuyez sur <b>Propriétés étendues d'exportation</b>. Cochez <b>Include bundles</b> pour ajouter vos lots.
- <b>Images</b> : prépare un téléchargement des photos de vos produits. Une fois prêt, appuyez sur <b>Télécharger les images</b>. Le lien ne fonctionne que pendant un temps limité, indiqué dans l'infobulle du bouton.
- Via l'API, votre système peut lire la même liste, et la télécharger en flux CSV ou JSON. Voir [Le canal Manuel/API](/docs/manual-and-api-channel).

Le stock et les prix changent. Retéléchargez la liste, ou lisez-la via l'API, assez souvent pour garder votre site à jour.

## Retirer un produit

Appuyez sur le bouton <b>X</b> sur la ligne du produit (infobulle <b>Supprimer le produit de la liste</b>). Le produit quitte votre liste. Les commandes déjà passées avec lui ne sont pas modifiées. Vous pouvez le rajouter plus tard avec <b>Ajouter des produits</b>.

## En cas de problème

- <b>Je ne trouve pas un produit dans la fenêtre Ajouter des produits.</b> Vérifiez que vous cherchez dans le bon onglet : <b>Produit</b> cherche les noms et codes produits, <b>Famille</b> et <b>Département</b> cherchent les noms de groupes. La fenêtre n'affiche pas les produits déjà dans votre liste, les produits pas à vendre et les produits arrêtés.
- <b>Mon import de tableur a ignoré des lignes avec « SKU not found in this shop ».</b> Le code de la colonne <b>sku</b> n'est pas l'un de nos codes produits sur ce site. Copiez le code exactement comme il apparaît sur le produit.
- <b>Mon import de tableur a ignoré des lignes avec « Product is not for sale ».</b> Nous ne vendons pas ce produit en ce moment. Laissez-le de côté.
- <b>Un produit apparaît comme arrêté ou pas à vendre.</b> Vous ne pouvez pas le commander. Retirez-le de votre propre site et de <b>Mes produits</b>.
- <b>Un produit est en rupture de stock.</b> Il reste dans votre liste. Utilisez <b>En rupture de stock</b> pour trouver ces produits et les masquer sur votre site jusqu'à leur retour.
- <b>Le lien de téléchargement des images ne fonctionne plus.</b> Le lien expire. Appuyez à nouveau sur <b>Images</b> pour en créer un nouveau.
- <b>Mes produits ne sont pas sur mon site.</b> Nous n'importons jamais depuis un canal Manuel/API. Chargez-les vous-même avec le téléchargement CSV ou l'API. Si vous vendez sur une plateforme affichée sur la page <b>Ajouter un canal de vente</b>, comme Shopify, WooCommerce, eBay ou TikTok Shop, connectez cette plateforme comme son propre canal et les produits sont importés pour vous.
