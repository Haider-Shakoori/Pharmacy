# Backup & Recovery — Batch 28

Batch 28 adds database-level disaster recovery for the BusinessOS Pharmacy
control plane and every pharmacy tenant database.

## Backup contents

A normal `pharmacy:backup:create` run snapshots:

1. the central SaaS database; and
2. every tenant pharmacy database.

Each backup is stored in a timestamped directory beneath `BACKUP_ROOT`.
The manifest contains only operational metadata, file sizes and SHA-256
checksums. Database passwords, cPanel tokens, `.env`, APP_KEY and the Ed25519
private signing key are deliberately excluded.

The current pharmacy system keeps its operational system of record in the
databases. If future modules add user-uploaded documents or product media,
those files must be added to the disaster-recovery policy separately.

## Supported database engines

- SQLite: consistent file snapshot after WAL checkpoint plus
  `PRAGMA integrity_check`.
- MySQL / MariaDB: compressed `mysqldump` using
  `--single-transaction --quick --skip-lock-tables`.

MySQL credentials are passed through the process environment using
`MYSQL_PWD`; passwords are not placed in the command arguments.

## Commands

Create central + all tenant backups:

```bash
php artisan pharmacy:backup:create
```

Back up one or more tenants plus central:

```bash
php artisan pharmacy:backup:create --tenant=TENANT_ULID
```

Central only:

```bash
php artisan pharmacy:backup:create --central-only
```

Verify checksums and SQLite integrity:

```bash
php artisan pharmacy:backup:verify BACKUP_NAME
```

Restore is intentionally destructive and therefore requires both maintenance
mode and `--force`:

```bash
php artisan down
php artisan pharmacy:backup:restore BACKUP_NAME --central --force
# or
php artisan pharmacy:backup:restore BACKUP_NAME --tenant=TENANT_ULID --force
php artisan up
```

Restore always verifies the entire backup before replacing a database and
refuses a database-engine mismatch.

Prune older backups while retaining the newest configured count:

```bash
php artisan pharmacy:backup:prune
```

## Scheduling on shared hosting / cPanel

For production, set `BACKUP_SCHEDULE_ENABLED=true` explicitly after choosing
and testing the backup destination. The default run times are 02:15 for backup
and 03:15 for pruning in the server scheduler context. The example environment
keeps scheduling disabled until deployment configuration is complete.

cPanel must invoke Laravel's scheduler every minute, for example:

```bash
* * * * * cd /home/ACCOUNT/pharmacy.businessos.af && php artisan schedule:run >> /dev/null 2>&1
```

The exact PHP binary/path should match the hosting account.

## Offsite rule

A backup stored only on the same hosting account is not a disaster-recovery
copy. Point `BACKUP_ROOT` to a protected non-public location and replicate
the completed directories offsite using the hosting provider's backup facility
or another secured storage process.

Keep a separate secure escrow of the production `.env`, APP_KEY, cPanel
credentials and Ed25519 signing keys. Never commit those secrets to Git.

## Recovery drill

At least monthly:

1. verify the newest backup;
2. restore it into an isolated staging environment;
3. run migrations/status checks without modifying the backup;
4. verify platform login, one tenant login, stock totals and recent sales;
5. record the recovery timestamp and operator.

A backup is not considered reliable until a restore drill has succeeded.
