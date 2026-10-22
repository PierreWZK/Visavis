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

- **Accueil** : un duel en direct (deux listes déroulantes, le duel se redessine), « En trois gestes » avec le catalogue en miniature, quatre modèles en photo.
- **Catalogue** : recherche, filtres appliqués dès qu'on les change, tri (marque, prix, puissance, coffre), sélection de 2 à 4 versions conservée d'un filtre à l'autre et affichée dans la barre du bas (on peut retirer une voiture masquée par un filtre).
- **Comparaison** : le meilleur de chaque ligne en rose, l'écart des autres en valeur et en %. Sur téléphone, une carte par caractéristique, chaque voiture nommée.
- **Fiche** : les chiffres de la version et « Face à elle », ses rivales de même carrosserie au prix le plus proche, comparables en un clic.
- **Design** : direction « split stage » tirée avec le skill tasteskill (Work Sans, marine et rose). Photos Wikimedia Commons, crédits dans le pied de page et dans `docs/photo-credits.json`.

## Lancer en local

```bash
composer install
# identifiants MySQL dans .env.local (jamais versionné)
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
symfony serve -d             # http://127.0.0.1:8000
```

## Mise en ligne

En ligne sur Coolify : **https://visavis.projectp.fr**. Chaque push sur `main` redéploie.

- Image `Dockerfile` (FrankenPHP, PHP 8.4), port 80. Au démarrage, `docker/entrypoint.sh` attend MySQL, lance les migrations et, si le catalogue est vide, charge les versions de démonstration.
- Base : MySQL 8.4 dans le même projet Coolify.
- Variables d'environnement : `APP_SECRET`, `DATABASE_URL` (URL interne, `?serverVersion=8.4.0&charset=utf8mb4`), `DEFAULT_URI`, `ADMIN_USER`, `ADMIN_PASSWORD_HASH`.
- L'administration (`/admin`) reste fermée tant que `ADMIN_PASSWORD_HASH` est vide : générez le hash avec `php bin/console security:hash-password`, puis collez-le dans Coolify.

## Stack

Symfony · Doctrine · MySQL.
