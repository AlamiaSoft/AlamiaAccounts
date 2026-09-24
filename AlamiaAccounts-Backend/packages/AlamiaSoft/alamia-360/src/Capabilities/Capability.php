<?php

namespace Alamia360\Capabilities;

use Alamia360\Contracts\ActorContract;
use Alamia360\Contracts\CapabilityContract;
use Closure;

/**
 * A meaningful business capability (not a raw endpoint). The handler should
 * delegate to an application service — Capability is a thin, auditable
 * wrapper, not where business logic lives.
 */
class Capability implements CapabilityContract
{
    protected string $description = '';
    protected array $inputSchema = [];
    protected array $outputSchema = [];
    protected string $sideEffect = 'read';
    protected array $allowedActorTypes = ['human', 'ai', 'system'];
    protected ?Closure $handler = null;

    public function __construct(protected string $name)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function describe(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function input(array $schema): static
    {
        $this->inputSchema = $schema;
        return $this;
    }

    public function inputSchema(): array
    {
        return $this->inputSchema;
    }

    public function output(array $schema): static
    {
        $this->outputSchema = $schema;
        return $this;
    }

    public function outputSchema(): array
    {
        return $this->outputSchema;
    }

    public function withSideEffect(string $sideEffect): static
    {
        $this->sideEffect = $sideEffect;
        return $this;
    }

    public function sideEffect(): string
    {
        return $this->sideEffect;
    }

    public function allowedFor(array $actorTypes): static
    {
        $this->allowedActorTypes = $actorTypes;
        return $this;
    }

    public function allowedActorTypes(): array
    {
        return $this->allowedActorTypes;
    }

    public function handleUsing(Closure $handler): static
    {
        $this->handler = $handler;
        return $this;
    }

    public function handle(array $input, ActorContract $actor): mixed
    {
        if (!$this->handler) {
            throw new \RuntimeException("Capability [{$this->name}] has no handler.");
        }

        return ($this->handler)($input, $actor);
    }
}
