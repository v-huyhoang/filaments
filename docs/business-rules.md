# Business Rules

## Contract

- A room cannot have overlapping active contracts.
- Contract start_date must be <= end_date.
- Creating an active contract changes room status to occupied.
- Ending the final active contract changes room status to available.
- Draft contracts can be edited and scheduled through an authorized Action; scheduling rechecks room ownership, reservations and overlaps.
- An occupied room cannot be made available, inactive or under maintenance until its active contract is checked out.
- Check-out dates must be between the tenancy start and now. A previous active contract must be checked out before another tenant checks in, even if planned dates do not overlap.

## Meter Reading

current_value >= previous_value

Only one reading exists per:

room + service + billing month.

- After the first reading, every intervening billing month is required; consumption must not silently span missing months.
- Only the latest reading can be corrected, with a mandatory reason and before/after audit history. All referencing invoices must first be cancelled. Invoice snapshots remain unchanged.

## Invoice

Invoice total equals sum(invoice_items.amount).

Invoice items are snapshots.

Historical invoices must not change when:
- room rent changes
- electricity price changes
- water price changes

- Checked-out and expired contracts may still bill months that overlap actual tenancy. No billing is allowed after the earlier of the planned end date and check-out date.
- Full monthly rent is charged for an overlapping month; daily proration is not supported and is disclosed before issue.
- The UI requires preview followed by confirmation. Changed inputs invalidate the preview; generation rechecks the reviewed calculation under transaction locks.
- A cancelled invoice may be explicitly replaced with a reason. Revisions and replacement links preserve history; only one non-cancelled invoice may exist per contract/month.
- Service activation/deactivation takes effect today and is versioned by date. Billing uses the status and property price effective at month end, not the current master status.

## Payment

payment.amount > 0

Total payments may not exceed invoice balance unless overpayment support is explicitly enabled.
