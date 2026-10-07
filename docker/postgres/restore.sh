#!/bin/sh
set -eu

backup_directory="${BACKUP_DIRECTORY:-/backups}"
restore_database="${RESTORE_DATABASE:-banking_restore}"

if [ -z "${BACKUP_FILE:-}" ]; then
    echo "BACKUP_FILE must name a .dump file from the backup directory." >&2
    exit 1
fi

if [ "${BACKUP_FILE}" != "$(basename "${BACKUP_FILE}")" ]; then
    echo "BACKUP_FILE must be a file name without directory components." >&2
    exit 1
fi

case "${BACKUP_FILE}" in
    *.dump) ;;
    *)
        echo "BACKUP_FILE must end with .dump." >&2
        exit 1
        ;;
esac

if ! printf '%s' "${restore_database}" | grep -Eq '^[a-zA-Z_][a-zA-Z0-9_]*$'; then
    echo "RESTORE_DATABASE must be a valid PostgreSQL database name." >&2
    exit 1
fi

if [ "${restore_database}" = "${POSTGRES_DB}" ]; then
    echo "Refusing to overwrite the live database [${POSTGRES_DB}]." >&2
    exit 1
fi

backup_path="${backup_directory}/${BACKUP_FILE}"
checksum_path="${backup_path}.sha256"

if [ ! -f "${backup_path}" ] || [ ! -f "${checksum_path}" ]; then
    echo "The backup and its .sha256 checksum file must both exist." >&2
    exit 1
fi

cd "${backup_directory}"
sha256sum --check "$(basename "${checksum_path}")"
pg_restore --list "${backup_path}" >/dev/null

database_user="${RESTORE_POSTGRES_USER:-${POSTGRES_USER}}"
export PGPASSWORD="${RESTORE_POSTGRES_PASSWORD:-${POSTGRES_PASSWORD}}"
postgres_host="${POSTGRES_HOST:-postgres}"
postgres_port="${POSTGRES_PORT:-5432}"

if psql \
    --host="${postgres_host}" \
    --port="${postgres_port}" \
    --username="${database_user}" \
    --dbname="${POSTGRES_DB}" \
    --tuples-only \
    --no-align \
    --command="SELECT 1 FROM pg_database WHERE datname = '${restore_database}'" \
    | grep -q 1; then
    echo "Recovery database [${restore_database}] already exists; refusing to replace it." >&2
    exit 1
fi

createdb \
    --host="${postgres_host}" \
    --port="${postgres_port}" \
    --username="${database_user}" \
    "${restore_database}"

cleanup_failed_restore() {
    echo "Restore failed; removing the incomplete recovery database." >&2
    dropdb \
        --host="${postgres_host}" \
        --port="${postgres_port}" \
        --username="${database_user}" \
        --if-exists \
        "${restore_database}"
}

trap cleanup_failed_restore INT TERM HUP EXIT

pg_restore \
    --host="${postgres_host}" \
    --port="${postgres_port}" \
    --username="${database_user}" \
    --dbname="${restore_database}" \
    --exit-on-error \
    --no-owner \
    --no-privileges \
    "${backup_path}"

# A usable application recovery must contain Laravel's migration history.
migration_count="$(psql \
    --host="${postgres_host}" \
    --port="${postgres_port}" \
    --username="${database_user}" \
    --dbname="${restore_database}" \
    --tuples-only \
    --no-align \
    --command='SELECT COUNT(*) FROM migrations')"

trap - INT TERM HUP EXIT

echo "Backup restored into isolated database [${restore_database}]."
echo "Verified migration records: ${migration_count}"
echo "Point a temporary application instance at this database for functional checks."

