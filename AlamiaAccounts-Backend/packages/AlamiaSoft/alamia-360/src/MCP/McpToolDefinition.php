<?php

namespace Alamia360\MCP;

/**
 * MCP-shaped view of a Capability. Purely a translation object — no
 * business logic here, and nothing MCP-specific leaks back into
 * Alamia360\Capabilities.
 */
class McpToolDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
    ) {
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => $this->inputSchema,
        ];
    }
}
