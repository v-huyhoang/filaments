# Phase 00 - Foundation

## Goal

Establish a stable, testable, production-oriented foundation before implementing rental-management business modules.

The output of this phase is a runnable Laravel + Filament application with database, cache, coding standards, testing, authentication, and project conventions ready for incremental feature development.

---

## Scope

### Application

Set up:

- PHP 8.3+
- Laravel 12+
- Filament 5
- Livewire 4
- MySQL 8.4
- Redis
- Composer scripts
- environment configuration

### Development Infrastructure

Provide Docker Compose services for:

```text
mysql
redis
```

Laravel may initially run directly on the host/WSL.

Required local flow:

```bash
docker compose up -d
php artisan migrate
php artisan test
php artisan serve
```

### Filament

Set up:

- admin/landlord panel
- login
- base navigation
- application branding placeholder
- initial Filament user
- authorization-ready panel configuration

### Quality Tooling

Set up:

- Laravel Pint
- Pest or PHPUnit
- PHPStan / Larastan
- consistent Composer scripts

Recommended scripts:

```text
composer test
composer lint
composer analyse
```

### Core Project Structure

Prepare:

```text
app/
├── Actions/
├── Enums/
├── Filament/
├── Models/
├── Policies/
├── Services/
└── Support/

docs/
├── architecture.md
├── domain.md
├── database.md
├── business-rules.md
└── phases/
```

### Agent Context

Create and maintain:

```text
AGENTS.md
```

The root `AGENTS.md` must define:

- stack
- architecture direction
- coding conventions
- transaction expectations
- authorization requirements
- testing requirements
- Filament boundaries
- tenant-isolation rules

---

## Rules

- Filament is a presentation layer, not the domain layer.
- Complex business operations must be implemented through application Actions or Services.
- Database schema changes must be created through migrations.
- Secrets must not be committed.
- `.env.example` must contain required variable names without production secrets.
- Development setup must be reproducible.
- All code committed from this phase onward must pass the configured formatter and test suite.
- Do not introduce Repository abstractions unless there is an actual need beyond Eloquent.
- Do not introduce microservices.
- Do not add Redis caching to business flows before a concrete use case exists.
- Do not add queues until a use case is clearly asynchronous or externally dependent.

---

## Environment

Minimum environment variables:

```text
APP_NAME
APP_ENV
APP_KEY
APP_DEBUG
APP_URL

DB_CONNECTION
DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD

CACHE_STORE
QUEUE_CONNECTION

REDIS_HOST
REDIS_PORT
```

Local database configuration should not reuse production credentials.

---

## Security

Foundation must include:

- Laravel CSRF protection
- secure password hashing
- no hardcoded credentials
- safe `.gitignore`
- production-safe debug configuration
- prepared authorization structure
- mass-assignment awareness

Production must use:

```text
APP_DEBUG=false
```

Never log:

- passwords
- session tokens
- API secrets
- database passwords

---

## Database

MySQL should use:

```text
utf8mb4
```

Migrations must:

- use foreign keys where relationships exist
- use appropriate integer/decimal types
- avoid unnecessary nullable fields
- avoid premature indexes

Money must not use floating point.

Use:

```text
DECIMAL
```

or integer minor units according to the project's final monetary convention.

The convention must remain consistent across the project.

---

## Testing

Create a working test setup.

Required baseline test:

```text
application boots successfully
```

Database tests must run against an isolated test database.

Tests must never target the developer's production-like local database.

---

## Developer Experience

Required commands should be documented in `README.md`.

At minimum:

```text
install dependencies
start infrastructure
copy environment file
generate application key
run migrations
run tests
run formatter
start Laravel
```

---

## Expected

At the end of Phase 00:

```text
Laravel boots
Filament login works
MySQL connection works
Redis connection is available
migrations run
tests run
Pint runs
static analysis runs
AGENTS.md exists
architecture documentation exists
```

A new developer or Codex agent should be able to understand how to start the project without reverse-engineering the repository.

---

## Acceptance Criteria

- `php artisan --version` succeeds
- Filament panel is reachable
- authenticated Filament user can log in
- MySQL runs through Docker
- Redis runs through Docker
- `php artisan migrate` succeeds
- `php artisan test` succeeds
- `vendor/bin/pint --test` or equivalent succeeds
- PHPStan/Larastan executes successfully
- `.env.example` is complete
- secrets are not committed
- project boot instructions exist
- `AGENTS.md` exists and documents architecture rules
- CI-friendly Composer commands are available

---

## Out of Scope

- Property management
- Room management
- Tenant management
- Contracts
- Meter readings
- Invoices
- Payments
- Role/permission management
- Dashboard analytics
- SaaS subscriptions
- payment gateway integration
- production deployment automation
- Kubernetes
- microservices
