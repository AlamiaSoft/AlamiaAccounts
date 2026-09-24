<?php

namespace Alamia360\Contracts;

use Alamia360\Actors\ActorType;

interface ActorContract
{
    public function id(): string;

    public function type(): ActorType;

    public function role(): ?string;

    /** @return string[] */
    public function capabilities(): array;

    public function isAuthorizedFor(string $capabilityName, mixed $subject = null): bool;
}
