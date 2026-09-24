# Security

## Security as a First-Class Concern

Security in Alamia 360 is structural, not advisory. Authorization is enforced at the execution layer rather than relying on call-site discipline. Audit records are written on every execution path. AI actors are subject to the same restrictions as human actors. The host application owns the authorization rules; Alamia 360 enforces them.

---

## Authorization Model: Host App Owns It, Alamia 360 Enforces It

Alamia 360 does not define what an actor is allowed to do — your application does, through `withCapabilities()` and `authorizeUsing()`. Alamia 360's role is to call those declarations at the right moment and refuse execution if they return false.

This means your existing Laravel Gates, Policies, and permission tables remain the source of truth. Alamia 360 wraps them, not replaces them.

```php
$actor = Actor::human($user->id, $user->role)
    ->withCapabilities($user->permission_list)
    ->authorizeUsing(fn($actor, $capability, $subject) => Gate::forUser($user)->allows($capability, $subject));
```

---

## CapabilityExecutor's Two Checks

Every capability invocation — regardless of origin (REST, MCP, Orchestrator, direct) — passes through `CapabilityExecutor::execute()`, which applies two authorization checks in sequence:

**Check 1 — Actor type restriction:**
The capability's `allowedFor()` declaration is compared against the actor's type. If the actor's type is not in the allowed list, execution stops immediately with a `CapabilityAuthorizationException`. No handler code runs.

```php
// Capability declared as: ->allowedFor(['human'])
// Actor type: AI → CapabilityAuthorizationException thrown
```

**Check 2 — Actor authorization:**
`$actor->isAuthorizedFor($capability, $subject)` is called. This applies the `withCapabilities()` list check first, then the `authorizeUsing()` closure. If either fails, a `CapabilityAuthorizationException` is thrown.

Neither check can be bypassed by any caller. MCP clients, AI agents, and orchestrated plans all pass through the same path.

---

## AI Actors and Least Privilege

An actor of type `AI` is granted no special permissions by virtue of being AI. It must have the capability declared in `withCapabilities()` and must pass the `authorizeUsing()` check like any other actor.

```php
$aiActor = Actor::ai('gpt-agent-1', 'assistant')
    ->withCapabilities(['get_todays_appointments', 'summarize_lab_result'])
    // 'discharge_patient' is NOT in the list
    ->authorizeUsing(fn($actor, $cap, $subject) => in_array($cap, $actor->capabilities()));
```

Attempting to invoke `'discharge_patient'` as this actor will throw `CapabilityAuthorizationException` before any handler runs.

> [!CAUTION]
> Never use a wildcard capability list for AI actors. Declare the minimal set of capabilities the agent genuinely needs to accomplish its task.

---

## `authorizeUsing()` + Laravel Gate Integration

The recommended pattern for human actors is to delegate the `authorizeUsing()` closure directly to a Laravel Gate check, using the authenticated user object captured in middleware:

```php
// In auth middleware:
$user = Auth::user();
$actor = Actor::human((string) $user->id, $user->role)
    ->withCapabilities($user->resolveCapabilities()) // your app's permission resolution
    ->authorizeUsing(function ($actor, $capability, $subject) use ($user) {
        return Gate::forUser($user)->allows($capability, $subject);
    });

Alamia360::actors()->register($actor);
```

Gate policies work identically — `Gate::forUser($user)->allows('review_lab_result', $labResult)` will invoke your `LabResultPolicy::reviewLabResult()` method transparently.

---

## Audit Trail: Every Capability Execution Logged

The `AuditService` writes a record for every capability invocation, whether it succeeds or throws:

| Field | Content |
|---|---|
| `capability` | The name of the capability invoked. |
| `actor_id` | The actor's ID. |
| `actor_type` | Human, AI, or System. |
| `input` | The sanitized input array (sensitive fields should be filtered before passing). |
| `result` | The serialized result on success, or `null` on error. |
| `error` | The exception class and message on failure, or `null` on success. |
| `executed_at` | Timestamp of the invocation. |

Query the audit trail:

```php
use Alamia\Alamia360\Audit\AuditService;

$audit = app(AuditService::class);

$audit->forActor('user_42');                    // Collection<AuditEventModel>
$audit->forCapability('review_lab_result');     // Collection<AuditEventModel>
$audit->forSituation(1);                        // Collection<AuditEventModel>
```

The audit table is populated even when a capability fails or authorization is denied (the denied check is logged before the exception propagates).

---

## Tenant Isolation

Alamia 360 does not manage multi-tenancy directly, but the authorization model supports it through two mechanisms:

**Via `authorizeUsing()`:**
```php
->authorizeUsing(function ($actor, $capability, $subject) use ($user) {
    // Check that $subject belongs to the actor's tenant before allowing
    if ($subject instanceof TenantScoped && $subject->tenant_id !== $user->tenant_id) {
        return false;
    }
    return Gate::forUser($user)->allows($capability, $subject);
});
```

**Via repository filtering:**
Inject a tenant-scoped repository into your application services. Because capability `handleUsing()` closures delegate to application services, and application services query through scoped repositories, tenant isolation is enforced at the data layer without any Alamia 360 changes.

---

## Sensitive Data: What Goes Into Context

`Observation` and `Situation` context arrays are persisted to the database and may be passed to an AI provider. Apply the following guidelines:

| Do include | Do not include |
|---|---|
| Entity IDs and public attributes | Passwords, API keys, tokens |
| Status flags and scalar metrics | PII beyond what is operationally necessary |
| Timestamps and type strings | Full model instances or large blobs |
| Diagnostic flags (e.g. `abnormal: true`) | Payment card numbers or health record details not needed for the action |

The resolver pattern (`resolveUsing`) deliberately separates entity lookup from context assembly: the entity is only loaded when a consumer explicitly requests it, not on every observation.

---

## Approval Requirements: Human-in-the-Loop Extension Point

For high-risk capabilities (e.g. `'destructive'` side effects), add an approval gate in the `authorizeUsing()` closure or in a custom verifier registered with `Orchestrator::verifyUsing()`:

```php
->authorizeUsing(function ($actor, $capability, $subject) {
    if ($capability === 'delete_patient_record') {
        // Check that a human has pre-approved this action
        return ApprovalRequest::approved($actor->id(), $capability, $subject?->id);
    }
    return Gate::allows($capability, $subject);
});
```

This pattern enables asynchronous human approval flows without changing the capability or orchestration implementation.
