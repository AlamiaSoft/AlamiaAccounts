# Event & Change Intelligence

## Purpose

The Event & Change Intelligence layer watches the application for meaningful changes and converts them into operational meaning. Raw application events — a lab result saved, a payment overdue, a queue stalling — are not inherently actionable. This layer interprets them: an `Observation` captures what happened; a `SituationDetector` decides whether it represents a condition that matters; a `Situation` is the named, prioritized result.

See [Situations](./situations.md) for what happens after a situation is created.

---

## `ObservationSource` Enum

Every observation declares where it came from. Choose the source that best describes the trigger:

| Value | When to use |
|---|---|
| `DomainEvent` | A Laravel event fired from your application's domain layer (e.g. `LabResultCreated`). |
| `StateScan` | A periodic query that looks for entities in an undesirable state (e.g. appointments with no clinician assigned 24 h before the slot). |
| `Scheduled` | A time-based trigger not tied to a specific state query — a heartbeat, a daily summary scan. |
| `ExternalEvent` | A webhook or integration payload from a third-party service. |
| `UserAction` | A deliberate action taken by a human through the UI that carries operational significance beyond its immediate effect. |
| `SystemEvent` | Infrastructure-level signals — queue failures, service health checks, deployment hooks. |

---

## Creating an Observation

```php
use Alamia\Alamia360\Observation\Observation;
use Alamia\Alamia360\Observation\ObservationSource;
use Alamia\Alamia360\Facades\Alamia360;

Alamia360::observer()->observe(new Observation(
    type:       'lab_result.created',
    source:     ObservationSource::DomainEvent,
    entityType: 'lab_result',
    entityId:   $labResult->id,
    payload:    ['abnormal' => true, 'test_code' => 'CBC'],
));
```

| Parameter | Type | Description |
|---|---|---|
| `type` | `string` | A dot-namespaced string identifying the kind of observation. |
| `source` | `ObservationSource` | Enum value describing where the observation came from. |
| `entityType` | `string` | The registered entity name the observation is about. |
| `entityId` | `string\|int` | The ID of the specific entity instance. |
| `payload` | `array` | Arbitrary key-value data relevant to the observation. |

---

## Observer: Adding Detectors and Calling `observe()`

The `Observer` orchestrates observation persistence and detection. Retrieve it from the facade and attach one or more detectors before your application boots into the main request cycle.

```php
use Alamia\Alamia360\Facades\Alamia360;

Alamia360::observer()->addDetector(new AbnormalLabResultDetector());
Alamia360::observer()->addDetector(new OverdueAppointmentDetector());
```

Calling `observe()` performs two steps in sequence:
1. Persists the observation to the database (always).
2. Passes the observation to each registered detector; any that claim the observation type are evaluated.

---

## Observation Persistence: Always Written, Detection Configurable

Observations are **always persisted** to the database regardless of whether any detector matches. This guarantees a complete audit trail of everything the application observed, even observations that did not produce a situation.

Detection behaviour is controlled via `config/alamia360.php`:

```php
'observation' => [
    'persist'          => env('ALAMIA_OBSERVE_PERSIST', true),   // always true in production
    'queue'            => env('ALAMIA_OBSERVE_QUEUE', false),     // run detectors on a queue worker
    'queue_connection' => env('ALAMIA_QUEUE_CONNECTION', null),   // which connection to use
],
```

When `queue` is `false` (default), detectors run synchronously in the same request. When `queue` is `true`, detection is dispatched as a queued job, keeping the request fast.

> [!IMPORTANT]
> Even in queued mode, the observation row is written **synchronously** before the job is dispatched. The audit trail is never deferred.

---

## Writing a SituationDetector

Extend `AbstractSituationDetector` and override two methods:

```php
use Alamia\Alamia360\Observation\AbstractSituationDetector;
use Alamia\Alamia360\Observation\Observation;
use Alamia\Alamia360\Situations\Situation;
use Alamia\Alamia360\Situations\SituationPriority;

class AbnormalLabResultDetector extends AbstractSituationDetector
{
    /**
     * Return the observation types this detector handles.
     */
    protected function handles(): array
    {
        return ['lab_result.created'];
    }

    /**
     * Evaluate the observation and return a Situation, or null if no situation applies.
     */
    protected function evaluate(Observation $observation): ?Situation
    {
        if (! ($observation->payload['abnormal'] ?? false)) {
            return null;
        }

        return new Situation(
            type:        'lab_result_review_required',
            summary:     'Abnormal lab result requires veterinarian review',
            priority:    SituationPriority::High,
            subjectType: 'lab_result',
            subjectId:   $observation->entityId,
            context:     ['result_id' => $observation->entityId],
        );
    }
}
```

- `handles()` acts as a filter. The `Observer` will only call `evaluate()` if the observation's type is in this list.
- `evaluate()` receives the full observation. Return a `Situation` if the condition is met, or `null` to pass.
- The base class handles idempotency: if an open situation with the same `type` + `subjectType` + `subjectId` already exists, the new one is silently deduplicated.

Register in a service provider:

```php
public function boot(): void
{
    Alamia360::observer()->addDetector(new AbnormalLabResultDetector());
}
```

---

## Connecting a Laravel Event Listener to Observer

The most common pattern for `DomainEvent` observations is a dedicated listener:

```php
use App\Events\LabResultCreated;
use Alamia\Alamia360\Observation\Observation;
use Alamia\Alamia360\Observation\ObservationSource;
use Alamia\Alamia360\Facades\Alamia360;

class ObserveLabResultCreated
{
    public function handle(LabResultCreated $event): void
    {
        Alamia360::observer()->observe(new Observation(
            type:       'lab_result.created',
            source:     ObservationSource::DomainEvent,
            entityType: 'lab_result',
            entityId:   $event->labResult->id,
            payload:    [
                'abnormal'  => $event->labResult->is_abnormal,
                'test_code' => $event->labResult->test_code,
            ],
        ));
    }
}
```

Register the listener in `EventServiceProvider` as you would any other listener.

---

## Scheduled Observation Pattern

Use a custom Artisan command to generate observations on a schedule. Register it in your console kernel:

```php
// app/Console/Commands/ScanOverdueAppointments.php

class ScanOverdueAppointments extends Command
{
    protected $signature = 'alamia:scan-overdue-appointments';

    public function handle(): void
    {
        Appointment::overdue()->each(function (Appointment $appointment) {
            Alamia360::observer()->observe(new Observation(
                type:       'appointment.overdue',
                source:     ObservationSource::Scheduled,
                entityType: 'appointment',
                entityId:   $appointment->id,
                payload:    ['scheduled_at' => $appointment->scheduled_at->toIso8601String()],
            ));
        });
    }
}
```

Schedule it in `Kernel::schedule()`:

```php
$schedule->command('alamia:scan-overdue-appointments')->hourly();
```

---

## State-Based Observation Pattern

For situations driven by entity state rather than domain events, query the database for entities in an undesirable state and observe each one:

```php
class ScanUnassignedAppointments extends Command
{
    protected $signature = 'alamia:scan-unassigned';

    public function handle(): void
    {
        // Find appointments starting within 24 h that have no clinician assigned
        $unassigned = Appointment::query()
            ->whereNull('clinician_id')
            ->whereBetween('scheduled_at', [now(), now()->addHours(24)])
            ->get();

        foreach ($unassigned as $appointment) {
            Alamia360::observer()->observe(new Observation(
                type:       'appointment.unassigned',
                source:     ObservationSource::StateScan,
                entityType: 'appointment',
                entityId:   $appointment->id,
                payload:    ['scheduled_at' => $appointment->scheduled_at->toIso8601String()],
            ));
        }
    }
}
```

Because situations are idempotent, running this scan repeatedly for the same appointment does not create duplicate open situations.
