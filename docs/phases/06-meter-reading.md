# Phase 06 - Meter Reading

## Goal

Capture periodic consumption for metered services such as electricity and water.

---

## Scope

Implement:

- `meter_readings` table
- `MeterReading` model
- Filament resource/relation manager
- billing-period representation
- usage calculation
- previous-reading lookup
- policy
- tests

Suggested fields:

```text
room_id
service_id
billing_period
previous_value
current_value
usage
recorded_at
recorded_by
notes
```

---

## Rules

- service must use `metered` billing method
- service must be enabled for room's property
- current value >= previous value
- usage = current - previous
- one reading per room/service/billing period
- previous reading should normally equal prior period's current reading
- `recorded_by` is server-controlled
- a reading referenced by an issued invoice must not be silently changed
- manual correction after invoicing requires explicit future adjustment workflow

Database constraint:

```text
UNIQUE(room_id, service_id, billing_period)
```

---

## Security

- landlord/authorized manager only
- room and service must belong to same landlord context
- client cannot control `recorded_by`
- tenant cannot edit readings
- direct room/service IDs must be tenant-scoped

---

## Performance

Recommended index:

```text
meter_readings(room_id, service_id, billing_period)
```

Previous-value lookup should be index-friendly and fetch only the latest relevant record.

---

## Expected

System can reliably calculate monthly consumption:

```text
current - previous = usage
```

Invoice phase can consume finalized usage records.

---

## Acceptance Criteria

- valid reading succeeds
- current < previous fails
- duplicate period fails at application/database level
- non-metered service fails
- disabled property service fails
- cross-landlord combination fails
- usage is calculated correctly
- prior reading lookup works
- invoiced-reading mutation strategy is enforced/documented

---

## Out of Scope

- Smart meters
- OCR
- Meter photos
- Meter replacement
- Tiered usage rates
- Invoice generation
- Usage anomaly detection
