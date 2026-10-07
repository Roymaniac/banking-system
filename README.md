# Banking System

A modular Laravel API for customer onboarding, account management, double-entry ledger operations, money transfers, administration, reporting, auditing, and reliable email delivery.

The codebase follows Domain-Driven Design and keeps financial rules outside controllers and framework-specific code. Money is stored as integer minor units, financial writes run inside database transactions, and completed ledger entries are immutable.

## Main capabilities

- Sanctum bearer-token authentication
- Customer profiles, addresses, and contact details
- Multi-currency customer accounts
- Double-entry ledger with projected balances
- Deposits, withdrawals, transfers, batch transfers, and reversals
- Customer and bank-controlled daily transaction limits
- Departments, staff, roles, and permissions
- Customer, ledger, and transaction reports
- Domain-event, user-activity, and security audit trails
- Encrypted email outbox with retry support
- Liveness and protected readiness monitoring
- Generated OpenAPI documentation

## Requirements

- PHP 8.4.1 or newer
- Composer 2
- A supported database; SQLite is used by default for local development
- PHP extensions required by Laravel, including OpenSSL, PDO, Mbstring, Tokenizer, XML, and Ctype

Node.js is only needed when working on Vite-managed frontend assets. The banking API and its automated tests do not require a frontend development server.

## Local setup

Clone the repository and enter its directory, then install the locked dependencies:

```bash
composer install
```

Create the local environment file:

```bash
cp .env.example .env
```

On PowerShell, use:

```powershell
Copy-Item .env.example .env
```

Generate the Laravel encryption key:

```bash
php artisan key:generate
```

The default configuration uses `database/database.sqlite`. Create that file if it does not already exist:

```bash
touch database/database.sqlite
```

On PowerShell, use:

```powershell
New-Item database/database.sqlite -ItemType File -ErrorAction SilentlyContinue
```

Create the database tables:

```bash
php artisan migrate
```

For disposable local data, the optional development seeder creates a verified user with the email `test@example.com` and password `password`:

```bash
php artisan db:seed
```

Never run the development seeder in production.

Start the API:

```bash
php artisan serve
```

The default base URL is `http://127.0.0.1:8000/api/v1`.

## Background processes

The application needs both a queue worker and Laravel's scheduler. They perform different jobs.

The queue worker processes asynchronous work:

```bash
php artisan queue:work --tries=3
```

The scheduler dispatches pending email-outbox work and records a heartbeat every minute:

```bash
php artisan schedule:work
```

In production, use a process supervisor for queue workers and invoke `php artisan schedule:run` every minute with the operating system's scheduler. Do not rely on a manually opened terminal.

## Container deployment

The repository includes a production-oriented container setup with five separate responsibilities:

- `web` accepts HTTP traffic through Nginx.
- `app` executes Laravel requests through PHP-FPM.
- `worker` processes queued jobs.
- `scheduler` runs Laravel's scheduled commands.
- PostgreSQL and Redis provide local production-like infrastructure.

Create a private container environment file before the first build:

```bash
cp .env.docker.example .env.docker
```

On PowerShell, use:

```powershell
Copy-Item .env.docker.example .env.docker
```

Generate a fresh application key and place the complete output after `APP_KEY=` in `.env.docker`:

```bash
docker compose --env-file=.env.docker build app
docker compose --env-file=.env.docker run --rm --no-deps app php artisan key:generate --show
```

The example database password is only for local use. Replace it, the public URL, mail settings, frontend URLs, and settlement-ledger UUIDs before a real deployment.

Build the application and web images without starting request-processing services:

```bash
docker compose --env-file=.env.docker build app web
```

Start only PostgreSQL and Redis, then wait for their health checks to pass:

```bash
docker compose --env-file=.env.docker up -d --wait postgres redis
```

For an update, stop the old request, worker, and scheduler processes before changing the schema. On a fresh deployment this command is harmless because those containers do not exist yet:

```bash
docker compose --env-file=.env.docker stop web worker scheduler app
```

Run migrations with the newly built application image as a separate, controlled deployment step:

```bash
docker compose --env-file=.env.docker run --rm app php artisan migrate --force
```

Only after the migration succeeds, start the application-facing services:

```bash
docker compose --env-file=.env.docker up -d app worker scheduler web
```

The API is available at `http://localhost:8080` by default. Check container state and the public liveness route with:

```bash
docker compose --env-file=.env.docker ps
curl http://localhost:8080/up
```

The application container runs as an unprivileged user, enables OPcache, and writes logs to the container's standard error stream. Its startup script never runs database migrations. Keeping migrations between infrastructure startup and application startup prevents requests or scheduled work from reaching a schema that is not ready yet.

Stop the stack without deleting database or Redis data:

```bash
docker compose --env-file=.env.docker down
```

Adding `--volumes` deletes the local persistent data and should only be used when that data is intentionally disposable.

## API authentication

Obtain a Sanctum token through the login endpoint:

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "test@example.com",
  "password": "password",
  "device_name": "local-development"
}
```

Send the returned token with protected requests:

```http
Authorization: Bearer YOUR_TOKEN
Accept: application/json
```

Customer endpoints verify ownership using the authenticated user's linked identity. Administrative and operational endpoints additionally require explicit permissions such as `administration.view`, `transactions.deposit`, or `audit.view`.

## API documentation

Interactive documentation is available at `/docs/api`. The OpenAPI JSON document is available at `/docs/api.json`.

Documentation is freely accessible only in the `local` environment. In other environments, the signed-in user must have the `documentation.view` permission.

Only `/api/v1` routes are included in the generated contract. Internal health and documentation routes are deliberately excluded.

## Money and dates

API money values use integer minor units and always include or derive a currency. For example, `1050` represents 10.50 in a currency with two decimal places. Floating-point values are not used for financial calculations.

Dates and times use ISO 8601. Stored operational timestamps are normalized to UTC.

Transaction references provide idempotency. Repeating a financial request with an existing reference is rejected instead of posting the money twice.

## Trusted settlement configuration

Deposits and withdrawals are privileged bank operations. Their opposite ledger is selected from server configuration and can never be supplied by the caller.

Configure the applicable internal ledger UUIDs:

```dotenv
BANKING_NGN_DEPOSIT_LEDGER_ID=
BANKING_USD_DEPOSIT_LEDGER_ID=
BANKING_GBP_DEPOSIT_LEDGER_ID=
BANKING_NGN_WITHDRAWAL_LEDGER_ID=
BANKING_USD_WITHDRAWAL_LEDGER_ID=
BANKING_GBP_WITHDRAWAL_LEDGER_ID=
```

An unconfigured currency fails safely instead of creating an incomplete financial entry.

## Email configuration

Email-verification and password-reset links can target a separately hosted frontend:

```dotenv
FRONTEND_VERIFICATION_URL=https://example.com/verify-email
FRONTEND_PASSWORD_RESET_URL=https://example.com/reset-password
```

Email contents and recipients are encrypted while waiting in the outbox. The scheduler finds pending messages, and the queue worker performs delivery. Operators with `notifications.view` can inspect delivery health; `notifications.retry` is required to retry an exhausted message.

## Architecture

The business code is organized into bounded contexts under `src`:

```text
src/
├── Shared/          Common domain building blocks and infrastructure contracts
├── Identity/        Users, authentication, verification, and authorization
├── Customer/        Customer profiles, addresses, and contacts
├── Account/         Account lifecycle and account numbers
├── Ledger/          Double-entry journal, postings, and balance projections
├── Transaction/     Deposits, withdrawals, transfers, limits, and reversals
├── Notification/    Email templates, encrypted outbox, and delivery jobs
├── Audit/           Domain, activity, and security audit records
├── Reporting/       Read-optimized financial and customer reports
└── Administration/ Departments, staff, roles, and permissions
```

Each context uses the same dependency direction:

```text
HTTP controller → application use case → domain model
                              ↓
                    infrastructure adapter
```

- `Domain` contains business rules and must not depend on Laravel.
- `Application` coordinates use cases through interfaces.
- `Infrastructure` implements persistence, mail, queues, and other technical details.
- `app/Http` translates HTTP input into application commands and resources.

More implementation rules are documented in [docs/engineering-principles.md](docs/engineering-principles.md).

## Financial safety rules

- Every posted ledger entry must balance debits and credits.
- A posted entry cannot be modified.
- Balance-changing workflows lock the records needed for concurrency safety.
- Related financial records and projections are committed atomically.
- Duplicate transaction references are rejected.
- Reversals create opposite entries and preserve the original audit trail.
- Domain events and HTTP activity avoid exposing passwords, tokens, full account numbers, or transaction amounts unnecessarily.

## Health endpoints

`GET /up` is the public liveness endpoint. It answers the simple question: “Is the application process responding?”

`GET /api/v1/operations/health` is the protected readiness endpoint. It checks operational dependencies such as the database, scheduler heartbeat, queue failures, and notification backlog. It requires the `operations.view` permission.

## Quality checks

Run the complete local quality gate before opening a pull request:

```bash
composer quality
```

That command runs:

1. Laravel Pint formatting validation
2. PHPStan level 5 static analysis
3. OpenAPI generation analysis
4. The complete Pest test suite

Individual commands are also available:

```bash
composer format:check
composer analyse
composer openapi:check
composer test
```

GitHub Actions repeats these checks, verifies that all migrations run successfully on a clean SQLite database, validates the Compose configuration, and builds both production images.

## Production checklist

Before deployment:

1. Use PHP 8.4.1 or newer and run `composer install --no-dev --classmap-authoritative`, or deploy the repository's production application image.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, and a production `APP_URL`.
3. Provide a securely generated `APP_KEY`; changing it later makes existing encrypted outbox data unreadable.
4. Configure a production database, cache, queue, mail transport, and HTTPS.
5. Configure settlement-ledger UUIDs for every enabled currency.
6. Configure the customer-facing verification and password-reset URLs.
7. Run `php artisan migrate --force` during a controlled deployment.
8. Run persistent queue workers and the scheduler under process supervision.
9. Cache Laravel configuration and routes after environment values are final.
10. Restrict documentation and operational permissions to authorized staff.
11. Monitor `/up`, protected readiness status, failed jobs, logs, and exhausted outbox messages.
12. Back up the database and test restoration procedures before handling real funds.

Typical optimization commands are:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

After deploying new code, restart long-running workers so they load the new release:

```bash
php artisan queue:restart
```

## License

This repository is licensed under the MIT License unless the repository owner specifies otherwise.
