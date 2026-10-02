# Database

## properties

- id
- landlord_id
- name
- address
- timestamps

Indexes:

- landlord_id

## rooms

- id
- property_id
- number
- floor
- area
- base_rent
- status

Unique:

(property_id, number)

Indexes:

property_id
status
(property_id, status)
