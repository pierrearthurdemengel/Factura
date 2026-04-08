#!/bin/sh
# Consomme les messages Doctrine toutes les 5 minutes.
# Limite de temps et de memoire pour eviter les fuites sur un container 256 Mo.
while true; do
    php /var/www/html/bin/console messenger:consume async --time-limit=60 --limit=20 --memory-limit=64M --env=prod --no-debug 2>&1
    sleep 300
done
