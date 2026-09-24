# AI Integration

## Alamia 360's Relationship with AI: Consumer, Not Dependency

Alamia 360 is not AI middleware. It is an operational intelligence architecture that *can* consume AI when reasoning is needed, but is fully functional without it. The default installation ships with `NullAiProvider`, which satisfies the `AiProviderContract` interface and always returns safe, empty responses. Every test, every lifecycle phase, and every capability invocation works correctly with `NullAiProvider` active.

AI becomes useful when the system encounters a situation where no deterministic rule produces a good answer. The architecture is designed for this: the escalation ladder makes the decision explicit.

---

## The Escalation Ladder

When processing a situation, choose the simplest approach that produces a correct answer:

| Level | Approach | When to use |
|---|---|---|
| 1 | **Deterministic** | `situation->recommend()` + static plan. Answer is always the same. |
| 2 | **Semantic** | Rule over entity attributes (e.g. `if $result->is_abnormal → 'review_lab_result'`). |
| 3 | **Rules** | Multi-condition logic in a `planUsing()` closure. |
| 4 | **Small model** | Classification model for labelling or routing (fast, cheap, offline-capable). |
| 5 | **Large model** | General reasoning for complex, multi-entity situations. |
| 6 | **Human** | Situation is ambiguous or high-risk — route to a human actor. |

Work upward through the ladder only as required. Reaching level 5 for every situation is an anti-pattern.

---

## `AiProviderContract` Interface

```php
namespace Alamia\Alamia360\Contracts;

interface AiProviderContract
{
    /**
     * Return true if the provider is configured and reachable.
     */
    public function isAvailable(): bool;

    /**
     * Perform free-text reasoning about a prompt with supporting context.
     *
     * @param  string  $prompt   The reasoning instruction.
     * @param  array   $context  Structured context (from OperationalContext::toArray()).
     * @return string            The model's response text.
     */
    public function reason(string $prompt, array $context): string;

    /**
     * Classify an input string into one of the provided labels.
     *
     * @param  string  $input   The text to classify.
     * @param  array   $labels  Candidate class labels.
     * @param  array   $context Supporting context.
     * @return string           The selected label.
     */
    public function classify(string $input, array $labels, array $context): string;
}
```

---

## `NullAiProvider`: The Safe Default

`NullAiProvider` is bound to `AiProviderContract` unless you replace it. Its behaviour:

- `isAvailable()` → `false`
- `reason()` → `''`
- `classify()` → `$labels[0]` (first label, deterministic fallback)

No exceptions are thrown. Code that checks `$provider->isAvailable()` before calling `reason()` will skip AI gracefully. The package test suite always runs with `NullAiProvider` — no AI calls are made during testing.

---

## Implementing a Real Provider

Implement `AiProviderContract` against your chosen model API. Example skeleton for OpenAI:

```php
use Alamia\Alamia360\Contracts\AiProviderContract;
use OpenAI\Client;

class OpenAiProvider implements AiProviderContract
{
    public function __construct(private readonly Client $client) {}

    public function isAvailable(): bool
    {
        return config('services.openai.api_key') !== null;
    }

    public function reason(string $prompt, array $context): string
    {
        $response = $this->client->chat()->create([
            'model'    => 'gpt-4o',
            'messages' => [
                ['role' => 'system',  'content' => 'You are an operational intelligence assistant.'],
                ['role' => 'user',    'content' => $prompt],
                ['role' => 'user',    'content' => 'Context: ' . json_encode($context)],
            ],
        ]);

        return $response->choices[0]->message->content ?? '';
    }

    public function classify(string $input, array $labels, array $context): string
    {
        $labelList = implode(', ', $labels);
        $text = $this->reason(
            "Classify the following into one of [{$labelList}]: {$input}",
            $context
        );

        return in_array(trim($text), $labels, true) ? trim($text) : $labels[0];
    }
}
```

---

## Binding the Provider in Your Service Provider

```php
use Alamia\Alamia360\Contracts\AiProviderContract;

public function register(): void
{
    if (config('alamia360.ai_enabled')) {
        $this->app->bind(AiProviderContract::class, OpenAiProvider::class);
    }
}
```

When `alamia360.ai_enabled` is `false` (the default), `NullAiProvider` remains active and no real API calls are made.

---

## `AiHarness`

`AiHarness` is the application-facing wrapper over `AiProviderContract`. It accepts an `OperationalContext` directly, so you do not need to call `toArray()` manually:

```php
use Alamia\Alamia360\Ai\AiHarness;
use Alamia\Alamia360\Facades\Alamia360;

$harness = app(AiHarness::class);

$context = Alamia360::context()->forSituation($situation, $actor);

// Free-text reasoning
$response = $harness->reasonAbout(
    'Which action should be taken for this situation, and why?',
    $context
);

// Classification
$priority = $harness->classify(
    $situation->summary,
    ['low', 'normal', 'high', 'critical'],
    $context
);
```

`AiHarness` also guards against calling a provider when `isAvailable()` is `false` — it returns empty strings and first-label defaults in that case, matching `NullAiProvider` behaviour.

---

## `OperationalContext`: Why It Matters

AI models require context to reason correctly, but sending the entire database is expensive, unsafe, and ineffective. `OperationalContext` provides a **selective**, **structured** snapshot:

- The current situation (type, summary, priority, subject).
- The resolved subject entity and its declared attributes.
- Related entities at configured depth.
- The actor's capabilities and role.

What it does **not** contain:
- Raw table data for undeclared attributes.
- Other tenants' data.
- Credentials, secrets, or full model instances.

Pass `$context->toArray()` into your AI prompts for predictable, token-efficient context.

---

## Future: AI Router (Tiered Model Selection)

The architecture is explicitly designed to accommodate an AI Router above the `Orchestrator`. An AI Router would inspect the situation's characteristics and route to the cheapest appropriate model — a local classifier for simple labelling, a small hosted model for moderate reasoning, a large model only for high-complexity situations. This can be implemented as a custom `planUsing()` closure or as a dedicated service sitting between the `Orchestrator` and the `AiHarness`.

No interface changes are required to add this layer.

---

## Security: AI Never Bypasses Authorization

An AI-generated plan (from a `planUsing()` closure) is subject to the same authorization checks as any deterministic plan. The AI cannot name a capability that the actor is not authorized for — `CapabilityExecutor` will throw `CapabilityAuthorizationException` before the handler runs. See [Security](./security.md) for the full authorization model.
