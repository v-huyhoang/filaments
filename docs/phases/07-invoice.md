# Phase 07 - Invoice Engine

## Goal

Generate immutable billing snapshots from active contracts, service configuration, and usage data.

---

## Scope

Implement:

- `invoices`
- `invoice_items`
- `Invoice`
- `InvoiceItem`
- `InvoiceStatus`
- `GenerateInvoiceAction`
- `InvoiceCalculator`
- duplicate prevention
- invoice detail UI
- transaction handling
- policies
- tests

Invoice fields:

```text
contract_id
billing_period
issued_at
due_date
subtotal
total
status
notes
```

Invoice item fields:

```text
invoice_id
type
name
quantity
unit
unit_price
amount
metadata
```

Statuses:

```text
draft
issued
partial
paid
overdue
cancelled
```

---

## Rules

- invoice belongs to contract
- contract must belong to current landlord
- rent item uses contract rent snapshot
- service pricing must be effective for billing period
- metered service uses corresponding meter reading
- total = sum(invoice items)
- invoice items are snapshots
- issued invoice must not be recalculated silently after source prices change
- duplicate live invoice for the same contract/billing period is forbidden; cancelled revisions may only be replaced explicitly
- cancelled invoice remains historical rather than being hard-deleted
- invoice generation is an application Action

Recommended business uniqueness:

```text
UNIQUE(contract_id, billing_period, revision)
UNIQUE(contract_id, billing_period, active_billing_key)
UNIQUE(replaces_invoice_id)
```

---

## Transaction Boundary

Generation should atomically:

```text
authorize
check duplicate business key
load contract
load billing inputs
calculate items
create invoice
create invoice items
persist totals
```

On any failure:

```text
rollback all changes
```

---

## Idempotency

Calling invoice generation twice for the same contract/billing period must not create two live invoices. Cancelled revisions remain immutable. Explicit replacement requires the latest cancelled invoice ID and a reason. The generated active_billing_key is NULL for cancelled invoices and 1 otherwise.

Database uniqueness is required in addition to pre-check logic.

---

## Concurrency

Concurrent generation attempts must result in at most one invoice.

Handle database duplicate-key race safely.

---

## Security

- landlord/authorized manager only
- all contract/service/meter inputs must be tenant-scoped
- invoice totals must never trust client-calculated values
- financial fields are server-calculated
- another landlord's invoice cannot be retrieved by direct ID

---

## Performance

Avoid N+1 while generating multiple invoices.

For batch generation:

- preload relevant contracts/services carefully
- chunk large workloads
- consider queue only once batch size requires it

Potential indexes:

```text
invoices(contract_id, billing_period)
invoices(status, due_date)
invoice_items(invoice_id)
```

---

## Expected

System can produce monthly billing like:

```text
Rent          3,000,000
Electricity     240,000
Water           100,000
Internet        100,000
-----------------------
Total          3,440,000
```

Changing room/service configuration later does not alter the issued invoice.

---

## Acceptance Criteria

- invoice is generated from valid contract
- duplicate billing period is rejected
- total equals item sum
- rent comes from contract snapshot
- metered usage is correctly billed
- issued invoice remains unchanged after service-price updates
- cross-landlord generation/access fails
- concurrent duplicate generation is protected
- failure rolls back invoice and items
- tests cover calculation and historical immutability

---

## Out of Scope

- Payments
- Online payment gateway
- Invoice PDF
- Email/SMS delivery
- Tax/VAT engine
- Credit notes
- Discounts
- Late fees
- Batch scheduler

## Implementation Decisions

- Preview does not write records. The UI requires a second confirmation with unchanged inputs; issue validates the reviewed fingerprint again under transaction locks. Draft remains an internal transactional state while items are inserted.
- Treat billing_period as the collection month. Collect that month's rent in advance and the previous month's services in arrears. First-month rent is prorated from move-in through month end using actual calendar days; later rental months remain full.
- Due dates use the contract payment day, clamped to month end and never before move-in. Permit the month after tenancy ends for final services only, without another rent charge or an empty final invoice.
- Resolve service pricing, meter readings, contract overrides and occupancy at the previous usage month. Snapshot the billing policy and item periods; do not charge services again if a legacy same-month invoice already includes them.
- Calculate per-person monthly fees from individual occupancy histories, using inclusive arrival and exclusive departure. Use actual month length and round the combined person-day fee to whole VND. Missing history blocks billing; manual headcounts are not accepted.
- Contract service versions override the property method/price from the first day of a month, preserving history and never billing both default and override for the same service.
- Manual services are not automatically billed; manual line-item entry is not supported in this phase.
- Round other lines to two decimal places using half-up rounding, then sum the rounded lines. Per-person lines use whole-VND rounding only after summing person-days.
- Store landlord ownership and tenant/property/room labels as invoice snapshots so historical access survives soft-deleted source records.
- Issued financial fields and line items cannot be edited or deleted through model operations. Cancellation preserves all snapshots; an explicitly linked new revision can replace the latest cancelled invoice with a mandatory reason.
- Active contracts and checked-out/expired contracts can bill overlapping actual tenancy months. Check-out bounds the final billable month, even when the original end date is later.
- Service status is effective-dated, so deactivation today does not remove services from earlier unbilled months.
- Use contract/property/room row locks, revision uniqueness and the generated active-period key to prevent duplicate live invoices; no scheduler or batch generation is included.
