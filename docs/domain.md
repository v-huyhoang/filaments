# Domain Model

## Property

A Property belongs to one Landlord.

A Property has many Rooms.

## Room

A Room belongs to one Property.

A Room may have many Contracts historically.

Only one active contract may occupy a room at a time.

## Tenant

A Tenant may have multiple Contracts historically.

Tenant does not directly own a room.

## Contract

Contract connects:

Tenant ↔ Room

Contract contains the agreed rental price.

Room price changes must not modify existing contracts.

## Invoice

Invoice belongs to a Contract.

Invoice contains immutable InvoiceItems.

Changes to current service prices must not alter historical invoices.

## Payment

Payment belongs to an Invoice.

An invoice may have multiple payments.
