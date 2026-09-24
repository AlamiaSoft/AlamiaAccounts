<?php

namespace Alamia360\Responsibilities;

/**
 * Explicit statement of "who should act" for a given situation type.
 * Deliberately declarative/data-driven so responsibility resolution does
 * NOT require an LLM — an AI resolver can be layered on top later.
 */
class Responsibility
{
    protected ?string $role = null;
    protected ?string $userId = null;
    protected ?string $department = null;
    protected ?string $escalateTo = null;
    protected ?int $escalateAfterMinutes = null;
    protected ?int $dueWithinMinutes = null;

    public function __construct(protected string $situationType)
    {
    }

    public function situationType(): string
    {
        return $this->situationType;
    }

    public function role(string $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function user(string $userId): static
    {
        $this->userId = $userId;
        return $this;
    }

    public function department(string $department): static
    {
        $this->department = $department;
        return $this;
    }

    public function escalatesTo(string $roleOrUserId, int $afterMinutes): static
    {
        $this->escalateTo = $roleOrUserId;
        $this->escalateAfterMinutes = $afterMinutes;
        return $this;
    }

    public function dueWithin(int $minutes): static
    {
        $this->dueWithinMinutes = $minutes;
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function getEscalation(): ?array
    {
        return $this->escalateTo
            ? ['to' => $this->escalateTo, 'after_minutes' => $this->escalateAfterMinutes]
            : null;
    }

    public function getDueWithinMinutes(): ?int
    {
        return $this->dueWithinMinutes;
    }
}
