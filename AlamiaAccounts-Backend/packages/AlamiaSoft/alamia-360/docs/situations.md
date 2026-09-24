# Situations

## What a Situation Is

A `Situation` is the central operational concept in Alamia 360. It represents a named, prioritized condition in the application that requires attention — not a raw event, and not a generic notification. A situation has a type that corresponds to a registered responsibility, a subject (the entity the situation is about), a priority, and a lifecycle status.

Situations are what the [Orchestrator](./orchestration.md) acts on. They are the bridge between observed events and purposeful action.

---

## Situation Properties

| Property | Type | Meaning |
|---|---|---|
| `id` | `int\|null` | Database-assigned ID once persisted. `null` before persistence. |
| `type` | `string` | A string matching a registered responsibility type (e.g. `lab_result_review_required`). |
| `summary` | `string` | A plain-language description of the situation, safe to display to actors or include in AI context. |
| `priority` | `SituationPriority` | Enum: `Low`, `Normal`, `High`, or `Critical`. |
| `subjectType` | `string` | The registered entity name the situation is about (e.g. `lab_result`). |
| `subjectId` | `string\|int` | The ID of the specific entity instance. |
| `context` | `array` | Arbitrary key-value payload providing additional situational data. |
| `source` | `string\|null` | The observation type that triggered this situation, when applicable. |

---

## SituationPriority Values

| Value | Use when |
|---|---|
| `Low` | The condition is worth tracking but not time-sensitive. |
| `Normal` | Standard operational attention is required. |
| `High` | Prompt action is expected; escalation is likely if unaddressed. |
| `Critical` | Immediate response is required; the situation may have patient safety or data integrity implications. |

```php
use Alamia\Alamia360\Situations\SituationPriority;

$priority = SituationPriority::High;
```

---

## SituationStatus State Machine

Situations move through a defined set of statuses. Not all transitions are valid from every state.

| Status | Meaning |
|---|---|
| `Open` | The situation has been detected and is awaiting acknowledgement or action. |
| `Acknowledged` | An actor has noted the situation. |
| `InProgress` | Active work is underway. |
| `Escalated` | The situation has been escalated to a higher role or priority. |
| `Resolved` | The condition has been addressed and the situation is closed successfully. |
| `Dismissed` | The situation was reviewed and determined to require no action. |

Valid transitions:

```
Open → Acknowledged → InProgress → Resolved
                                 → Dismissed
     → Escalated    → InProgress → Resolved
                                 → Dismissed
```

Transition using the `transitionTo()` method:

```php
$situation->transitionTo(SituationStatus::Acknowledged);
$situation->transitionTo(SituationStatus::InProgress);
$situation->transitionTo(SituationStatus::Resolved);
```

---

## Idempotency: How Deduplication Works

If a situation with the same `type`, `subjectType`, and `subjectId` already exists with status `Open`, creating a new one will not produce a duplicate row. The `SituationRegistry` checks for an open situation matching those three fields before persisting.

This means scheduled scans and repeated event listeners can call `observe()` freely without accumulating phantom situations:

```php
// Safe to call multiple times — only one open situation will exist
// for type='lab_result_review_required', subjectType='lab_result', subjectId=42
Alamia360::observer()->observe(new Observation(...));
```

---

## SituationRegistry

The `SituationRegistry` is the runtime store for situations. It delegates persistence to whichever repository is configured (Eloquent by default).

```php
use Alamia\Alamia360\Facades\Alamia360;

// Record a new situation (idempotency check is applied here)
Alamia360::situations()->record($situation);

// Retrieve all open situations
$open = Alamia360::situations()->open(); // Situation[]

// Retrieve open situations of a specific type
$toReview = Alamia360::situations()->ofType('lab_result_review_required'); // Situation[]

// Retrieve all situations (all statuses)
$all = Alamia360::situations()->all(); // Situation[]

// Replace the persistence backend
Alamia360::situations()->persistUsing($customRepository);
```

---

## Eloquent Persistence: SituationModel and the Migration

When the default Eloquent driver is active, situations are stored in the `alamia360_situations` table. The migration is published by `alamia360:install` and creates the following columns:

| Column | Type | Notes |
|---|---|---|
| `id` | `bigIncrements` | Primary key |
| `type` | `string` | Situation type string |
| `summary` | `text` | Human-readable summary |
| `priority` | `string` | Enum value stored as string |
| `status` | `string` | Current status, default `open` |
| `subject_type` | `string` | Entity name |
| `subject_id` | `string` | Entity instance ID |
| `context` | `json` | Situational context payload |
| `source` | `string\|null` | Originating observation type |
| `recommended_action` | `string\|null` | Set by `recommend()` |
| `assigned_to` | `string\|null` | Actor ID, set by `assignTo()` |
| `due_at` | `timestamp\|null` | Deadline set by responsibility |
| `resolved_at` | `timestamp\|null` | Populated on resolution |
| `created_at` / `updated_at` | `timestamp` | Standard Laravel timestamps |

---

## Recommended Action: How `recommend()` Feeds the Orchestrator

Calling `recommend()` on a situation stores the name of the capability that should be invoked when the situation is processed. This is how the deterministic planning path works — no AI needed:

```php
$situation->recommend('review_lab_result');

// Later, in the Orchestrator's default planner:
$plan = ['capability' => $situation->recommendedAction()]; // 'review_lab_result'
```

When `recommend()` has been called, the Orchestrator's default planner uses the stored capability name directly. If no recommended action is set and no custom planner is configured, the Orchestrator returns `null` and logs a warning.

See [Orchestration](./orchestration.md) for the full planning lifecycle.
