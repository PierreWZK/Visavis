# Vis-à-vis · Deux voitures face à face, sans parti pris

> Jour 09 du défi **30 jours, 30 projets** · Symfony · Doctrine · MySQL · ⏳ **À venir**

Un comparateur de voitures : on choisit deux à quatre modèles et **Vis-à-vis** les aligne caractéristique par caractéristique (prix, puissance, consommation, coffre, émissions), en mettant en valeur le meilleur de chaque ligne.

📄 Les règles détaillées sont dans le [cahier des charges](CAHIER_DES_CHARGES.md).

## Au programme

- Catalogue de modèles (marque, modèle, version, année) avec recherche et filtres.
- Comparaison de 2 à 4 versions côte à côte, la meilleure valeur de chaque ligne en évidence.
- Fiche détaillée d'une version.
- Lien partageable vers une comparaison.
- Administration du catalogue (EasyAdmin ou formulaires Symfony).

## Ce qui est fait

- **Données** : environ 770 voitures neuves vendues en France, importées du jeu public **ADEME - Car Labelling** (prix, puissance, consommation, CO₂, poids, boîte, autonomie électrique), plus 51 versions de démonstration qui ajoutent le coffre et les places. Une valeur que la source ne donne pas s'affiche « — » et n'est jamais classée.
- **Accueil** : un duel en direct (deux champs à suggestions, le duel se redessine), « En trois gestes » avec le catalogue en miniature, quatre modèles en photo, les jeux du jour.
- **Catalogue** : recherche, filtres (énergie, carrosserie, boîte, budget) appliqués dès qu'on les change, tri (marque, prix, puissance, CO₂), 50 versions par page, sélection de 2 à 4 versions conservée d'une page et d'un filtre à l'autre.
- **Comparaison** : le meilleur de chaque ligne en rose, l'écart des autres en valeur et en %. Sur téléphone, une carte par caractéristique, chaque voiture nommée.
- **Fiche** : les chiffres de la version, sa source, et « Face à elle », ses rivales de même carrosserie au prix le plus proche.
- **Jeux du jour** (`/jeu`), les mêmes pour tout le monde, une partie par jour, score à partager :
  - **À l'aveugle** : quatre voitures, leurs noms seulement, trois questions (la moins chère, la plus puissante, la plus légère…). Les chiffres tombent après le choix.
  - **Devine la voiture** : un modèle à trouver en six essais ; chaque erreur dévoile un indice (prix, puissance, consommation, poids, initiale de la marque) et chaque essai dit si la marque et la carrosserie sont les bonnes, si la voiture à trouver est plus chère ou plus puissante.
- **Design** : direction « split stage » tirée avec le skill tasteskill (Work Sans, marine et rose). Photos Wikimedia Commons, crédits dans le pied de page et dans `docs/photo-credits.json`.

### Deux autres jeux possibles (quatre au plus)

- **Plus ou moins** : une voiture, puis une autre ; plus chère ou moins chère ? Une série à prolonger le plus loin possible.
- **Le juste prix** : une version et ses chiffres, son prix à deviner à 5 % près.

## Lancer en local

```bash
composer install
# identifiants MySQL dans .env.local (jamais versionné)
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
php bin/console app:import:ademe   # les voitures de l'ADEME (relancer pour les mettre à jour)
symfony serve -d             # http://127.0.0.1:8000
```

## Mise en ligne

En ligne sur Coolify : **https://visavis.projectp.fr**. Chaque push sur `main` redéploie.

- Image `Dockerfile` (FrankenPHP, PHP 8.4), port 80. Au démarrage, `docker/entrypoint.sh` attend MySQL, lance les migrations, charge le catalogue de démonstration s'il manque, puis importe les voitures de l'ADEME (un échec de l'API n'empêche pas le site de démarrer).
- Base : MySQL 8.4 dans le même projet Coolify.
- Variables d'environnement : `APP_SECRET`, `DATABASE_URL` (URL interne, `?serverVersion=8.4.0&charset=utf8mb4`), `DEFAULT_URI`, `ADMIN_USER`, `ADMIN_PASSWORD_HASH`.
- L'administration (`/admin`) reste fermée tant que `ADMIN_PASSWORD_HASH` est vide : générez le hash avec `php bin/console security:hash-password`, puis collez-le dans Coolify.

## Stack

Symfony · Doctrine · MySQL · open data ADEME (Licence Ouverte).
