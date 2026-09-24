<?php

namespace Alamia360\Actors;

use Alamia360\Contracts\ActorContract;

class ActorRegistry
{
    /** @var array<string, ActorContract> */
    protected array $actors = [];

    public function register(ActorContract $actor): void
    {
        $this->actors[$actor->id()] = $actor;
    }

    public function find(string $id): ?ActorContract
    {
        return $this->actors[$id] ?? null;
    }

    /** @return ActorContract[] */
    public function byType(ActorType $type): array
    {
        return array_values(array_filter(
            $this->actors,
            fn (ActorContract $a) => $a->type() === $type
        ));
    }

    /** @return ActorContract[] */
    public function byRole(string $role): array
    {
        return array_values(array_filter(
            $this->actors,
            fn (ActorContract $a) => $a->role() === $role
        ));
    }
}
