<?php

namespace Alamia360\Semantic;

use Alamia360\Contracts\RelationshipDefinitionContract;

class RelationshipDefinition implements RelationshipDefinitionContract
{
    public function __construct(
        protected string $name,
        protected string $relatedEntity,
        protected string $type,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function relatedEntity(): string
    {
        return $this->relatedEntity;
    }

    public function type(): string
    {
        return $this->type;
    }
}
