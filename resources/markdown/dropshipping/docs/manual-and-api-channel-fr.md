---
title: Le canal Manuel/API
summary: Créez un canal Manuel/API pour vendre depuis votre propre site, une marketplace ou une application, passez des commandes à la main ou envoyez-les-nous via notre API.
date: 2026-09-25
source_date: 2026-09-25
tags: manuel, api, canal de vente, site propre, jeton api, intégration
category: sales-channels
series: manual
order: 1
shops: awd, dssk, dse
---

<aside class="tldr">
Utilisez un canal <b>Manuel/API</b> quand votre boutique n'est sur aucune des plateformes auxquelles nous nous connectons, ou quand vous voulez saisir les commandes vous-même. Allez dans <b>Chaînes</b>, appuyez sur <b>Ajouter un canal de vente</b>, puis sur <b>Create</b> sur la carte <b>Manual/API</b> et donnez-lui un nom. Vous ajoutez ensuite des produits à <b>My Products</b>, ajoutez vos acheteurs comme <b>Clients</b> et créez des commandes pour eux, à la main ou via l'API.
</aside>

## Quand utiliser un canal Manuel/API

Un canal Manuel/API n'est lié à aucune boutique. Rien n'est importé vers un site et aucune commande n'arrive d'elle-même. Utilisez-le quand :

- Vous vendez sur votre propre site, sur une marketplace à laquelle nous ne nous connectons pas, sur les réseaux sociaux ou par téléphone, et voulez nous envoyer chaque commande vous-même.
- Vous avez votre propre système ou développeur et voulez nous envoyer des commandes via notre API.

Si votre boutique est sur une plateforme affichée sur la page <b>Add Sales Channel</b>, comme Shopify, WooCommerce, eBay ou TikTok Shop, connectez plutôt cette plateforme. Les produits sont alors importés pour vous et les commandes arrivent d'elles-mêmes.

Vous pouvez avoir plusieurs canaux Manuel/API, par exemple un par site.

## Créer le canal

1. Ouvrez <b>Chaînes</b> dans le menu. Vous voyez la liste de vos <b>Sales Channels</b>.
2. Appuyez sur <b>Ajouter un canal de vente</b>. La page affiche <b>Select channel you want to create</b>.
3. Sur la carte <b>Manual/API</b>, appuyez sur <b>Create</b>.
4. Une fenêtre <b>Create platform manual</b> s'ouvre. Tapez un nom pour le canal, par exemple le nom de votre site. Le nom peut compter jusqu'à 28 caractères.
5. Appuyez sur <b>Create</b>.

<!-- screenshot: la page Add Sales Channel avec la carte Manual/API et son bouton Create, et la fenêtre Create platform manual -->

Vous voyez le message <b>Your Manual store has been created.</b> et la page du canal s'ouvre.

Chacun de vos canaux a besoin de son propre nom. Si le nom est déjà utilisé par un autre de vos canaux, la fenêtre affiche une erreur. Choisissez un nom différent.

## La page de votre canal

La page du canal a le nom de votre canal comme titre et le titre <b>Manual/API order management</b>. Elle affiche trois encadrés, chacun avec un lien <b>View all</b> :

- <b>Orders</b> : les commandes que vous avez passées dans ce canal.
- <b>Clients</b> : les personnes à qui vous envoyez des commandes.
- <b>Products</b> : les produits dans votre liste <b>My Products</b>.

Dans le menu, sous le nom du canal, vous trouvez :

- <b>Baskets</b> : les commandes que vous avez commencées et pas encore payées.
- <b>My Products</b> : les produits que vous vendez dans ce canal. Voir [Gérer les produits sur le canal Manuel/API](/docs/managing-products-on-the-manual-channel).
- <b>Clients</b> : vos acheteurs. Voir [Gérer les clients](/docs/managing-clients).
- <b>Orders</b> : vos commandes passées. Voir [Passer des commandes manuellement](/docs/placing-orders-manually).
- <b>API</b> : jetons et documentation pour connecter votre propre système.

La façon habituelle de procéder :

1. Ajoutez les produits que vous vendez à <b>My Products</b> avec <b>Add products</b>. C'est nécessaire pour l'API. Pour les commandes que vous saisissez vous-même, c'est facultatif : le panier vous permet de choisir n'importe quel produit que nous vendons.
2. Quand vous recevez une commande, ouvrez <b>Clients</b>, trouvez votre acheteur ou ajoutez-le.
3. Sur la page du client, appuyez sur <b>Create Order</b>, ajoutez les produits et les quantités, et payez.

## Changer le nom ou fermer le canal

Pour renommer le canal, appuyez sur <b>Edit</b> sur la page du canal et changez <b>Store name</b>.

Pour fermer un canal, allez dans <b>Chaînes</b> et appuyez sur le bouton de fermeture dans la colonne <b>Action</b> (infobulle <b>Close channel</b>). La fenêtre demande <b>Are you sure you want to close this channel?</b> et avertit <b>This operation is irreversible.</b> Un canal fermé quitte le menu. Vos commandes et factures passées sont conservées.

## Connecter votre propre système avec l'API

L'API permet à votre site ou application de faire d'elle-même ce que vous faites sur les pages du canal : lire notre catalogue de produits avec les prix en direct, ajouter des produits à <b>My Products</b>, créer et changer des clients, créer des commandes, y ajouter des produits, les soumettre et les suivre. Vous pouvez aussi télécharger votre liste <b>My Products</b> en CSV ou en flux JSON pour charger des produits dans votre propre site.

Ouvrez <b>API</b> sous votre canal. La page comporte ces onglets :

- <b>Overview</b> : comment se connecter, l'adresse de base de l'API et le bouton <b>API documentation</b>. La documentation liste chaque point de terminaison avec des exemples.
- <b>API tokens</b> : les jetons de ce canal.
- <b>API calls</b> : les requêtes faites par votre système.
- <b>History</b> : les changements effectués sur votre compte.

### Obtenir un jeton

1. Appuyez sur <b>Generate API token</b>.
2. Si le jeton ne sert qu'à lire des données, cochez <b>Read only (cannot create, change or submit orders)</b>.
3. Appuyez sur <b>Click to Generate</b>.
4. Copiez le jeton avec l'icône de copie et gardez-le en lieu sûr. La fenêtre indique <b>Put this token in a safe place, you won't be able to see it again.</b> Le libellé court dans la liste des jetons n'est qu'un nom, pas le jeton.

Envoyez le jeton avec chaque requête dans l'en-tête <b>Authorization: Bearer</b> suivi de votre jeton. Chaque jeton appartient à un seul canal : les produits, clients et commandes que votre système crée vont vers ce canal. Pour empêcher un jeton de fonctionner, supprimez-le dans l'onglet <b>API tokens</b>.

<!-- screenshot: l'onglet Overview de la page API avec les boutons API documentation et Generate API token -->

### Tester d'abord en staging

L'onglet <b>Overview</b> comporte aussi <b>Open staging mirror</b>. Le staging est une copie distincte du site où vous pouvez tester sans vraies commandes ni paiements. Connectez-vous avec le même e-mail et mot de passe. Le staging est réinitialisé régulièrement avec une copie fraîche, ce qui efface ce que vous y avez créé. Les jetons du site réel ne fonctionnent pas en staging : générez un jeton séparé sur le staging, et un nouveau après chaque réinitialisation. L'adresse de base du staging est indiquée dans l'onglet <b>Overview</b>.

### Comment les commandes API sont payées

Quand votre système soumet une commande, nous la payons d'abord depuis le solde de votre compte, puis depuis vos cartes enregistrées. Ajoutez une carte avant de commencer. Une fois que vous avez un jeton, le menu affiche <b>Saved Cards</b>. Tant qu'aucune carte n'est enregistrée, la page API affiche <b>You have no cards saved yet.</b> avec un bouton <b>Add card</b>.

Si ni votre solde ni vos cartes ne couvrent la commande, elle est marquée <b>Unpaid</b> et ne part pas vers l'entrepôt. Ajoutez de l'argent à votre solde avec <b>Top Up</b>, ouvrez la commande et appuyez sur <b>Pay … with balance</b>. Le bouton s'affiche quand votre solde couvre le montant dû.

## En cas de problème

- <b>Le nom est déjà pris quand je crée le canal.</b> Un autre de vos canaux ouverts porte ce nom. Tapez un nom différent. Le nom d'un canal fermé peut être réutilisé.
- <b>Mes commandes n'arrivent pas d'elles-mêmes.</b> Un canal Manuel/API ne récupère jamais de commandes depuis un site. Créez-les sur la page du client, ou envoyez-les depuis votre système via l'API. Si vous vendez sur une plateforme affichée sur la page <b>Add Sales Channel</b>, connectez cette plateforme comme son propre canal.
- <b>Mes produits ne sont pas sur mon site.</b> Nous n'importons rien depuis un canal Manuel/API. Chargez-les vous-même dans votre site, avec le téléchargement CSV sur <b>My Products</b> ou via l'API.
- <b>J'ai perdu mon jeton API.</b> Il ne peut pas être réaffiché. Générez un nouveau jeton, mettez-le dans votre système et supprimez l'ancien.
- <b>L'API répond que je ne peux pas créer ou changer de commandes.</b> Le jeton est en lecture seule. Générez un jeton sans <b>Read only</b> coché.
- <b>L'API refuse mes requêtes pendant un moment.</b> Chaque jeton peut faire jusqu'à 120 requêtes par minute. Ralentissez votre système et réessayez après une minute.
- <b>L'API indique « This order has no products yet ».</b> Ajoutez au moins un produit à la commande avant de la soumettre.
- <b>L'API indique « Unable to find related portfolio item ».</b> Via l'API, vous ajoutez un produit à une commande par son élément <b>My Products</b>, pas par le produit lui-même. Ajoutez d'abord le produit à <b>My Products</b> et utilisez l'identifiant de cet élément.
- <b>L'API indique qu'une autre transaction avec le même produit existe déjà.</b> Le produit est déjà sur la commande. Changez la quantité de cette ligne plutôt que de l'ajouter à nouveau.
- <b>L'API indique que la commande « is already in the 'submitted' state and cannot be updated ».</b> Les commandes soumises ne peuvent pas être changées ou supprimées via l'API. Demandez-nous dans le chat de notre site si la commande doit changer.
- <b>Ma commande API affiche Unpaid.</b> Votre solde et vos cartes enregistrées ne l'ont pas couverte. Rechargez votre solde, ouvrez la commande et appuyez sur <b>Pay … with balance</b>, et vérifiez que votre carte enregistrée est toujours valide.
