# Orchestration

## The Observe→Interpret→Assess→Plan→Authorize→Act→Verify Lifecycle

Every meaningful action in Alamia 360 follows a seven-phase lifecycle:

| Phase | Description |
|---|---|
| **Observe** | An `Observation` is created from a domain event, state scan, schedule, or external signal. |
| **Interpret** | The `Observer` passes the observation to registered `SituationDetector` instances. |
| **Assess** | Detectors evaluate the observation and produce `Situation` objects where conditions are met. |
| **Plan** | The `Orchestrator` determines which capability to invoke (deterministic or AI-assisted). |
| **Authorize** | The `CapabilityExecutor` verifies the actor type and calls `isAuthorizedFor()`. |
| **Act** | The capability handler runs and returns a result. The audit record is written. |
| **Verify** | An optional extension point inspects the `ActionOutcome` before it is returned to the caller. |

The `Observer` handles Observe → Interpret → Assess. The `Orchestrator` handles Plan → Authorize → Act → Verify.

---

## `process(Situation $situation, ActorContract $performedBy): ?Action`

The primary entry point into the Orchestrator:

```php
use Alamia\Alamia360\Facades\Alamia360;

$action = Alamia360::orchestrator()->process($situation, $actor);
```

`process()` performs the following in sequence:
1. Resolves the responsible actors via `ResponsibilityResolverContract`.
2. Invokes the configured planner to produce a plan `['capability' => string, 'input' => array]`.
3. Delegates to `CapabilityExecutor::execute()` with the plan and the actor.
4. Runs the verify phase if a verifier is configured.
5. Returns an `Action` wrapping the outcome, or `null` if planning failed.

---

## Default Planning: `situation->recommendedAction()` → Deterministic

When no custom planner is set, the Orchestrator uses the situation's `recommendedAction()` as the plan. This is the **deterministic path** — no AI call is made:

```php
// During situation creation or detection:
$situation->recommend('review_lab_result');

// During orchestration — the planner reads the stored recommendation:
$plan = ['capability' => $situation->recommendedAction(), 'input' => ['result_id' => $situation->subjectId]];
```

Use this path whenever the correct capability to invoke is knowable from the situation type alone. This covers the majority of operational workflows.

---

## Custom Planner: `planUsing(Closure)` → Rule-Based or AI-Driven

Provide a custom planner closure when the choice of capability depends on runtime factors that cannot be determined statically:

```php
Alamia360::orchestrator()->planUsing(function (Situation $situation, array $actors) {
    // Rule-based example
    if ($situation->priority === SituationPriority::Critical) {
        return [
            'capability' => 'escalate_to_emergency',
            'input'      => ['situation_id' => $situation->id],
        ];
    }

    return [
        'capability' => 'review_lab_result',
        'input'      => ['result_id' => $situation->subjectId],
    ];
});
```

For AI-driven planning, pass the situation through `AiHarness::reasonAbout()` inside the closure:

```php
Alamia360::orchestrator()->planUsing(function (Situation $situation, array $actors) {
    $context = Alamia360::context()->forSituation($situation, $actors[0] ?? null);
    $reasoning = app(AiHarness::class)->reasonAbout(
        'Which capability should be invoked for this situation?',
        $context
    );
    // Parse $reasoning into a plan array
    return json_decode($reasoning, true);
});
```

> [!IMPORTANT]
> Even with an AI-generated plan, the **Authorize** and **Act** phases are still enforced. The AI cannot bypass `isAuthorizedFor()` by constructing a plan that names a restricted capability.

---

## Action and ActionOutcome Domain Objects

The Orchestrator returns an `Action` object that wraps the planning decision and its outcome:

```php
$action = Alamia360::orchestrator()->process($situation, $actor);

if ($action !== null) {
    $outcome = $action->outcome();          // ActionOutcome

    $outcome->succeeded();                  // bool
    $outcome->result();                     // mixed — the value returned by handleUsing()
    $outcome->error();                      // \Throwable|null
    $outcome->capability();                 // string — capability name
    $outcome->actor();                      // ActorContract
    $outcome->executedAt();                 // Carbon
}
```

`ActionOutcome` is also what the `AuditService` persists. See [Security](./security.md) for the audit record schema.

---

## Verify Phase: Extension Point

Register a verifier closure to inspect or react to every `ActionOutcome` after execution:

```php
Alamia360::orchestrator()->verifyUsing(function (Action $action, Situation $situation) {
    if (! $action->outcome()->succeeded()) {
        Log::error('Capability execution failed', [
            'capability' => $action->outcome()->capability(),
            'situation'  => $situation->id,
            'error'      => $action->outcome()->error()?->getMessage(),
        ]);
    }

    // Optionally transition the situation based on outcome
    if ($action->outcome()->succeeded()) {
        $situation->transitionTo(SituationStatus::Resolved);
    }
});
```

The verifier runs inside `process()` after the capability executes. It is not a gate — it cannot prevent the outcome, only react to it.

---

## Deterministic-First Principle: When NOT to Use AI

Engage AI planning only when no deterministic rule can produce the answer. Signs that deterministic planning is correct:

- The situation type maps 1-to-1 with a capability (use `recommend()`).
- The choice of capability depends only on the situation's properties (use a rule-based `planUsing()` closure).
- The input to the capability can be derived directly from `$situation->subjectId` or `$situation->context`.

Signs that AI planning may be warranted:

- Multiple capabilities are plausible and the choice requires understanding free-text context.
- The optimal input parameters cannot be derived from structured data alone.
- The situation requires interpreting relationships between multiple entities.

---

## Full End-to-End Example

```php
use Alamia\Alamia360\Facades\Alamia360;
use Alamia\Alamia360\Observation\Observation;
use Alamia\Alamia360\Observation\ObservationSource;
use Alamia\Alamia360\Actors\Actor;

// 1. Register entity, capability, responsibility, and actor (on boot)
Alamia360::entity('lab_result')
    ->describe('A diagnostic lab result')
    ->attribute('abnormal', ['type' => 'boolean', 'label' => 'Abnormal Flag'])
    ->resolveUsing(fn(int $id) => LabResult::find($id));

Alamia360::capability('review_lab_result')
    ->describe('Marks a lab result as reviewed by a veterinarian')
    ->input(['result_id' => ['type' => 'integer', 'required' => true]])
    ->output(['reviewed' => ['type' => 'boolean']])
    ->withSideEffect('write')
    ->allowedFor(['human'])
    ->handleUsing(fn(array $input, $actor) => app(LabResultService::class)->review($input['result_id'], $actor->id()));

Alamia360::responsibility('lab_result_review_required')
    ->role('veterinarian')
    ->escalatesTo('chief_vet', afterMinutes: 60)
    ->dueWithin(120);

$actor = Actor::human('user_7', 'veterinarian')
    ->withCapabilities(['review_lab_result'])
    ->authorizeUsing(fn($a, $cap, $subject) => Gate::forUser($user)->allows($cap, $subject));

Alamia360::actors()->register($actor);
Alamia360::observer()->addDetector(new AbnormalLabResultDetector());

// 2. Observe (triggered from a domain event listener)
Alamia360::observer()->observe(new Observation(
    type:       'lab_result.created',
    source:     ObservationSource::DomainEvent,
    entityType: 'lab_result',
    entityId:   42,
    payload:    ['abnormal' => true],
));

// 3. A Situation 'lab_result_review_required' for lab_result#42 is now Open

// 4. Orchestrate (triggered by controller, queue job, or AI agent)
$situation = Alamia360::situations()->ofType('lab_result_review_required')->first();
$situation->recommend('review_lab_result');

$action = Alamia360::orchestrator()->process($situation, $actor);

// $action->outcome()->succeeded() === true
// Audit record written. Situation can now be transitioned to Resolved.
```
