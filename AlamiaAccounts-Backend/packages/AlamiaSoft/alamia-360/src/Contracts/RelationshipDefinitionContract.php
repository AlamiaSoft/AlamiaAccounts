<?php

namespace Alamia360\Contracts;

interface RelationshipDefinitionContract
{
    public function name(): string;

    public function relatedEntity(): string;

    public function type(): string;
}
