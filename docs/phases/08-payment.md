# Phase 08 - Payment Management

## Goal

Record one or more payments against an invoice and maintain a correct invoice balance/status.

---

## Scope

Implement:

- `payments` table
- `Payment` model
- `PaymentMethod` enum
- `RecordPaymentAction`
- payment history UI
- invoice balance calculation
- status transitions
- policy
- transaction handling
- tests

Suggested fields:

```text
invoice_id
amount
method
paid_at
reference
notes
recorded_by
```

Methods:

```text
cash
bank_transfer
other
```

Future providers may add gateway-specific methods.

---

## Rules

- payment amount > 0
- payment belongs to invoice
- invoice belongs to current landlord
- payment cannot exceed remaining balance unless overpayment feature is explicitly supported
- invoice balance = total - successful payments
- invoice becomes `partial` when `0 < paid < total`
- invoice becomes `paid` when paid amount equals total
- cancelled/void payment must not count toward paid balance
- `recorded_by` is server-controlled
- historical payment records should not be hard-deleted casually
- correction should use explicit reversal/void semantics once audit requirements mature

---

## Transaction Boundary

Record-payment action should atomically:

```text
authorize
lock/read invoice
calculate current balance
validate amount
create payment
recalculate invoice status
```

---

## Concurrency

Two concurrent payments must not both spend the same remaining balance.

Use transaction + locking where needed.

Example:

```text
invoice remaining = 100
request A = 100
request B = 100
```

Only one should succeed if overpayment is disabled.

---

## Security

- payment amount is validated server-side
- user cannot control `recorded_by`
- direct invoice IDs are tenant-scoped
- manager needs payment permission
- tenant cannot mark own invoice as paid
- payment references must not expose secrets

---

## Performance

Indexes:

```text
payments(invoice_id, paid_at)
payments(recorded_by, paid_at)
```

Prefer SQL aggregation for balances when needed.

Avoid loading all payments into PHP solely to calculate totals.

---

## Expected

Invoice supports:

```text
unpaid
partial
paid
overdue
```

and can have multiple payments.

Example:

```text
Invoice = 3,500,000
Payment A = 2,000,000
Payment B = 1,500,000
Balance = 0
Status = paid
```

---

## Acceptance Criteria

- positive payment can be recorded
- zero/negative payment fails
- partial payment updates invoice to partial
- final payment updates invoice to paid
- overpayment fails
- cross-landlord invoice payment fails
- concurrent overpayment is prevented
- transaction rollback works
- payment history displays correctly
- `recorded_by` is server-controlled

---

## Out of Scope

- Payment gateway
- Webhooks
- Refunds
- Overpayment credit balance
- Bank reconciliation
- Payment proof upload
- Automatic reminders
