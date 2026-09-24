<?php

namespace Alamia360\Actions;

class ActionOutcome
{
    public function __construct(
        public readonly bool $success,
        public readonly mixed $result = null,
        public readonly ?string $error = null,
        public readonly ?\DateTimeImmutable $completedAt = null,
    ) {
    }
}
