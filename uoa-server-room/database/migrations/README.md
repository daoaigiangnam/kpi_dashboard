# UOA Database Migrations

## Current baseline

- Migration runner: `database/migrate.php`
- Migration files: `database/migrations/*.sql`
- Database target: MySQL 8.0+, InnoDB, utf8mb4
- Timestamp storage: UTC
- Global FK policy: `ON DELETE RESTRICT` (ADR-014) + `ON UPDATE RESTRICT` (ADR-015)

## Commands

From the `uoa-server-room` directory:

```bash
php database/migrate.php status
php database/migrate.php migrate
```

The runner loads the existing Composer autoloader, .env and PDO Database connection.

## Safety behavior

1. A MySQL advisory lock prevents two migration runners from applying migrations concurrently.
2. Each SQL file is ordered by its three-digit prefix and tracked by name, SHA-256 checksum, batch and UTC execution time.
3. A migration checksum change after it has been applied is a hard error. Never edit an applied migration; add a new forward migration.
4. Before applying a migration, the runner checks that target tables do not already exist without a migration record. This deliberately blocks silent adoption of legacy/skeleton tables.
5. MySQL DDL may implicitly commit. If a migration fails partway through, do not blindly rerun it or DROP tables. Inspect the actual schema and create an approved forward-fix.
6. The runner does not execute `database/schema.sql`. That file is a legacy bootstrap skeleton and is not the authoritative migration path.

## First migration

`001_create_core_entities.sql` creates only the first CORE foundation tables:

- users
- roles
- permissions
- server_rooms

It deliberately does not seed a default administrator or password. User credentials must be provisioned through a separate secure bootstrap procedure; never commit a real password or password hash.

## Important

The first migration has been committed to GitHub but has **not** been executed against any database in this workflow. Before running it on a server, verify the target database is a fresh/approved schema. If legacy tables already exist, stop and perform a schema inventory and explicit baseline reconciliation first.
