# Actors

## The Three Actor Types

Every action in Alamia 360 is performed by an actor. Actors are first-class objects — they carry an identity, a type, a role, a declared capability list, and an authorization callback. The system tracks who did what, enforces what each actor is allowed to do, and never grants elevated access implicitly.

Three actor types are defined:

| Type | When to use |
|---|---|
| `Human` | A person authenticated through the host application (e.g. a logged-in user). |
| `AI` | An AI agent or assistant that reasons about situations and invokes capabilities on behalf of a user or autonomously. |
| `System` | An automated process, scheduled command, or integration that acts without a human in the loop. |

---

## ActorType Enum

```php
use Alamia\Alamia360\Actors\ActorType;

ActorType::Human;
ActorType::AI;
ActorType::System;
```

The `ActorType` enum is used internally for `allowedFor` checks in [Capabilities](./capabilities.md).

---

## Actor Factory Methods

Use the static factory methods on `Actor` to construct instances:

```php
use Alamia\Alamia360\Actors\Actor;

// Human actor
$actor = Actor::human('user_42', 'veterinarian');

// AI actor
$actor = Actor::ai('gpt-agent-1', 'assistant');

// System actor (background process, CLI command)
$actor = Actor::system('queue-worker', 'system');
```

Each factory method signature: `(string $id, string $role)`.

- `$id` — a stable identifier, typically a database primary key or a service identifier.
- `$role` — a string matching the roles declared in your [Responsibilities](./responsibilities.md).

---

## `withCapabilities()` — Declaring What an Actor Can Do

An actor must explicitly declare the capabilities it is permitted to invoke. The declaration is a list of capability names:

```php
$actor = Actor::human('user_42', 'veterinarian')
    ->withCapabilities([
        'get_todays_appointments',
        'review_lab_result',
        'discharge_patient',
    ]);
```

This is the **positive list**. If a capability name is not in this list, the actor will be denied authorization for it, regardless of any other checks.

---

## `authorizeUsing()` — Delegating Authorization to the Host Application

After the capability list check, the actor runs a second authorization check via the closure provided to `authorizeUsing()`. This is where you wire Alamia 360 into your application's existing Gate, Policies, or custom authorization logic.

```php
use Illuminate\Support\Facades\Gate;

$actor = Actor::human('user_42', 'veterinarian')
    ->withCapabilities(['get_todays_appointments', 'review_lab_result'])
    ->authorizeUsing(function (ActorContract $actor, string $capability, mixed $subject) use ($user) {
        return Gate::forUser($user)->allows($capability, $subject);
    });
```

The closure signature: `(ActorContract $actor, string $capability, mixed $subject): bool`.

- `$actor` — the actor being checked.
- `$capability` — the capability name being requested.
- `$subject` — the subject entity being acted upon, or `null` for global capabilities.

If no `authorizeUsing` closure is set, the capability list check alone governs authorization.

---

## `isAuthorizedFor()` — How the Check Works

`isAuthorizedFor(string $capability, mixed $subject = null): bool` applies both checks in sequence:

1. Is `$capability` in `withCapabilities()`? If not, return `false` immediately.
2. Is there an `authorizeUsing` closure? If yes, invoke it and return its result. If no closure is set, return `true`.

```php
$actor->isAuthorizedFor('review_lab_result', $labResult); // bool
```

The `CapabilityExecutor` calls this method before invoking the handler. See [Capabilities](./capabilities.md).

---

## ActorRegistry: `register`, `find`, `byType`, `byRole`

```php
use Alamia\Alamia360\Facades\Alamia360;

// Register an actor (typically done in a service provider or middleware)
Alamia360::actors()->register($actor);

// Retrieve by ID
$actor = Alamia360::actors()->find('user_42'); // ActorContract|null

// All actors of a given type
$humans = Alamia360::actors()->byType(ActorType::Human); // ActorContract[]

// All actors with a given role
$vets = Alamia360::actors()->byRole('veterinarian'); // ActorContract[]
```

---

## Security Principle: AI Actors Never Receive Elevated Privileges Automatically

An `AI` actor is subject to the same `withCapabilities()` declaration and `authorizeUsing()` check as any other actor. The fact that an actor is of type `AI` does not grant it any additional capabilities or bypass any authorization gate. An AI actor that has not been granted `'discharge_patient'` in its capability list cannot invoke that capability.

> [!CAUTION]
> Never omit `withCapabilities()` for AI actors and rely on `authorizeUsing()` alone. Declare the minimal set of capabilities the AI agent genuinely needs.

---

## Example: Syncing Actors from Your Users Table

**On boot (global registry)** — suitable for system and AI actors with fixed capabilities:

```php
// In AppServiceProvider::boot()
Alamia360::actors()->register(
    Actor::system('queue-worker', 'system')
        ->withCapabilities(['send_reminder_email', 'generate_invoice'])
);
```

**Per-request (request-scoped registry)** — suitable for human actors authenticated per HTTP request:

```php
// In an auth middleware or a request lifecycle hook
$user = Auth::user();

if ($user) {
    $actor = Actor::human((string) $user->id, $user->role)
        ->withCapabilities($user->capability_list)
        ->authorizeUsing(fn($actor, $cap, $subject) => Gate::forUser($user)->allows($cap, $subject));

    Alamia360::actors()->register($actor);
}
```

Keep per-request registration lightweight — the `ActorRegistry` is a simple in-memory store and does not hit the database on `find()`.
