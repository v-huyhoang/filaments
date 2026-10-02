# Phase 10 - Dashboard & Reporting

## Goal

Provide landlords with useful operational and financial visibility based on existing system data.

## Scope

Implement dashboard metrics:

```text
total properties
total rooms
occupied rooms
available rooms
occupancy rate
monthly billed revenue
monthly collected revenue
outstanding balance
overdue invoices
```

Implement initial reports:

- occupancy summary
- monthly revenue summary
- outstanding invoices
- overdue invoices
- payment history

Filament components:

- StatsOverviewWidget
- ChartWidget
- TableWidget
- report filters

Filters:

```text
property
date range
billing month
invoice status
```

## Rules

- all metrics must be landlord-scoped
- collected revenue must come from payments, not invoice totals
- billed revenue and collected revenue are separate metrics
- overdue status must use invoice due date and outstanding balance
- occupancy must be based on active contract/room state according to established domain rules
- financial reports must not mutate source data
- report numbers must be reproducible from authoritative tables

## Query Strategy

Prefer SQL aggregation.

Avoid:

```php
Model::all()->filter(...)->sum(...)
```

for large datasets.

Use:

```text
SUM
COUNT
GROUP BY
date predicates
indexed filters
```

Avoid N+1 queries inside widget loops.

Large report datasets must remain paginated.

## Performance

Review query plans for expensive reports.

Potential indexes:

```text
invoices(contract_id, status)
invoices(due_date, status)
payments(invoice_id, paid_at)
contracts(room_id, status)
rooms(property_id, status)
```

Only add indexes justified by actual queries.

Optional short-lived caching may be used for dashboard aggregates.

## Expected

After this phase landlord can answer:

```text
How many rooms are occupied?
How much was billed this month?
How much money was actually collected?
Which invoices are overdue?
Which properties have low occupancy?
```

Dashboard loads without N+1 queries.

## Acceptance Criteria

- metrics are landlord-scoped
- property/date filters work
- billed and collected revenue are separately calculated
- outstanding balance is correct with partial payments
- overdue list excludes fully paid invoices
- reports are paginated
- dashboard queries are reviewed for N+1 and unnecessary full-table loading
- report totals are covered by tests

## Out of Scope

- predictive analytics
- AI insights
- accounting statements
- tax reporting
- custom report builder
- scheduled report emails
- PDF/Excel exports
