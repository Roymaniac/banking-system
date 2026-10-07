#!/bin/sh
set -eu

umask 077

backup_directory="${BACKUP_DIRECTORY:-/backups}"
timestamp="$(date -u +%Y%m%dT%H%M%SZ)"
backup_name="${POSTGRES_DB}_${timestamp}.dump"
backup_path="${backup_directory}/${backup_name}"

mkdir -p "${backup_directory}"
database_user="${BACKUP_POSTGRES_USER:-${POSTGRES_USER}}"
export PGPASSWORD="${BACKUP_POSTGRES_PASSWORD:-${POSTGRES_PASSWORD}}"

echo "Creating a consistent PostgreSQL backup of database [${POSTGRES_DB}]..."

pg_dump \
    --host="${POSTGRES_HOST:-postgres}" \
    --port="${POSTGRES_PORT:-5432}" \
    --username="${database_user}" \
    --dbname="${POSTGRES_DB}" \
    --format=custom \
    --compress=9 \
    --no-owner \
    --no-privileges \
    --file="${backup_path}"

# Listing the archive makes PostgreSQL read its catalogue and catches an
# incomplete or malformed dump before the backup is reported as successful.
pg_restore --list "${backup_path}" >/dev/null
sha256sum "${backup_path}" > "${backup_path}.sha256"

echo "Backup created: ${backup_path}"
echo "Checksum created: ${backup_path}.sha256"

