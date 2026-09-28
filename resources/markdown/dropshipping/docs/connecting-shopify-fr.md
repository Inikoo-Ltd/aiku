---
title: Connecter votre boutique Shopify
summary: Liez votre boutique Shopify à votre compte dropshipping avec son nom myshopify.com, installez notre application dans Shopify, et corrigez une boutique qui indique encore qu'elle n'est pas connectée.
date: 2026-09-25
source_date: 2026-09-25
tags: shopify, canal de vente, connecter, installer, myshopify
category: sales-channels
series: shopify
order: 1
shops: awd, dssk, dse
---

<aside class="tldr">
Allez dans <b>Chaînes</b>, appuyez sur <b>Ajouter un canal de vente</b>, puis sur <b>Connecter</b> sur la carte Shopify. Tapez le nom <b>myshopify.com</b> de votre boutique, pas votre propre domaine, et appuyez sur <b>Connecter</b>. Shopify s'ouvre dans un nouvel onglet : appuyez sur <b>Install</b> là-bas. La connexion n'est terminée que lorsque l'application est installée dans Shopify.
</aside>

## Avant de commencer

- Vous avez besoin du nom <b>myshopify.com</b> de votre boutique. Shopify vous l'a donné à la création de la boutique, par exemple <b>mystore.myshopify.com</b> ou un code comme <b>ab12cd-3e.myshopify.com</b>. Votre propre domaine, comme <b>www.mystore.com</b>, ne fonctionne pas.
- Pour le trouver, ouvrez votre administration Shopify et allez dans <b>Settings</b>, <b>Domains</b>. Vous pouvez aussi regarder la barre d'adresse de votre administration Shopify : dans <b>admin.shopify.com/store/ab12cd-3e</b>, le nom est <b>ab12cd-3e</b>.
- Connectez-vous à votre administration Shopify dans le même navigateur, en tant que propriétaire de la boutique ou membre du personnel pouvant installer des applications.
- Shopify est disponible sur tous nos sites de dropshipping. Connectez-le depuis le site où vous avez votre compte dropshipping.

## Connecter votre boutique

1. Ouvrez <b>Chaînes</b> dans le menu. La page <b>Sales Channels</b> liste les canaux que vous avez déjà.
2. Appuyez sur <b>Ajouter un canal de vente</b>.
3. Trouvez la carte <b>Shopify</b> et appuyez sur <b>Connecter</b>. Une fenêtre s'ouvre : <b>Please enter your Shopify unique domain name</b>.
4. Tapez le nom de votre boutique dans le champ. La fin, <b>.myshopify.com</b>, est déjà écrite pour vous. Vous pouvez aussi coller l'adresse complète <b>xxx.myshopify.com</b> ou l'adresse <b>admin.shopify.com/store/...</b> : nous ne gardons que le nom de la boutique.
5. Appuyez sur <b>Connecter</b>.
6. Shopify s'ouvre dans un nouvel onglet et vous demande d'installer notre application. Appuyez sur <b>Install</b>. Si aucun nouvel onglet ne s'ouvre, votre navigateur l'a bloqué : autorisez les fenêtres pop-up pour notre site, ou utilisez <b>Click here to install</b> sur la page du canal (voir ci-dessous).
7. Quand l'application est installée, Shopify affiche la page de l'application. Vous pouvez fermer cet onglet et revenir à votre compte dropshipping.

<!-- screenshot: la fenêtre de connexion Shopify avec un nom de boutique tapé et la fin .myshopify.com affichée à droite -->

Le nouveau canal est maintenant dans votre liste <b>Sales Channels</b>. Ouvrez-le pour voir son tableau de bord.

Si vous n'êtes pas sûr du nom de votre boutique, appuyez sur le lien <b>Click here</b> à côté de <b>Not sure which is your Shopify store name?</b> dans la même fenêtre.

## Ce que nous configurons dans votre boutique

Quand l'application est installée, nous faisons ces choses pour vous dans votre boutique Shopify :

- Nous ajoutons un emplacement de traitement dont le nom commence par <b>aiku-</b>. Le stock des produits que vous connectez est conservé dans cet emplacement, et Shopify nous envoie les commandes de ces produits par son intermédiaire.
- Nous ajoutons cet emplacement à votre profil d'expédition par défaut, pour que Shopify puisse vendre et expédier depuis lui. Voir [L'emplacement de traitement AW dans Shopify](/docs/shopify-fulfilment-location).
- Nous configurons les messages que Shopify nous envoie à l'arrivée des commandes.

Vous n'avez rien à faire de tout cela vous-même.

## Vérifier que le canal est connecté

Ouvrez le canal depuis <b>Chaînes</b>. Quand notre application est installée, trois petites icônes apparaissent à côté du nom de la boutique. Passez la souris dessus pour lire leurs noms :

- <b>App installed</b> : notre application est installée et nous pouvons lire votre boutique.
- <b>Exist in platform</b> et <b>Platform status</b> : notre emplacement de traitement est configuré dans votre boutique.

Quand les trois sont des coches vertes, le tableau de bord affiche les encadrés <b>Orders</b> et <b>Products</b> et, dans le menu de gauche sous votre canal, <b>My Products</b> et <b>Orders</b>. Vous pouvez maintenant ajouter des produits : voir [Gérer les produits sur Shopify](/docs/managing-products-on-shopify).

<!-- screenshot: tableau de bord du canal avec les trois coches vertes, le bouton Fetch orders et les encadrés Orders et Products -->

## Si le canal indique qu'il n'est pas encore connecté

Si vous voyez <b>Your channel is not connected yet to the platform. Please connect it to be able to synchronize your products.</b>, l'application n'a pas été installée dans Shopify. C'est le problème le plus fréquent. Il se produit quand l'onglet Shopify a été fermé avant d'appuyer sur <b>Install</b>, ou quand votre navigateur a bloqué le nouvel onglet.

1. Connectez-vous à votre administration Shopify dans le même navigateur.
2. Sur la page du canal, cliquez sur <b>Click here to install</b>, à la fin de <b>Make sure you click the button "Install" in the Shopify dashboard to finalize the connection.</b>
3. Shopify s'ouvre dans le même onglet. Appuyez sur <b>Install</b>.
4. Retournez sur la page du canal et actualisez-la.

Si cela ne fonctionne toujours pas, vous pouvez appuyer sur <b>Delete</b> à côté de <b>Or delete the channel and try again</b>, puis reconnecter la boutique depuis le début.

## Supprimer ou réinitialiser un canal

- <b>Delete channel</b> : affiché sur un canal connecté. Il demande <b>Are you sure you want to delete channel</b> ; appuyez sur <b>Yes, delete channel</b> pour confirmer. Si vous reconnectez la même boutique Shopify plus tard, nous pouvons rouvrir l'ancien canal avec ses produits au lieu d'en créer un nouveau.
- <b>Reset channel</b> : affiché quand le canal a perdu sa connexion mais a encore des produits. Il reconfigure l'emplacement de traitement et les messages de commande. Vos produits doivent alors être liés à nouveau. Les commandes déjà passées ne sont pas modifiées.

## En cas de problème

**« This does not look like a Shopify store name. Use the .myshopify.com name, not your own domain. »** Vous avez tapé votre propre domaine, comme <b>mystore.com</b>. Tapez plutôt le nom <b>myshopify.com</b>. Vous le trouvez dans Shopify sous <b>Settings</b>, <b>Domains</b>.

**« Shopify shop ... not found ».** Aucune boutique Shopify n'a ce nom. Vérifiez l'orthographe. Le nom est souvent un code de lettres et de chiffres, pas le nom de votre boutique.

**« Shopify shop ... already exists, please use other name ».** Cette boutique est déjà connectée à un compte dropshipping. Vérifiez votre liste <b>Sales Channels</b>. Si elle est connectée à un autre de vos comptes, supprimez-la d'abord là-bas.

**« Shop name cannot contain spaces ».** Le nom myshopify.com ne contient jamais d'espaces. Copiez-le depuis Shopify plutôt que de taper le nom de votre boutique.

**J'ai connecté Shopify mais cela indique encore non connecté.** L'application n'a pas été installée. Suivez les étapes de <b>Si le canal indique qu'il n'est pas encore connecté</b> ci-dessus.

**« Click here to install » affiche « Something went wrong ».** Le canal a perdu son lien avec votre boutique. Appuyez sur <b>Delete</b> à côté de <b>Or delete the channel and try again</b>, puis reconnectez la boutique.

**Après avoir appuyé sur Connecter, aucun onglet Shopify ne s'ouvre.** Votre navigateur a bloqué le nouvel onglet. Autorisez les fenêtres pop-up pour notre site, ou ouvrez le canal et utilisez <b>Click here to install</b>.

**Les produits ne se vendent pas dans Shopify, ou apparaissent comme épuisés.** Vérifiez que l'emplacement <b>aiku-</b> figure dans votre profil d'expédition. Voir [L'emplacement de traitement AW dans Shopify](/docs/shopify-fulfilment-location).

Si rien de tout cela n'aide, demandez-nous dans le chat de notre site et indiquez-nous votre nom myshopify.com.

<aside class="wayfinder"><strong>Où cliquer</strong>
<ul>
<li><b>Connecter une nouvelle boutique :</b> <b>Chaînes</b> → <b>Ajouter un canal de vente</b> → <b>Shopify</b> → <b>Connecter</b>.</li>
<li><b>Terminer une installation :</b> ouvrez le canal → <b>Click here to install</b> → <b>Install</b> dans Shopify.</li>
<li><b>Vérifier la connexion :</b> ouvrez le canal → les trois icônes à côté de son nom.</li>
<li><b>Changer les paramètres de stock :</b> ouvrez le canal → <b>Manage Sales Channel</b>.</li>
</ul>
</aside>
