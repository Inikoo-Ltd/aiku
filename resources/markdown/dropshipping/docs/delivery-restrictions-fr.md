---
title: Pays que nous ne pouvons pas livrer
summary: Ce que signifie le message "We cannot deliver to" sur un panier ou une commande, et comment corriger les caisses Shopify qui refusent d'expédier nos produits vers un pays.
date: 2026-09-25
source_date: 2026-09-25
tags: livraison, pays, expédition, shopify, profil d'expédition, adresse interdite
category: orders
shops: awd, dssk, dse
---

<aside class="tldr">
Chacun de nos sites a une liste de pays qu'il ne livre pas. Quand l'adresse de livraison d'une commande se trouve dans l'un d'eux, vous voyez <b>We cannot deliver to ...</b>, vous ne pouvez pas payer, et la commande ne part pas à l'entrepôt. Modifiez l'adresse tant que la commande est encore un panier, ou contactez-nous via le chat de notre site. Un problème différent est une caisse Shopify qui refuse d'expédier nos produits vers un pays : c'est un paramètre d'expédition de votre boutique Shopify.
</aside>

## "We cannot deliver to ..." sur votre panier ou commande

Si l'adresse de livraison se trouve dans un pays que le site ne livre pas, vous voyez ceci en rouge :

<b>We cannot deliver to (country). Please update the address or contact support.</b>

Ce qui se passe alors :

- Dans un panier, les boutons <b>Continue to Checkout</b> et <b>Place order</b> sont masqués.
- Une commande arrivant de votre boutique n'est pas payée et reste <b>Submitted</b>. Elle n'est pas envoyée à l'entrepôt.
- Sur la page de la commande, l'encadré jaune vous demandant de recharger et le bouton <b>Pay ... with balance</b> sont masqués, car la commande ne peut pas être envoyée. La commande s'affiche toujours comme <b>Unpaid</b>.

Que faire :

- **Panier (canal Manuel/API)** : cliquez sur <b>Edit</b> à côté de l'adresse de livraison et modifiez-la, si l'adresse était incorrecte.
- **Commande de votre boutique** : vous ne pouvez pas modifier l'adresse sur la commande. Contactez-nous via le chat de notre site avec la référence de la commande.

Certains pays sont bloqués seulement pour une partie du pays, par code postal. Le même message s'affiche.

La liste est différente pour chaque site. Ce site ne livre pas ces pays :

{blocked_delivery_countries}

## "Your current billing address is marked as forbidden"

Ce message concerne votre propre adresse de facturation, pas celle de votre acheteur. Mettez à jour l'adresse dans votre compte, ou contactez-nous via le chat de notre site.

## Shopify : "unable to deliver" à la caisse de votre boutique

Cela se produit dans votre boutique Shopify, avant que la commande ne nous parvienne. Shopify bloque la caisse quand il n'a aucun tarif d'expédition entre l'emplacement du produit et le pays de l'acheteur. Les produits que vous avez créés vous-même peuvent tout de même fonctionner, car ils utilisent un emplacement différent.

Nos produits sont stockés dans Shopify à notre emplacement de traitement. Son nom est <b>aiku-</b> suivi du code du site, puis du code de votre canal entre parenthèses, par exemple <b>aiku-awd (my-store)</b>. Voir [L'emplacement AW dans Shopify](/docs/shopify-fulfilment-location). Vérifiez ces paramètres dans votre administration Shopify :

1. **Locations** (Settings → Locations) : notre emplacement doit être actif. Supprimez les anciens emplacements de dropshipping ou les doublons que vous n'utilisez plus.
2. **Shipping profile** (Settings → Shipping and delivery) : ouvrez le profil qui contient nos produits et vérifiez que notre emplacement y figure.
3. **Zones and rates** : dans ce profil, le pays de l'acheteur doit être dans une zone d'expédition, et la zone doit avoir au moins un tarif (payant ou gratuit).
4. **Product** : ouvrez le produit qui échoue et vérifiez quel profil d'expédition il utilise. Déplacez-le vers le profil de l'étape 2 si nécessaire.

<!-- screenshot: le profil d'expédition Shopify avec l'emplacement aiku- et une zone contenant le pays de l'acheteur -->

Si les quatre points sont corrects et que la caisse échoue encore, contactez le support Shopify. Les zones et tarifs d'expédition sont définis dans votre boutique, nous ne pouvons donc pas les modifier pour vous.

Même quand Shopify autorise la caisse, nous ne pouvons envoyer la commande que si le pays ne figure pas sur notre liste ci-dessus.

## En cas de problème

**Ma commande est Submitted depuis des jours et il n'y a pas de bouton de paiement.** Ouvrez la commande. Si vous voyez <b>We cannot deliver to ...</b>, le pays est bloqué. Contactez-nous via le chat de notre site avec la référence de la commande.

**L'acheteur a donné un pays erroné par erreur.** Contactez-nous via le chat de notre site avec la référence de la commande et la bonne adresse.
