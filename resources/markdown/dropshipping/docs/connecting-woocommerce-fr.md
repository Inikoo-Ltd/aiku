---
title: Connecter votre boutique WooCommerce
summary: Liez votre boutique WooCommerce à votre compte dropshipping, réparez les messages que vous pouvez voir pendant la connexion, et reconnectez une boutique qui a cessé de répondre.
date: 2026-09-25
source_date: 2026-09-25
tags: woocommerce, wordpress, canal de vente, connecter, clés api
category: sales-channels
series: woocommerce
order: 1
shops: awd, dssk, dse
---

<aside class="tldr">
Allez dans <b>Chaînes</b>, appuyez sur <b>Ajouter un canal de vente</b>, puis sur <b>Connecter</b> sur la carte Woocommerce. Tapez un nom pour votre boutique et appuyez sur <b>Suivant</b>. Tapez l'adresse de votre boutique, appuyez sur <b>Auth Store</b>, approuvez notre application dans WooCommerce, revenez et appuyez sur <b>Suivant</b>. Si votre hébergeur bloque les clés automatiques, vous pouvez créer les clés dans WooCommerce et les coller vous-même.
</aside>

## Avant de commencer

Vérifiez ceci d'abord sur votre site WordPress. La plupart des connexions échouées viennent de l'un de ces points.

- WooCommerce est installé et actif.
- L'adresse de votre boutique commence par <b>https://</b>. Nous ne connectons pas les boutiques sans certificat SSL valide.
- Dans WordPress, <b>Settings</b>, <b>Permalinks</b> n'est pas réglé sur <b>Plain</b>. Avec des permaliens Plain, l'API WooCommerce est introuvable.
- Votre plugin de sécurité, votre pare-feu ou Cloudflare ne bloque pas les requêtes vers <b>/wp-json/</b>. Nous parlons à votre boutique via cette adresse.
- Vous pouvez vous connecter à votre administration WordPress en tant qu'administrateur. Vous en avez besoin pour approuver la connexion.
- Facultatif mais utile : réglez l'unité de poids voulue dans WooCommerce (<b>Settings</b>, <b>Products</b>) avant de vous connecter. Nous la lisons lors de la connexion et envoyons les poids des produits dans cette unité.

WooCommerce est disponible sur tous nos sites de dropshipping. Connectez-le depuis le site où vous avez votre compte dropshipping.

## Connecter votre boutique

1. Ouvrez <b>Chaînes</b> dans le menu. La page <b>Canaux de vente</b> liste les canaux que vous avez déjà.
2. Appuyez sur <b>Ajouter un canal de vente</b>.
3. Trouvez la carte <b>Woocommerce</b> et appuyez sur <b>Connecter</b>. Une fenêtre s'ouvre.
4. Dans <b>Nom du compte WooCommerce</b>, tapez un nom pour votre boutique, par exemple le nom de votre commerce. Le nom est obligatoire, et c'est celui que vous verrez dans votre liste de canaux. Appuyez sur <b>Suivant</b>.
5. Sous <b>Paramètres d'authentification</b>, tapez l'adresse complète de votre boutique, par exemple <b>https://mystore.com</b>. Appuyez sur <b>Auth Store</b>.
6. Nous vérifions d'abord que votre boutique répond. Si c'est le cas, un nouvel onglet s'ouvre sur votre site WordPress. Connectez-vous si WordPress vous le demande.
7. WooCommerce indique qu'<b>AW Connect</b> demande un accès <b>Read/Write</b>. Vérifiez que vous êtes connecté à la bonne boutique, puis appuyez sur <b>Approuver</b>.
8. L'onglet affiche un court message et se ferme tout seul. Retournez à la fenêtre de votre compte dropshipping et appuyez sur <b>Suivant</b>.
9. Vous voyez <b>Connecté !</b> Appuyez sur <b>D'ACCORD</b>.

<!-- screenshot: la fenêtre de connexion Woocommerce, étape Paramètres d'authentification avec le champ d'adresse de la boutique et le bouton Auth Store -->

<!-- screenshot: la page d'approbation WooCommerce avec AW Connect demandant un accès Read/Write et le bouton Approve -->

Terminez toutes les étapes dans l'heure. Passé ce délai, nous oublions le nom et les clés avec lesquels vous avez commencé, et vous devez recommencer depuis <b>Connecter</b>.

Si votre navigateur bloque le nouvel onglet, la page d'approbation s'ouvre dans le même onglet et vous quittez la fenêtre de connexion. Autorisez les fenêtres pop-up pour notre site, puis recommencez depuis <b>Connecter</b>.

## Si votre boutique n'a pas pu nous envoyer les clés

Quand vous approuvez, WooCommerce envoie les nouvelles clés depuis votre hébergement vers nos serveurs. Certains hébergeurs bloquent cela. Vous voyez alors <b>Your store approved the connection but could not send us the keys</b>, et <b>Suivant</b> indique <b>You are not connected yet</b>.

Vous pouvez tout de même vous connecter en collant les clés vous-même :

1. Dans WordPress, allez dans <b>WooCommerce</b>, <b>Settings</b>, <b>Advanced</b>, <b>REST API</b>.
2. Ajoutez une clé. Donnez-lui une description, choisissez votre utilisateur administrateur et réglez <b>Permissions</b> sur <b>Read/Write</b>. Générez la clé.
3. Copiez la <b>Consumer key</b> (elle commence par ck_) et le <b>Consumer secret</b> (il commence par cs_). WooCommerce n'affiche le secret qu'une seule fois.
4. Dans la fenêtre Woocommerce de votre compte dropshipping, vérifiez que l'adresse de votre boutique est toujours dans le champ d'adresse.
5. Appuyez sur <b>My store could not send the keys, let me paste them</b>.
6. Collez la clé et le secret, et appuyez sur <b>Use these keys</b>.

<!-- screenshot: l'étape Paramètres d'authentification avec la section des clés manuelles ouverte, montrant les champs ck_ et cs_ et le bouton Use these keys -->

## Après la connexion

Votre boutique apparaît maintenant sur la page <b>Canaux de vente</b>. Cliquez sur son nom pour ouvrir le tableau de bord du canal. Vous y voyez <b>Orders</b>, <b>Clients</b> et <b>Products</b>. Appuyez sur <b>Afficher tout</b> sous <b>Products</b> pour ajouter des produits. Voir [Gérer les produits sur WooCommerce](managing-products-on-woocommerce).

Quand vous vous connectez, nous ajoutons aussi deux webhooks à votre boutique : un pour les nouvelles commandes et un pour les produits supprimés. Ne les supprimez pas dans WooCommerce, <b>Settings</b>, <b>Advanced</b>, <b>Webhooks</b>. Sans eux, les nouvelles commandes ne nous parviennent pas immédiatement.

Nous importons les commandes qui sont payées, ont le statut <b>Processing</b> dans WooCommerce et ont un pays de livraison. Si une commande manque, appuyez sur <b>Fetch orders</b> sur le tableau de bord du canal. Cela vérifie les commandes des 14 derniers jours dans votre boutique qui ne nous sont pas encore parvenues.

Avec <b>Gérer le canal de vente</b>, vous pouvez changer le nom de la boutique, vos paramètres de stock et votre règle de prix pour les nouveaux produits.

## Reconnecter la même boutique

Si vous supprimez votre canal WooCommerce et reconnectez plus tard la même adresse de boutique, nous récupérons le même canal, avec ses produits et commandes. Vous ne repartez pas de zéro.

## Quand votre boutique cesse de répondre

Nous vérifions régulièrement votre boutique connectée. Si votre boutique cesse de répondre, ou si les clés cessent de fonctionner, le canal affiche <b>Your channel is not connected yet to the platform</b>. Au-dessus, vous pouvez voir le message d'erreur envoyé par votre boutique. Tant que cela s'affiche, votre liste de produits est masquée et vous ne pouvez pas importer de produits vers votre boutique.

Pour réparer cela :

1. Vérifiez que votre site est en ligne et que vous pouvez l'ouvrir dans votre navigateur.
2. Sur la page du canal, appuyez sur <b>Essayez de vous reconnecter</b>. Votre site WordPress s'ouvre. Connectez-vous en tant qu'administrateur et appuyez à nouveau sur <b>Approuver</b>. Cela crée de nouvelles clés.
3. Si cela ne fonctionne toujours pas, appuyez sur <b>Test de connexion</b> pour vérifier à nouveau la connexion.
4. En dernier recours, appuyez sur <b>Supprimer</b> et reconnectez la boutique. Vos produits et commandes reviennent quand vous utilisez la même adresse de boutique.

Si votre boutique continue d'échouer pendant longtemps, nous arrêtons de la vérifier. Elle recommence à fonctionner quand vous la reconnectez.

<!-- screenshot: l'avertissement de non-connexion sur un canal WooCommerce avec les boutons Try to reconnect, Test Connection et Delete -->

## En cas de problème

Voici les messages que vous pouvez voir en appuyant sur <b>Auth Store</b>, et quoi faire.

- <b>We could not resolve your store domain</b> : l'adresse est mal orthographiée ou le domaine n'est pas actif. Copiez l'adresse depuis votre navigateur quand votre boutique est ouverte.
- <b>Your store SSL certificate could not be verified</b> : votre certificat est expiré, auto-signé ou incomplet. Demandez à votre hébergeur de le renouveler ou de le réparer.
- <b>Your store refused our connection</b> ou <b>Your store did not answer within 2 minutes</b> : votre hébergement ou votre pare-feu bloque nos serveurs. Le message liste nos adresses IP. Envoyez-les à votre hébergeur et demandez-lui de les autoriser.
- <b>Your store redirects to ...</b> : votre boutique se trouve à une autre adresse, par exemple avec ou sans www. Saisissez l'adresse indiquée dans le message.
- <b>Your store url redirects in a loop</b> : saisissez l'adresse finale de votre boutique, celle que vous voyez dans le navigateur une fois la page chargée.
- <b>We could not find the WooCommerce API on this store</b> : WooCommerce n'est pas actif, son API REST est désactivée, ou vos permaliens sont réglés sur <b>Plain</b>. Changez les permaliens dans WordPress, <b>Settings</b>, <b>Permalinks</b>.
- <b>Your store answered with 401</b> ou <b>403</b> <b>and blocked our request</b> : un plugin de sécurité, un pare-feu ou Cloudflare nous bloque. Autorisez les requêtes vers <b>/wp-json/</b> dans cet outil.
- <b>Your WooCommerce store returned an error 500</b> (ou un autre numéro commençant par 5) : votre site a une erreur. Vérifiez votre journal d'erreurs d'hébergement, ou demandez à votre hébergeur, puis réessayez.
- <b>Your store answered with ... but did not return the WooCommerce REST API</b> : l'adresse ne pointe pas vers votre site WordPress. Vérifiez que vous avez saisi la boutique elle-même, pas une page d'atterrissage ou un autre site.
- <b>You are not connected yet, click auth store to connect and follow the instructions</b> : vous avez appuyé sur <b>Suivant</b> avant d'approuver dans WooCommerce, l'approbation ne nous est pas parvenue, ou plus d'une heure s'est écoulée. Appuyez à nouveau sur <b>Auth Store</b>, ou collez les clés vous-même comme montré ci-dessus.
- <b>We can't access your store, make sure you already put correct store url</b> : nous avons obtenu les clés, mais n'avons pas pu les utiliser à cette adresse. Vérifiez l'adresse, et que les clés ont la permission <b>Read/Write</b>.

Les problèmes sur votre propre site, comme une boutique hors ligne, lente ou qui nous bloque, ne peuvent être réparés que par vous ou votre hébergeur. Nous ne pouvons pas changer les paramètres de votre site.
