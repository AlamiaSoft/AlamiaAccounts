# Alamia 360 — Laravel Reference Implementation

**Alamia 360** is an application-aware operational intelligence architecture.
It lets a Laravel app expose its semantic model, organizational data,
operational state, events, responsibilities and capabilities to humans, AI
agents and system actors — through one consistent path, not a chatbot bolted
onto CRUD.

This package is a **starter skeleton**: the architectural layers are wired
up and functional end-to-end, with intentionally minimal example wiring left
for your dev to flesh out for your actual domain.

## Architecture layers → code

| Concept (design guide)         | Namespace                          |
|---------------------------------|-------------------------------------|
| Semantic Interface               | `Alamia360\Semantic`               |
| Actors                           | `Alamia360\Actors`                 |
| Responsibilities                 | `Alamia360\Responsibilities`       |
| Capabilities / Actions           | `Alamia360\Capabilities`, `Alamia360\Actions` |
| Observation / Event Intelligence | `Alamia360\Observation`            |
| Situations                       | `Alamia360\Situations`             |
| Orchestration (Observe→Verify)   | `Alamia360\Orchestration`          |
| Context construction             | `Alamia360\Context`                |
| AI integration (optional)        | `Alamia360\AI`                     |
| MCP adapter                      | `Alamia360\MCP`                    |
| Persistence                      | `Alamia360\Infrastructure`         |

Everything is reachable through the `Alamia360` facade, but each registry
is also injectable directly.

## Install

```bash
composer require alamia/alamia-360   # once published, or path-repo it locally
php artisan vendor:publish --tag=alamia360-config
php artisan vendor:publish --tag=alamia360-migrations
php artisan migrate
```

## Minimal usage sketch (illustrative only — not a full domain build)

```php
use Alamia360\Facades\Alamia360;
use Alamia360\Actors\Actor;
use Alamia360\Observation\Observation;
use Alamia360\Observation\ObservationSource;
use Alamia360\Situations\Situation;
use Alamia360\Situations\SituationPriority;

// 1. Describe semantics
Alamia360::entity('lab_result')
    ->describe('A diagnostic lab result for a patient')
    ->relatesTo('patient', 'patient', 'belongsTo');

// 2. Declare a capability (delegates to your existing app service)
Alamia360::capability('get_pending_lab_results')
    ->describe('Returns lab results awaiting veterinarian review')
    ->allowedFor(['human', 'ai'])
    ->handleUsing(fn (array $input, $actor) => app(LabResultService::class)->pending());

// 3. Declare responsibility
Alamia360::responsibility('lab_result_review_required')->role('veterinarian');

// 4. Register actors (typically synced from your users/roles on boot or per-request)
Alamia360::actors()->register(
    Actor::human('user_42', 'veterinarian')
        ->withCapabilities(['get_pending_lab_results'])
        ->authorizeUsing(fn ($actor, $cap, $subject) => Gate::forUser($user)->allows($cap, $subject))
);

// 5. Feed an Observation (e.g. from a LabResultCreated Laravel event listener)
Alamia360::observer()->observe(new Observation(
    type: 'lab_result.created',
    source: ObservationSource::DomainEvent,
    entityType: 'lab_result',
    entityId: $labResult->id,
    payload: ['abnormal' => true],
));
```

A `SituationDetectorContract` implementation turns that observation into a
`Situation` (e.g. "Unreviewed abnormal lab result") when it's operationally
meaningful — plug in your own detectors via `Alamia360::observer()->addDetector(...)`.

## Deliberately out of scope for this starter drop

Per project scope, the following were **not** built out here and are left
for your dev:

- Full veterinary-clinic example domain (entities/capabilities are stubbed,
  not a working clinic app)
- Automated test suite
- Full narrative documentation set (this README + inline docblocks cover
  the essentials; expand `docs/` as needed)
- A concrete `SituationDetector`/`AiResponsibilityResolver` for your domain
- A wired MCP transport (the `McpAdapter` is transport-agnostic; pick a
  server package and call `listTools()` / `callTool()` from it)
- Eloquent-backed `SituationRegistry`/`ActorRegistry` persistence adapters
  (models + migrations are included; repositories that swap the in-memory
  registries for them are not)

## Key design decisions / deviations

- `SemanticRegistry`/`EntityDefinition` describe meaning, not Eloquent
  models directly — you wire a resolver closure per entity to your actual
  models, keeping the domain contract portable to non-Laravel implementations.
- `CapabilityExecutor` is the **single** execution path for internal API,
  REST, MCP and Agent callers — never bypassed, always authorization-checked
  against the host app via `ActorContract::isAuthorizedFor()`.
- AI is fully optional: `NullAiProvider` is bound by default; rebind
  `Alamia360\Contracts\AiProviderContract` to enable reasoning/classification.
- Responsibility resolution is deterministic/data-driven by default
  (`DefaultResponsibilityResolver`); an AI-based resolver can implement the
  same `ResponsibilityResolverContract` and be swapped in later without
  touching callers.
