<?php

namespace Alamia360\Tests\Unit\Observation;

use Alamia360\Contracts\SituationDetectorContract;
use Alamia360\Observation\Observation;
use Alamia360\Observation\ObservationSource;
use Alamia360\Observation\Observer;
use Alamia360\Situations\Situation;
use Alamia360\Situations\SituationPriority;
use Alamia360\Situations\SituationRegistry;
use Alamia360\Tests\TestCase;

class ObserverTest extends TestCase
{
    private Observer $observer;
    private SituationRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->observer = $this->app->make(Observer::class);
        $this->registry = $this->app->make(SituationRegistry::class);
    }

    private function makeObservation(string $type = 'test.event'): Observation
    {
        return new Observation($type, ObservationSource::DomainEvent, 'entity', 1, []);
    }

    private function makeDetector(?Situation $returns): SituationDetectorContract
    {
        return new class($returns) implements SituationDetectorContract {
            private int $callCount = 0;
            public function __construct(private readonly ?Situation $returns) {}
            public function detect(Observation $observation): ?Situation { $this->callCount++; return $this->returns; }
            public function callCount(): int { return $this->callCount; }
        };
    }

    public function test_detector_is_called_when_observation_arrives(): void
    {
        $detector = $this->makeDetector(null);
        $this->observer->addDetector($detector);

        $this->observer->observe($this->makeObservation());

        $this->assertEquals(1, $detector->callCount());
    }

    public function test_detector_returning_null_does_not_record_situation(): void
    {
        $this->observer->addDetector($this->makeDetector(null));
        $this->observer->observe($this->makeObservation());

        $this->assertCount(0, $this->registry->all());
    }

    public function test_detector_returning_situation_records_it_in_registry(): void
    {
        $situation = new Situation('test_type', 'A test situation', SituationPriority::Normal);
        $this->observer->addDetector($this->makeDetector($situation));

        $this->observer->observe($this->makeObservation());

        $this->assertCount(1, $this->registry->all());
        $this->assertSame($situation, $this->registry->all()[0]);
    }

    public function test_multiple_detectors_are_all_called(): void
    {
        $d1 = $this->makeDetector(null);
        $d2 = $this->makeDetector(null);
        $d3 = $this->makeDetector(null);

        $this->observer->addDetector($d1);
        $this->observer->addDetector($d2);
        $this->observer->addDetector($d3);

        $this->observer->observe($this->makeObservation());

        $this->assertEquals(1, $d1->callCount());
        $this->assertEquals(1, $d2->callCount());
        $this->assertEquals(1, $d3->callCount());
    }

    public function test_persister_is_called_when_configured(): void
    {
        $persisted = null;
        $this->observer->persistUsing(function (Observation $obs) use (&$persisted) {
            $persisted = $obs;
        });

        $observation = $this->makeObservation();
        $this->observer->observe($observation);

        $this->assertSame($observation, $persisted);
    }

    public function test_persister_is_called_before_detectors(): void
    {
        $order = [];

        $this->observer->persistUsing(function () use (&$order) {
            $order[] = 'persist';
        });

        $this->observer->addDetector(new class($order) implements SituationDetectorContract {
            public function __construct(private array &$order) {}
            public function detect(Observation $obs): ?Situation {
                $this->order[] = 'detect';
                return null;
            }
        });

        $this->observer->observe($this->makeObservation());

        $this->assertEquals(['persist', 'detect'], $order);
    }

    public function test_detectors_method_returns_registered_detectors(): void
    {
        $d1 = $this->makeDetector(null);
        $d2 = $this->makeDetector(null);

        $this->observer->addDetector($d1);
        $this->observer->addDetector($d2);

        $this->assertCount(2, $this->observer->detectors());
    }
}
