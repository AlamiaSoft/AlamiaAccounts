<?php

namespace Alamia360\Contracts;

interface EntityDefinitionContract
{
    public function name(): string;

    public function description(): string;

    /** @return array<string, mixed> attribute name => meta (type, label, description) */
    public function attributes(): array;

    /** @return array<string, RelationshipDefinitionContract> */
    public function relationships(): array;

    public function resolve(mixed $id): mixed;
}
