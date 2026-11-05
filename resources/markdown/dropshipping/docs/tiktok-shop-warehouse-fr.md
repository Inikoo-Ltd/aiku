---
title: Configurer votre entrepôt TikTok Shop
summary: Gardez votre propre entrepôt par défaut dans TikTok Shop Seller Center, n'ajoutez pas le nôtre et utilisez « Shipped by Seller », pour que votre stock et vos commandes fonctionnent.
date: 2026-11-02
source_date: 2026-11-02
tags: tiktok, entrepôt, adresse, shipped by seller, seller center
category: sales-channels
series: tiktok
order: 2
shops: awd, dssk, dse
---

<aside class="tldr">
Votre boutique TikTok a besoin d'un entrepôt par défaut avant que vous la connectiez. Nous envoyons votre stock à cet entrepôt, et sans lui la connexion ne se termine pas. Utilisez votre propre entrepôt, avec votre propre adresse : n'ajoutez pas le nôtre. C'est toujours nous qui préparons et envoyons vos commandes, avec un modèle d'expédition « Shipped by Seller ». Les commandes qui utilisent TikTok Shipping peuvent être annulées.
</aside>

## Pourquoi l'entrepôt compte

Quand vous connectez TikTok, nous lisons l'entrepôt par défaut de votre boutique TikTok. Chaque produit que vous importez reçoit son stock dans cet entrepôt, et nous le tenons à jour.

- Si votre boutique n'a pas d'entrepôt, la page du canal affiche <b>Your TikTok shop has no warehouse yet. Add a warehouse in TikTok Seller Center and set it as default, then choose your shop and press Save again.</b>
- Nous mémorisons l'entrepôt qui était par défaut au moment de la connexion. Ne le supprimez pas et ne le remplacez pas par un autre plus tard. Si vous devez le changer, demandez-nous d'abord dans le chat de notre site.

Configurez l'entrepôt avant d'appuyer sur <b>Connecter</b>. Si vous êtes déjà connecté, configurez-le maintenant, puis ouvrez votre canal, choisissez votre boutique et appuyez sur <b>Save</b>.

## Utilisez votre propre entrepôt, pas le nôtre

L'entrepôt dans TikTok est l'endroit où TikTok compte votre stock. Il ne décide pas qui envoie le colis : nous préparons et envoyons vos commandes avec nos transporteurs via « Shipped by Seller », quelle que soit l'adresse de votre entrepôt TikTok.

N'essayez pas d'ajouter notre entrepôt dans TikTok. TikTok ne propose des entrepôts que dans certains pays, et le nôtre n'en fait peut-être pas partie. Vous n'avez besoin d'aucune autorisation spéciale de TikTok ni de nous.

Les écrans ci-dessous se trouvent dans TikTok Shop Seller Center. TikTok peut changer leurs noms.

1. Connectez-vous à TikTok Shop Seller Center.
2. Ouvrez « Logistics » puis « Warehouses ».
3. Si vous avez déjà un entrepôt, gardez-le. Si vous n'en avez pas, appuyez sur « Add Warehouse » et saisissez l'adresse de votre propre entreprise.
4. Assurez-vous que cet entrepôt est bien l'entrepôt par défaut.

<!-- screenshot: TikTok Seller Center, Logistics > Warehouses, avec l'entrepôt par défaut -->

TikTok peut aussi demander un entrepôt de retour avant d'accepter des produits. Ajoutez-en un au même endroit.

## Choisir « Shipped by Seller »

Liez un modèle d'expédition « Shipped by Seller » à cet entrepôt. Nous envoyons le colis avec nos transporteurs et le suivi remonte vers TikTok. Les commandes qui utilisent TikTok Shipping, où TikTok collecte le colis ou vous le déposez dans un point relais, peuvent être annulées. Voir [Configurer un modèle d'expédition TikTok Shop](/docs/tiktok-shop-shipping-template).

## En cas de problème

<b>Your TikTok shop has no warehouse yet.</b> Ajoutez un entrepôt dans Seller Center, définissez-le par défaut, puis ouvrez votre canal, choisissez votre boutique et appuyez sur <b>Save</b>.

<b>No warehouse matches 0</b>, <b>Nonexistent warehouse ID(s): 0</b> ou <b>WarehouseId of Inventory[0] is invalid</b> lors de l'import de produits : nous n'avons pas d'entrepôt TikTok pour votre canal, ou celui que nous avions mémorisé n'est plus dans votre boutique. Vérifiez que votre boutique a un entrepôt par défaut dans Seller Center, puis allez dans <b>Chaînes</b>, appuyez sur <b>Ajouter un canal de vente</b>, <b>Connecter</b> sur la carte TikTok et <b>Auth Store</b>, avec le même compte TikTok. Nous relisons les entrepôts de votre boutique et prenons celui par défaut. Vous gardez le même canal et ses produits. Une fois notre application approuvée dans TikTok, vous pouvez fermer la fenêtre : inutile d'appuyer sur <b>Suivant</b>.

<b>the warehouse does not belong to this shop</b> lors de l'import de produits : l'entrepôt que nous avions mémorisé a été supprimé ou appartient à une autre boutique. Reconnectez-vous avec <b>Auth Store</b>, comme ci-dessus.

<b>requires a return warehouse</b> lors de l'import de produits : ajoutez un entrepôt de retour dans Seller Center, puis importez à nouveau.

<b>An order says we are unable to ship it because the shipping template is not "Shipped by Seller" and the handover method is DROP_OFF.</b> Cette commande utilise TikTok Shipping, et elle peut être annulée. Liez un modèle d'expédition « Shipped by Seller » à votre entrepôt, pour que vos prochaines commandes puissent être envoyées.
