<?php

namespace Alamia360\Semantic;

use Alamia360\Contracts\EntityDefinitionContract;
use Alamia360\Contracts\RelationshipDefinitionContract;
use Closure;

/**
 * Fluent, portable description of a business entity's meaning.
 * Deliberately NOT an Eloquent model — Alamia 360 describes semantics,
 * the host application owns persistence.
 */
class EntityDefinition implements EntityDefinitionContract
{
    protected string $description = '';

    /** @var array<string, mixed> */
    protected array $attributes = [];

    /** @var array<string, RelationshipDefinitionContract> */
    protected array $relationships = [];

    protected ?Closure $resolver = null;

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

    public function attribute(string $name, array $meta = []): static
    {
        $this->attributes[$name] = $meta;
        return $this;
    }

    public function attributes(): array
    {
        return $this->attributes;
    }

    public function relatesTo(string $relationName, string $relatedEntity, string $type = 'belongsTo'): static
    {
        $this->relationships[$relationName] = new RelationshipDefinition($relationName, $relatedEntity, $type);
        return $this;
    }

    public function relationships(): array
    {
        return $this->relationships;
    }

    /**
     * Define how to resolve the underlying record (e.g. an Eloquent model lookup).
     */
    public function resolveUsing(Closure $resolver): static
    {
        $this->resolver = $resolver;
        return $this;
    }

    public function resolve(mixed $id): mixed
    {
        if (!$this->resolver) {
            throw new \RuntimeException("No resolver defined for entity [{$this->name}].");
        }

        return ($this->resolver)($id);
    }
}
