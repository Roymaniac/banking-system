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

## Database backup and recovery

Create a verified PostgreSQL archive with:

```bash
docker compose --env-file=.env.docker run --rm backup
```

The maintenance service stores the archive and its SHA-256 checksum in the ignored `backups/` directory. Backup files contain sensitive banking data and must be copied to encrypted, access-controlled offsite storage.

Restoration is intentionally limited to a separate recovery database; the provided tooling refuses to overwrite the configured live database. The complete restore drill, validation checklist, and production incident procedure are documented in [docs/disaster-recovery.md](docs/disaster-recovery.md).

## Releases

Pushing a semantic-version tag such as `v1.0.0` runs a separate release gate and publishes multi-platform `app` and `web` images to GitHub Container Registry. Published images include a software bill of materials, build provenance, and a signed GitHub artifact attestation.

Production Compose deployments can select registry images through `APP_IMAGE` and `WEB_IMAGE` in `.env.docker`; local development keeps using the local image names by default. Prefer immutable image digests for production rollouts.

The complete tagging, registry authentication, migration-first deployment, rollback, and provenance-verification procedure is documented in [docs/releasing.md](docs/releasing.md).

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

Customer endpoints verify ownership using the authenticated user's linked identity. Ordinary tokens expire after 24 hours by default.

Administrative, operational, reporting, audit, and notification endpoints require a short privileged staff session in addition to their explicit permission. An active staff member obtains one by confirming their password with an ordinary token:

```http
POST /api/v1/auth/privileged-access
Authorization: Bearer ORDINARY_TOKEN
Content-Type: application/json

{
  "password": "current-password",
  "device_name": "operations-workstation"
}
```

Use the returned bearer token for privileged requests. It expires after 15 minutes by default, and a staff deactivation or removed permission still takes effect through the authorization check. Successful and rejected privileged-access attempts are written to the security audit trail.

## Customer identity verification

Customers submit identity evidence through `POST /api/v1/customer/compliance/due-diligence`. The raw document number is encrypted at rest and is never returned by the API; responses show only its final four characters. A separate keyed fingerprint detects one document being reused across customers without storing a searchable plain-text number.

Authorized compliance staff use the privileged review endpoints under `/api/v1/compliance/due-diligence`. The person whose identity is being checked cannot approve or reject their own case. Rejected evidence can be corrected and resubmitted, while every submission and decision is kept in an append-only history. An account cannot be activated until its customer's case is approved.

After approval, staff with the `accounts.provision` permission can call `POST /api/v1/operations/accounts/{account}/provision`. One database transaction assigns the permanent account number, activates the pending account, and creates its authoritative ledger. If any step fails, none of those changes are kept.

Privileged staff can restrict accounts through the freeze and unfreeze operations using the `accounts.freeze` permission. Permanent closure requires `accounts.close`, a controlled closure reason, and a zero ledger balance. These operations share account-row locks with money movement so a status decision cannot race with an already-authorized transaction.

Production must provide an independent secret of at least 32 characters. Do not reuse `APP_KEY`:

```dotenv
KYC_DOCUMENT_FINGERPRINT_KEY=
```

## Request limits

The API applies separate per-minute budgets to login attempts, routine customer requests, money movement, privileged operations, and reporting. Authenticated budgets are isolated per user. Login keys combine the client address with a one-way hash of the normalized email address, so the email is not exposed in cache keys.

Clients that exceed a budget receive HTTP `429 Too Many Requests` with a `Retry-After` header. The limits can be adjusted without changing code:

```dotenv
RATE_LIMIT_LOGIN_PER_MINUTE=6
RATE_LIMIT_LOGIN_IP_PER_MINUTE=30
RATE_LIMIT_PRIVILEGED_AUTH_PER_MINUTE=5
RATE_LIMIT_CUSTOMER_PER_MINUTE=120
RATE_LIMIT_MONEY_MOVEMENT_PER_MINUTE=20
RATE_LIMIT_OPERATOR_PER_MINUTE=60
RATE_LIMIT_REPORTING_PER_MINUTE=30
```

These controls reduce automated abuse and accidental request loops; they do not replace transaction idempotency or permission checks.

## API documentation

Interactive documentation is available at `/docs/api`. The OpenAPI JSON document is available at `/docs/api.json`.

Documentation is freely accessible only in the `local` environment. In other environments, the signed-in user must have the `documentation.view` permission.

Only `/api/v1` routes are included in the generated contract. Internal health and documentation routes are deliberately excluded.

## Money and dates

API money values use integer minor units and always include or derive a currency. For example, `1050` represents 10.50 in a currency with two decimal places. Floating-point values are not used for financial calculations.

Dates and times use ISO 8601. Stored operational timestamps are normalized to UTC.

Transaction references provide a second domain-level duplicate check. HTTP retry safety is described in the API idempotency section below.

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
OPERATIONS_PORTAL_URL=https://operations.example.com/money-movement/resume-requests
```

Email contents and recipients are encrypted while waiting in the outbox. The scheduler finds pending messages, and the queue worker performs delivery. Operators with `notifications.view` can inspect delivery health; `notifications.retry` is required to retry an exhausted message.

Creating a money-movement resume request also queues a privacy-safe email for every active, verified staff member who currently has `money_movement.approve`. The requester is excluded because approval must be independent. The email contains the request identifier, expiry time, and protected operations-portal link, but not the incident reason. The request and encrypted email intents are stored in one transaction so a partial notification cannot be reported as successful.

At least one independent eligible approver must exist when the request is submitted. If none exists, the API returns a conflict and rolls back both the request and any partial outbox work. Operators must restore staff access, role assignment, permission, or email verification before trying again.

When a current pending request enters its final ten minutes, the scheduler queues one reminder to the same eligible reviewers. The persisted `reminded_at` timestamp prevents repeated reminders, and stale, completed, expired, or superseded requests are never reminded. If reviewer coverage temporarily disappears, `reminded_at` stays empty so the scheduler can retry after coverage is restored.

When a request is approved, rejected, cancelled, expired, or superseded, the requester receives a durable outcome email. It contains only the request identifier, final status, and protected portal link; incident evidence and closure reasons remain inside the authenticated operations interface.

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
- Operators can verify the journal and balance projections without changing them:

```bash
php artisan banking:reconcile-ledger
```

The command exits unsuccessfully when a posted entry is unbalanced, an immutable projection contribution differs from its posting, or a stored ledger balance differs from totals recalculated from the journal. Treat any failure as a financial incident: preserve evidence and investigate rather than automatically rewriting balances.

The scheduler runs reconciliation hourly and stores only the latest safe status and mismatch counts. Protected readiness becomes unhealthy when reconciliation fails, has never run, or is older than two hours.

### Money-movement safety switch

Operators can stop new financial writes while keeping authentication, reports, health checks, and other read-only diagnostics available:

```bash
php artisan banking:money-movement:suspend "Investigating reconciliation incident INC-1234"
```

Deposits, withdrawals, transfers, batch transfers, and reversals share this database-backed transaction gate. A failed automated reconciliation suspends money movement automatically; a successful later check does not resume it. Resumption always requires an explicit operator decision after investigation. Every state change records an immutable operational event and reason.

Authorized operations tooling can use the protected API as well:

```text
GET  /api/v1/operations/money-movement
GET  /api/v1/operations/money-movement/events
GET  /api/v1/operations/money-movement/resume-requests
POST /api/v1/operations/money-movement/suspend
POST /api/v1/operations/money-movement/resume
POST /api/v1/operations/money-movement/resume-requests/{request}/approve
POST /api/v1/operations/money-movement/resume-requests/{request}/reject
POST /api/v1/operations/money-movement/resume-requests/{request}/cancel
```

Viewing current state requires `operations.view`, reviewing its bounded event history requires `money_movement.audit`, and changing the switch requires `money_movement.manage`. API suspension is immediate. API resumption creates a 30-minute approval request that a different operator with `money_movement.approve` can discover in the bounded approval queue and approve. The scheduler marks elapsed requests as expired every minute. API actions record both authenticated identities and the reason.

Each new API resume request must also provide an `incident_reference` and a 30–2,000 character `evidence_summary`. The reference connects the decision to the external incident or change record, while the summary gives the independent reviewer enough operational evidence to evaluate the request. Both remain in the protected approval history; neither is placed in notification emails.

The requester may cancel their own pending request, while a different approver may reject it with a review reason. Cancellation, rejection, and expiry always leave money movement suspended.

Every approval request is also tied to the safety switch revision that existed when the request was created. If another incident changes the suspension reason, the old request becomes `superseded` and cannot reopen money movement. The incident must be investigated and a new approval request submitted.

In production, the CLI cannot perform an ordinary resume; use the protected two-person API workflow. If that workflow itself is unavailable during a severe incident, an operator with the dedicated `money_movement.break_glass` permission may use the emergency path:

```bash
php artisan banking:money-movement:resume "Primary approval tooling is unavailable" --break-glass --operator=OPERATOR-USER-UUID --incident=INC-1234
```

The command requires an explicit confirmation (or `--yes` in a controlled non-interactive run), records the operator and incident on the switch event, and creates a critical security event. Infrastructure access logs must independently identify the person using the production shell; the UUID supplied to the command is validated for permission but is audit attribution, not shell authentication.

- Duplicate transaction references are rejected.
- Reversals create opposite entries and preserve the original audit trail.
- Domain events and HTTP activity avoid exposing passwords, tokens, full account numbers, or transaction amounts unnecessarily.

## Health endpoints

`GET /up` is the public liveness endpoint. It answers the simple question: “Is the application process responding?”

`GET /api/v1/operations/health` is the protected readiness endpoint. It checks operational dependencies such as the database, scheduler heartbeat, queue failures, and notification backlog. It requires the `operations.view` permission.

While money movement is suspended, readiness also reports safe approval-workflow signals: whether the current request is pending, expiring within ten minutes, or overdue, plus bounded counts and the next expiry time. It never exposes request IDs, incident references, reasons, or evidence.

## API idempotency

Money-changing API calls require an `Idempotency-Key` header containing 8 to 100 safe characters. A client should generate one unique key for each intended operation and keep the same key when retrying after a timeout or lost response. An identical retry returns the original response with `Idempotency-Replayed: true`; changing the request while reusing its key returns HTTP 409. Successful responses are retained for 24 hours by default, controlled by `IDEMPOTENCY_RETENTION_HOURS`.

## Observability

Every HTTP response includes an `X-Request-ID` that connects the response to structured Laravel and Nginx logs. Completion logs use safe route names and omit raw URLs, query strings, request bodies, and route parameters so operational tracing does not unnecessarily copy banking data.

Production container logs use JSON. The monitoring platform should collect them from standard output and error, monitor both liveness and protected readiness, and alert on database failure, stale scheduler heartbeats, failed jobs, exhausted emails, elevated server errors, abnormal latency, and unusual rate limiting.

See [docs/observability.md](docs/observability.md) for log fields, recommended starting alerts, investigation steps, retention, and access guidance.

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
composer security:check
composer test
composer test:postgresql
```

`composer test:postgresql` requires a PostgreSQL test database plus the PHP `pdo_pgsql` and `pcntl` extensions. It starts real competing processes and verifies that the database locks allow only one withdrawal to spend shared funds and only one operator to approve a resume request.

The security check audits the exact versions in `composer.lock` against Composer's vulnerability advisory database. GitHub Actions repeats these checks, verifies migrations on clean SQLite and PostgreSQL databases, runs the PostgreSQL concurrency suite, validates the Compose configuration, and builds both production images.

## Production checklist

Before deployment:

1. Use PHP 8.4.1 or newer and run `composer install --no-dev --classmap-authoritative`, or deploy the repository's production application image.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, and a production `APP_URL`.
3. Provide securely generated, different values for `APP_KEY` and `KYC_DOCUMENT_FINGERPRINT_KEY`; changing them later affects encrypted data or identity-document matching.
4. Configure a production database, cache, queue, mail transport, and HTTPS.
5. Configure settlement-ledger UUIDs for every enabled currency.
6. Configure the customer-facing verification and password-reset URLs and the protected operations-portal URL.
7. Run `php artisan migrate --force` during a controlled deployment.
8. Run persistent queue workers and the scheduler under process supervision.
9. Cache Laravel configuration and routes after environment values are final.
10. Restrict documentation and operational permissions to authorized staff.
11. Monitor `/up`, protected readiness status, failed jobs, logs, and exhausted outbox messages.
12. Configure encrypted offsite database backups and complete the documented restoration drill before handling real funds.

After building the release image and running migrations, execute the production preflight before starting request-processing services:

```bash
php artisan banking:production-preflight
```

The command checks production-safe configuration, settlement-ledger identifiers, and database connectivity without printing configured secrets. A failed check returns a non-zero exit code and means traffic must remain disabled.

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
