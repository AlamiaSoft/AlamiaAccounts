<?php

namespace Alamia360\Tests\Unit\Situations;

use Alamia360\Situations\Situation;
use Alamia360\Situations\SituationPriority;
use Alamia360\Situations\SituationRegistry;
use Alamia360\Situations\SituationStatus;
use Alamia360\Tests\TestCase;

class SituationRegistryTest extends TestCase
{
    private SituationRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = $this->app->make(SituationRegistry::class);
    }

    private function makeSituation(
        string $type = 'test_type',
        ?string $subjectType = 'entity',
        mixed $subjectId = 1,
    ): Situation {
        return new Situation(
            type: $type,
            summary: 'Test',
            priority: SituationPriority::Normal,
            subjectType: $subjectType,
            subjectId: $subjectId,
        );
    }

    public function test_record_adds_situation(): void
    {
        $situation = $this->makeSituation();
        $this->registry->record($situation);

        $this->assertCount(1, $this->registry->all());
        $this->assertSame($situation, $this->registry->all()[0]);
    }

    public function test_open_returns_only_open_situations(): void
    {
        $open = $this->makeSituation('open_type');
        $resolved = $this->makeSituation('resolved_type', 'entity', 2);
        $resolved->resolve(['done' => true]);

        $this->registry->record($open);
        $this->registry->record($resolved);

        $openSituations = $this->registry->open();
        $this->assertCount(1, $openSituations);
        $this->assertSame($open, $openSituations[0]);
    }

    public function test_of_type_filters_by_type(): void
    {
        $this->registry->record($this->makeSituation('type_a'));
        $this->registry->record($this->makeSituation('type_b', 'entity', 2));
        $this->registry->record($this->makeSituation('type_a', 'entity', 3));

        $typeA = $this->registry->ofType('type_a');
        $this->assertCount(2, $typeA);
        foreach ($typeA as $s) {
            $this->assertEquals('type_a', $s->type);
        }
    }

    public function test_idempotency_prevents_duplicate_open_situation(): void
    {
        $s1 = $this->makeSituation('review_required', 'lab_result', 99);
        $s2 = $this->makeSituation('review_required', 'lab_result', 99); // same type + subject

        $this->registry->record($s1);
        $this->registry->record($s2); // should be silently dropped

        $this->assertCount(1, $this->registry->all());
    }

    public function test_idempotency_allows_different_subject_ids(): void
    {
        $s1 = $this->makeSituation('review_required', 'lab_result', 1);
        $s2 = $this->makeSituation('review_required', 'lab_result', 2); // different subject

        $this->registry->record($s1);
        $this->registry->record($s2);

        $this->assertCount(2, $this->registry->all());
    }

    public function test_idempotency_allows_same_type_after_resolution(): void
    {
        $s1 = $this->makeSituation('review_required', 'lab_result', 99);
        $this->registry->record($s1);
        $s1->resolve(['done' => true]); // now status = Resolved

        // Same type+subject but previous one is now resolved — new one allowed
        $s2 = $this->makeSituation('review_required', 'lab_result', 99);
        $this->registry->record($s2);

        $this->assertCount(2, $this->registry->all());
    }

    public function test_persister_is_called_on_record(): void
    {
        $persisted = [];
        $this->registry->persistUsing(function (Situation $s) use (&$persisted) {
            $persisted[] = $s;
        });

        $situation = $this->makeSituation();
        $this->registry->record($situation);

        $this->assertCount(1, $persisted);
        $this->assertSame($situation, $persisted[0]);
    }

    public function test_persister_not_called_for_deduplicated_situation(): void
    {
        $persistCount = 0;
        $this->registry->persistUsing(function () use (&$persistCount) {
            $persistCount++;
        });

        $this->registry->record($this->makeSituation('dupe_type', 'entity', 5));
        $this->registry->record($this->makeSituation('dupe_type', 'entity', 5)); // deduped

        $this->assertEquals(1, $persistCount);
    }

    public function test_count_returns_correct_count(): void
    {
        $this->registry->record($this->makeSituation('a', 'e', 1));
        $this->registry->record($this->makeSituation('b', 'e', 2));
        $this->assertEquals(2, $this->registry->count());
    }
}
