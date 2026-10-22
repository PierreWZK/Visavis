#!/bin/sh
# ---------------------------------------------------------------------------
# Démarrage du conteneur Vis-à-vis : prépare l'appli puis lance le serveur web (CMD du Dockerfile).
# Une commande passée au conteneur est exécutée telle quelle.
# ---------------------------------------------------------------------------
set -e

if [ "$1" = 'frankenphp' ]; then
    mkdir -p var/share

    # MySQL peut démarrer après l'appli : on l'attend jusqu'à une minute
    tries=30
    until php bin/console dbal:run-sql -q 'SELECT 1' > /dev/null 2>&1; do
        tries=$((tries - 1))
        if [ "$tries" -le 0 ]; then
            echo 'MySQL ne répond pas : vérifiez DATABASE_URL.' >&2
            php bin/console dbal:run-sql 'SELECT 1'
            exit 1
        fi
        echo 'En attente de MySQL…'
        sleep 2
    done

    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

    # Premier démarrage : pas encore de catalogue de démonstration, on le charge (jamais par-dessus des données)
    if php bin/console dbal:run-sql "SELECT COUNT(*) AS n FROM version WHERE source = 'demo'" | grep -qE '^\s*0\s*$'; then
        echo 'Chargement du catalogue de démonstration.'
        php bin/console doctrine:fixtures:load --append --no-interaction
    fi

    # Les voitures de l'ADEME, rafraîchies à chaque démarrage : si l'API ne répond pas, le site démarre avec le catalogue déjà en base
    php bin/console app:import:ademe --no-interaction || echo 'Import ADEME impossible pour le moment : catalogue inchangé.' >&2

    php bin/console cache:warmup
fi

exec "$@"
