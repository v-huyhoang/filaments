# Architecture

## 1. System Overview

Rental Management System is a multi-tenant property-management application built with Laravel and Filament.

Primary actors:

- System Admin
- Landlord
- Manager
- Tenant

Primary business flow:

```text
Landlord
  ↓
Property
  ↓
Room
  ↓
Tenant / Contract
  ↓
Meter Reading / Service Charges
  ↓
Invoice
  ↓
Payment
  ↓
Reporting
```

The system should be designed as a modular monolith first.

Do not introduce microservices unless operational scale or deployment boundaries justify it.

---

## 2. Technology Stack

### Backend

- PHP 8.3+
- Laravel 12+
- Filament 5
- Livewire 4
- MySQL 8.4
- Redis

### Development

- Docker Compose for MySQL and Redis
- Pest or PHPUnit
- Laravel Pint
- PHPStan / Larastan

### Optional Later

- Queue worker
- Laravel Horizon
- Object storage
- Payment gateway
- Email / SMS providers
- Observability stack

---

## 3. Architectural Style

Use a layered modular-monolith architecture.

```text
Presentation
   ↓
Application
   ↓
Domain
   ↓
Infrastructure
```

Recommended dependency direction:

```text
Filament / HTTP / CLI
        ↓
Application Actions
        ↓
Domain Services / Rules
        ↓
Models / Repositories
        ↓
Database / External Services
```

Higher layers may depend on lower layers.

Lower layers must not depend on Filament.

---

## 4. Layers

### 4.1 Presentation Layer

Responsible for:

- Filament Resources
- Filament Pages
- Relation Managers
- Widgets
- Controllers
- API endpoints
- Request validation
- Rendering UI
- Calling application actions
- Showing notifications

Must not contain:

- Invoice calculations
- Cross-model business workflows
- Complex authorization logic
- Reusable domain rules
- Transaction orchestration

Example:

```text
ContractResource
    ↓
CreateContractAction
```

---

### 4.2 Application Layer

Responsible for business use cases.

Examples:

```text
CreateContractAction
TerminateContractAction
GenerateInvoiceAction
RecordPaymentAction
AssignManagerAction
CreateMaintenanceRequestAction
```

Responsibilities:

- orchestrate domain operations
- enforce use-case-level rules
- define transaction boundaries
- coordinate multiple models
- call domain services
- dispatch events when needed

Example:

```php
DB::transaction(function () {
    // validate availability
    // create contract
    // update room status
    // write audit record
});
```

---

### 4.3 Domain Layer

Contains business concepts and reusable rules.

Examples:

- RoomStatus
- ContractStatus
- InvoiceStatus
- PaymentStatus
- MaintenanceStatus
- BillingPeriod
- InvoiceCalculator
- ContractOverlapChecker
- SubscriptionLimitChecker

Rules must not depend on Filament.

---

### 4.4 Infrastructure Layer

Responsible for technical implementations:

- Eloquent persistence
- Redis cache
- queues
- email
- SMS
- payment gateway
- object storage
- external APIs

Infrastructure details should not leak into domain rules.

---

## 5. Suggested Project Structure

```text
app/
├── Actions/
│   ├── Contracts/
│   ├── Invoices/
│   ├── Payments/
│   ├── Maintenance/
│   └── Subscriptions/
│
├── Domain/
│   ├── Billing/
│   ├── Contracts/
│   ├── Properties/
│   └── Subscriptions/
│
├── Enums/
├── Filament/
│   ├── Admin/
│   └── Landlord/
│
├── Models/
├── Policies/
├── Services/
├── Support/
└── Providers/

docs/
├── architecture.md
├── domain.md
├── database.md
├── business-rules.md
└── phases/
```

Do not over-engineer domain folders before complexity exists.

Prefer simple application Actions first.

---

## 6. Multi-Tenancy

The initial tenancy model is landlord-scoped shared-database tenancy.

Example:

```text
users
properties.landlord_id
rooms → property → landlord
contracts → room → property → landlord
```

Every landlord-owned query must be tenant-scoped.

Security invariant:

```text
Landlord A must never read or mutate Landlord B data.
```

Do not rely only on Filament UI visibility.

Enforce tenancy through:

- query scopes
- policies
- relationship constraints
- application actions
- ownership checks

For security-sensitive writes, reload the target through an authorized scoped query.

Avoid:

```php
Room::findOrFail($id);
```

Prefer conceptually:

```php
$landlord->rooms()->findOrFail($id);
```

or an equivalent ownership-aware query.

---

## 7. Authentication and Authorization

Authentication answers:

```text
Who is the user?
```

Authorization answers:

```text
Can this user perform this operation?
```

Use:

- Laravel authentication
- Policies
- Gates where appropriate
- optional permission package for configurable manager permissions

Roles:

```text
system_admin
landlord
manager
tenant
```

Manager permissions should be capability-based where possible.

Example:

```text
rooms.view
rooms.update
contracts.create
meter_readings.create
payments.confirm
reports.view
```

Never use client-provided role names to authorize operations.

---

## 8. Database Design Principles

Use:

- foreign keys
- unique constraints
- composite indexes
- transaction boundaries
- immutable historical snapshots

Examples:

```text
UNIQUE(property_id, room_number)

UNIQUE(room_id, service_id, billing_month)

INDEX(property_id, status)

INDEX(invoice_id, created_at)
```

Do not add indexes blindly.

Indexes must support real query patterns.

Avoid duplicated data unless it is an intentional snapshot or denormalization.

---

## 9. Historical Data Strategy

Historical financial data must remain stable.

Examples:

A later change to:

```text
room.base_rent
electricity price
water price
```

must not change an existing invoice.

Therefore invoices should store line-item snapshots:

```text
invoice_items
- name
- quantity
- unit_price
- amount
- metadata
```

Historical contracts should also preserve agreed rent.

---

## 10. Transaction Boundaries

Use database transactions for operations that mutate multiple related records.

Examples:

### Create Contract

```text
validate room availability
create contract
update room status
write audit log
```

### Generate Invoice

```text
check duplicate invoice
read contract snapshot
read service usage
create invoice
create invoice items
calculate total
```

### Record Payment

```text
validate remaining balance
create payment
recalculate invoice status
```

These operations must be atomic.

---

## 11. Idempotency

Operations that may be retried must be idempotent.

Important examples:

- monthly invoice generation
- external payment webhook
- scheduled notifications
- import jobs

Invoice generation should prevent duplicates with a database constraint.

Concept:

```text
UNIQUE(contract_id, billing_period)
```

or another business-key equivalent.

Do not rely only on:

```php
if (!Invoice::exists(...))
```

because concurrent requests can race.

---

## 12. Concurrency

Critical operations must consider race conditions.

Examples:

- two users creating a contract for the same room
- two payment callbacks for the same transaction
- invoice generation running twice

Use appropriate combinations of:

- unique constraints
- row-level locks
- transactions
- idempotency keys

Application validation alone is insufficient.

---

## 13. Caching

Do not cache prematurely.

Good cache candidates:

- property service configuration
- subscription features
- dashboard aggregates with short TTL
- rarely changing reference data

Do not cache authoritative financial writes.

Cache invalidation must be explicit.

Redis should be used for:

- cache
- queues
- rate limiting
- locks where necessary

---

## 14. Queue Strategy

Synchronous by default for short critical operations.

Queue operations that are:

- slow
- retryable
- externally dependent

Examples:

- email invoice
- export reports
- send notifications
- generate large PDFs
- webhook follow-up processing

Do not queue an operation when the caller requires immediate transactional confirmation.

---

## 15. Events

Use domain/application events when they reduce coupling.

Examples:

```text
ContractCreated
ContractTerminated
InvoiceGenerated
PaymentRecorded
MaintenanceRequestCreated
```

Events should represent facts that already happened.

Avoid using events as an excuse to hide essential transaction logic.

---

## 16. API Design

If APIs are added later:

```text
/api/v1/...
```

Use:

- resource-oriented endpoints
- Form Request validation
- consistent error shape
- authentication
- policy checks
- pagination
- rate limiting

Do not expose internal database IDs across tenants without authorization checks.

---

## 17. Filament Architecture

Filament is the presentation/admin layer.

Recommended panels:

```text
/admin
/landlord
```

Possible later tenant UI:

```text
/tenant
```

but tenant-facing UX does not have to use Filament.

Filament Resources should contain:

- form schema
- table schema
- filters
- actions
- navigation
- relation managers

They should call application Actions for non-trivial business operations.

---

## 18. Validation Strategy

Validation exists at multiple layers.

### UI / Request Layer

Examples:

```text
required
numeric
date
max length
```

### Business Layer

Examples:

```text
room cannot have overlapping contract
payment cannot exceed balance
meter current value cannot be below previous value
```

### Database Layer

Examples:

```text
unique constraints
foreign keys
not null constraints
```

Critical rules should not exist only in Filament validation.

---

## 19. Observability

At minimum, production should provide:

- application logs
- failed jobs
- exception tracking
- slow query visibility

Important business operations should log context safely.

Never log:

- passwords
- tokens
- sensitive identity data
- payment secrets

Use structured logs where useful.

---

## 20. Performance Principles

### Database

Always inspect:

- query count
- N+1 problems
- indexes
- pagination
- expensive aggregate queries

Prefer eager loading for known relationships.

Avoid loading entire collections when pagination or aggregation can happen in SQL.

### Filament

Tables must paginate.

Avoid expensive computed columns executed per row.

Dashboard widgets should aggregate efficiently.

Large reports should move to optimized queries or background exports.

---

## 21. Security Principles

Required:

- CSRF protection
- authorization policies
- tenant isolation
- mass-assignment protection
- rate limiting for public endpoints
- secure file validation
- audit trail for sensitive changes

Sensitive actions:

```text
payment confirmation
contract termination
permission changes
subscription changes
```

should be auditable.

---

## 22. Testing Strategy

### Unit Tests

Use for:

- calculators
- status transitions
- pure domain rules

### Feature Tests

Use for:

- actions
- policies
- tenancy isolation
- database writes
- Filament flows where important

Minimum business-feature coverage:

```text
happy path
validation failure
authorization failure
tenant isolation
important invariant
```

Critical financial flows should include transaction and duplicate-protection tests.

---

## 23. Deployment Architecture

Initial production architecture:

```text
Internet
   ↓
Nginx
   ↓
Laravel
   ├── MySQL
   ├── Redis
   └── Queue Worker
```

Possible later:

```text
Load Balancer
   ↓
Multiple Laravel instances
   ↓
Managed MySQL
Managed Redis
Object Storage
```

Application instances should be stateless.

---

## 24. Architectural Rules for Agents

Before implementing a feature:

1. read `AGENTS.md`
2. read relevant phase file
3. inspect existing models and migrations
4. inspect related policies
5. check business rules
6. identify transaction boundary
7. identify authorization boundary
8. identify indexes needed by expected queries

Do not implement future phases unless explicitly requested.

Do not introduce architectural abstractions without a concrete need.
