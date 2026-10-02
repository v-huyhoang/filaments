# Business Rules

## Contract

- A room cannot have overlapping active contracts.
- Contract start_date must be <= end_date.
- Creating an active contract changes room status to occupied.
- Ending the final active contract changes room status to available.

## Meter Reading

current_value >= previous_value

Only one reading exists per:

room + service + billing month.

## Invoice

Invoice total equals sum(invoice_items.amount).

Invoice items are snapshots.

Historical invoices must not change when:
- room rent changes
- electricity price changes
- water price changes

## Payment

payment.amount > 0

Total payments may not exceed invoice balance unless overpayment support is explicitly enabled.
