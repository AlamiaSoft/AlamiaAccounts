# Extension Points

## Custom SituationDetector

Implement `SituationDetectorContract` or extend the provided `AbstractSituationDetector` to add new detection logic without modifying the package.

**Using `AbstractSituationDetector` (recommended):**

```php
use Alamia\Alamia360\Observation\AbstractSituationDetector;
use Alamia\Alamia360\Observation\Observation;
use Alamia\Alamia360\Situations\Situation;
use Alamia\Alamia360\Situations\SituationPriority;

class OverdueInvoiceDetector extends AbstractSituationDetector
{
    protected function handles(): array
    {
        return ['invoice.payment_checked'];
    }

    protected function evaluate(Observation $observation): ?Situation
    {
        if (! ($observation->payload['overdue'] ?? false)) {
            return null;
        }

        return new Situation(
            type:        'invoice.overdue',
            summary:     'Invoice payment is overdue',
            priority:    SituationPriority::Normal,
            subjectType: 'invoice',
            subjectId:   $observation->entityId,
            context:     ['days_overdue' => $observation->payload['days_overdue'] ?? 0],
        );
    }
}
```

**Implementing `SituationDetectorContract` directly (for maximum control):**

```php
use Alamia\Alamia360\Contracts\SituationDetectorContract;
use Alamia\Alamia360\Observation\Observation;
use Alamia\Alamia360\Situations\Situation;

class MyDetector implements SituationDetectorContract
{
    public function canHandle(Observation $observation): bool
    {
        return $observation->type === 'custom.event';
    }

    public function detect(Observation $observation): ?Situation
    {
        // Your detection logic
        return null;
    }
}
```

Register in a service provider:

```php
public function boot(): void
{
    Alamia360::observer()->addDetector(new OverdueInvoiceDetector());
}
```

---

## Custom ResponsibilityResolver

When role-based resolution is insufficient — for example, when workload balancing, availability calendars, or AI-driven assignment are needed — implement `ResponsibilityResolverContract`:

```php
use Alamia\Alamia360\Contracts\ResponsibilityResolverContract;
use Alamia\Alamia360\Situations\Situation;
use Illuminate\Support\Collection;

class WorkloadBalancedResolver implements ResponsibilityResolverContract
{
    public function __construct(private readonly WorkloadService $workload) {}

    public function resolve(Situation $situation): Collection
    {
        $responsibility = Alamia360::responsibilities()->for($situation->type);
        if (! $responsibility) {
            return collect();
        }

        return collect(
            $this->workload->leastBusyActorsForRole($responsibility->role())
        );
    }
}
```

Bind in your application service provider:

```php
use Alamia\Alamia360\Contracts\ResponsibilityResolverContract;

public function register(): void
{
    $this->app->bind(ResponsibilityResolverContract::class, WorkloadBalancedResolver::class);
}
```

The `Orchestrator` resolves `ResponsibilityResolverContract` from the container on every `process()` call — no other changes are needed.

---

## Custom AI Provider

Implement `AiProviderContract` to integrate any AI model:

```php
use Alamia\Alamia360\Contracts\AiProviderContract;

class AnthropicProvider implements AiProviderContract
{
    public function isAvailable(): bool
    {
        return ! empty(config('services.anthropic.api_key'));
    }

    public function reason(string $prompt, array $context): string
    {
        // Call Anthropic API
        return $this->client->messages()->create([
            'model'    => 'claude-3-5-sonnet-20241022',
            'messages' => [['role' => 'user', 'content' => $prompt . "\n\n" . json_encode($context)]],
        ])->content[0]->text ?? '';
    }

    public function classify(string $input, array $labels, array $context): string
    {
        $result = $this->reason("Classify into one of [" . implode(', ', $labels) . "]: $input", $context);
        return in_array(trim($result), $labels, true) ? trim($result) : $labels[0];
    }
}
```

Bind in your service provider:

```php
use Alamia\Alamia360\Contracts\AiProviderContract;

public function register(): void
{
    if (config('alamia360.ai_enabled')) {
        $this->app->bind(AiProviderContract::class, AnthropicProvider::class);
    }
}
```

---

## Eloquent Repositories

The default Eloquent repositories for situations, observations, and audit events can be extended or replaced. Each repository implements a contract:

| Contract | Default implementation | Table |
|---|---|---|
| `SituationRepositoryContract` | `EloquentSituationRepository` | `alamia360_situations` |
| `ObservationRepositoryContract` | `EloquentObservationRepository` | `alamia360_observations` |
| `AuditRepositoryContract` | `EloquentAuditRepository` | `alamia360_audit_events` |

To extend:

```php
use Alamia\Alamia360\Persistence\EloquentSituationRepository;

class TenantScopedSituationRepository extends EloquentSituationRepository
{
    public function open(): \Illuminate\Support\Collection
    {
        return parent::open()->filter(fn($s) => $this->belongsToCurrentTenant($s));
    }
}
```

Bind the extended class:

```php
use Alamia\Alamia360\Contracts\SituationRepositoryContract;

$this->app->bind(SituationRepositoryContract::class, TenantScopedSituationRepository::class);
```

---

## Custom `ActorContract`

If your application's user model already carries the information Alamia 360 needs (ID, type, role, capabilities), you can implement `ActorContract` directly on your user model rather than constructing separate `Actor` instances:

```php
use Alamia\Alamia360\Contracts\ActorContract;
use Alamia\Alamia360\Actors\ActorType;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements ActorContract
{
    public function id(): string        { return (string) $this->getKey(); }
    public function type(): ActorType   { return ActorType::Human; }
    public function role(): string      { return $this->role; }
    public function capabilities(): array { return $this->resolveCapabilities(); }

    public function isAuthorizedFor(string $capability, mixed $subject = null): bool
    {
        return in_array($capability, $this->capabilities())
            && Gate::forUser($this)->allows($capability, $subject);
    }
}
```

Register the user directly:

```php
Alamia360::actors()->register(Auth::user());
```

---

## MCP Transport

The `McpAdapter` is transport-agnostic. Wire any transport by calling `listTools()` and `callTool()`:

```php
$adapter = app(McpAdapter::class);

// Your transport reads a request and calls:
$tools  = $adapter->listTools();                              // for tools/list
$result = $adapter->callTool($name, $arguments, $actor);     // for tools/call
```

No package changes are needed to support a new transport (stdio, HTTP+SSE, WebSocket, gRPC). See [MCP](./mcp.md) for example wiring patterns.

---

## Custom `ContextBuilder`

The default `ContextBuilder` resolves the situation's subject entity, traverses declared relationships, and assembles an `OperationalContext`. Extend or replace it when you need domain-specific context assembly:

```php
use Alamia\Alamia360\Context\ContextBuilder;
use Alamia\Alamia360\Context\OperationalContext;
use Alamia\Alamia360\Situations\Situation;
use Alamia\Alamia360\Contracts\ActorContract;

class EnrichedContextBuilder extends ContextBuilder
{
    public function forSituation(Situation $situation, ?ActorContract $actor): OperationalContext
    {
        $context = parent::forSituation($situation, $actor);

        // Add clinic-specific operational data
        $context->enrich('clinic_stats', $this->statsService->current());

        return $context;
    }
}
```

Bind in your service provider:

```php
use Alamia\Alamia360\Context\ContextBuilder;

$this->app->bind(ContextBuilder::class, EnrichedContextBuilder::class);
```

---

## Future: Copilot / AI Router Layer

The architecture is explicitly designed to accept a tiered model selection layer above the `Orchestrator`. An AI Router would:
1. Inspect the situation's characteristics (priority, type, complexity).
2. Select the cheapest appropriate model (local classifier → small hosted model → large model).
3. Route the reasoning request accordingly.

This can be implemented today as a custom `planUsing()` closure on the `Orchestrator`, or as a dedicated service class injected into a custom orchestrator subclass. No interface changes are required — the existing contracts support this pattern without modification.
