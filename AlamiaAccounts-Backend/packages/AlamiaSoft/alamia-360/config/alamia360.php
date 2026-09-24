<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Reasoning
    |--------------------------------------------------------------------------
    | Enable AI-assisted reasoning globally. The architecture remains fully
    | functional without AI — deterministic/rule-based paths only.
    | Bind Alamia360\Contracts\AiProviderContract to enable a real provider.
    */
    'ai_enabled' => env('ALAMIA_AI_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Persistence Driver
    |--------------------------------------------------------------------------
    | 'eloquent'  — use Eloquent models (alamia_situations, alamia_audit_events,
    |               alamia_observations tables). Recommended for all environments.
    | 'memory'    — in-memory only (registries only, no DB writes). Useful for
    |               isolated unit tests or lightweight tooling.
    |
    | Recommended databases:
    |   dev/test  → SQLite (in-memory via Orchestra Testbench, or file)
    |   production → PostgreSQL (full JSON support, mature query planner)
    |   also supported → MySQL 8+, MariaDB 10.5+
    */
    'persistence' => [
        'driver'     => env('ALAMIA_PERSISTENCE_DRIVER', 'eloquent'),
        'connection' => env('ALAMIA_DB_CONNECTION', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Observation Persistence & Detection
    |--------------------------------------------------------------------------
    | persist:           Always write every Observation to the DB (recommended).
    |                    This provides a complete audit trail regardless of whether
    |                    the observation produces a Situation.
    |
    | queue:             When false (default), detectors run synchronously inline
    |                    with observe(). Simpler, fine for low-volume apps.
    |                    When true, a ProcessObservation job is dispatched to
    |                    the queue — detectors run async on a worker, keeping the
    |                    HTTP request fast.
    |
    | queue_connection:  Laravel queue connection to use for ProcessObservation
    |                    jobs. null = use the application's default connection.
    */
    'observation' => [
        'persist'          => env('ALAMIA_OBSERVE_PERSIST', true),
        'queue'            => env('ALAMIA_OBSERVE_QUEUE', false),
        'queue_connection' => env('ALAMIA_QUEUE_CONNECTION', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Adapter
    |--------------------------------------------------------------------------
    | Wire McpAdapter to your chosen MCP server transport when enabled.
    | The adapter exposes the same CapabilityRegistry used internally —
    | there is no MCP-specific implementation of business logic.
    */
    'mcp' => [
        'enabled' => env('ALAMIA_MCP_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Actor-Type Restrictions
    |--------------------------------------------------------------------------
    | Applied to destructive capabilities unless a Capability overrides
    | allowedActorTypes() explicitly.
    */
    'default_destructive_actor_types' => ['human'],

];
