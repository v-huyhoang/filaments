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
- After a non-metered month, returning to metered billing requires a fresh opening reading. Do not carry usage across months already charged per-person or fixed; consecutive metered months still cannot be skipped.

## Invoice

Invoice total equals sum(invoice_items.amount).

Invoice items are snapshots.

Historical invoices must not change when:
- room rent changes
- electricity price changes
- water price changes

- The invoice billing period is the collection month: rent is collected in advance for that month; services are collected in arrears for the previous usage month, including metered, per-person and fixed monthly fees.
- Checked-out and expired contracts may still bill tenancy months and the following collection month for final services only. Never charge rent after tenancy ends; do not create an empty final invoice when there are no services to settle.
- First-month rent is prorated from the inclusive contract start through month end using actual calendar days, rounded half-up to two decimal places. Later overlapping rental months retain full monthly rent.
- Payment is due on the contract payment day in the collection month (clamped to month end), never before move-in. No implicit grace period is added.
- Snapshot the billing policy and each item's usage/rental month. Existing invoices remain immutable; services already charged by a legacy same-month invoice are not charged again in the following collection month.
- The UI requires preview followed by confirmation. Changed inputs invalidate the preview; generation rechecks the reviewed calculation under transaction locks.
- A cancelled invoice may be explicitly replaced with a reason. Revisions and replacement links preserve history; only one non-cancelled invoice may exist per contract/month.
- Service activation/deactivation takes effect today and is versioned by date. Billing uses the status and property price effective at the end of the previous usage month, not the collection month or current master status.

## Occupancy and Per-person Services

- Record each resident, including the primary tenant, on the contract. Temporary absence remains billable; only a recorded move-out ends occupancy.
- Move-in is inclusive, move-out is exclusive. Same-day arrival/departure costs zero; same-day handover does not double-charge.
- Monthly per-person fees use unit price times total person-days divided by actual calendar days in that month (including leap years). Round the combined service amount half-up to one VND, never each day or resident separately.
- Clamp chargeable service days to contract start, inclusive planned end, and exclusive actual check-out date. First-month rent uses the prorating rule above; later overlapping rental months remain full.
- Occupancy changes must stay within tenancy and cannot exceed room capacity on any day. Changes affecting issued, non-cancelled periods require cancellation/replacement; snapshots are immutable.
- Contract service versions select exactly one method per service/month, or explicitly restore the property default. They cannot introduce foreign/unconfigured services or re-enable disabled property services.
- Contract overrides and per-person property price revisions take effect only on the first of a month. Revisions preserve earlier prices. Meter readings are only requested for metered configurations.
- An initial contract override may start at the current month's first day if no affected invoice exists; subsequent revisions cannot be backdated. Metered overrides explicitly store the meter unit.
- Missing occupancy history blocks per-person billing instead of assuming room capacity or accepting a manually supplied headcount.

## Payment

payment.amount > 0

Total payments may not exceed invoice balance unless overpayment support is explicitly enabled.
