---
title: Gérer les clients
summary: Ajoutez les acheteurs auxquels vous expédiez comme clients d'un canal Manuel/API, un par un ou depuis un tableur, et modifiez-les ou désactivez-les plus tard.
date: 2026-09-25
source_date: 2026-09-25
tags: manuel, api, clients, acheteurs, adresse de livraison, import
category: orders
series: manual
order: 3
shops: awd, dssk, dse
---

<aside class="tldr">
Un client est la personne à qui vous vendez : nous envoyons le colis à l'adresse du client. Dans un canal Manuel/API, ouvrez <b>Clients</b> et appuyez sur <b>Créer un client client</b> pour en ajouter un, ou <b>Upload File</b> pour en ajouter plusieurs depuis un tableur. Chaque commande d'un canal Manuel/API est créée depuis une page client.
</aside>

## Où vivent les clients

Les clients appartiennent à un canal. Ouvrez votre canal Manuel/API dans le menu puis <b>Clients</b>, ou appuyez sur <b>Afficher tout</b> sur l'encadré <b>Clients</b> de la page du canal.

La liste affiche <b>Nom</b>, <b>E-mail</b>, <b>phone</b>, <b>location</b> et <b>since</b> (quand vous les avez ajoutés). Elle a deux onglets :

- <b>Actif</b> : vos clients actuels.
- <b>Inactif</b> : les clients que vous avez désactivés.

Seuls les canaux Manuel/API ont une page <b>Clients</b>. Sur les canaux connectés, les informations de l'acheteur arrivent avec chaque commande de votre boutique.

## Ajouter un client

1. Sur la page <b>Clients</b>, appuyez sur <b>Créer un client client</b>.
2. Le formulaire <b>Nouveau client</b> s'ouvre. Remplissez :
   - <b>Entreprise</b> : si votre acheteur est une entreprise.
   - <b>Nom du contact</b> : le nom pour l'étiquette d'expédition.
   - <b>E-mail</b>
   - <b>phone</b> : au moins 6 caractères si vous le renseignez.
   - <b>Adresse</b> : l'adresse de livraison. Le pays commence par le pays de notre boutique. Changez-le si votre acheteur habite ailleurs.
3. Appuyez sur <b>Sauvegarder</b>.

<!-- screenshot: le formulaire Nouveau client avec Entreprise, Nom du contact, E-mail, phone et Adresse -->

La page du client s'ouvre. Depuis là, vous pouvez appuyer sur <b>Créer une commande</b>. Voir [Passer des commandes manuellement](/docs/placing-orders-manually).

L'adresse doit être complète pour le pays choisi. Le formulaire indique ce qui manque, par exemple <b>The address is required</b>, <b>The town is required</b>, <b>The postal code is required</b> ou <b>The province is required</b>. Certains pays n'ont pas de code postal ni de ville, et le formulaire ne les demande alors pas.

## Ajouter plusieurs clients depuis un tableur

1. Sur la page <b>Clients</b>, appuyez sur <b>Upload File</b>.
2. Dans la fenêtre <b>Import your clients</b>, téléchargez le modèle.
3. Remplissez un client par ligne, avec ces colonnes : contact_name, company_name, email, phone, address_line_1, address_line_2, postal_code, locality, country_code. Toutes les colonnes sauf address_line_2 doivent être remplies, et l'e-mail doit être une adresse valide.
4. Pour country_code, utilisez le code pays à deux lettres, par exemple GB, ES, DE ou FR.
5. Importez le fichier.

## Modifier un client

Ouvrez le client et appuyez sur <b>Modifier</b>. La page <b>Modifier le client</b> permet de changer l'<b>Entreprise</b>, le <b>Nom du contact</b>, l'<b>E-mail</b>, le <b>phone</b> et l'<b>Adresse de livraison</b>.

Une nouvelle adresse est utilisée pour les nouvelles commandes. Pour une commande encore dans le panier, vous pouvez aussi changer l'adresse de livraison sur la page du panier avec <b>Modifier</b> sous l'adresse.

## Désactiver un client

Sur la page <b>Modifier le client</b>, désactivez <b>statut</b>. Le client passe dans l'onglet <b>Inactif</b>. Ses commandes passées restent. Réactivez <b>statut</b> pour l'utiliser à nouveau.

## Les clients via l'API

Votre propre système peut lister, créer, modifier et désactiver des clients via l'API, et créer des commandes pour eux. Voir [Le canal Manuel/API](/docs/manual-and-api-channel).

## En cas de problème

- <b>Je ne trouve pas Créer un client client.</b> Le bouton n'existe que sur les canaux Manuel/API, et seulement tant que le canal est ouvert. Les autres canaux n'ont pas de page <b>Clients</b>.
- <b>Le formulaire indique que la ville, le code postal ou la province est requis.</b> L'adresse n'est pas complète pour ce pays. Remplissez le champ indiqué. Vérifiez que le pays est correct.
- <b>Le formulaire indique que l'e-mail n'est pas valide.</b> Vérifiez qu'il n'y a pas d'espaces et qu'il ne manque pas de @ ou de point. Vous pouvez aussi laisser l'e-mail vide.
- <b>Mon client n'est pas dans la liste.</b> Regardez dans l'onglet <b>Inactif</b>. Vérifiez aussi que vous êtes dans le bon canal : les clients d'un canal n'apparaissent pas dans un autre.
- <b>L'import de mon tableur a échoué pour certaines lignes.</b> Vérifiez que chaque colonne obligatoire est remplie (seule address_line_2 peut être vide), que l'e-mail est valide, que country_code est un code à deux lettres et que l'adresse a les champs requis par le pays.
