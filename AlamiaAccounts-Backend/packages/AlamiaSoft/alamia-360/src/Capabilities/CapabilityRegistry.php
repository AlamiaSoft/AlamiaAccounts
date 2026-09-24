<?php

namespace Alamia360\Capabilities;

use Alamia360\Contracts\CapabilityContract;

/**
 * Single source of truth for capabilities. Internal API, REST, MCP and
 * Agent callers all read from this registry — there is exactly one
 * implementation per business capability, exposed through multiple adapters.
 */
class CapabilityRegistry
{
    /** @var array<string, CapabilityContract> */
    protected array $capabilities = [];

    public function define(string $name): Capability
    {
        $capability = new Capability($name);
        $this->capabilities[$name] = $capability;
        return $capability;
    }

    public function register(CapabilityContract $capability): void
    {
        $this->capabilities[$capability->name()] = $capability;
    }

    public function has(string $name): bool
    {
        return isset($this->capabilities[$name]);
    }

    public function get(string $name): CapabilityContract
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException("Unknown capability [{$name}].");
        }

        return $this->capabilities[$name];
    }

    /** @return array<string, CapabilityContract> */
    public function all(): array
    {
        return $this->capabilities;
    }
}
