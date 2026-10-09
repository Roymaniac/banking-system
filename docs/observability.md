# Production observability

Observability should answer three questions quickly:

1. Is the service available?
2. Which component is unhealthy or falling behind?
3. Which safe request identifier connects the customer-visible failure to application and web logs?

## Request identifiers

Every HTTP response includes `X-Request-ID`.

- A valid UUID supplied by a trusted client or gateway is preserved.
- Missing or malformed values are replaced with a generated UUID.
- Laravel includes the identifier in all log context written during that request.
- Nginx records the identifier returned by Laravel.

Support teams should ask for this identifier when investigating an API failure. Never ask a customer to send passwords, bearer tokens, complete account numbers, or full request bodies.

## Structured logs

Production containers use the `stderr` channel with Monolog's JSON formatter. Each successful request completion records:

- `request_id`
- HTTP method
- Laravel route name
- response status
- total duration in milliseconds
- whether the request was authenticated

Raw URLs, route parameters, query strings, request bodies, IP addresses, and user-agent strings are deliberately excluded from application completion logs. Nginx likewise logs timing and response information without the raw request target.

The `/up` liveness probe is excluded from application completion logs by default because frequent successful probes add noise. It can be enabled temporarily with:

```dotenv
OBSERVABILITY_EXCLUDE_LIVENESS_LOGS=false
```

Request completion logging itself can be disabled with `OBSERVABILITY_REQUEST_LOGS=false`, although production environments should normally keep it enabled.

## Health signals

Use the endpoints for different purposes:

- `GET /up` is public liveness. The container or load balancer uses it to determine whether Laravel responds.
- `GET /api/v1/operations/health` is protected readiness. Authorized operators use it to inspect the database, scheduler heartbeat, queue failures, and notification backlog.

Liveness does not prove the database schema or background processes are healthy. Readiness must be monitored separately using a dedicated operator credential stored in the monitoring platform's secret manager.

## Minimum alerts

Configure the monitoring platform to notify operators when:

| Signal | Suggested condition | Priority |
| --- | --- | --- |
| Liveness | `/up` fails for two consecutive probes | Critical |
| Readiness | Overall status is `unhealthy` | Critical |
| Scheduler | Heartbeat age exceeds 120 seconds | Critical |
| Database | Readiness reports database unhealthy | Critical |
| Reconciliation | Latest check fails or is older than two hours | Critical |
| Money movement | Safety switch is suspended | Critical |
| HTTP errors | 5xx responses exceed 1% for five minutes | Critical |
| Queue failures | `failed_jobs` is greater than zero | Warning |
| Email delivery | `exhausted_messages` is greater than zero | Warning |
| Backlog | Pending jobs or messages continuously increase | Warning |
| Latency | Route-specific p95 exceeds its agreed objective | Warning |
| Abuse | 429 responses increase sharply above normal traffic | Warning |

The percentage and latency thresholds are starting points, not universal banking objectives. Establish normal traffic baselines and tune them without weakening the immediate database, scheduler, or exhausted-message alerts.

## Investigation procedure

When an alert fires:

1. Record the alert start time and affected environment.
2. Check liveness and protected readiness.
3. Search application and Nginx logs by `request_id` when one is available.
4. Group failures by safe Laravel route name and response status.
5. Check scheduler freshness, failed jobs, and exhausted outbox messages.
6. Check the most recent deployment digest and migration run.
7. Run financial reconciliation reports if money movement may be affected.
   The read-only `php artisan banking:reconcile-ledger` command provides a deployment-friendly integrity check.
8. Preserve relevant logs and database evidence before recovery actions.
9. Record the resolution, customer impact, and follow-up work.

A reconciliation failure automatically suspends new money movement. It never automatically resumes it. After resolving the incident and independently verifying balances, an authorized operator must use `banking:money-movement:resume` with an auditable incident reason.

Operator tooling may instead use the permission-protected money-movement API. Grant `money_movement.manage` only to incident responders authorized to stop or request restoration of financial writes. API restoration requires a second operator with `money_movement.approve` within 30 minutes; that permission also exposes the bounded approval queue. The scheduler marks elapsed requests as expired every minute. Grant `money_movement.audit` to reviewers who need its bounded, filterable event history, and review those immutable records after every use.

Requesters should cancel obsolete requests, and approvers should reject requests whose evidence is insufficient. Both actions require a reason and leave financial writes suspended.

Approval requests record the safety-switch revision they reviewed. A new incident or changed suspension reason advances that revision, makes older requests `superseded`, and prevents a stale approval from reopening financial writes.

New requests queue encrypted email alerts for active, verified staff holding `money_movement.approve`, excluding the requester. Monitor the email outbox so exhausted approval alerts are investigated before the 30-minute approval window closes; reviewers can still discover requests through the protected queue.

Production CLI resumption is disabled by default so it cannot bypass two-person approval. Emergency `--break-glass` use requires a separately authorized operator UUID, an incident reference, and explicit confirmation. It emits a `critical` `operations.money_movement_break_glass_resumed` security event; alert immediately and review both the application record and infrastructure shell-access logs.

Do not place secrets or private financial details into incident tickets. Reference protected audit records by their identifiers instead.

## Retention and access

- Send container logs to durable centralized storage; containers are disposable.
- Encrypt log storage in transit and at rest.
- Restrict access to operational personnel with a documented need.
- Audit access to logs and monitoring dashboards.
- Define retention with legal and compliance stakeholders.
- Test that request IDs remain searchable across Nginx and Laravel logs after rotation.

Operational logs complement the immutable domain, activity, and security audit records. They do not replace those records.

