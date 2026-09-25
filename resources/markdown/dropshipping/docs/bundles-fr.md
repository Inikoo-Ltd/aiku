---
title: Créer des lots
summary: Combinez plusieurs de nos produits dans un lot avec votre propre titre, description et images, et vendez-le sur votre canal comme n'importe quel produit.
date: 2026-09-25
source_date: 2026-09-25
tags: produits, lots, mes lots, ia, coffrets cadeaux
category: products
shops: awd, dssk, dse
---

<aside class="tldr">
Un lot est un ensemble de nos produits vendu comme un seul article, par exemple un coffret cadeau. Vous le créez dans l'onglet <b>My Bundles</b> d'un canal, ou depuis notre site avec le bouton lot sur un produit. Vous choisissez les produits et les quantités, donnez un titre, une description et au moins une image, puis appuyez sur <b>Create Bundle</b>. Il apparaît ensuite dans <b>My Bundles</b>, où vous l'envoyez vers votre boutique comme n'importe quel produit.
</aside>

## Où vivent les lots

Chaque lot appartient à un canal. Ouvrez <b>Channels</b> dans le menu, cliquez sur le canal, ouvrez <b>My Products</b> puis cliquez sur l'onglet <b>My Bundles</b>. Tous vos lots, de tous les canaux, sont aussi listés sous <b>Catalogue</b> → <b>Bundles</b>.

## Créer un lot depuis My Bundles

1. Dans l'onglet <b>My Bundles</b>, appuyez sur <b>Create bundle</b>. La fenêtre <b>Create Your Bundle</b> s'ouvre à <b>STEP 1/2</b>.
2. Saisissez un <b>Bundle Title</b>, ou appuyez sur le bouton étincelle (<b>Generate AI</b>) pour en obtenir un généré une fois les produits choisis.
3. Recherchez des produits. Comme pour l'ajout de produits, vous pouvez chercher par <b>Product</b>, <b>Department</b>, <b>Sub-department</b> ou <b>Family</b>. Choisissez les produits et fixez la quantité de chacun.
4. L'encadré à droite additionne les prix au fur et à mesure :
   - <b>Cost Price (Individual Purchase)</b> : ce que les produits vous coûtent achetés un par un.
   - <b>Bundle Price</b> : ce que vous nous payez pour le lot. Il n'est plus bas que le prix de revient que lorsque votre site fait une remise sur le lot ; sinon les deux sont identiques.
   - <b>RRP</b> : le prix de vente conseillé total.
   - <b>Profit</b> : RRP moins le prix du lot, avec le pourcentage.
5. Appuyez sur <b>Next</b>. Vous êtes à <b>STEP 2 / 2</b>.
6. Rédigez une <b>Description</b>, ou appuyez sur <b>Generate with AI</b>. Vous pouvez modifier le texte généré.
7. Ajoutez des images. Déposez des fichiers sur <b>Upload Media</b> (<b>Drag & drop images or click</b>), ou appuyez sur <b>Select existing media</b> pour utiliser des photos des produits du lot. Choisissez la <b>MAIN IMAGE</b> avec le bouton rond sur l'image.
8. Facultatif : appuyez sur <b>Generate Image AI</b> pour créer une nouvelle image. Sélectionnez d'abord des images, cochez les images de produit à inclure, écrivez dans <b>Describe your image</b> et appuyez sur <b>Generate</b>.
9. Appuyez sur <b>Create Bundle</b>. Le bouton n'est actif que lorsque le lot a une description et au moins une image.

Si vous fermez la fenêtre avant la fin, elle demande <b>Discard bundle?</b>. Fermer abandonne le lot et vous recommencez.

<!-- screenshot: STEP 1/2 de Create Your Bundle avec des produits choisis et l'encadré des prix à droite -->
<!-- screenshot: STEP 2/2 avec la description, les médias et le bouton Create Bundle -->

## Créer un lot depuis notre site

1. Connectez-vous et parcourez notre site.
2. Sur une fiche produit, appuyez sur le bouton rond lot (<b>Create bundle</b>). Sur une page produit, appuyez sur <b>Add to bundle</b>. Un panneau <b>Create Your Bundle</b> s'ouvre sur le côté.
3. Choisissez le <b>Sales Channel</b> pour lequel le lot est destiné.
4. Continuez à parcourir et ajoutez d'autres produits. Changez les quantités ou retirez un produit avec le bouton corbeille.
5. Saisissez le <b>Bundle Title</b>, appuyez sur <b>Next</b>, puis ajoutez la description et les images comme ci-dessus.

Le bouton lot n'apparaît que sur les produits en stock. Tant qu'un lot est ouvert, le site vous empêche de quitter la page tant que vous n'avez pas terminé ou abandonné.

## Envoyer le lot vers votre boutique

Le lot se trouve maintenant dans <b>My Bundles</b>. Il fonctionne comme une ligne de <b>My Products</b> : appuyez sur <b>Create new product</b> pour le créer sur votre boutique, ou associez-le à une fiche que vous avez déjà. Voir [Utiliser My Products](/docs/my-products).

Un lot a son propre code produit, qui est le SKU envoyé à votre boutique.

## Modifier ou supprimer un lot

- <b>Edit Bundle</b> (bouton crayon) : changez le titre, la description, les images, les produits et le RRP. <b>Save</b> conserve les changements chez nous uniquement. Quand le lot est lié à votre boutique, <b>Save & Sync</b> envoie aussi le nouveau titre et la nouvelle description à votre boutique (<b>Bundle saved and synchronised with ...</b>). Après un simple <b>Save</b>, une icône d'avertissement sur la ligne indique <b>Saved changes have not been pushed to ... yet</b>.
- <b>Unlink Bundle</b> (bouton rouge) : arrête le lot. Nous le marquons comme discontinué, il ne peut plus être commandé, et son lien avec votre boutique est retiré. Cette action ne demande pas de confirmation et est irréversible : pour revendre le même ensemble, créez un nouveau lot. La fiche reste sur votre boutique, supprimez-la donc là-bas.

## Télécharger les données des lots

Les exports <b>CSV</b> de <b>My Products</b> excluent les lots sauf si vous cochez <b>Include bundles</b> sous <b>⋮</b>. Voir [Exporter les données et images produits](/docs/exporting-product-data).

## En cas de problème

- **"The OpenAI service is currently unreachable, please try again later."** L'assistant IA n'a pas répondu. Réessayez plus tard, ou rédigez le texte vous-même.
- **"You have reached the limit of 3 attempts."** Chaque bouton IA (titre, description et image) peut être utilisé 3 fois en 5 minutes. Attendez 5 minutes et réessayez.
- **Le bouton Create Bundle reste grisé.** Ajoutez une description et au moins une image.
- **"Failed to create bundle" / "Failed to submit the data, please try again".** Vérifiez que le titre est rempli et que chaque produit a une quantité d'au moins 1, puis réessayez. Si le problème persiste, contactez-nous via le chat de notre site.
- **Le lot ne se télécharge pas vers ma boutique.** Lisez le message rouge sur sa ligne. C'est la réponse de votre plateforme, comme pour les produits. Voir la liste dans [Utiliser My Products](/docs/my-products).
