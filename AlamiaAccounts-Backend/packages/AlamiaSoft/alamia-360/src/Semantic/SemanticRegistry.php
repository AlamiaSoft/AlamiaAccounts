<?php

namespace Alamia360\Semantic;

use Alamia360\Contracts\EntityDefinitionContract;

/**
 * Central registry answering: "What does this application mean?"
 * (entities, relationships, business concepts) — as opposed to "what
 * tables exist?".
 */
class SemanticRegistry
{
    /** @var array<string, EntityDefinitionContract> */
    protected array $entities = [];

    public function entity(string $name): EntityDefinition
    {
        $definition = new EntityDefinition($name);
        $this->entities[$name] = $definition;
        return $definition;
    }

    public function hasEntity(string $name): bool
    {
        return isset($this->entities[$name]);
    }

    public function getEntity(string $name): EntityDefinitionContract
    {
        if (!$this->hasEntity($name)) {
            throw new \InvalidArgumentException("Unknown entity [{$name}]. Did you register it via Alamia360::entity()?");
        }

        return $this->entities[$name];
    }

    /** @return array<string, EntityDefinitionContract> */
    public function allEntities(): array
    {
        return $this->entities;
    }
}
