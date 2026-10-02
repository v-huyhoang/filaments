# Phase 11 - Expense Management

## Goal

Allow landlords to record operating expenses and calculate basic net operating results.

## Scope

Implement:

```text
expenses
expense_categories
```

Expense fields:

```text
property_id
category_id
amount
expense_date
description
reference
created_by
```

Initial categories may include:

```text
maintenance
utilities
cleaning
equipment
tax
other
```

Filament:

- ExpenseResource
- ExpenseCategoryResource or controlled master-data UI
- property/date/category filters
- expense summary widget

## Rules

- expense amount must be greater than zero
- expense must belong to landlord-owned property
- expense date is required
- historical expense amount must not change because a category definition changes
- deleting a category already referenced by expenses should be restricted or soft-deactivated
- expense changes must respect authorization
- financial totals must use authoritative expense records

## Security

Managers may create expenses only when granted permission.

Sensitive fields such as creator identity must not be client-controlled.

Set:

```text
created_by
```

server-side.

## Performance

Indexes should support:

```text
property + date range
category + date range
landlord reporting through property relationship
```

Potential:

```text
expenses(property_id, expense_date)
expenses(category_id, expense_date)
```

## Expected

Landlord can:

```text
record an expense
filter expenses by property
filter by period/category
view monthly expense total
compare collected revenue vs expenses
```

Basic net operating result:

```text
collected revenue - expenses
```

## Acceptance Criteria

- landlord can CRUD own expenses
- cross-landlord property assignment fails
- amount <= 0 is rejected
- manager permissions are enforced
- date/category/property filters work
- monthly expense total is correct
- dashboard/report integration uses SQL aggregation
- tests cover authorization and tenant isolation

## Out of Scope

- accounts payable
- vendor management
- purchase orders
- depreciation
- double-entry accounting
- tax accounting
- receipt OCR
- bank reconciliation
