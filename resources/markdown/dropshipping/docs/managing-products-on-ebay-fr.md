---
title: Gérer les produits et commandes sur eBay
summary: Listez nos produits sur eBay ou liez-les à des annonces que vous avez déjà, contrôlez les prix et le stock, et comprenez comment les commandes eBay nous parviennent et sont marquées comme expédiées.
date: 2026-09-25
source_date: 2026-09-25
tags: ebay, produits, annonces, correspondance, sku, prix, stock, commandes, erreurs
category: products
series: ebay
order: 2
shops: awd, dssk, dse
---

<aside class="tldr">
Ajoutez des produits à votre canal eBay, puis ouvrez <b>My Products</b> et appuyez sur <b>Create new product</b> pour les lister sur eBay, ou <b>Match with this product</b> pour lier une annonce que vous avez déjà avec le même SKU. Nous tenons le stock et, si vous le souhaitez, les prix à jour sur eBay. Quand un acheteur commande un produit lié, la commande nous parvient, est payée depuis votre solde, et nous envoyons le suivi à eBay quand elle est expédiée.
</aside>

## Ajouter des produits à votre canal

Ouvrez votre canal eBay et allez dans <b>My Products</b>. Appuyez sur <b>Add products</b> et choisissez les produits que vous voulez vendre. Ils apparaissent dans la liste, mais ils ne sont pas encore sur eBay.

En haut de la liste, vous voyez « You have … products not synced yet » tant que certains produits ne sont pas liés à une annonce eBay.

## Lister des produits sur eBay

Pour un produit, appuyez sur <b>Create new product</b> sur sa ligne. Pour plusieurs, cochez-les et appuyez sur <b>Create New (…)</b>. Une fenêtre affiche la progression de l'import. Vous pouvez la fermer ; l'import continue.

Ce que nous envoyons à eBay :

- Le nom du produit comme titre. eBay autorise 80 caractères, donc les noms plus longs sont coupés.
- La description, les images, le SKU, le poids et la taille.
- Une catégorie que eBay suggère pour le produit, et les caractéristiques d'article que cette catégorie exige, remplies à partir des données du produit.
- Le prix issu de votre politique de tarification et le stock que nous avons.

Si <b>Upload as draft</b> est activé dans <b>Manage Sales Channel</b>, le produit est créé sur eBay mais non publié. Sa ligne affiche « Draft: uploaded to eBay but not published yet » et un bouton <b>Publish on eBay</b>. Pour publier tous les brouillons à la fois, appuyez sur <b>Publish … drafts</b> en haut de la liste.

Une coche verte dans la colonne de statut signifie que le produit est en ligne sur eBay et lié.

<!-- screenshot: My Products sur un canal eBay, une ligne avec Create new product et une avec la coche verte -->

## Lier des annonces que vous avez déjà (correspondance par SKU)

Si vous vendez déjà nos produits sur eBay, liez-les au lieu de créer une seconde annonce.

- Quand une annonce eBay a le même SKU que le produit, sa ligne affiche cette annonce. Appuyez sur <b>Match with this product</b>.
- Pour lier une autre annonce, appuyez sur <b>Choose another product from your shop</b> ou <b>Match it with an existing product in your shop</b>, cherchez vos annonces eBay et appuyez sur <b>Link … to selected item on your platform</b>.
- Pour en lier plusieurs à la fois, cochez-les et appuyez sur <b>Match (…)</b>, ou appuyez sur <b>Match all with default product</b> dans le message « not synced yet ». Les deux correspondent par SKU.
- Sur un produit lié, <b>Change linked listing</b> le lie à une autre annonce eBay.

Si la correspondance échoue, la ligne indique « Your product is not in the listing yet » : nous n'avons pas trouvé d'annonce eBay publiée pour ce produit. La correspondance ne trouve que les annonces qu'eBay conserve dans son système d'inventaire avec un SKU, par exemple les annonces créées par nous ou par un autre outil de listing. Une annonce saisie à la main sur eBay peut ne pas être trouvée. Utilisez alors <b>Create new product</b>, et terminez l'ancienne annonce sur eBay.

## Prix

Votre <b>Pricing Policy</b> dans <b>Manage Sales Channel</b> fixe le prix eBay de chaque produit à partir de son PVC en direct :

- <b>± % over live RRP</b> ou <b>± £ over live RRP</b> (€ sur les boutiques Europe et España) : nous fixons le prix et le suivons quand le PVC change. Quand vous enregistrez une nouvelle règle, on vous demande <b>Reprice every product?</b>. Appuyez sur <b>Save and reprice</b> pour mettre à jour tous les prix sur eBay. Les produits pour lesquels vous fixez votre propre prix ne sont pas touchés, sauf si vous cochez aussi la case pour les réinitialiser.
- <b>Do not follow RRP</b> : vous fixez vous-même les prix sur eBay. Nous ne les envoyons ni ne les écrasons jamais.

Pour donner leur propre prix à certains produits, cochez-les et appuyez sur <b>Edit Price (…)</b>. Pour changer le titre, la description ou le prix d'un produit, appuyez sur le bouton d'édition sur sa ligne. La fenêtre <b>Edit Product</b> comporte <b>Title</b>, <b>Price Mapping</b> et <b>Description</b>. Appuyez sur <b>Save & Publish</b> pour envoyer les changements à eBay tout de suite, ou sur <b>Save as Draft</b>. Le prix doit rester supérieur à zéro.

## Stock

Avec <b>Stock Update</b> activé, nous envoyons notre stock à eBay automatiquement. Dans <b>Manage Sales Channel</b>, vous pouvez régler :

- <b>Max Quantity To Advertise</b> : la quantité maximale affichée sur eBay, même si nous en avons plus.
- <b>Stock Threshold</b> : quand notre stock descend à ce nombre, eBay affiche le produit en rupture.

Quand un produit est en rupture, non en vente ou arrêté, nous envoyons une quantité de 0. Le bouton <b>Update Stock</b> en haut de <b>My Products</b> envoie le stock actuel de tous les produits du canal immédiatement.

<b>Astuce :</b> eBay peut terminer une annonce dont la quantité atteint 0. Pour garder l'annonce et simplement la masquer jusqu'au retour du stock, activez l'option de rupture de stock dans vos préférences de vente eBay.

## Retirer des produits

- Le bouton corbeille sur une ligne retire le produit de votre canal et termine son annonce eBay.
- <b>Unlink (…)</b> garde le produit dans votre liste et votre annonce sur eBay, mais arrête de les lier. Nous ne mettons plus à jour cette annonce, et ses commandes ne nous parviennent plus.
- <b>Unlink & Delete (…)</b> retire les produits sélectionnés de votre liste. Si une annonce est encore en ligne sur eBay ensuite, terminez-la sur eBay.

## Télécharger les données et images des produits

Dans <b>My Products</b>, ouvrez les options d'export. Vous pouvez exporter un CSV de vos produits avec les colonnes de votre choix, et appuyer sur <b>Download images</b> pour obtenir les images des produits.

## Comment les commandes eBay nous parviennent

- Nous vérifions régulièrement votre compte eBay pour les nouvelles commandes non expédiées et non annulées. Pour vérifier tout de suite, appuyez sur <b>Fetch orders</b> sur la page du canal.
- Seuls les produits liés dans <b>My Products</b> sont importés. Les autres articles de la même commande eBay sont laissés de côté, et vous les envoyez vous-même. Une commande sans aucun produit lié n'est pas importée.
- La commande est payée d'abord depuis votre solde, puis depuis vos cartes enregistrées. Si le paiement échoue, nous vous envoyons un e-mail et la commande attend d'être payée.
- Vous voyez les commandes sur la page <b>Orders</b> du canal.
- Quand nous expédions la commande, nous envoyons le numéro de suivi et le transporteur à eBay, et eBay la marque comme expédiée.

Un retour ou une annulation faite par l'acheteur sur eBay ne change pas automatiquement la commande chez nous.

- Il n'y a pas de bouton d'annulation. Pour annuler, demandez-nous dans le chat de notre site. Cela n'est possible qu'avant l'expédition de la commande. Une fois qu'elle est préparée, il peut être trop tard.
- Votre acheteur ne doit rien renvoyer avant que le retour ait été convenu avec nous dans le chat de notre site.
- Les remboursements vont sur votre solde.

## Factures et preuve d'approvisionnement

Nos factures sont établies à votre nom, pas à celui de votre acheteur. Vous les trouvez sous <b>Invoices</b> une fois la commande expédiée. Si eBay demande une preuve d'approvisionnement, vous pouvez leur montrer ces factures.

Vous facturez vous-même votre acheteur. Nous ne facturons jamais votre acheteur.

Si eBay demande une preuve que vous êtes autorisé à revendre nos produits, demandez-nous dans le chat de notre site une lettre d'autorisation.

## En cas de problème

La raison d'un échec d'import s'affiche sur la ligne du produit, et l'historique complet se trouve dans l'onglet <b>Logs</b> (l'icône horloge) de <b>My Products</b>.

- <b>« This listing would cause you to exceed the amount you can list … this month »</b> ou <b>« … the number of items you can list »</b> : ce sont vos limites de vente eBay. Importez moins de produits ou des produits moins chers, demandez à eBay d'augmenter vos limites, ou attendez le mois suivant. Seul eBay peut les changer.
- <b>« invalid data in the associated fulfilment policy … add at least one valid postage service »</b> : votre politique d'affranchissement sur eBay n'a aucun service d'affranchissement valide. Ajoutez-en un à cette politique sur eBay, puis importez à nouveau.
- <b>« eBay will not list anything until your seller account is finished »</b> ou <b>eBay has not finished setting up your seller account</b> : connectez-vous à eBay, terminez votre inscription vendeur, puis importez à nouveau.
- <b>« not allowed to revise an ended item »</b> : l'annonce s'est terminée sur eBay. Le produit s'affiche à nouveau comme non lié. Appuyez sur <b>Create new product</b> pour le lister à nouveau.
- <b>« This Offer is not available »</b> : l'annonce n'existe plus sur eBay. Appuyez sur <b>Create new product</b> pour le lister à nouveau.
- <b>Mes annonces eBay se sont toutes terminées d'un coup</b> : les annonces se terminent sur eBay quand la quantité atteint 0 sans l'option de rupture de stock, quand vous les supprimez sur eBay, ou quand eBay les retire. Activez l'option de rupture de stock, puis appuyez sur <b>Create new product</b> sur les produits que vous voulez récupérer.
- <b>« The title or description may contain improper words, or the listing or seller may be in violation of eBay policy »</b> : eBay a refusé l'annonce selon ses propres règles. Lisez les messages d'eBay dans votre compte eBay. Seul eBay peut examiner cela.
- <b>« The item specific Brand is missing »</b> (ou Type, Item Length, Item Width), ou <b>« custom values for Size are no longer supported »</b> : la catégorie eBay a besoin d'une valeur que nous n'avons pas pu remplir à partir du produit. Demandez-nous dans le chat de notre site, avec le code du produit.
- <b>Listings blocked under the Overseas Warehouse Block Policy</b> : eBay bloque les annonces de marchandises stockées dans un autre pays pour les vendeurs enregistrés dans certains pays. Contactez le support client eBay pour demander une approbation.
- <b>« A system error has occurred »</b>, <b>« Unable to process your request »</b>, <b>« Too many requests »</b>, ou un délai dépassé : eBay a eu un problème de son côté. Rien ne va pas avec votre produit. Réessayez plus tard.
- <b>Les produits ne se synchronisent pas avec eBay</b> : vérifiez que le canal est connecté (pas de bouton <b>Reconnect</b>), que <b>Stock Update</b> est activé, et que le produit affiche la coche verte. Un produit sans coche n'est pas lié.
- <b>Une commande eBay ne nous est pas parvenue</b> : vérifiez que ses produits sont liés dans <b>My Products</b>, puis appuyez sur <b>Fetch orders</b>.
