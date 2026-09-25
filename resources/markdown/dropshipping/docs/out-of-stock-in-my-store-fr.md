---
title: Pourquoi un produit apparaît en rupture de stock dans ma boutique
summary: Découvrez pourquoi votre boutique affiche un produit en rupture de stock alors qu'il est en stock chez nous, et corrigez cela sur Shopify, WooCommerce, eBay, TikTok Shop et Wix.
date: 2026-09-25
source_date: 2026-09-25
tags: stock, rupture de stock, inventaire, shopify, wix, woocommerce, ebay, tiktok, emplacement
category: troubleshooting
shops: awd, dssk, dse
---

<aside class="tldr">
Nous envoyons du stock à votre boutique uniquement pour les produits <b>liés</b> dans <b>Mes produits</b>, et seulement tant que votre canal est connecté. Les raisons habituelles d'une « rupture de stock » sont : le produit n'est pas lié, il est vraiment en rupture ou n'est plus en vente chez nous, les réglages de votre canal masquent le stock faible, ou (sur Shopify) le stock se trouve à un autre emplacement. Vérifiez ces points dans l'ordre ci-dessous, puis appuyez sur <b>Update Stock</b>.
</aside>

## Comment le stock arrive dans votre boutique

- Nous envoyons du stock uniquement pour les produits liés à une fiche de votre boutique : vert dans la colonne <b>Status</b> de <b>Mes produits</b>.
- Quand notre stock change, nous mettons à jour votre boutique nous-mêmes. Vous n'avez rien à faire.
- Nous envoyons 0 pour les produits qui ne sont plus en vente ou arrêtés, même s'il en reste quelques unités.
- Les réglages de votre canal peuvent réduire le nombre que nous envoyons. Voir l'étape 4.

## Vérifiez ces points, un par un

### 1. Le canal est-il connecté ?

Ouvrez <b>Channels</b> dans le menu, cliquez sur votre canal et ouvrez <b>Mes produits</b>. Si un encadré rouge indique <b>Your channel is not connected yet to the platform</b>, nous ne pouvons rien envoyer. Reconnectez d'abord le canal. Sur Shopify, assurez-vous d'avoir appuyé sur <b>Install</b> dans Shopify pour terminer la connexion.

### 2. Le produit est-il lié ?

Trouvez le produit dans <b>Mes produits</b>. Sur Shopify, le statut doit être la poignée de main verte (<b>Product connected to shopify</b>). Sur les autres plateformes, les trois coches doivent être vertes.

Si elle est rouge, la fiche de votre boutique n'est pas reconnue comme la nôtre, donc nous ne mettons jamais son stock à jour. Cela arrive souvent quand vous avez créé le produit vous-même, ou l'avez importé depuis une autre application. Liez-le avec <b>Match with this product</b>, ou associez tout en une fois avec <b>Match all with default product</b>. Voir [Utiliser Mes produits](/docs/my-products).

### 3. Est-il en stock chez nous ?

Regardez <b>Stocks</b> (sur Shopify, <b>Stock</b>) sur la ligne. Sauf sur Shopify, vous pouvez utiliser le filtre <b>Out of stock</b> pour lister tous les produits sans stock. Un dollar barré signifie <b>This product line is currently not for sale</b>, et une case barrée signifie qu'il est arrêté. Dans tous ces cas, votre boutique a raison d'afficher « rupture de stock ».

Pour être prévenu quand un produit revient, utilisez le bouton enveloppe sur le produit de notre site. Vos rappels se trouvent sous <b>Back In Stock Reminders</b> dans le menu.

### 4. Vérifiez les réglages de stock de votre canal

Sur la page du canal, appuyez sur <b>Manage Sales Channel</b> (ou <b>Edit</b>). Sous <b>Manage Stock</b> :

- <b>Stock Update</b> : quand c'est désactivé, nous arrêtons de mettre le stock à jour automatiquement. Laissez-le activé.
- <b>Stock Threshold</b> : quand notre stock descend à ce nombre ou en dessous, nous envoyons 0. Par exemple, avec un seuil de 10, un produit avec 8 unités apparaît en rupture de stock. Laissez le champ vide pour envoyer le stock réel.
- <b>Max Quantity To Advertise</b> : le maximum que nous affichons, même si nous en avons plus. Laissez vide pour ne pas plafonner.

<!-- screenshot : la section Manage Stock des réglages du canal avec Stock Update, Max Quantity To Advertise et Stock Threshold -->

### 5. Envoyez le stock maintenant

Dans <b>Mes produits</b>, appuyez sur <b>Update Stock</b> (Shopify, WooCommerce, eBay, TikTok Shop et Wix). Vous voyez <b>Stock update started</b>. Cela peut prendre quelques minutes. Ouvrez ensuite l'onglet <b>Logs</b> : les lignes de type <b>Update Stock</b> indiquent <b>Done</b> ou <b>Failed</b> avec la réponse de votre plateforme.

Si le message est <b>Nothing to update</b>, aucun de vos produits n'est encore lié. Revenez à l'étape 2.

## Shopify

Sur Shopify, notre stock se trouve dans notre propre emplacement de traitement des commandes, nommé <b>aiku-</b> suivi du code de notre boutique et du code de votre canal entre parenthèses, par exemple <b>aiku-awd (my-store)</b>.

1. Dans Shopify, ouvrez <b>Products</b> et le produit qui apparaît en rupture de stock.
2. Dans la section <b>Inventory</b>, vérifiez que notre emplacement figure dans la liste et a du stock.
3. Si le stock se trouve à un autre emplacement (par exemple votre propre adresse de boutique) avec 0, c'est le nombre que Shopify affiche pour cet emplacement. Notre stock n'est jamais que dans notre emplacement.

Si Shopify répond que le produit n'est pas stocké à notre emplacement, nous l'y ajoutons nous-mêmes, afin que la prochaine mise à jour de stock puisse passer. Si l'onglet <b>Logs</b> indique <b>No variant on Shopify matches this sku</b>, le SKU de la variante Shopify n'est pas notre code produit. Changez le SKU dans Shopify pour notre code, ou reliez le produit à nouveau avec <b>Connect with other product</b>.

## Wix

Nous n'envoyons jamais de stock pour un produit Wix qui n'est pas lié. Si Wix indique que tous vos produits sont en rupture de stock, ils ont probablement été ajoutés directement dans Wix ou n'ont pas été associés. Dans <b>Mes produits</b>, utilisez <b>Match all with default product</b> pour les lier par SKU, ou <b>Create new product</b> pour nous laisser les créer. Appuyez ensuite sur <b>Update Stock</b>.

## eBay

Quand nous envoyons 0, eBay affiche l'annonce en rupture de stock. Si votre compte eBay n'utilise pas l'option de rupture de stock d'eBay, eBay peut mettre fin à l'annonce à la place. Activez l'option dans vos préférences de vente eBay pour que les annonces restent et reviennent quand nous avons du stock.

## WooCommerce et TikTok Shop

Vérifiez l'onglet <b>Logs</b>. Sur WooCommerce, une mise à jour de stock <b>Failed</b> avec « 503 », « timed out » ou « The store answered with a web page instead of data » signifie que votre site ne nous a pas laissé entrer. Vérifiez que votre site est en ligne et que votre hébergement ou votre extension de sécurité ne nous bloque pas, puis appuyez à nouveau sur <b>Update Stock</b>.

## Quand quelque chose ne va pas

- **« Stock update failed. This channel is not connected to the platform, so stock cannot be updated. »** Reconnectez le canal, puis réessayez.
- **« Nothing to update ».** Aucun de vos produits n'est lié. Liez-les d'abord (étape 2).
- **Le stock est correct dans Mes produits mais faux dans ma boutique, et Logs affiche Done.** Votre boutique ajoute peut-être du stock depuis ses propres emplacements ou applications. Vérifiez qu'aucune autre application ou emplacement ne modifie le stock de ce produit.
- **Le produit est revenu en stock mais ma boutique affiche toujours 0.** Appuyez sur <b>Update Stock</b> et vérifiez l'onglet <b>Logs</b>. Si la mise à jour indique <b>Failed</b>, le message explique pourquoi.
