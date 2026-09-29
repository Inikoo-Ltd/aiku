---
title: Utiliser My Products
summary: Lisez la liste My Products d'un canal, envoyez des produits vers votre boutique ou associez-les à des fiches déjà existantes, gardez le stock à jour et lisez les erreurs de téléversement.
date: 2026-09-25
source_date: 2026-09-25
tags: produits, my products, portfolio, téléversement, association, sku, stock, journaux
category: products
shops: awd, dssk, dse
---

<aside class="tldr">
<b>My Products</b> est la liste des produits que vous vendez dans un canal. Chaque canal a sa propre liste. Depuis là, vous envoyez chaque produit vers votre boutique avec <b>Create new product</b>, ou le liez à une fiche que vous avez déjà avec <b>Match</b>. Quand le produit est lié, nous maintenons son stock à jour, et l'onglet <b>Logs</b> affiche chaque téléversement, association et mise à jour de stock avec la réponse de votre plateforme.
</aside>

## Ouvrir My Products

1. Ouvrez <b>Channels</b> dans le menu. Chaque canal s'affiche dessous avec son logo.
2. Cliquez sur le canal, puis sur <b>My Products</b>. Le chiffre à côté indique combien de produits sont dans la liste.

La page comporte trois onglets : <b>My Products</b>, <b>My Bundles</b> (voir [Créer des lots](/docs/bundles)) et <b>Logs</b> (l'icône horloge à droite).

Si un encadré rouge indique <b>Your channel is not connected yet to the platform</b>, la connexion à votre boutique est rompue. Rien ne peut être téléversé et aucun stock n'est envoyé tant que vous ne vous reconnectez pas. Suivez le guide de connexion de votre plateforme.

<!-- screenshot: la page My Products d'un canal Shopify avec les onglets, les boutons en haut et quelques lignes -->

## Ce que montre chaque ligne

- **Product** : notre code produit (cliquez dessus pour ouvrir le produit), le nom, <b>Stocks</b>, <b>Weight</b> (poids du produit / poids avec emballage), <b>Dimension</b>, notre <b>Price</b> (ce que vous nous payez) et le <b>RRP</b>. Si votre canal affiche les prix avec TVA, vous voyez <b>Price (include VAT)</b> et <b>RRP (include VAT)</b> (pas sur Shopify).
- **Status** : sur Shopify, une poignée de main verte signifie <b>Product connected to shopify</b> et une rouge signifie <b>Not connected</b>. Sur les autres plateformes, il y a trois coches : <b>Has valid platform product id</b>, <b>Exist in platform</b> et <b>Platform status</b>. Trois coches vertes signifient que le produit est publié et lié.
- **Message** : une coche verte quand tout va bien. Un message rouge quand votre plateforme a refusé le produit (sur Shopify, regardez plutôt l'onglet <b>Logs</b>). Cliquez dessus pour voir <b>Answer of ...</b> avec le texte complet de votre plateforme et, souvent, ce qu'il faut faire. Une case barrée signifie <b>This product line has been discontinued. Please remove this item</b>. Un dollar barré signifie <b>This product line is currently not for sale</b>.
- **La colonne de votre plateforme** (par exemple <b>Shopify product</b> ou <b>eBay product</b>) : à quelle fiche de votre boutique ce produit est lié, ou les boutons pour le lier.

## Envoyer un produit vers votre boutique

Pour un produit pas encore lié, vous avez deux choix.

**Créer une nouvelle fiche.** Appuyez sur <b>Create new product</b>. Nous créons le produit dans votre boutique avec notre nom, notre description, nos images, le prix, le SKU et le stock.

**Lier à une fiche que vous avez déjà.** Utilisez ceci quand vous vendez déjà le produit et ne voulez pas d'un doublon.
- Si nous avons trouvé une fiche dans votre boutique avec le même SKU, elle s'affiche sur la ligne. Appuyez sur <b>Match with this product</b>.
- Pour en choisir une différente, appuyez sur <b>Choose another product from your shop</b>, ou <b>Match it with an existing product in your shop</b> quand nous n'avons rien trouvé. Cherchez dans votre boutique, choisissez l'article et appuyez sur <b>Link ... to selected item on your platform</b>.
- Pour changer un produit déjà lié, appuyez sur <b>Change linked listing</b> (sur Shopify : <b>Connect with other product</b>).

## Traiter plusieurs produits à la fois

Quand certains produits ne sont pas encore liés, une barre jaune indique <b>You have ... products not synced yet</b>. Elle comporte deux boutons :

- <b>Upload all as new product</b> : les crée tous dans votre boutique. Non affiché sur eBay.
- <b>Match all with default product</b> : lie chaque produit à la fiche de votre boutique portant le même SKU. Nous comparons le SKU de votre boutique avec le SKU du produit dans <b>My Products</b> et avec notre code produit, sans tenir compte des majuscules ou minuscules. Les produits sans fiche de ce SKU restent inchangés.

Pour traiter seulement certains produits, cochez-les dans la liste. Ces boutons apparaissent :

- <b>Create New (...)</b> : crée les produits cochés dans votre boutique.
- <b>Match (...)</b> : lie les produits cochés par SKU.
- <b>Unlink (...)</b> et <b>Unlink & Delete (...)</b> : voir [Supprimer des produits](/docs/removing-products).
- <b>Edit Price (...)</b> : définit votre prix de vente pour les produits cochés sur eBay, Shopify, WooCommerce et Wix, en pourcentage ou en montant au-dessus ou en dessous du RRP. Non affiché quand votre canal est configuré pour garder ses propres prix.

Les grosses tâches s'exécutent en arrière-plan. Une fenêtre de progression indique combien sont terminées, et la page se recharge d'elle-même.

<!-- screenshot: la barre jaune "products not synced yet" avec Upload all as new product et Match all with default product -->

## Trouver des produits dans la liste

Utilisez la zone de recherche, ou les boutons de filtre : <b>Only For Sale</b>, <b>Not For Sale</b>, <b>Discontinued</b> et <b>Out of stock</b>. Sur Shopify, les filtres sont dans le menu <b>Filter</b> : <b>Only For Sale</b>, <b>Not For Sale</b>, <b>Discontinued</b>, <b>Connected to Shopify</b> et <b>Not Connected</b>. Il n'y a pas de filtre de rupture de stock sur Shopify.

## Stock

Nous envoyons le stock seulement pour les produits liés (statut vert). Vous n'avez rien à faire : quand notre stock change, nous mettons à jour votre boutique.

Pour pousser le stock maintenant, appuyez sur <b>Update Stock</b> en haut de la page. Cela envoie le stock actuel des produits de ce canal. Si aucun de vos produits n'est encore lié, cela indique <b>Nothing to update</b>. Le bouton est présent sur les canaux Shopify, WooCommerce, eBay, TikTok Shop et Wix, pas sur Allegro ni sur les canaux manuels.

Vous pouvez plafonner ou masquer le stock dans les paramètres du canal. Voir [Pourquoi un produit affiche une rupture de stock dans ma boutique](/docs/out-of-stock-in-my-store).

## Autres boutons

- <b>Add products</b>, le bouton de téléversement et <b>Clone portfolio from channel:</b> : ajouter des produits. Voir [Trouver et ajouter des produits](/docs/sourcing-products).
- <b>CSV</b>, <b>⋮</b> (<b>Other Export Options</b>) et <b>Images</b> : téléchargez vos données produits et photos. Voir [Exporter les données et images produits](/docs/exporting-product-data).
- <b>Publish ... drafts</b> (eBay uniquement) : publie les fiches téléversées vers eBay en tant que brouillons.
- <b>Update all dimensions</b> (Shopify uniquement) : envoie nos dimensions actuelles à tous vos produits Shopify.

## L'onglet Logs

L'onglet <b>Logs</b> liste chaque téléversement, association et mise à jour de stock pour ce canal : <b>Product Code</b>, <b>Type</b> (<b>upload</b>, <b>match</b> ou <b>update-stock</b>), <b>Platform</b>, <b>Status</b> (<b>Done</b>, <b>In progress</b> ou <b>Failed</b>), la <b>Response</b> de votre plateforme et la <b>Date</b>. Regardez ici en premier quand un produit ou son stock n'est pas arrivé.

## En cas de problème

Le message rouge sur la ligne, et la <b>Response</b> dans <b>Logs</b>, est la réponse de votre plateforme. Les plus courantes :

- **Throttled / too many calls / request timeout / internal error.** Votre plateforme nous a demandé de ralentir, ou n'a pas répondu à temps. Rien ne cloche avec le produit. Réessayez dans quelques minutes.
- **La boutique a répondu avec une page web au lieu de données, ou a renvoyé 503, un délai dépassé ou une réponse vide** (WooCommerce). Votre propre site est hors ligne, en maintenance, ou son extension de sécurité ou son hébergeur nous bloque. Vérifiez que votre site est en ligne. Demandez à votre hébergeur d'autoriser notre connexion, puis réessayez.
- **A product with this SKU already exists in your store / Invalid or duplicated SKU / already present in the lookup table** (WooCommerce). Vous avez déjà un produit avec ce SKU. Utilisez <b>Match</b> au lieu de <b>Create new product</b>. Si l'ancien produit est dans la corbeille WooCommerce, videz-la d'abord.
- **Invalid or duplicated GTIN** (WooCommerce). Un autre produit de votre boutique utilise déjà ce code-barres. Retirez le code-barres de l'autre produit dans WooCommerce, ou associez-vous à lui.
- **Cannot list more products: your Shop probation tier allows at most 100 total product listings** (TikTok). C'est une limite TikTok pour les nouvelles boutiques, pas un problème avec le produit. Retirez des fiches dont vous n'avez pas besoin, ou demandez à TikTok de relever votre palier.
- **product_weight received 0 / weight cannot be zero** (TikTok). TikTok a besoin d'un poids. Vous ne pouvez pas modifier vous-même notre poids produit : contactez-nous via le chat de notre site, avec le code produit.
- **Image must be at least 300:300** (TikTok). Une de nos images est trop petite pour TikTok. Contactez-nous via le chat de notre site, avec le code produit.
- **Price out of range / incorrect price** (TikTok). TikTok fixe la fourchette de prix que votre boutique peut utiliser. Vérifiez la fourchette dans le TikTok Shop Seller Center. Si le prix que nous envoyons est en dehors, contactez-nous via le chat de notre site, avec le code produit.
- **Category qualification / category is restricted** (TikTok). Demandez la catégorie dans le Qualification Center du TikTok Shop Seller Center, puis téléversez à nouveau.
- **Requires an active seller account** (TikTok) ou **create a seller account** (eBay). Terminez d'abord votre compte vendeur sur la plateforme.
- **The listing would cause you to exceed the amount you can list this month** (eBay). Vous avez atteint votre limite de vente eBay. Demandez à eBay de la relever, ou attendez le mois prochain.
- **Invalid data in the associated fulfilment policy** (eBay). Votre politique d'expédition (fulfilment) eBay a un problème. Corrigez-la dans eBay, puis vérifiez les politiques choisies dans les paramètres de votre canal.
- **Item specific Type / Brand missing, or custom values for Size no longer supported** (eBay). eBay demande des détails supplémentaires pour cette catégorie. Voir [Gérer les produits sur eBay](/docs/managing-products-on-ebay).
- **Not allowed to revise an ended item** (eBay). La fiche s'est terminée sur eBay. Déliez le produit et recréez-le.
- **Overseas Warehouse Block Policy** (eBay). Si votre compte est enregistré dans certains pays, un <b>Important Notice</b> rouge s'affiche en haut. eBay peut bloquer les fiches stockées à l'étranger. Contactez le support eBay pour demander une approbation.
- **This product line has been discontinued.** Nous ne le vendons plus. Retirez-le de votre liste et de votre boutique.
