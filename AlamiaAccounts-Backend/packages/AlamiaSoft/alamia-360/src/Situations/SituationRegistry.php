<?php

namespace Alamia360\Situations;

/**
 * In-memory + pluggable-persistence store of situations.
 *
 * The in-memory store is the authoritative list during a request/process
 * lifetime. The optional persister closure writes to Eloquent (or any
 * store) as a side-effect — wired by the ServiceProvider.
 *
 * Idempotency: if an open Situation of the same type + subject already
 * exists in the in-memory registry, the new one is silently dropped.
 * This prevents cascading duplicate situations from repeated observations.
 */
class SituationRegistry
{
    /** @var Situation[] */
    protected array $situations = [];

    public function __construct(protected ?\Closure $persister = null)
    {
    }

    public function persistUsing(\Closure $persister): static
    {
        $this->persister = $persister;
        return $this;
    }

    public function record(Situation $situation): void
    {
        // Idempotency: do not record a duplicate open situation for the same
        // type + subjectType + subjectId combination.
        foreach ($this->situations as $existing) {
            if (
                $existing->type === $situation->type
                && $existing->subjectType === $situation->subjectType
                && (string) $existing->subjectId === (string) $situation->subjectId
                && $existing->status() === SituationStatus::Open
            ) {
                return;
            }
        }

        $this->situations[] = $situation;

        if ($this->persister) {
            ($this->persister)($situation);
        }
    }

    /** @return Situation[] */
    public function open(): array
    {
        return array_values(array_filter(
            $this->situations,
            fn (Situation $s) => $s->status() === SituationStatus::Open
        ));
    }

    /** @return Situation[] */
    public function ofType(string $type): array
    {
        return array_values(array_filter(
            $this->situations,
            fn (Situation $s) => $s->type === $type
        ));
    }

    /** @return Situation[] */
    public function all(): array
    {
        return $this->situations;
    }

    public function count(): int
    {
        return count($this->situations);
    }
}
