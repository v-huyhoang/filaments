# Phase 12 - Maintenance Requests

## Goal

Provide a structured workflow for room/property maintenance requests.

## Scope

Implement:

```text
maintenance_requests
maintenance_comments
```

Optional later attachment relation may be prepared but not required.

Request fields:

```text
property_id
room_id
tenant_id
title
description
priority
status
assigned_to
reported_at
resolved_at
```

Statuses:

```text
open
assigned
in_progress
resolved
cancelled
```

Priorities:

```text
low
normal
high
urgent
```

Filament features:

- MaintenanceRequestResource
- filters by status, property, priority, assignee
- assign manager action
- change status action
- comment history

Tenant entry point may be added only if tenant UI already exists.

## Rules

- room must belong to selected property
- tenant must be related to room through a valid/current tenancy when request is tenant-created
- assigned manager must belong to same landlord
- status transitions must be valid
- resolved_at is set when entering resolved state
- resolved_at is cleared only if reopening is explicitly supported
- comments must not change request ownership
- maintenance actions must remain tenant-scoped

Recommended transition rules:

```text
open → assigned
open → in_progress
assigned → in_progress
in_progress → resolved
open/assigned/in_progress → cancelled
```

Other transitions require explicit support.

## Security

Tenant must never create a request for another room by changing room_id.

Managers can only access maintenance requests for their assigned landlord.

Comments and attachments must inherit parent request authorization.

## Performance

Indexes:

```text
maintenance_requests(property_id, status)
maintenance_requests(assigned_to, status)
maintenance_requests(room_id, created_at)
```

Use pagination for request history.

## Expected

Operational flow:

```text
request created
→ manager assigned
→ work started
→ issue resolved
```

Landlord can track unresolved maintenance workload.

## Acceptance Criteria

- valid request can be created
- unauthorized room/tenant combination is rejected
- manager assignment respects landlord boundary
- invalid status transitions fail
- resolved_at is maintained correctly
- comments are scoped to authorized request
- filters work
- open/in-progress counts can be shown in dashboard
- tests cover transition rules

## Out of Scope

- external contractor portal
- maintenance inventory
- spare-parts management
- SLA engine
- automated dispatch
- SMS integration
- image upload
- recurring preventive maintenance
