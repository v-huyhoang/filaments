# Phase 04 - Contract Management

## Goal

Model the rental agreement between tenant and room and make room occupancy a controlled business workflow.

---

## Scope

Implement:

- `contracts` table
- `Contract` model
- `ContractStatus` enum
- `ContractResource`
- `CreateContractAction`
- `ActivateContractAction` if separated
- `TerminateContractAction`
- overlap detection
- room status synchronization
- transaction handling
- policies
- tests

Suggested fields:

```text
room_id
tenant_id
start_date
end_date
rent_amount
deposit_amount
payment_due_day
status
signed_at
terminated_at
notes
```

Statuses:

```text
draft
active
expired
terminated
cancelled
```

---

## Rules

- room and tenant must belong to same landlord
- `start_date <= end_date` when end date exists
- rent amount >= 0
- deposit amount >= 0
- payment due day must follow documented supported range
- one room must not have overlapping active tenancy periods
- activating contract changes room to occupied where applicable
- terminating/expiring the last active contract changes room to available
- room base-rent changes must not modify contract rent
- active contract room/tenant references should not be freely editable
- contract activation/termination is an Action, not a plain status field update
- inactive/maintenance room cannot receive an active contract unless explicitly allowed by business rule

---

## Transaction Boundary

Contract activation should atomically:

```text
authorize
lock/check room
check overlaps
create/update contract
update room status
persist audit context
```

Termination should atomically:

```text
authorize
terminate contract
check remaining active tenancy
update room status
```

---

## Concurrency

Two concurrent active-contract attempts for the same room must not both succeed.

Use:

- transaction
- row locking where useful
- overlap query
- database invariants where possible

Do not rely solely on Filament validation.

---

## Security

- `room_id` must be tenant-scoped
- `tenant_id` must be tenant-scoped
- manager requires contract permission
- tenant cannot modify landlord-controlled financial terms
- direct IDs cannot bypass ownership checks

---

## Performance

Recommended indexes:

```text
contracts(room_id, status)
contracts(tenant_id, status)
contracts(room_id, start_date, end_date)
```

Overlap queries must be reviewed with `EXPLAIN` once realistic data exists.

---

## Expected

System can safely represent:

```text
Tenant
   ↓
Contract
   ↓
Room
```

Room occupancy is no longer manually managed independently from active contracts.

---

## Acceptance Criteria

- valid contract can be created
- invalid date range fails
- cross-landlord references fail
- overlapping active contract fails
- activation updates room occupancy
- termination restores room availability when appropriate
- transaction rolls back on failure
- concurrency-sensitive logic has tests where feasible
- historical rent remains unchanged after room-price update

---

## Out of Scope

- Contract PDF
- E-signature
- Deposit return
- Renewal automation
- Multiple tenants per contract
- Legal templates
- Invoice generation
