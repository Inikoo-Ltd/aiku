---
title: Ajouter et synchroniser des produits vers Allegro
summary: Ajoutez nos produits à votre canal Allegro, créez-les comme offres Allegro ou liez-les à des offres que vous avez déjà, et comprenez les messages qu'Allegro renvoie.
date: 2026-09-25
source_date: 2026-09-25
tags: allegro, produits, offres, import, correspondance, prix, devise
category: products
series: allegro
order: 2
shops: dssk, dse
---

<aside class="tldr">
Ouvrez votre canal Allegro, allez dans <b>My Products</b> et appuyez sur <b>Add products</b>. Choisissez les produits et appuyez sur <b>Add</b>. Puis appuyez sur <b>Upload all as new product</b> pour les créer comme offres sur Allegro, ou sur <b>Match all with default product</b> pour les lier à des offres que vous avez déjà. Un produit avec trois coches vertes est en ligne sur Allegro. Si un import échoue, passez la souris sur le message rouge de sa ligne pour voir pourquoi.
</aside>

## Avant de commencer

Votre canal Allegro doit afficher trois coches vertes sur son tableau de bord, et votre compte Allegro a besoin de conditions de réclamation (<b>Warunki reklamacji</b>). Voir le guide sur la connexion de votre compte Allegro.

## Ajouter des produits à My Products

1. Ouvrez votre canal Allegro depuis le menu et allez dans <b>My Products</b>.
2. Si la liste est vide, appuyez sur <b>Add Product</b> au milieu de la page. Sinon, appuyez sur <b>Add products</b> en haut à droite. Une fenêtre s'ouvre avec notre catalogue.
3. Cherchez par nom ou code de produit, ou parcourez par <b>Department</b>, <b>Sub-department</b> ou <b>Family</b>.
4. Cochez les produits que vous voulez. Le bouton en haut à droite indique combien vous en avez choisi, par exemple <b>Add 5</b>. Appuyez dessus.

<!-- screenshot: la fenêtre Add products avec quelques produits cochés et le bouton Add -->

Les produits sont maintenant dans <b>My Products</b> mais pas encore sur Allegro. Un message jaune indique <b>You have ... products not synced yet</b>.

## Créer les offres sur Allegro

- Pour tous les créer, appuyez sur <b>Upload all as new product</b> dans le message jaune. Une fenêtre affiche <b>Uploading Portfolios...</b> et compte les produits. Quand elle indique <b>Uploading Complete!</b>, la page se recharge d'elle-même. Sinon, actualisez la page.
- Pour n'en créer que quelques-uns, cochez-les dans la liste et appuyez sur <b>Create New (N)</b>, où N est le nombre coché.
- Pour en créer un, appuyez sur <b>Create new product</b> sur sa ligne.

<!-- screenshot: My Products avec le message jaune « products not synced yet » et le bouton Upload all as new product -->

Pour chaque produit, nous :

- Cherchons le produit dans le catalogue Allegro par son code-barres pour trouver sa catégorie. Si Allegro ne connaît pas le code-barres, Allegro suggère une catégorie à partir du nom du sous-rayon du produit.
- Proposons le produit au catalogue Allegro, ou utilisons le produit du catalogue qu'Allegro a déjà.
- Créons une offre <b>Buy Now</b> et la publions comme active immédiatement.
- Utilisons la grille tarifaire d'expédition <b>AW-EU-</b> et la politique de retour que nous avons créées lors de votre connexion.

Ce que contient l'offre :

- Le titre, coupé à 75 caractères. Allegro n'accepte pas de titres plus longs.
- Votre description. Allegro n'accepte que le texte brut, le gras et les paragraphes, donc nous retirons les autres mises en forme. Les sauts de ligne deviennent des espaces.
- Le texte est envoyé en anglais. Allegro le traduit pour les acheteurs dans ses propres langues.
- Votre prix, converti dans la devise de votre marché Allegro : PLN pour la Pologne, CZK pour la République tchèque, EUR pour la Slovaquie et HUF pour la Hongrie. Nous utilisons le taux de change actuel depuis la devise de votre compte dropshipping. Les prix en HUF sont arrondis au 5 HUF supérieur, car Allegro Hongrie n'accepte que ceux-ci.
- Notre stock, plafonné par <b>Max Quantity To Advertise</b> si vous l'avez réglé dans <b>Manage Sales Channel</b>, <b>Manage Stock</b>.
- Un délai d'expédition de 24 heures.

## Lier des offres que vous avez déjà sur Allegro

Si le produit est déjà sur Allegro comme votre propre offre, liez-le au lieu de créer une seconde offre. Lier ne change pas votre offre Allegro.

- <b>Match all with default product</b> dans le message jaune lie chaque produit dont l'identifiant externe (<b>sygnatura</b>) sur Allegro est identique à notre code produit. Les produits sans correspondance sont laissés de côté. La liaison s'exécute en arrière-plan, donc actualisez la page après un moment.
- Pour ne lier que certains produits, cochez-les et appuyez sur <b>Match (N)</b>.
- Pour un produit, appuyez sur <b>Match it with an existing product in your shop</b> sur sa ligne, choisissez l'offre et appuyez sur <b>Link ... to selected item on your platform</b>.

Pour faire correspondre, mettez d'abord notre code produit dans le champ d'identifiant externe de votre offre Allegro.

## Vérifier qu'un produit est en ligne

Chaque ligne a trois petites coches dans <b>Status</b> : <b>Has valid platform product id</b>, <b>Exist in platform</b> et <b>Platform status</b>. Trois coches vertes signifient que l'offre est sur Allegro et liée. Un cercle vert à côté indique que le dernier import s'est bien passé.

Pour voir vos offres dans Allegro, connectez-vous à <b>Moje Allegro</b> et ouvrez votre liste d'offres.

## Changer ou retirer des produits

- Vous ne pouvez pas modifier le titre, la description ou le prix d'une offre Allegro depuis <b>My Products</b>, et nous ne changeons jamais une offre après l'avoir créée. Modifiez-les dans Allegro.
- <b>Unlink (N)</b> arrête de lier les produits cochés à leurs offres. Les offres restent sur Allegro.
- <b>Unlink & Delete (N)</b>, ou la corbeille sur une ligne, retire le produit de <b>My Products</b>. Nous ne supprimons rien sur Allegro : l'offre y reste. Terminez-la dans Allegro si vous ne voulez plus la vendre.

## En cas de problème

Passez la souris sur le message rouge d'une ligne pour lire la réponse d'Allegro. Les messages les plus fréquents :

<b>You do not have any Complaints Terms.</b>
Créez des conditions de réclamation (<b>Warunki reklamacji</b>) dans vos paramètres de vente Allegro. Puis importez à nouveau.

<b>The user with an inactive or unverified account cannot create new product proposals.</b>
Allegro n'a pas terminé de vérifier votre compte. Terminez la vérification dans Allegro, puis importez à nouveau.

<b>No shipping price list set.</b>
La grille tarifaire d'expédition <b>AW-EU-</b> manque dans votre compte Allegro. Reconnectez le même compte Allegro depuis <b>Create Channels</b> : nous recréons la grille. Si le message reste, demandez-nous dans le chat de notre site.

<b>You cannot create a product without providing correct values for all the required parameters: [...]</b> ou <b>Missing mandatory parameters: ...</b>
La catégorie choisie par Allegro exige des détails que nous n'avons pas pour ce produit, par exemple une longueur ou un code-barres (EAN). Choisissez un autre produit, ou créez l'offre vous-même dans Allegro et liez-la avec <b>Match</b>.

<b>Allegro has no matching category for "..."</b>
Allegro n'a pas trouvé de catégorie pour le produit. Créez l'offre vous-même dans Allegro et liez-la avec <b>Match</b>, ou choisissez un autre produit.

<b>Unable to get the ... exchange rate.</b>
Nous n'avons pas pu convertir le prix dans votre devise Allegro à ce moment-là. Réessayez l'import plus tard.

<b>Upload all as new product is missing</b>
Le bouton, et <b>Add products</b>, se masquent un moment après que votre compte Allegro n'a pas répondu. Actualisez la page après un moment. S'il reste absent, vérifiez que le tableau de bord du canal a toujours trois coches vertes. Sinon, appuyez sur <b>Reconnect</b>.
