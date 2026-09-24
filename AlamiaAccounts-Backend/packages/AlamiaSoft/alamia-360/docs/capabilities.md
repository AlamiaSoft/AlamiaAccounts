# Capabilities

## What a Capability Is

A capability is a thin, auditable wrapper around a unit of application behaviour. It is not where business logic lives — that lives in your application services. A capability is the *declared*, *authorized*, and *recorded* path through which any actor — human, AI, or system — invokes that behaviour.

Every action available through Alamia 360 (including via [MCP](./mcp.md) and the [Orchestrator](./orchestration.md)) goes through the capability layer. This means authorization is enforced in one place, audit records are written consistently, and the capability surface can be introspected by any consumer.

---

## Capability Fluent API

Register capabilities in a service provider using `Alamia360::capability(string $name)`:

```php
use Alamia\Alamia360\Facades\Alamia360;

Alamia360::capability('review_lab_result')
    ->describe('Allows a veterinarian to review and annotate a lab result')
    ->input([
        'result_id' => ['type' => 'integer', 'required' => true,  'description' => 'ID of the lab result to review'],
        'notes'     => ['type' => 'string',  'required' => false, 'description' => 'Clinician notes'],
    ])
    ->output([
        'reviewed'    => ['type' => 'boolean'],
        'reviewed_at' => ['type' => 'string', 'format' => 'datetime'],
    ])
    ->withSideEffect('write')
    ->allowedFor(['human'])
    ->handleUsing(function (array $input, $actor) {
        return app(LabResultService::class)->review(
            $input['result_id'],
            $actor->id(),
            $input['notes'] ?? null
        );
    });
```

| Method | Signature | Purpose |
|---|---|---|
| `describe` | `describe(string $desc)` | Human/AI-readable description used in context and MCP tool listings. |
| `input` | `input(array $schema)` | Declares named input parameters. Each key maps to a schema array with `type`, `required`, and optionally `description`. |
| `output` | `output(array $schema)` | Declares the shape of the returned data. Used for documentation and MCP tool schema generation. |
| `withSideEffect` | `withSideEffect(string $effect)` | Declares the side-effect class: `'read'`, `'write'`, or `'destructive'`. |
| `allowedFor` | `allowedFor(array $actorTypes)` | Restricts which actor types may invoke this capability. Values: `'human'`, `'ai'`, `'system'`. |
| `handleUsing` | `handleUsing(callable $handler)` | The handler closure. Receives `(array $input, ActorContract $actor)` and returns any value. |

---

## Side Effects: Implications

| Side effect | Meaning | Implications |
|---|---|---|
| `'read'` | Retrieves data, no state change. | Safe to call idempotently; low audit priority. |
| `'write'` | Creates or mutates state. | Audited; authorization strictly enforced; may require human-in-the-loop for AI actors. |
| `'destructive'` | Deletes or irreversibly alters data. | Highest scrutiny; should be restricted from AI actors by default; consider requiring explicit human approval. |

---

## CapabilityRegistry: `define`, `register`, `has`, `get`, `all`

```php
use Alamia\Alamia360\Facades\Alamia360;

// Check existence
Alamia360::semantics()->hasCapability('review_lab_result'); // bool

// Retrieve a single capability
$cap = Alamia360::semantics()->getCapability('review_lab_result'); // Capability|null

// Retrieve all registered capabilities
$caps = Alamia360::semantics()->allCapabilities(); // Capability[]
```

You can also register a pre-built `Capability` object directly if you construct it outside the fluent builder.

---

## CapabilityExecutor: The Single Execution Path

The `CapabilityExecutor` is the one place where capabilities are actually invoked. Whether the request comes from a REST controller, an MCP client, or the Orchestrator, every execution passes through this class:

```php
use Alamia\Alamia360\Facades\Alamia360;

$outcome = Alamia360::capabilities()->execute('review_lab_result', $input, $actor);
// Returns ActionOutcome
```

The executor performs the following steps:
1. Resolves the capability from the registry.
2. Checks that the actor's type is in `allowedFor()`.
3. Calls `$actor->isAuthorizedFor($capabilityName, $subject)`.
4. Invokes the `handleUsing` closure.
5. Writes the audit record via the configured `AuditService`.
6. Returns an `ActionOutcome`.

---

## Authorization Enforcement in the Executor

Two authorization checks are applied before the handler runs:

**Check 1 — Actor type:**
```php
// Internally: capability->allowedFor() returns ['human']
// If $actor->type() === ActorType::AI → CapabilityAuthorizationException thrown
```

**Check 2 — Actor authorization:**
```php
// Internally: $actor->isAuthorizedFor('review_lab_result', $subject)
// Runs the withCapabilities() list check, then the authorizeUsing() closure
// If false → CapabilityAuthorizationException thrown
```

If either check fails, a `CapabilityAuthorizationException` is thrown and no handler code runs.

---

## Audit Hook: `auditUsing()`

By default the executor uses the built-in `AuditService` to write audit records. You can provide a custom audit hook per executor call or by replacing the binding in the container:

```php
Alamia360::capabilities()->auditUsing(function (string $capability, ActorContract $actor, array $input, mixed $result, ?\Throwable $error) {
    // Your custom audit logic — write to a dedicated audit log, push to a SIEM, etc.
});
```

The hook receives the capability name, the actor, the sanitized input, the result (or `null` on error), and any thrown exception.

---

## `CapabilityAuthorizationException`

```php
use Alamia\Alamia360\Exceptions\CapabilityAuthorizationException;

try {
    Alamia360::capabilities()->execute('discharge_patient', $input, $actor);
} catch (CapabilityAuthorizationException $e) {
    // $e->getMessage() describes which check failed
}
```

---

## Best Practice: `handleUsing()` Delegates to an Application Service

Capability handlers should be thin. All domain logic, validation, and persistence belongs in your application service layer:

```php
// Good
->handleUsing(fn(array $input, $actor) => app(LabResultService::class)->review(
    $input['result_id'], $actor->id(), $input['notes'] ?? null
));

// Avoid — business logic directly in the handler makes capabilities hard to test
->handleUsing(function (array $input, $actor) {
    $result = LabResult::findOrFail($input['result_id']);
    $result->reviewed_by = $actor->id();
    $result->reviewed_at = now();
    $result->notes = $input['notes'] ?? null;
    $result->save();
    return $result;
});
```

---

## The Execution Flow

```
MCP Client / REST Controller / Orchestrator
            │
            ▼
    CapabilityExecutor::execute()
            │
     ┌──────┴──────┐
     │  Auth checks │  (actor type + isAuthorizedFor)
     └──────┬──────┘
            │
     handleUsing() closure
            │
     Application Service
            │
     Domain Logic / Eloquent
            │
     AuditService::record()
            │
     ActionOutcome returned
```

See [Orchestration](./orchestration.md) for how the Orchestrator feeds situations into this flow, and [MCP](./mcp.md) for how external agents reach it.
