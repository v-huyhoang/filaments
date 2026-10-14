# Phase 05 - Property Services

## Goal

Configure reusable services and property-specific prices used later by billing.

---

## Scope

Implement:

- `services`
- `property_services`
- models
- billing method enum
- Filament service/configuration UI
- validation
- policies
- tests

Example services:

```text
electricity
water
internet
parking
trash
other
```

Service fields:

```text
name
code
billing_method
unit
is_active
```

Property-service fields:

```text
property_id
service_id
unit_price
fixed_price
is_enabled
effective_from
```

Billing methods:

```text
metered
fixed
per_person
manual
```

---

## Rules

- service definition is reusable master data
- pricing belongs to property
- property can independently enable/disable a service
- prices >= 0
- billing method determines valid pricing fields
- current price change affects future billing only
- historical invoices must store their own snapshots
- disabled services must not be automatically billed
- service status changes are dated from today and preserve earlier billing eligibility; resolve status at the billing month's end rather than filtering only the current master flag
- only valid property/service combinations may be configured

---

## Security

- landlord modifies only owned-property configurations
- global service master data may be system-admin controlled
- manager requires explicit service-management permission
- cross-landlord property references are rejected

---

## Performance

Recommended indexes:

```text
property_services(property_id, service_id)
property_services(property_id, is_enabled)
```

If price history is supported:

```text
property_services(property_id, service_id, effective_from)
```

---

## Expected

Each property can define its active utilities/services and current billing rules.

This phase provides billing configuration but does not calculate invoices yet.

---

## Acceptance Criteria

- service can be configured for owned property
- negative price fails
- duplicate active configuration is prevented
- cross-landlord configuration fails
- disabled service is excluded from billing candidates
- changing a price does not rewrite historical data
- tests cover billing-method validation

---

## Out of Scope

- Meter readings
- Invoice generation
- Tiered electricity/water pricing
- Tax
- External utility integrations
- Service bundles
