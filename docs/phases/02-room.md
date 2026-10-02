# Phase 02 - Room Management

## Goal

Allow landlords to manage rooms inside properties and establish the inventory model used later by contracts, occupancy, billing, and maintenance.

---

## Scope

Implement:

- `rooms` table
- `Room` model
- `RoomStatus` enum
- relationships
- Filament `RoomResource`
- `RoomsRelationManager` under Property
- search/filter/sort
- policies
- factories
- tests

Suggested fields:

```text
property_id
room_number
floor
area
base_rent
deposit_amount
max_occupants
status
description
```

Statuses:

```text
available
occupied
maintenance
inactive
```

Relationships:

```text
Property hasMany Rooms
Room belongsTo Property
```

---

## Rules

- room belongs to exactly one property
- property must belong to current landlord
- room number must be unique inside a property
- same room number may exist in different properties
- base rent >= 0
- deposit amount >= 0
- max occupants > 0
- once contract flow exists, occupancy status must be driven by contract operations
- room with active contract cannot be deleted
- inactive room cannot accept new contract

Database constraint:

```text
UNIQUE(property_id, room_number)
```

---

## Security

- property selector must be landlord-scoped
- another landlord's `property_id` must be rejected
- direct room URL from another landlord must fail
- manager access is permission-controlled
- ownership transfer must not happen through normal room edit forms

---

## Performance

Recommended indexes:

```text
rooms(property_id)
rooms(property_id, status)
```

Avoid N+1 for:

```text
property.name
active contract
```

Do not calculate occupancy with one query per row.

---

## Expected

Landlord can manage rooms under owned properties.

Room records are ready for:

```text
contracts
meter readings
maintenance
billing
```

---

## Acceptance Criteria

- room can be created under owned property
- duplicate room number in same property fails
- duplicate number in different property succeeds
- invalid negative monetary values fail
- cross-landlord property assignment fails
- filters by property/status work
- room policy blocks unauthorized direct access
- dependency-safe deletion behavior is tested

---

## Out of Scope

- Tenant assignment
- Contracts
- Reservations
- Meter readings
- Invoices
- Room images
- Public room listings
- Booking calendar
