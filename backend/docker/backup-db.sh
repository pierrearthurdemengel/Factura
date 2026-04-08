#!/bin/sh
# Sauvegarde quotidienne de la BDD vers S3 Scaleway.
# A executer via cron Fly.io ou manuellement : fly ssh console -C "/var/www/html/docker/backup-db.sh"
#
# Prerequis en prod :
#   - DATABASE_URL configure dans les secrets Fly.io
#   - S3_BUCKET, S3_REGION, S3_ENDPOINT, S3_KEY, S3_SECRET configures
#   - aws cli installe dans le container (ou script adapte pour curl S3)

set -e

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="/tmp/factura_backup_${TIMESTAMP}.sql.gz"

# Extraction des parametres de connexion depuis DATABASE_URL
DB_HOST=$(echo "$DATABASE_URL" | sed -E 's|.*@([^:]+):.*|\1|')
DB_PORT=$(echo "$DATABASE_URL" | sed -E 's|.*:([0-9]+)/.*|\1|')
DB_NAME=$(echo "$DATABASE_URL" | sed -E 's|.*/([^?]+).*|\1|')
DB_USER=$(echo "$DATABASE_URL" | sed -E 's|.*://([^:]+):.*|\1|')

echo "[$(date)] Debut de la sauvegarde de ${DB_NAME}..."

pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" "$DB_NAME" | gzip > "$BACKUP_FILE"

echo "[$(date)] Sauvegarde compressee : $(du -h "$BACKUP_FILE" | cut -f1)"

# Upload vers S3 Scaleway
S3_PATH="backups/${TIMESTAMP}/factura_backup.sql.gz"

if command -v aws > /dev/null 2>&1; then
    aws s3 cp "$BACKUP_FILE" "s3://${S3_BUCKET}/${S3_PATH}" \
        --endpoint-url "${S3_ENDPOINT}" \
        --region "${S3_REGION}"
    echo "[$(date)] Upload S3 termine : s3://${S3_BUCKET}/${S3_PATH}"
else
    echo "[$(date)] ATTENTION : aws cli non disponible. Sauvegarde locale uniquement : ${BACKUP_FILE}"
    exit 1
fi

# Nettoyage du fichier temporaire
rm -f "$BACKUP_FILE"

echo "[$(date)] Sauvegarde terminee avec succes."
