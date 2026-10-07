# Backup and disaster recovery

This runbook explains how to protect and recover the banking system's durable data. PostgreSQL is the authoritative store for identities, accounts, ledger entries, balances, transaction records, audits, and the email outbox.

Redis is used for cache, sessions, rate limits, and queued work. It is configured with append-only persistence for local durability, but it is not treated as the financial system of record. After Redis loss, Laravel can rebuild caches and the database-backed email outbox can schedule pending delivery again.

## Recovery objectives

Before production launch, the operator must choose and document:

- **Recovery point objective (RPO):** the maximum amount of recently committed data the organization can afford to lose.
- **Recovery time objective (RTO):** the maximum acceptable time needed to restore service.

For example, an RPO of 15 minutes requires backups or continuous PostgreSQL archiving capable of recovering to within 15 minutes. A nightly dump alone cannot satisfy that target.

The included dump workflow is a dependable baseline and recovery-drill tool. A real banking deployment should additionally use the managed database provider's encrypted snapshots and point-in-time recovery.

Production environments should configure `BACKUP_POSTGRES_USER` with read access sufficient for `pg_dump`. `RESTORE_POSTGRES_USER` requires permission to create a separate recovery database. Keep both passwords in the deployment secret manager, not in source control.

## Create a backup

Start PostgreSQL if it is not already running:

```bash
docker compose --env-file=.env.docker up -d --wait postgres
```

Create and verify a database archive:

```bash
docker compose --env-file=.env.docker run --rm backup
```

The command writes two private files under `backups/`:

```text
banking_YYYYMMDDTHHMMSSZ.dump
banking_YYYYMMDDTHHMMSSZ.dump.sha256
```

The dump uses PostgreSQL's custom archive format. Before reporting success, the script asks `pg_restore` to read the archive catalogue and records a SHA-256 checksum.

The repository ignores backup contents because they contain sensitive customer and financial data. Copy both files to encrypted storage with restricted access, geographic separation, retention controls, and access auditing. Do not leave the only copy on the application server.

## Perform a restore drill

Choose the exact backup file name shown in `backups/`. Restore it into a separate database:

```bash
docker compose --env-file=.env.docker run --rm \
  -e BACKUP_FILE=banking_YYYYMMDDTHHMMSSZ.dump \
  -e RESTORE_DATABASE=banking_restore \
  restore
```

On PowerShell, the same command can be written on one line:

```powershell
docker compose --env-file=.env.docker run --rm -e BACKUP_FILE=banking_YYYYMMDDTHHMMSSZ.dump -e RESTORE_DATABASE=banking_restore restore
```

The restore process:

1. Rejects directory paths and unexpected file extensions.
2. Verifies the SHA-256 checksum.
3. Confirms PostgreSQL can read the archive catalogue.
4. Refuses to use the configured live database name.
5. Refuses to replace an existing recovery database.
6. Creates a new recovery database and restores into it.
7. Confirms that Laravel migration records exist.
8. Removes an incomplete recovery database if restoration fails.

Point a temporary, isolated application instance at the recovery database. Do not allow that instance to send real emails or execute real integrations.

## Validate recovered data

A successful `pg_restore` command is necessary but not enough. During every drill:

1. Run the protected readiness check against the isolated application.
2. Confirm migration status with `php artisan migrate:status`.
3. Compare important row counts with the source backup record.
4. Generate ledger and reconciliation reports for known periods.
5. Confirm posted entries remain balanced by currency.
6. Confirm audit records and the encrypted email outbox can be read.
7. Record the measured recovery time and whether it met the RTO.

Never perform financial test transactions against the production database during a recovery drill.

## Production recovery

For an actual incident:

1. Stop web, worker, and scheduler traffic to prevent additional writes.
2. Preserve the damaged database and its logs for investigation.
3. Restore the selected snapshot or archive into a new database instance.
4. Complete the validation checklist against an isolated application.
5. Update application secrets or database connection settings to select the recovered database.
6. Start the application in the documented migration-first order.
7. Monitor readiness, logs, failed jobs, outbox backlog, and ledger reconciliation.
8. Record the actual recovery point, recovery time, approvals, and any known data gap.

Do not use the included restore command to overwrite a live database. Recovery by switching to a separately validated database keeps rollback possible and preserves incident evidence.

## Recommended schedule

- Run managed encrypted snapshots at a frequency that satisfies the agreed RPO.
- Run the portable dump workflow at least daily.
- Copy verified archives off the application host immediately.
- Test a restore at least monthly and before major infrastructure changes.
- Review access to backup storage quarterly.
- Test point-in-time recovery at least quarterly when the database provider supports it.

Backups that have never been restored are not yet proven backups.

