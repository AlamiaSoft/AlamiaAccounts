# Organizational Data Fabric

## What the Organizational Data Fabric Is

The Organizational Data Fabric is the static definition layer that describes *who* and *what* exists in your application: the entities that matter, the relationships between them, the roles that carry responsibility, and the capabilities those roles can exercise. The term "fabric" is intentional — it is woven at boot time from many threads (entities, relationships, roles, responsibilities) into a coherent structure that any layer of the system can query without hitting the database directly.

The fabric does not replace your database schema or your Eloquent models. It sits above them, providing a semantic representation of your domain that is safe to share with AI, auditable, and queryable by any actor type.

---

## Entities

An entity is a named concept in your domain. Entities are registered with `Alamia360::entity(string $name)` and described with a fluent builder. Common examples from a veterinary practice application:

| Entity name | Description |
|---|---|
| `patient` | A registered animal receiving care |
| `appointment` | A scheduled consultation between a patient and a clinician |
| `invoice` | A billing record for services rendered |
| `lab_result` | A diagnostic test result attached to a patient |

Entities carry:
- A **description** — a plain-language explanation of what the entity represents.
- **Attributes** — named fields with types and labels, mirroring the data that matters semantically (not necessarily every column).
- **Relationships** — pointers to other named entities.
- A **resolver** — a closure that loads a concrete instance by ID when needed.

```php
Alamia360::entity('patient')
    ->describe('A registered animal receiving veterinary care')
    ->attribute('name',       ['type' => 'string',   'label' => 'Patient Name'])
    ->attribute('species',    ['type' => 'string',   'label' => 'Species'])
    ->attribute('birth_date', ['type' => 'date',     'label' => 'Date of Birth'])
    ->relatesTo('owner',        'client',      'belongsTo')
    ->relatesTo('appointments', 'appointment', 'hasMany')
    ->resolveUsing(fn(int $id) => Patient::with('owner')->find($id));
```

---

## Relationships

Relationships are declared on an entity definition using `relatesTo(string $attribute, string $entityName, string $type)`. The `$type` parameter follows Eloquent naming conventions:

| Type | Meaning |
|---|---|
| `belongsTo` | This entity holds a foreign key pointing to the related entity |
| `hasMany` | This entity is the parent; the related entity holds the foreign key |
| `hasOne` | Same as `hasMany` but cardinality is one |
| `belongsToMany` | Many-to-many through a pivot |

```php
Alamia360::entity('appointment')
    ->relatesTo('patient',   'patient',   'belongsTo')
    ->relatesTo('clinician', 'clinician', 'belongsTo')
    ->relatesTo('invoice',   'invoice',   'hasOne');
```

Relationship declarations are semantic metadata — Alamia 360 does not execute joins. They exist so that context builders and AI consumers understand the entity graph without inspecting the database schema.

---

## Roles and Departments in the Responsibility Model

Roles are first-class concepts in Alamia 360's responsibility model. A responsibility declaration binds a situation type to one or more roles, optionally specifying escalation paths and deadlines.

```php
Alamia360::responsibility('lab_result_review_required')
    ->role('veterinarian')
    ->escalatesTo('chief_vet', afterMinutes: 60)
    ->dueWithin(120);
```

Roles are strings — they match the role attached to an `Actor` at registration time. Departments are a higher-level grouping that can appear in responsibility resolution when you implement a custom `ResponsibilityResolverContract`. The default resolver works at the role level.

See [Responsibilities](./responsibilities.md) for the full resolution mechanics.

---

## Workflows and Policies as Extension Points

The current fabric layer focuses on entities, relationships, capabilities, and responsibilities. Workflow definitions (multi-step processes with branching) and policy objects (fine-grained attribute-level access rules) are planned extension points. The architecture accommodates them without changes to the existing layers: a workflow registry and a policy engine would sit alongside `CapabilityRegistry` and `ResponsibilityRegistry` in the fabric layer, consumed by the Orchestrator.

---

## Data Access: Selective Context via ContextBuilder

When a situation arises and the Orchestrator needs to reason about it, it does not send the entire database to an AI model. Instead, it calls the `ContextBuilder` to assemble an `OperationalContext` — a selective, bounded snapshot of the relevant entities, relationships, and situational data.

```php
use Alamia\Alamia360\Facades\Alamia360;

$context = Alamia360::context()
    ->forSituation($situation, $actor);
// Returns OperationalContext
```

The `ContextBuilder` resolves the situation's subject entity (using `resolveUsing`), traverses declared relationships to a configurable depth, includes the actor's capabilities, and packages the result. Only what is semantically declared is included — raw table data that has not been registered as an entity attribute is never surfaced.

---

## `OperationalContext::toArray()` for AI Prompt Construction

`OperationalContext` is the object passed to the AI harness when reasoning is needed. It is also the shape that custom planners receive when you configure `planUsing(Closure)` on the Orchestrator.

```php
$array = $context->toArray();
/*
[
    'situation'   => ['type' => 'lab_result_review_required', 'summary' => '...', 'priority' => 'high', ...],
    'subject'     => ['entity' => 'lab_result', 'id' => 42, 'attributes' => [...]],
    'actor'       => ['id' => 'user_7', 'type' => 'human', 'role' => 'veterinarian', 'capabilities' => [...]],
    'related'     => [...],  // resolved relationships at configured depth
    'timestamp'   => '2026-09-24T13:00:00Z',
]
*/
```

Pass `$context->toArray()` as the `$context` parameter when constructing AI prompts. The array is structured, predictable, and safe — it contains only what the fabric layer declares, not raw query results.

> [!TIP]
> Keep `resolveUsing` closures lean. Load only the attributes you have declared in `attribute()` calls. Eager-loading large relationships inside the resolver will bloat the context and increase token costs.

See [AI Integration](./ai-integration.md) for how `OperationalContext` flows into the `AiHarness`.
