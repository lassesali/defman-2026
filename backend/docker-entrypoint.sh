#!/bin/sh
set -e

if [ "${WAIT_FOR_DB:-false}" = "true" ] || [ "${AUTO_SEED:-false}" = "true" ]; then
    echo "[DefMan Entrypoint] Waiting for MySQL database to be ready..."
    MAX_RETRIES=30
    COUNT=0
    until php -r '
        require "/var/www/html/src/Infrastructure/Database/Database.php";
        try {
            $pdo = DefMan\Infrastructure\Database\Database::getConnection();
            $pdo->query("SELECT 1");
            exit(0);
        } catch (\Throwable $e) {
            exit(1);
        }
    ' >/dev/null 2>&1; do
        COUNT=$((COUNT + 1))
        if [ "$COUNT" -ge "$MAX_RETRIES" ]; then
            echo "[DefMan Entrypoint] Warning: Database not ready after ${MAX_RETRIES} attempts, continuing startup..."
            break
        fi
        sleep 2
    done
fi

if [ "${AUTO_SEED:-false}" = "true" ]; then
    echo "[DefMan Entrypoint] Running database seeder check..."
    php /var/www/html/bin/seed.php --if-empty || echo "[DefMan Entrypoint] Warning: Database seeding failed."
fi

exec docker-php-entrypoint "$@"
