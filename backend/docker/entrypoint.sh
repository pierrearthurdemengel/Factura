#!/bin/sh
# Point d'entree du container : decode les cles JWT depuis les variables
# d'environnement base64 avant de lancer l'application.
# En local, les cles sont sur le filesystem et ce bloc est ignore.

JWT_DIR="/var/www/html/config/jwt"

if [ -n "$JWT_SECRET_KEY_BASE64" ]; then
    mkdir -p "$JWT_DIR"
    printf '%s' "$JWT_SECRET_KEY_BASE64" | base64 -d > "$JWT_DIR/private.pem"
    chown www-data:www-data "$JWT_DIR/private.pem"
    chmod 644 "$JWT_DIR/private.pem"
fi

if [ -n "$JWT_PUBLIC_KEY_BASE64" ]; then
    mkdir -p "$JWT_DIR"
    printf '%s' "$JWT_PUBLIC_KEY_BASE64" | base64 -d > "$JWT_DIR/public.pem"
    chown www-data:www-data "$JWT_DIR/public.pem"
    chmod 644 "$JWT_DIR/public.pem"
fi

# Appliquer les migrations Doctrine en attente
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration 2>&1 || true

exec "$@"
