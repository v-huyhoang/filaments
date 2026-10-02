# Phase 13 - SaaS Plans & Subscription Limits

## Goal

Introduce plan-based usage limits and feature access without coupling business code directly to plan names.

## Scope

Implement:

```text
plans
plan_features
subscriptions
```

or an equivalent normalized design.

Example plan capabilities:

```text
max_properties
max_rooms
max_managers
advanced_reports
export_enabled
tenant_portal_enabled
```

Create application abstractions:

```text
SubscriptionService
FeatureGate
UsageLimitChecker
```

Enforce limits at application-action level.

Example:

```text
CreatePropertyAction
    ↓
UsageLimitChecker
```

## Rules

- business code must not depend on hardcoded plan names
- use features/capabilities instead of:

  if ($plan === 'pro')

- limits must be enforced server-side
- UI hiding alone is insufficient
- current usage must be counted from authoritative tenant-scoped data
- subscription downgrade must not silently delete data
- when current usage exceeds downgraded limits, block creation of additional resources until compliant
- expired/cancelled subscription behavior must be explicit
- existing historical invoices/payments remain accessible regardless of plan changes
- subscription status changes must be auditable

## Subscription States

Recommended:

```text
trialing
active
past_due
cancelled
expired
```

Exact states may depend on payment provider later.

## Concurrency

Usage limits must consider concurrent creates.

Example:

Two simultaneous requests must not both pass:

```text
max_rooms = 10
current_rooms = 9
```

For hard limits, enforcement may require:

- transaction
- locking
- usage counters
- unique business constraints where possible

## Security

Only authorized landlord/system-admin actions can modify subscriptions.

Do not trust payment-provider callbacks without signature verification when integration is added later.

Never expose payment secrets.

## Performance

Frequently checked feature configuration may be cached.

Cache key must include tenant/subscription identity.

Invalidate on plan/subscription change.

Avoid expensive repeated usage counts in every page render.

## Expected

The system can support:

```text
Free
Pro
Business
```

without scattering plan-specific conditionals through Resources and Actions.

Example behavior:

```text
Free:
1 property
10 rooms
1 manager

Pro:
10 properties
200 rooms
5 managers
advanced reports
```

These are configuration values, not hardcoded domain logic.

## Acceptance Criteria

- landlord on limited plan cannot exceed configured usage
- direct application Action also blocks limit bypass
- feature flags work independently of plan name
- downgrade does not delete existing data
- usage above downgraded limit blocks new resource creation
- feature checks are tenant-scoped
- subscription state changes are tested
- cache invalidation works if caching is used

## Out of Scope

- payment gateway integration
- recurring card billing
- invoicing the SaaS customer
- coupons
- tax/VAT billing
- proration
- dunning
- multi-currency SaaS billing
- reseller plans
