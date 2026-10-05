---
title: Exporter les données et images produits
summary: Téléchargez les produits d'un canal sous forme de fichier CSV, choisissez vos propres colonnes et filtres, et téléchargez toutes les photos de produits en un seul fichier zip.
date: 2026-09-25
source_date: 2026-09-25
tags: produits, export, csv, images, téléchargement, flux de données
category: products
shops: awd, dssk, dse
---

<aside class="tldr">
Sur <b>Mes produits</b> d'un canal, il y a trois boutons de téléchargement : <b>CSV</b> donne tous les détails de chaque produit de la liste, <b>⋮</b> vous permet de choisir les colonnes et filtres pour un CSV plus petit, et <b>Images</b> place toutes les photos dans un seul fichier zip. Vous pouvez aussi télécharger un produit, une famille, un département ou une collection depuis sa page dans le <b>Catalogue</b>.
</aside>

## Où se trouvent les boutons

1. Ouvrez <b>Chaînes</b> dans le menu, cliquez sur votre canal et ouvrez <b>Mes produits</b>.
2. En haut à droite, vous voyez un groupe de boutons : <b>CSV</b>, <b>⋮</b> et <b>Images</b>.

Les boutons ne s'affichent que lorsque le canal a des produits et n'est pas fermé, et pas sur l'onglet <b>Mes offres groupées</b>. Les fichiers contiennent uniquement les produits de ce canal. Pour exporter un autre canal, ouvrez son <b>Mes produits</b>.

<!-- screenshot: le groupe de boutons CSV / ⋮ / Images en haut de Mes produits -->

## Détails complets des produits (CSV)

Appuyez sur <b>CSV</b>. Le fichier se télécharge immédiatement. Ouvrez-le dans Excel, Google Sheets ou tout tableur. Chaque ligne est un produit, chaque colonne un détail.

Les colonnes sont :

- <b>Status</b> : <b>Actif</b>, <b>Arrêt de production</b> ou <b>Arrêté</b>.
- <b>Product code</b>, <b>Product user reference</b> (votre propre référence pour le produit, si vous en avez défini une).
- <b>Department code</b>, <b>Department</b>, <b>Subdepartment code</b>, <b>Subdepartment</b>, <b>Family code</b>, <b>Family</b>.
- <b>Barcode</b>, <b>CPNP number</b> (numéro cosmétique européen, quand le produit en a un).
- <b>Price</b> : votre prix pour un carton (le lot que vous commandez). <b>Units per outer</b>, <b>Unit label</b>, <b>Unit price</b>.
- <b>Unit Name</b> : le nom du produit.
- <b>Unit RRP</b> : prix de vente conseillé pour une unité.
- <b>Unit net weight</b> et <b>Package weight (shipping)</b>, en kilogrammes. <b>Unit dimensions</b>.
- <b>Materials/Ingredients</b>.
- <b>Webpage description (html)</b> et <b>Webpage description (plain text)</b>.
- <b>Country of origin</b>, <b>Tariff code</b>, <b>Duty rate</b>, <b>HTS US</b>.
- <b>Stock</b> : un niveau de stock, pas un nombre : <b>Normal</b>, <b>Low</b> (moins de 20), <b>VeryLow</b> (moins de 5), <b>OutofStock</b>, <b>Discontinuing</b> ou <b>Discontinued</b>.
- <b>Images</b> : liens vers les photos en pleine taille, séparés par des virgules.
- <b>Data updated</b>, <b>Stock updated</b>, <b>Price updated</b>, <b>Images updated</b> : la dernière modification de chaque partie.
- <b>Available Quantity</b> : le nombre d'unités en stock. Il vaut 0 quand le produit n'est pas en vente.
- <b>For sale</b> : <b>Yes</b> ou <b>No</b>.

Les lots sont exclus. Pour les inclure, ouvrez <b>⋮</b> et cochez <b>Include bundles</b> d'abord.

## Vos propres colonnes et filtres

Appuyez sur <b>⋮</b> (<b>Autres options d'exportation</b>). Un panneau s'ouvre :

- <b>Bundles</b> : cochez <b>Include bundles</b> pour ajouter vos lots. C'est désactivé par défaut et s'applique aux deux téléchargements CSV.
- <b>Colonnes à exporter</b> : cochez les colonnes voulues. <b>Tout sélectionner</b> et <b>Désélectionnez tout</b> sont en haut. Les colonnes sont les codes et noms de produit, département, sous-département et famille, le code-barres, les matériaux, les dimensions, les poids, les codes d'origine et de douane, <b>Stock</b> (un nombre), <b>Status</b> (<b>En stock</b> ou <b>En rupture de stock</b>), <b>For sale</b> et <b>Data updated</b>.
- <b>État du produit</b> : <b>Actif</b>, <b>Arrêt de production</b>, <b>Arrêté</b>. Seul <b>Actif</b> est coché au départ.
- <b>État des ventes du produit</b> : <b>Exclure les produits qui ne sont pas destinés à la vente</b>, <b>Exclure les produits en rupture de stock</b>, <b>Only products that are not for sale</b>.

Appuyez sur <b>Propriétés étendues d'exportation</b>. Le fichier s'ouvre dans un nouvel onglet et se télécharge. Ce fichier n'a pas de prix, de descriptions ni de liens d'images : utilisez le <b>CSV</b> complet pour cela.

<!-- screenshot: le panneau Options d'exportation avec Colonnes à exporter, État du produit et État des ventes du produit -->

## Toutes les photos de produits (zip)

1. Appuyez sur <b>Images</b>. Une fenêtre indique <b>Your download images request is being processed.</b> Nous rassemblons les photos de chaque produit de la liste.
2. Une fois prêt, la fenêtre indique <b>Your images are ready for download.</b> Appuyez sur <b>Télécharger</b> et enregistrez le fichier zip.
3. Le bouton indique maintenant <b>Télécharger les images</b>. Passez la souris dessus pour voir combien de temps le lien fonctionne encore. Le lien expire un jour après sa création.

Chaque photo est nommée avec le code produit et un numéro, par exemple <b>abc-01__12345.jpg</b>, pour que vous puissiez voir à quel produit elle appartient.

Chaque fois que des produits du canal sont ajoutés ou modifiés, l'ancien zip est supprimé. Appuyez à nouveau sur <b>Images</b> pour en créer un nouveau.

Il n'y a pas de vidéos de produits dans ce téléchargement.

## Un produit, une famille ou une collection

Dans <b>Catalogue</b>, ouvrez un produit, une famille, un sous-département, un département ou une collection. En haut à droite :

- <b>CSV</b> télécharge ses produits avec les mêmes colonnes que le CSV complet.
- Sur les pages produit, famille et collection, appuyez sur <b>⋮</b> et choisissez <b>images</b> sous <b>Select another download file type</b> pour télécharger leurs photos en fichier zip.

Sur les pages catalogue de notre site, chaque famille et produit de la liste a deux icônes de téléchargement : <b>Download products (csv)</b> et <b>Download images (zip)</b>.

## En cas de problème

- **Je ne vois pas les boutons CSV et Images.** Le canal n'a pas encore de produits, le canal est fermé, ou vous êtes sur l'onglet <b>Mes offres groupées</b>. Ajoutez d'abord des produits, ou retournez sur l'onglet <b>Mes produits</b>.
- **Le CSV a moins de produits que Mes produits.** Le <b>CSV</b> complet exclut les lots. Le fichier <b>Propriétés étendues d'exportation</b> utilise aussi les filtres <b>État du produit</b> et <b>État des ventes du produit</b> : cochez tous les états pour tout obtenir.
- **« Select at least one column ».** Cochez au moins une colonne sous <b>Colonnes à exporter</b>.
- **Le lien des images indique Expired ou ne s'ouvre pas.** Appuyez à nouveau sur <b>Images</b> pour créer un nouveau zip.
- **Le zip ne contient qu'un fichier appelé error.txt.** Aucun produit de la liste n'a de photo. Vérifiez que le canal a des produits.
- **Excel affiche des lettres étranges.** Ouvrez le fichier avec <b>Data → From Text/CSV</b> et choisissez UTF-8, ou ouvrez-le dans Google Sheets.
- **« The data feed for ... is not available yet, please try again later. »** Le fichier pour cette famille ou ce département est encore en cours de création. Réessayez dans quelques minutes.
