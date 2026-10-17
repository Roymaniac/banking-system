# Operator resource lookup contract

The operations portal uses these read-only APIs to search for recognizable
records and submit their IDs through the existing mutation endpoints. A lookup
never grants permission to perform the selected action. Every mutation repeats
its current authorization, status, balance, uniqueness, and concurrency checks.

All endpoints require authentication, a current privileged staff token, the
`operator-api` rate limit, and the permission listed below. Responses include
`Cache-Control: no-store`.

## Shared search and pagination

- `search` is trimmed, case-insensitive, and limited to 100 characters.
- Names use prefix matching. Exact account numbers, contacts, UUIDs, and the
  display references returned by these APIs are also accepted where applicable.
- New lists default to 10 records and accept `per_page` from 1 through 50.
- Ordering always ends with the resource UUID so pages remain deterministic.
- Lists use `data.items` and `meta.page`, `per_page`, `total`, and `last_page`.
- Detail endpoints resolve a previously selected ID even when it has since
  become ineligible. The response then contains a safe reason code.
- Full account numbers, addresses, dates of birth, identity documents,
  credentials, MFA records, tokens, and sessions are never returned.

## Accounts

`GET /api/v1/operations/accounts` and
`GET /api/v1/operations/accounts/{account}` require `purpose`:

| Purpose | Required permission | Search-time eligibility |
| --- | --- | --- |
| `provision` | `accounts.provision` | Pending, no number or ledger, approved due diligence |
| `freeze` | `accounts.freeze` | Active |
| `unfreeze` | `accounts.freeze` | Frozen |
| `close` | `accounts.close` | Active/frozen with a zero projected balance |
| `deposit` | `transactions.deposit` | Active with an authoritative ledger |
| `withdrawal` | `transactions.withdraw` | Active with an authoritative ledger |
| `transaction-report` | `transaction_reports.view` | Has an authoritative ledger; historical statuses remain searchable |
| `audit` | `audit.view` | Any stored account |

Optional filters are `customer_id`, `status`, `currency`, and `type`.
`reference` is a stable display reference derived from the account UUID; the
existing mutation routes still receive the `id` UUID.

```json
{
  "data": {
    "items": [
      {
        "id": "30000000-0000-4000-8000-000000000001",
        "display_label": "Ada Okafor · Account ending 0001 · NGN",
        "reference": "ACCOUNT-30000000000040008000000000000001",
        "customer": {
          "id": "20000000-0000-4000-8000-000000000001",
          "display_name": "Ada Okafor",
          "reference": "CUSTOMER-20000000000040008000000000000001"
        },
        "account_last_four": "0001",
        "type": "savings",
        "currency": "NGN",
        "status": "active",
        "eligible": true,
        "ineligibility_reason_code": null,
        "version": 2,
        "created_at": "2026-10-16T09:00:00+00:00"
      }
    ]
  },
  "meta": { "page": 1, "per_page": 10, "total": 1, "last_page": 1 }
}
```

## Customers

`GET /api/v1/operations/customers` and
`GET /api/v1/operations/customers/{customer}` require
`customer_reports.view`. Search accepts a name prefix, customer UUID/display
reference, exact stored contact, or exact account number. Results expose only a
masked contact discriminator. Use the account list's `customer_id` filter for
paginated linked accounts.

```json
{
  "id": "20000000-0000-4000-8000-000000000001",
  "display_name": "Ada Okafor",
  "display_label": "Ada Okafor · a•••@example.com",
  "reference": "CUSTOMER-20000000000040008000000000000001",
  "masked_contact": "a•••@example.com"
}
```

## Staff candidates

`GET /api/v1/administration/staff-candidates` and
`GET /api/v1/administration/staff-candidates/{user}` require `staff.manage`.
The list includes only verified identities not linked to staff. Detail
resolution can return `email_not_verified` or `already_staff` so a stale
selection is understandable. `user_id` is the Identity user UUID expected by
`POST /api/v1/administration/staff`; it is not a staff or customer UUID.

The staff mutation independently requires an existing verified identity and a
unique staff link, so direct or stale UUID submission cannot bypass onboarding.

```json
{
  "user_id": "10000000-0000-4000-8000-000000000006",
  "display_name": "Nkiru Candidate",
  "display_label": "Nkiru Candidate · n•••@example.com",
  "masked_email": "n•••@example.com",
  "eligible": true,
  "ineligibility_reason_code": null
}
```

## Original ledger entries for reversal

`GET /api/v1/operations/ledger-entries?purpose=reversal` and
`GET /api/v1/operations/ledger-entries/{entry}` require
`transactions.reverse`. Search uses the original business reference and may be
narrowed by `account_id`, `customer_id`, `from`, and `to`. `entry_id` is the
exact original ledger-entry UUID expected by the existing reversal mutation;
it is never a transaction, ledger, or posting UUID.

List results contain the transaction type, amount, currency, masked account and
customer context, and whether a reversal already exists. Detail adds the
original postings. The reversal command atomically rechecks that the entry is
posted, has not already been reversed, and has sufficient credited balances.

## Existing administration selectors

Continue using the existing paginated department, staff, role, and permission
list/detail endpoints. They now have deterministic UUID tie-break ordering.
Staff responses additionally provide `display_name`, `display_label`, and
`masked_email`; staff search includes name and email, and `department_id` can
limit the list. Relationship writes remain protected by their existing mutation
permissions and duplicate/self-protection rules.

## Audit identifier namespaces

Audit filters deliberately remain typed investigation controls rather than one
generic UUID picker:

| Filter | Namespace |
| --- | --- |
| Activity `actor_id` | Numeric Laravel `users.id`; `actor_type` is currently `App\\Models\\User`. It is not an Identity user or staff UUID. |
| Identity security-event `subject_id` | Identity `UserId` UUID. Failed unknown-email logins instead have a null subject and a one-way fingerprint. |
| `transaction.transfer_velocity_blocked` subject | Account UUID. |
| `operations.money_movement_break_glass_resumed` subject | Operator Identity `UserId` UUID. |
| Domain-event `aggregate_id` | The UUID represented by `aggregate_type`, which is the fully-qualified identifier value-object class, such as `AccountId`, `CustomerId`, `UserId`, `LedgerEntryId`, `LedgerId`, `DepartmentId`, `StaffId`, `RoleId`, `PermissionId`, or `TransactionId`. |
| `correlation_id` | Request/workflow trace UUID, not a business resource identifier. |

Typed audit resolvers remain a separate design because an audit-only operator
must not need broad customer or staff-administration access. Until those
resolvers are added, the UI should present these as advanced exact filters and
must not treat the UUID namespaces as interchangeable.

## Confirmed contract decisions

- Existing permissions are reused; no new `accounts.view` or `customers.view`
  permission was introduced.
- Existing mutation URL and request ID contracts are unchanged.
- Full account numbers and full candidate emails are not returned by selectors.
- Page-number pagination remains consistent with the current administration
  API. Cursor pagination can be introduced later without silently changing
  these contracts.
- Audit typed resolvers and role-scoped assignment pagination are intentionally
  deferred; their current semantics are documented above rather than guessed.
