# Jour 9/30 — Vis-à-vis, deux voitures face à face, sans parti pris

> Stack : **Symfony**, **Doctrine**, **MySQL** · hébergement **Coolify**, sous-domaine `visavis.projectp.fr`.
> Durée visée : **2 h 45** pour le MVP, les bonus ensuite.

Ce document est notre contrat : on le suit étape par étape, et toute décision qui s'en écarte est d'abord notée ici. Il s'ajuste au début de la journée.

## 1. L'idée

Un comparateur de voitures : on choisit deux à quatre modèles et **Vis-à-vis** les aligne caractéristique par caractéristique (prix, puissance, consommation, émissions, poids, autonomie, coffre), en mettant en valeur le meilleur de chaque ligne.

## 2. MVP — ce qui doit fonctionner à la fin

1. Catalogue des voitures neuves vendues en France (marque, modèle, version) avec recherche, filtres et pages.
2. Comparaison de 2 à 4 versions côte à côte, la meilleure valeur de chaque ligne en évidence.
3. Fiche détaillée d'une version.
4. Lien partageable vers une comparaison.
5. Administration du catalogue (EasyAdmin).
6. Deux jeux du jour (section 3).
7. En ligne sur Coolify, avec un README à jour et un aperçu de lien (Open Graph).

### Périmètre volontairement limité

- Pas d'annonces d'occasion, pas de photos par version.
- Pas de coffre ni de nombre de places pour les versions ADEME : la source ne les donne pas (section 4).

## 3. Cahier des charges fonctionnel

### Les données

Deux sources, dans le même catalogue :

| Source | Volume | Ce qu'elle apporte | Ce qui manque |
| --- | --- | --- | --- |
| **ADEME, Car Labelling** (open data, Licence Ouverte) | environ 780 versions, 37 marques | prix, puissance, consommation, CO₂, poids, boîte, carrosserie, autonomie électrique | coffre, places, année |
| **Catalogue de démonstration** (fixtures, 51 versions) | 51 versions | coffre et places | — |

- Une valeur absente s'affiche « — » et n'est jamais classée.
- La fiche et le catalogue disent d'où vient une version (« Données ADEME » ou « Catalogue de démonstration »).

### Comparaison

- Lignes : prix, puissance, consommation, émissions de CO₂, poids à vide, autonomie électrique, coffre. Pour chacune, on sait si « plus » ou « moins » est mieux.
- Écart affiché en valeur et en % par rapport au meilleur.
- La consommation se départage entre voitures de même unité : thermiques et hybrides entre elles (L/100 km), électriques entre elles (kWh/100 km). Une unité seule dans la comparaison n'est pas classée.
- Une ligne sans aucune valeur (autonomie de quatre thermiques, coffre de quatre versions ADEME) n'est pas affichée.

### Filtres

- Recherche, énergie (essence, diesel, hybride, hybride rechargeable, électrique, superéthanol, GPL), carrosserie, boîte (automatique, manuelle), budget.
- Un champ laissé vide ou mal saisi est ignoré (jamais d'erreur). Les filtres s'appliquent dès qu'on les change, la sélection en cours est conservée.
- Tri : marque, prix croissant, puissance, CO₂ (les valeurs inconnues en dernier). 50 versions par page.

### Jeux du jour

Les mêmes pour tout le monde un jour donné (heure de Paris), une partie par jour, la série retenue sur l'appareil, un résultat à partager sans rien dévoiler. Les réponses restent sur le serveur jusqu'à ce qu'on joue.

1. **À l'aveugle** : quatre voitures, seulement leur nom. Trois questions (« Laquelle coûte le moins cher ? », « La plus puissante ? »…). Chaque réponse dévoile les chiffres de la ligne. Score sur 3.
2. **Devine la voiture** : un modèle à trouver en six essais. On part de sa carrosserie et de son énergie ; chaque erreur dévoile un indice de plus (prix, puissance, consommation, poids, CO₂, initiale de la marque). Chaque essai dit aussi si la marque est la bonne et si le prix et la puissance sont au-dessus ou en dessous.

## 4. Cahier des charges technique

- Entités `Brand`, `Model`, `Version` (unités en base : euros, ch, L ou kWh/100 km, g CO₂/km, kg, km, litres).
- `Version.source` : `ademe` ou `demo` ; `Version.sourceKey` identifie une version ADEME d'un import à l'autre.
- **Import** : `php bin/console app:import:ademe` lit l'API data-fair de l'ADEME (`data.ademe.fr/data-fair/api/v1/datasets/ademe-car-labelling/lines`), dédoublonne (une ligne par variante de poids ou d'options), crée ou met à jour les versions, retire celles qui ont disparu. Les versions de démonstration ne sont jamais touchées.
- Conversion des lignes ADEME :
  - noms des clés accentuées normalisés en ASCII (l'API les renvoie mal encodées) ;
  - marque et modèle remis en casse lisible (`B.M.W.` → BMW, `SERIE 3` → Série 3), un modèle existant est réutilisé ;
  - puissance lue dans la description (« (110ch) », « (57Kw) »), sinon la puissance maximale en kW × 1,36. Limite connue : pour certaines électriques la source ne donne que la puissance nominale en kW, plus basse que la puissance maximale (Taycan affiché à 154 ch) ;
  - consommation mixte : moyenne du minimum et du maximum WLTP ; électriques : `Conso_elec` (Wh/km) / 10 ;
  - carrosserie : `TS TERRAINS/CHEMINS` → SUV, `BERLINE` selon la gamme (économique et inférieure → citadine, moyenne inférieure → compacte, au-delà → berline), `BREAK`, `COUPE`, `CABRIOLET`, monospaces et minibus → monospace.
- L'import tourne au démarrage du conteneur (après les migrations ; un échec n'empêche pas le site de démarrer).
- Comparaison dans l'adresse : `/comparer?v=12,34,56`.
- Fixtures Doctrine pour le catalogue de démonstration, chargées si aucune version de démo n'existe.
- Mise en ligne sur Coolify à chaque push sur la branche principale.

## 5. Critères de réussite

- [x] Une comparaison de 4 versions reste lisible sur mobile.
- [x] Le meilleur de chaque ligne est juste, selon le sens de la caractéristique.
- [x] Le lien partagé recrée la même comparaison.
- [x] Le catalogue compte plus de 700 versions après l'import (816 en local : 765 ADEME + 51 de démonstration).
- [x] Une valeur absente n'est jamais classée ni comptée comme zéro.
- [x] Les deux jeux donnent la même partie à tout le monde le même jour.

## 6. Bonus facultatifs

- Coût total sur 5 ans (achat + énergie).
- Graphique radar.
- Deux autres jeux (quatre au plus) : voir README.

## 7. Définition du « terminé »

Le MVP fonctionne, les critères de la section 5 sont cochés, le site est en ligne sur `visavis.projectp.fr`, le README a son lien et sa capture, et la carte du projet passe à « terminé » sur la vitrine.
