# Responsibilities

## The Responsibility Question: "Who Should Act?"

When a [Situation](./situations.md) is detected, the system must answer a fundamental question: *which actor is responsible for addressing it?* The responsibility layer maps situation types to roles, users, or departments, and defines escalation paths and deadlines so that ownership is never ambiguous.

Responsibilities are declared in the semantic layer at boot time and resolved at runtime when a situation needs to be assigned.

---

## Responsibility DSL

Use `Alamia360::responsibility(string $situationType)` to declare who handles a given situation type. The method returns a fluent `Responsibility` builder.

```php
use Alamia\Alamia360\Facades\Alamia360;

Alamia360::responsibility('lab_result_review_required')
    ->role('veterinarian')
    ->escalatesTo('chief_vet', afterMinutes: 60)
    ->dueWithin(120);
```

| Method | Signature | Purpose |
|---|---|---|
| `role` | `role(string $role)` | Assigns the situation type to a role. Multiple calls add alternative roles. |
| `user` | `user(string $userId)` | Assigns directly to a specific user ID (highest priority in resolution). |
| `department` | `department(string $department)` | Assigns to a department string (used by custom resolvers). |
| `escalatesTo` | `escalatesTo(string $role, int $afterMinutes)` | Defines the role to escalate to and the escalation window in minutes. |
| `dueWithin` | `dueWithin(int $minutes)` | Sets a deadline relative to situation creation. |

Multiple situation types map to separate responsibility declarations:

```php
Alamia360::responsibility('appointment.unassigned')
    ->role('receptionist')
    ->escalatesTo('practice_manager', afterMinutes: 30)
    ->dueWithin(60);

Alamia360::responsibility('invoice.overdue')
    ->role('billing_officer')
    ->dueWithin(1440); // 24 hours
```

---

## ResponsibilityRegistry: `define` and `for`

```php
use Alamia\Alamia360\Facades\Alamia360;

// Retrieve the Responsibility for a situation type
$responsibility = Alamia360::responsibilities()->for('lab_result_review_required');
// Returns Responsibility|null

// Programmatic definition (equivalent to using the facade fluent builder)
Alamia360::responsibilities()->define(
    new Responsibility(type: 'lab_result_review_required', role: 'veterinarian')
);
```

---

## DefaultResponsibilityResolver: How Resolution Works

The `DefaultResponsibilityResolver` implements `ResponsibilityResolverContract` and applies the following priority order when determining who should handle a situation:

1. **User** — if a specific `user()` is declared and that actor is registered, they are selected.
2. **Role** — all registered actors matching the declared `role()` are returned.
3. **Department** — all registered actors matching the `department()` string are returned (requires a custom resolver to act on this).

If no match is found at any level, the resolver returns an empty collection and the situation remains unassigned.

```php
use Alamia\Alamia360\Responsibilities\DefaultResponsibilityResolver;
use Alamia\Alamia360\Facades\Alamia360;

$resolver = app(DefaultResponsibilityResolver::class);
$actors = $resolver->resolve($situation); // Collection<ActorContract>
```

---

## Escalation Data Structure

Call `getEscalation()` on a `Responsibility` to retrieve the escalation specification:

```php
$responsibility = Alamia360::responsibilities()->for('lab_result_review_required');

$escalation = $responsibility->getEscalation();
/*
[
    'role'         => 'chief_vet',
    'after_minutes' => 60,
]
*/
```

Returns `null` if no escalation is declared. Alamia 360 does not run an automatic escalation timer — the host application is responsible for polling open situations against their `due_at` timestamps and transitioning them to `SituationStatus::Escalated`.

---

## Extending: Implementing `ResponsibilityResolverContract`

When role-based resolution is not sufficient — for example, when availability calendars, workload balancing, or AI-based assignment are needed — implement the `ResponsibilityResolverContract`:

```php
use Alamia\Alamia360\Contracts\ResponsibilityResolverContract;
use Alamia\Alamia360\Situations\Situation;
use Illuminate\Support\Collection;

class AvailabilityAwareResolver implements ResponsibilityResolverContract
{
    public function resolve(Situation $situation): Collection
    {
        $responsibility = Alamia360::responsibilities()->for($situation->type);

        if (! $responsibility) {
            return collect();
        }

        // Query your availability or scheduling system
        return collect(
            $this->scheduleService->availableActorsForRole(
                $responsibility->role(),
                now()
            )
        );
    }
}
```

---

## Wiring a Custom Resolver in a Service Provider

Bind your custom resolver to the `ResponsibilityResolverContract` in your application's service provider. Alamia 360's service container binding will be overridden:

```php
use Alamia\Alamia360\Contracts\ResponsibilityResolverContract;

public function register(): void
{
    $this->app->bind(
        ResponsibilityResolverContract::class,
        AvailabilityAwareResolver::class
    );
}
```

The `Orchestrator` resolves `ResponsibilityResolverContract` from the container, so no other changes are needed.
