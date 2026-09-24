<?php

namespace Alamia360\Actions;

use Alamia360\Contracts\ActorContract;
use Alamia360\Situations\Situation;

/**
 * A concrete, executable step taken in response to a Situation — either by
 * a human, an AI recommendation the human approved, or a fully automated
 * system action. Always resolves through the CapabilityExecutor so the
 * same authorization/audit path applies regardless of actor type.
 */
class Action
{
    protected ?ActionOutcome $outcome = null;

    public function __construct(
        public readonly Situation $situation,
        public readonly string $capabilityName,
        public readonly array $input,
        public readonly ActorContract $performedBy,
        public readonly ?ActorContract $authorizedBy = null,
    ) {
    }

    public function complete(ActionOutcome $outcome): static
    {
        $this->outcome = $outcome;
        return $this;
    }

    public function outcome(): ?ActionOutcome
    {
        return $this->outcome;
    }
}
