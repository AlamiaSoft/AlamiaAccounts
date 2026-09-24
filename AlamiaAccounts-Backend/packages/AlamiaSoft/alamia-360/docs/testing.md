# Testing

## Running the Package Test Suite

```bash
composer install
vendor/bin/phpunit --testdox
```

The `--testdox` flag formats output as a human-readable checklist of test descriptions. No database server is required — the test suite uses SQLite in-memory via Orchestra Testbench.

No `.env` configuration is needed. All test runs use `NullAiProvider` by default — zero real AI calls are made.

---

## Test Structure

The test suite is organized into two categories:

### Unit Tests

Each major component has a dedicated unit test file:

| Test file | What it covers |
|---|---|
| `SemanticTest` | `EntityDefinition` fluent builder, attribute and relationship declarations, `SemanticRegistry` retrieval. |
| `CapabilitiesTest` | Capability registration, `allowedFor`, `withSideEffect`, `handleUsing`, `CapabilityRegistry` methods. |
| `ObservationTest` | `Observation` constructor, `ObservationSource` enum values, `Observer::observe()` persistence. |
| `SituationsTest` | `Situation` properties, `SituationPriority`, `SituationStatus` transitions, idempotency / deduplication. |
| `ResponsibilitiesTest` | Responsibility DSL, `ResponsibilityRegistry::for()`, escalation data, default resolver resolution order. |
| `OrchestrationTest` | `Orchestrator::process()`, default planner, custom `planUsing()`, verifier hook, `Action` / `ActionOutcome`. |
| `ActionsTest` | `Action` and `ActionOutcome` domain objects, field accessors, succeeded/failed states. |
| `ActorsTest` | `Actor` factory methods, `withCapabilities()`, `authorizeUsing()`, `isAuthorizedFor()`, `ActorRegistry`. |

### Integration Tests

| Test file | What it covers |
|---|---|
| `LifecycleTest` | Full end-to-end deterministic lifecycle: observation → detection → situation → orchestration → capability execution → audit record. |

---

## `LifecycleTest` as the Acceptance Gate

`LifecycleTest` is the canonical acceptance test for the package. It:
- Registers an entity, a capability, a responsibility, a situation detector, and a human actor.
- Fires an observation.
- Asserts that a situation is created with the correct type, priority, and subject.
- Calls `Orchestrator::process()` with the situation and actor.
- Asserts that the capability handler ran, returned the expected value, and that an audit record was written.
- Makes **zero AI calls** — `NullAiProvider` is active throughout.

If `LifecycleTest` passes, the full deterministic path is functional.

---

## Testing in Your Own Application: Orchestra Testbench

Extend `Orchestra\Testbench\TestCase` in your feature tests to get a full Laravel application with Alamia 360 booted:

```php
use Orchestra\Testbench\TestCase;
use Alamia\Alamia360\Alamia360ServiceProvider;

abstract class Alamia360FeatureTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [Alamia360ServiceProvider::class];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }
}
```

---

## Testing a Custom SituationDetector

```php
use Tests\Alamia360FeatureTest;
use Alamia\Alamia360\Facades\Alamia360;
use Alamia\Alamia360\Observation\Observation;
use Alamia\Alamia360\Observation\ObservationSource;
use App\Alamia\Detectors\AbnormalLabResultDetector;

class AbnormalLabResultDetectorTest extends Alamia360FeatureTest
{
    public function test_creates_situation_for_abnormal_result(): void
    {
        Alamia360::observer()->addDetector(new AbnormalLabResultDetector());

        Alamia360::observer()->observe(new Observation(
            type:       'lab_result.created',
            source:     ObservationSource::DomainEvent,
            entityType: 'lab_result',
            entityId:   99,
            payload:    ['abnormal' => true],
        ));

        $situations = Alamia360::situations()->ofType('lab_result_review_required');
        $this->assertCount(1, $situations);
        $this->assertEquals(99, $situations->first()->subjectId);
    }

    public function test_no_situation_for_normal_result(): void
    {
        Alamia360::observer()->addDetector(new AbnormalLabResultDetector());

        Alamia360::observer()->observe(new Observation(
            type:       'lab_result.created',
            source:     ObservationSource::DomainEvent,
            entityType: 'lab_result',
            entityId:   100,
            payload:    ['abnormal' => false],
        ));

        $situations = Alamia360::situations()->ofType('lab_result_review_required');
        $this->assertCount(0, $situations);
    }
}
```

---

## Testing a Custom Capability

```php
use Tests\Alamia360FeatureTest;
use Alamia\Alamia360\Facades\Alamia360;
use Alamia\Alamia360\Actors\Actor;

class ReviewLabResultCapabilityTest extends Alamia360FeatureTest
{
    public function test_capability_returns_expected_output(): void
    {
        Alamia360::capability('review_lab_result')
            ->describe('Review a lab result')
            ->input(['result_id' => ['type' => 'integer', 'required' => true]])
            ->output(['reviewed' => ['type' => 'boolean']])
            ->withSideEffect('write')
            ->allowedFor(['human'])
            ->handleUsing(fn(array $input, $actor) => ['reviewed' => true, 'result_id' => $input['result_id']]);

        $actor = Actor::human('user_1', 'veterinarian')
            ->withCapabilities(['review_lab_result']);

        Alamia360::actors()->register($actor);

        $outcome = Alamia360::capabilities()->execute('review_lab_result', ['result_id' => 5], $actor);

        $this->assertTrue($outcome->succeeded());
        $this->assertEquals(['reviewed' => true, 'result_id' => 5], $outcome->result());
    }
}
```

---

## Testing Responsibility Resolution

```php
use Tests\Alamia360FeatureTest;
use Alamia\Alamia360\Facades\Alamia360;
use Alamia\Alamia360\Actors\Actor;
use Alamia\Alamia360\Responsibilities\DefaultResponsibilityResolver;
use Alamia\Alamia360\Situations\Situation;
use Alamia\Alamia360\Situations\SituationPriority;

class ResponsibilityResolutionTest extends Alamia360FeatureTest
{
    public function test_resolves_actor_by_role(): void
    {
        Alamia360::responsibility('lab_result_review_required')
            ->role('veterinarian')
            ->dueWithin(120);

        $vet = Actor::human('user_10', 'veterinarian')->withCapabilities(['review_lab_result']);
        Alamia360::actors()->register($vet);

        $situation = new Situation(
            type:        'lab_result_review_required',
            summary:     'Abnormal result',
            priority:    SituationPriority::High,
            subjectType: 'lab_result',
            subjectId:   1,
            context:     [],
        );

        $resolver = app(DefaultResponsibilityResolver::class);
        $actors   = $resolver->resolve($situation);

        $this->assertCount(1, $actors);
        $this->assertEquals('user_10', $actors->first()->id());
    }
}
```

---

## Using `NullAiProvider` in Tests

`NullAiProvider` is always the active AI provider unless you explicitly bind a real one. You never need to mock or disable AI calls in tests — they simply don't happen.

If you want to assert that your code behaves correctly when AI is available, bind a fake provider in the test:

```php
$this->app->bind(AiProviderContract::class, function () {
    return new class implements AiProviderContract {
        public function isAvailable(): bool { return true; }
        public function reason(string $prompt, array $context): string { return 'review_lab_result'; }
        public function classify(string $input, array $labels, array $context): string { return $labels[0]; }
    };
});
```

This pattern lets you test AI-dependent branches without real API calls or credentials.
