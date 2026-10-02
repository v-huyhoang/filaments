# Phase 03 - Tenant Management

## Goal

Create landlord-scoped tenant profiles independent from room assignment and contract history.

---

## Scope

Implement:

- `tenants` table
- `Tenant` model
- `TenantStatus` enum if useful
- landlord ownership
- Filament `TenantResource`
- search/filter/sort
- policy
- factory
- tests

Suggested fields:

```text
landlord_id
full_name
phone
email
date_of_birth
identity_number
identity_issued_date
identity_issued_place
address
emergency_contact_name
emergency_contact_phone
status
notes
```

Statuses:

```text
active
inactive
blacklisted
```

---

## Rules

- tenant belongs to landlord context
- tenant does not directly belong to a room
- room relationship must be derived through contracts
- contract history must survive tenant inactivity
- phone should be normalized consistently
- email may be nullable
- identity-number uniqueness must follow documented business policy
- deleting a tenant with historical contracts should be prevented or replaced by soft-delete/inactive status
- status changes must not rewrite historical contracts

---

## Security

Tenant identity data is sensitive.

Requirements:

- tenant data must be landlord-scoped
- identity number should not appear unnecessarily in table views
- do not log full identity numbers
- manager needs explicit tenant permission
- search/autocomplete must not leak another landlord's tenants
- identity fields should be excluded from generic debug logging where practical

---

## Performance

Potential indexes:

```text
tenants(landlord_id)
tenants(landlord_id, status)
tenants(phone)
```

Add identity/email indexes only when real lookup patterns justify them.

---

## Expected

Landlord can manage tenant profiles independently from tenancy history.

Future contract flow can reference:

```text
Tenant ↔ Contract ↔ Room
```

---

## Acceptance Criteria

- tenant can be created/updated
- tenant belongs to current landlord automatically
- cross-landlord access fails
- sensitive fields are not unnecessarily exposed
- search/filter works
- manager permission is enforced
- historical-use strategy is tested/documented

---

## Out of Scope

- Tenant login
- Contracts
- Identity verification
- Document upload
- Messaging
- Tenant scoring
- Public tenant registration
