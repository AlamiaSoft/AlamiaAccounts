# Semantic Interface

## Purpose

The semantic interface is the static description layer of Alamia 360. It answers the question: *what does this application know about?* Rather than exposing database tables or raw Eloquent models, you register named entity definitions, capability declarations, and responsibility mappings. Any consumer — a human operator, an AI agent, or an MCP client — interacts with the application through this vocabulary instead of through raw SQL or ad-hoc endpoints.

---

## `entity()` — Defining Application Entities

Use `Alamia360::entity(string $name)` to declare a named concept in your domain. The call returns an `EntityDefinition` instance with a fluent builder API.

```php
use Alamia\Alamia360\Facades\Alamia360;

Alamia360::entity('appointment')
    ->describe('A scheduled appointment between a patient and a clinician')
    ->attribute('status', [
        'type'    => 'string',
        'label'   => 'Status',
        'values'  => ['scheduled', 'completed', 'cancelled'],
    ])
    ->attribute('scheduled_at', [
        'type'  => 'datetime',
        'label' => 'Scheduled At',
    ])
    ->relatesTo('patient', 'patient', 'belongsTo')
    ->relatesTo('clinician', 'clinician', 'belongsTo')
    ->resolveUsing(fn(string|int $id) => Appointment::find($id));
```

| Method | Signature | Purpose |
|---|---|---|
| `describe` | `describe(string $description)` | Human-readable description of the entity. |
| `attribute` | `attribute(string $name, array $schema)` | Declares a semantic attribute with a type and optional label/values. |
| `relatesTo` | `relatesTo(string $attribute, string $entityName, string $type)` | Declares a relationship to another named entity. |
| `resolveUsing` | `resolveUsing(callable $resolver)` | Provides a closure that retrieves a concrete instance by ID. |

---

## `SemanticRegistry` — Retrieving Entity Definitions

The `SemanticRegistry` holds every entity registered during boot. Access it via the facade or by injecting the class directly.

```php
use Alamia\Alamia360\Facades\Alamia360;

// Retrieve a single definition
$definition = Alamia360::semantics()->entity('appointment');

// Retrieve all registered entity definitions
$all = Alamia360::semantics()->allEntities(); // EntityDefinition[]
```

Trying to retrieve an entity that has not been registered will return `null` from `entity()`. Guard accordingly if the registry is populated dynamically.

---

## Relationships (`relatesTo`, `RelationshipDefinition`)

Calling `->relatesTo($attribute, $entityName, $type)` on an `EntityDefinition` creates a `RelationshipDefinition` that describes how two named entities are connected.

```php
Alamia360::entity('invoice')
    ->describe('A billing invoice')
    ->relatesTo('patient', 'patient', 'belongsTo')
    ->relatesTo('line_items', 'invoice_line_item', 'hasMany');
```

The `$type` parameter follows Eloquent naming conventions (`belongsTo`, `hasMany`, `hasOne`, `belongsToMany`) but is used semantically — Alamia 360 does not manage the actual database join. The relationship declarations exist so that context builders and AI consumers understand how entities connect without querying the schema.

Retrieve declared relationships from a definition:

```php
$relationships = Alamia360::semantics()->entity('invoice')->relationships();
// Returns RelationshipDefinition[]
```

---

## `capability()` — Declaring Application Capabilities

Use `Alamia360::capability(string $name)` to declare a named action the application can perform. Capabilities are the executable surface of the semantic interface — the things actors are permitted to do.

```php
Alamia360::capability('get_todays_appointments')
    ->describe('Returns all appointments scheduled for today')
    ->input([
        'date' => ['type' => 'string', 'required' => false, 'description' => 'ISO 8601 date; defaults to today'],
    ])
    ->output([
        'appointments' => ['type' => 'array'],
    ])
    ->withSideEffect('read')
    ->allowedFor(['human', 'ai'])
    ->handleUsing(fn(array $input, $actor) => app(AppointmentService::class)->today());
```

| Method | Purpose |
|---|---|
| `describe(string $desc)` | Human/AI-readable description of the capability. |
| `input(array $schema)` | Declares named input parameters with types and required flags. |
| `output(array $schema)` | Declares the shape of the returned data. |
| `withSideEffect(string $effect)` | Marks the capability as `'read'`, `'write'`, or `'destructive'`. |
| `allowedFor(array $actorTypes)` | Restricts which actor types (`'human'`, `'ai'`, `'system'`) may invoke this capability. |
| `handleUsing(callable $handler)` | The closure that performs the work. Receives `(array $input, ActorContract $actor)`. |

See [Capabilities](./capabilities.md) for authorization and execution details.

---

## `CapabilityRegistry` — Managing Capabilities

```php
// Check existence
Alamia360::semantics()->hasCapability('get_todays_appointments'); // bool

// Retrieve a single capability
$cap = Alamia360::semantics()->getCapability('get_todays_appointments'); // Capability|null

// Retrieve all
$caps = Alamia360::semantics()->allCapabilities(); // Capability[]
```

> [!NOTE]
> You can also register a pre-built `Capability` object directly if you prefer constructing it outside the fluent builder. The `CapabilityRegistry` accepts both approaches.

---

## `responsibility()` — Declaring Responsibilities

Use `Alamia360::responsibility(string $situationType)` to declare who should respond when a situation of a given type arises.

```php
Alamia360::responsibility('lab_result_review_required')
    ->role('veterinarian')
    ->escalatesTo('chief_vet', afterMinutes: 60)
    ->dueWithin(120);
```

See [Responsibilities](./responsibilities.md) for the full DSL and resolution mechanics.

---

## When to Use `resolveUsing`

`resolveUsing` is the bridge between the semantic layer and the persistence layer. Provide it whenever a consumer needs to load a live instance of the entity by ID — for example, when the `ContextBuilder` assembles an `OperationalContext` for a situation whose subject is an appointment.

```php
Alamia360::entity('patient')
    ->describe('A registered patient')
    ->resolveUsing(fn(int $id) => Patient::with('owner')->find($id));
```

You may include eager-loads and query scopes inside the closure. The closure is only called when resolution is explicitly requested; registering an entity without `resolveUsing` is valid when you only need the semantic definition and never need to load an instance.
