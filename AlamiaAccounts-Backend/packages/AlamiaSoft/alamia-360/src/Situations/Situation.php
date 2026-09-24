<?php

namespace Alamia360\Situations;

use Alamia360\Contracts\ActorContract;
use Alamia360\Observation\Observation;

/**
 * A meaningful operational condition that may require attention or action.
 * This is the central concept of Alamia 360 — the bridge between raw
 * events/state and something a human, AI or system actor can act on.
 */
class Situation
{
    protected SituationStatus $status = SituationStatus::Open;

    /** @var ActorContract[] */
    protected array $responsibleActors = [];

    protected ?string $recommendedAction = null;
    protected ?array $resolution = null;
    protected ?\DateTimeImmutable $dueAt = null;
    protected readonly \DateTimeImmutable $detectedAt;

    public function __construct(
        public readonly string $type,
        public readonly string $summary,
        public readonly SituationPriority $priority = SituationPriority::Normal,
        public readonly ?string $subjectType = null,
        public readonly mixed $subjectId = null,
        public readonly array $context = [],
        public readonly ?Observation $source = null,
        public readonly ?string $id = null,
    ) {
        $this->detectedAt = new \DateTimeImmutable();
    }

    public function status(): SituationStatus
    {
        return $this->status;
    }

    public function transitionTo(SituationStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function assignTo(array $actors): static
    {
        $this->responsibleActors = $actors;
        return $this;
    }

    /** @return ActorContract[] */
    public function responsibleActors(): array
    {
        return $this->responsibleActors;
    }

    public function recommend(string $action): static
    {
        $this->recommendedAction = $action;
        return $this;
    }

    public function recommendedAction(): ?string
    {
        return $this->recommendedAction;
    }

    public function due(\DateTimeImmutable $dueAt): static
    {
        $this->dueAt = $dueAt;
        return $this;
    }

    public function dueAt(): ?\DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function detectedAt(): \DateTimeImmutable
    {
        return $this->detectedAt;
    }

    public function resolve(array $resolution): static
    {
        $this->resolution = $resolution;
        $this->status = SituationStatus::Resolved;
        return $this;
    }

    public function resolution(): ?array
    {
        return $this->resolution;
    }
}
