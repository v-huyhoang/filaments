# Phase 01 - Property

## Goal

Landlord can manage properties.

## Scope

Implement:

- Property model
- migration
- factory
- policy
- Filament Resource
- tests

## Fields

name
address
description

## Rules

Property belongs to landlord.

Landlord can only access their own properties.

## UI

Property table:

- name
- room count
- address
- created_at

Filters:

none initially.

## Acceptance Criteria

- landlord creates property
- landlord edits own property
- landlord deletes property with no rooms
- landlord cannot access another landlord property
- tests pass

## Out of Scope

- Room
- Manager assignment
- Subscription limits
