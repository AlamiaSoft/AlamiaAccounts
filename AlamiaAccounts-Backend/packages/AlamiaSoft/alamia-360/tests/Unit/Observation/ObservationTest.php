<?php

namespace Alamia360\Tests\Unit\Observation;

use Alamia360\Observation\Observation;
use Alamia360\Observation\ObservationSource;
use Alamia360\Tests\TestCase;

class ObservationTest extends TestCase
{
    public function test_observation_stores_all_constructor_values(): void
    {
        $obs = new Observation(
            type: 'lab_result.created',
            source: ObservationSource::DomainEvent,
            entityType: 'lab_result',
            entityId: 99,
            payload: ['abnormal' => true],
        );

        $this->assertEquals('lab_result.created', $obs->type);
        $this->assertEquals(ObservationSource::DomainEvent, $obs->source);
        $this->assertEquals('lab_result', $obs->entityType);
        $this->assertEquals(99, $obs->entityId);
        $this->assertEquals(['abnormal' => true], $obs->payload);
    }

    public function test_observation_defaults_observed_at_to_now(): void
    {
        $before = new \DateTimeImmutable();
        $obs = new Observation('test', ObservationSource::SystemEvent);
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before->getTimestamp(), $obs->observedAt->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $obs->observedAt->getTimestamp());
    }

    public function test_observation_accepts_explicit_observed_at(): void
    {
        $at = new \DateTimeImmutable('2024-01-15 10:00:00');
        $obs = new Observation('test', ObservationSource::Scheduled, observedAt: $at);

        $this->assertEquals($at, $obs->observedAt);
    }

    public function test_observation_source_enum_values_are_correct(): void
    {
        $this->assertEquals('domain_event', ObservationSource::DomainEvent->value);
        $this->assertEquals('state_scan', ObservationSource::StateScan->value);
        $this->assertEquals('scheduled', ObservationSource::Scheduled->value);
        $this->assertEquals('external_event', ObservationSource::ExternalEvent->value);
        $this->assertEquals('user_action', ObservationSource::UserAction->value);
        $this->assertEquals('system_event', ObservationSource::SystemEvent->value);
    }

    public function test_observation_null_defaults(): void
    {
        $obs = new Observation('minimal', ObservationSource::StateScan);

        $this->assertNull($obs->entityType);
        $this->assertNull($obs->entityId);
        $this->assertEmpty($obs->payload);
    }
}
