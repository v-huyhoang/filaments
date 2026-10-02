# Phase 09 - Authorization & Tenant Isolation

## Goal

Enforce secure landlord-scoped access and introduce role/capability-based authorization.

## Scope

Implement:

- system roles:
  - system_admin
  - landlord
  - manager
  - tenant
- Laravel Policies for core entities
- landlord tenant-scoping
- manager permissions
- authorization checks in Filament Resources and Actions
- negative authorization tests
- cross-landlord isolation tests

Core policies:

```text
PropertyPolicy
RoomPolicy
TenantPolicy
ContractPolicy
InvoicePolicy
PaymentPolicy
```

Manager capability examples:

```text
properties.view
rooms.view
rooms.update
tenants.view
tenants.update
contracts.create
contracts.update
meter_readings.create
invoices.view
payments.create
reports.view
```

## Rules

- Landlord can access only records owned by that landlord.
- Manager can access only landlord data they are assigned to.
- Manager capability must be checked in addition to ownership.
- Tenant access is limited to records explicitly related to that tenant.
- System admin privileges must not leak into landlord panel behavior.
- UI visibility is not an authorization mechanism.
- Every sensitive application Action must perform authorization or receive an already-authorized aggregate.
- Direct IDs from URL/request must never bypass tenant scope.
- Cross-landlord access is considered a critical security defect.

## Security

Test IDOR-style scenarios.

Examples:

```text
Landlord A requests Room B owned by Landlord B
Manager A requests Contract belonging to another landlord
Tenant A requests Invoice belonging to Tenant B
```

All must fail.

Do not expose unauthorized records through:

- search
- select options
- relation managers
- exports
- global search
- dashboard widgets

## Performance

Avoid adding repeated ownership queries where relationships already make ownership derivable.

Add indexes supporting tenancy filters where necessary.

Typical examples:

```text
properties(landlord_id)
managers(landlord_id)
contracts(room_id, status)
```

## Expected

After this phase:

- every existing core resource is protected
- landlord data isolation is enforced
- manager permissions can be configured
- unauthorized direct URL access fails
- cross-tenant data leakage tests pass

## Acceptance Criteria

- landlord can CRUD owned entities according to policy
- landlord cannot access another landlord's records
- manager can access only permitted capabilities
- manager cannot elevate own permissions
- tenant cannot access another tenant's records
- Filament navigation respects permissions
- direct URL access is still blocked even when navigation item is hidden
- automated authorization tests pass

## Out of Scope

- subscription feature limits
- audit log UI
- enterprise RBAC editor
- organization/team hierarchy
- SSO
- OAuth provider integrations
