<?php

namespace Alamia360\Contracts;

interface CapabilityContract
{
    public function name(): string;

    public function description(): string;

    /** @return array<string,mixed> */
    public function inputSchema(): array;

    /** @return array<string,mixed> */
    public function outputSchema(): array;

    /** 'read' | 'write' | 'destructive' */
    public function sideEffect(): string;

    /** @return string[] */
    public function allowedActorTypes(): array;

    public function handle(array $input, ActorContract $actor): mixed;
}
