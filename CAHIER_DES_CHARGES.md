# Jour 9/30 — Vis-à-vis, deux voitures face à face, sans parti pris

> Stack : **Symfony**, **Doctrine**, **MySQL** · hébergement **Coolify**, sous-domaine `visavis.projectp.fr`.
> Durée visée : **2 h 45** pour le MVP, les bonus ensuite.

Ce document est notre contrat : on le suit étape par étape, et toute décision qui s'en écarte est d'abord notée ici. Il s'ajuste au début de la journée.

## 1. L'idée

Un comparateur de voitures : on choisit deux à quatre modèles et **Vis-à-vis** les aligne caractéristique par caractéristique (prix, puissance, consommation, coffre, émissions), en mettant en valeur le meilleur de chaque ligne.

## 2. MVP — ce qui doit fonctionner à la fin

1. Catalogue de modèles (marque, modèle, version, année) avec recherche et filtres.
2. Comparaison de 2 à 4 versions côte à côte, la meilleure valeur de chaque ligne en évidence.
3. Fiche détaillée d'une version.
4. Lien partageable vers une comparaison.
5. Administration du catalogue (EasyAdmin ou formulaires Symfony).
6. En ligne sur Coolify, avec un README à jour et un aperçu de lien (Open Graph).

### Périmètre volontairement limité

- Données de démonstration (une cinquantaine de versions), pas de flux constructeur.
- Pas d'annonces d'occasion.

## 3. Cahier des charges fonctionnel

### Comparaison

- Pour chaque caractéristique, on sait si « plus » ou « moins » est mieux (prix bas, puissance haute).
- Écart affiché en valeur et en % par rapport au meilleur.
- La consommation se départage entre voitures de même unité : les thermiques et hybrides entre elles (L/100 km), les électriques entre elles (kWh/100 km). Une unité seule dans la comparaison n'est pas classée. *(décidé le jour 9 : avant, une seule électrique empêchait de classer les trois autres.)*

### Filtres

- Énergie (essence, diesel, hybride, électrique), budget, carrosserie, places.
- Un champ laissé vide ou mal saisi est ignoré (jamais d'erreur). Les filtres s'appliquent dès qu'on les change, la sélection en cours est conservée.
- Tri : marque, prix croissant, puissance, coffre.

## 4. Cahier des charges technique

- Entités `Brand`, `Model`, `Version` (caractéristiques chiffrées, unités en base).
- Comparaison dans l'adresse : `/comparer?v=12,34,56`.
- Fixtures Doctrine pour le catalogue de démo.
- Mise en ligne sur Coolify à chaque push sur la branche principale.

## 5. Critères de réussite

- [x] Une comparaison de 4 versions reste lisible sur mobile.
- [x] Le meilleur de chaque ligne est juste, selon le sens de la caractéristique.
- [x] Le lien partagé recrée la même comparaison.

## 6. Bonus facultatifs

- Coût total sur 5 ans (achat + énergie).
- Import CSV du catalogue.
- Graphique radar.

## 7. Définition du « terminé »

Le MVP fonctionne, les critères de la section 5 sont cochés, le site est en ligne sur `visavis.projectp.fr`, le README a son lien et sa capture, et la carte du projet passe à « terminé » sur la vitrine.
