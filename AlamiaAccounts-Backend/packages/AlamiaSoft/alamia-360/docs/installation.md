# Installation

## Requirements

- **PHP** 8.1 or higher
- **Laravel** 10 or 11
- **Database** — SQLite (development/testing), PostgreSQL (recommended for production), MySQL, or MariaDB

---

## 1. Install via Composer

```bash
composer require alamia/alamia-360
```

Laravel's package auto-discovery registers `Alamia360ServiceProvider` automatically. No manual provider registration is needed.

---

## 2. Run the Install Command

```bash
php artisan alamia360:install
```

This command:
- Publishes the configuration file to `config/alamia360.php`.
- Publishes the database migrations to `database/migrations/`.
- Publishes stub files for a custom `SituationDetector` and a service provider bootstrap example to your `app/` directory.
- Prints a summary of next steps to the console.

---

## 3. Publish Assets Individually (Optional)

If you prefer to publish assets selectively rather than running `alamia360:install`:

```bash
# Publish configuration only
php artisan vendor:publish --tag=alamia360-config

# Publish migrations only
php artisan vendor:publish --tag=alamia360-migrations

# Publish stub files only
php artisan vendor:publish --tag=alamia360-stubs
```

---

## 4. Run Migrations

```bash
php artisan migrate
```

This creates the following tables:

| Table | Purpose |
|---|---|
| `alamia360_observations` | Persisted observation records (full audit trail). |
| `alamia360_situations` | Open and historical situation records. |
| `alamia360_audit_events` | Capability execution audit log. |

---

## Database Recommendations

| Environment | Recommended driver | Notes |
|---|---|---|
| Development | SQLite | Zero-config, works with Orchestra Testbench. |
| Testing | SQLite (in-memory) | Used by the package test suite. |
| Production | PostgreSQL | Recommended for JSON column performance and reliability. |
| Production (alt) | MySQL 8+ / MariaDB 10.5+ | Supported; JSON columns and index behaviour differ slightly. |

Alamia 360 uses no database-specific SQL. All queries are built through Eloquent and the Laravel query builder.

---

## Environment Variables

Add the following to your `.env` file. All variables are optional; defaults are shown.

| Variable | Default | Description |
|---|---|---|
| `ALAMIA_AI_ENABLED` | `false` | Set to `true` to activate a real AI provider binding. When `false`, `NullAiProvider` is used. |
| `ALAMIA_PERSISTENCE_DRIVER` | `eloquent` | Persistence backend for situations, observations, and audit records. `eloquent` is the only built-in driver. |
| `ALAMIA_MCP_ENABLED` | `false` | Set to `true` to enable the MCP adapter. When `false`, `listTools()` returns an empty array. |
| `ALAMIA_OBSERVE_PERSIST` | `true` | Whether observations are written to the database. Should remain `true` in production. |
| `ALAMIA_OBSERVE_QUEUE` | `false` | Whether situation detection runs on a queue worker instead of synchronously. |
| `ALAMIA_QUEUE_CONNECTION` | `null` | The queue connection to use when `ALAMIA_OBSERVE_QUEUE=true`. Defaults to the app's default queue connection. |

Example `.env` block:

```ini
ALAMIA_AI_ENABLED=false
ALAMIA_PERSISTENCE_DRIVER=eloquent
ALAMIA_MCP_ENABLED=false
ALAMIA_OBSERVE_PERSIST=true
ALAMIA_OBSERVE_QUEUE=false
ALAMIA_QUEUE_CONNECTION=
```

---

## Quick Smoke Test

After installation, verify the package is wired correctly by registering one entity and one capability in a service provider and calling the capability directly:

```php
use Alamia\Alamia360\Facades\Alamia360;
use Alamia\Alamia360\Actors\Actor;

// In AppServiceProvider::boot() or a dedicated Alamia360ServiceProvider::boot()

// 1. Register an entity
Alamia360::entity('ping')
    ->describe('A simple ping entity for smoke testing');

// 2. Register a capability
Alamia360::capability('ping')
    ->describe('Returns pong')
    ->input([])
    ->output(['message' => ['type' => 'string']])
    ->withSideEffect('read')
    ->allowedFor(['human', 'ai', 'system'])
    ->handleUsing(fn(array $input, $actor) => ['message' => 'pong']);

// 3. Register an actor
$actor = Actor::system('smoke-test', 'system')
    ->withCapabilities(['ping']);

Alamia360::actors()->register($actor);

// 4. Execute the capability
$outcome = Alamia360::capabilities()->execute('ping', [], $actor);

// $outcome->succeeded() should be true
// $outcome->result()['message'] should be 'pong'
```

If `$outcome->succeeded()` returns `true`, Alamia 360 is installed and functioning correctly. Remove the smoke test code before deploying to production.
