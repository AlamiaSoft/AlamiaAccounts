<?php

namespace Alamia360\Tests\Unit\Actions;

use Alamia360\Actions\Action;
use Alamia360\Actions\ActionOutcome;
use Alamia360\Actors\Actor;
use Alamia360\Situations\Situation;
use Alamia360\Situations\SituationPriority;
use Alamia360\Tests\TestCase;

class ActionOutcomeTest extends TestCase
{
    private function makeSituation(): Situation
    {
        return new Situation('test_type', 'Test', SituationPriority::Normal);
    }

    public function test_successful_outcome_has_correct_shape(): void
    {
        $outcome = new ActionOutcome(
            success: true,
            result: ['data' => 'value'],
            error: null,
            completedAt: new \DateTimeImmutable(),
        );

        $this->assertTrue($outcome->success);
        $this->assertEquals(['data' => 'value'], $outcome->result);
        $this->assertNull($outcome->error);
        $this->assertNotNull($outcome->completedAt);
    }

    public function test_failed_outcome_has_correct_shape(): void
    {
        $outcome = new ActionOutcome(
            success: false,
            result: null,
            error: 'Something went wrong',
            completedAt: new \DateTimeImmutable(),
        );

        $this->assertFalse($outcome->success);
        $this->assertNull($outcome->result);
        $this->assertEquals('Something went wrong', $outcome->error);
    }

    public function test_action_references_its_situation(): void
    {
        $situation = $this->makeSituation();
        $actor = Actor::human('user_1');
        $action = new Action($situation, 'review_item', [], $actor);

        $this->assertSame($situation, $action->situation);
        $this->assertEquals('review_item', $action->capabilityName);
        $this->assertSame($actor, $action->performedBy);
    }

    public function test_action_outcome_is_null_before_completion(): void
    {
        $action = new Action($this->makeSituation(), 'cap', [], Actor::human('u1'));
        $this->assertNull($action->outcome());
    }

    public function test_action_complete_stores_outcome(): void
    {
        $action = new Action($this->makeSituation(), 'cap', [], Actor::human('u1'));
        $outcome = new ActionOutcome(true, 'done', null, new \DateTimeImmutable());

        $action->complete($outcome);

        $this->assertSame($outcome, $action->outcome());
        $this->assertTrue($action->outcome()->success);
    }

    public function test_action_with_authorized_by(): void
    {
        $performer = Actor::human('ai_agent');
        $approver  = Actor::human('manager_1');
        $action = new Action($this->makeSituation(), 'approve_invoice', [], $performer, $approver);

        $this->assertSame($approver, $action->authorizedBy);
    }
}
