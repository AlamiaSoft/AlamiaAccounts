<?php

namespace Alamia360\MCP;

use Alamia360\Capabilities\CapabilityExecutor;
use Alamia360\Capabilities\CapabilityRegistry;
use Alamia360\Contracts\ActorContract;

/**
 * MCP is treated strictly as an interoperability adapter: it lists and
 * invokes the SAME capability registry/executor used internally and by
 * REST — there is no parallel MCP-only implementation of business logic.
 *
 * Wire this into whichever MCP server package/transport you adopt
 * (stdio, HTTP+SSE, etc.) — this class exposes the two operations any MCP
 * server needs: tool listing and tool invocation.
 */
class McpAdapter
{
    public function __construct(
        protected CapabilityRegistry $registry,
        protected CapabilityExecutor $executor,
    ) {
    }

    /** @return McpToolDefinition[] */
    public function listTools(): array
    {
        return array_map(
            fn ($capability) => new McpToolDefinition(
                $capability->name(),
                $capability->description(),
                $capability->inputSchema(),
            ),
            array_values($this->registry->all())
        );
    }

    public function callTool(string $name, array $arguments, ActorContract $actor): mixed
    {
        return $this->executor->execute($name, $arguments, $actor);
    }
}
