# MCP (Model Context Protocol) Adapter

## MCP as an Interoperability Adapter

The MCP adapter is not a separate implementation of your application's capabilities — it is a protocol-level translation layer that sits in front of the existing [Capability](./capabilities.md) registry. Every tool exposed via MCP is backed by a capability that was already registered on boot. Authorization, audit logging, and handler logic are identical whether a capability is invoked through MCP, a REST controller, or the [Orchestrator](./orchestration.md).

This design guarantees that MCP access is never a privilege escalation vector and never requires duplicating business logic.

---

## `McpAdapter`: `listTools()` and `callTool()`

Resolve `McpAdapter` from the service container:

```php
use Alamia\Alamia360\Mcp\McpAdapter;

$adapter = app(McpAdapter::class);
```

### `listTools(): McpToolDefinition[]`

Returns one `McpToolDefinition` per registered capability, shaped for MCP consumption:

```php
$tools = $adapter->listTools();

foreach ($tools as $tool) {
    echo $tool->name;        // capability name
    echo $tool->description; // capability description
    // $tool->inputSchema is a JSON Schema array derived from capability->input()
}
```

### `callTool(string $name, array $args, ActorContract $actor): mixed`

Invokes a capability by name with the given arguments on behalf of the given actor. Delegates entirely to `CapabilityExecutor::execute()`:

```php
$result = $adapter->callTool('get_todays_appointments', ['date' => '2026-09-24'], $actor);
```

If the actor is not authorized for the capability, a `CapabilityAuthorizationException` is thrown — the same exception thrown for any other unauthorized invocation.

---

## `McpToolDefinition`

| Property | Type | Source |
|---|---|---|
| `name` | `string` | The capability name. |
| `description` | `string` | From `capability->describe()`. |
| `inputSchema` | `array` | JSON Schema object derived from `capability->input()`. |

```php
$tool = $tools[0];
$array = $tool->toArray();
/*
[
    'name'        => 'get_todays_appointments',
    'description' => 'Returns all appointments scheduled for today',
    'inputSchema' => [
        'type'       => 'object',
        'properties' => [
            'date' => ['type' => 'string', 'description' => 'ISO 8601 date; defaults to today'],
        ],
        'required'   => [],
    ],
]
*/
```

---

## Same Capability → Same Authorization → No Special MCP Permissions

The `McpAdapter` has no separate permission configuration. An actor calling a tool via MCP must satisfy the same two checks as any other invocation:

1. The actor's type is in `allowedFor()`.
2. `$actor->isAuthorizedFor($capabilityName)` returns `true`.

There is no MCP-specific allow-list or bypass. Do not add one.

---

## Wiring a stdio Transport

The stdio transport is typical for local agents and CLI integrations. Implement a thin script that reads JSON-RPC from stdin and writes to stdout, delegating tool calls to `McpAdapter`:

```php
// bin/mcp-server.php (pseudocode — adapt to your MCP client library's API)

$adapter = app(McpAdapter::class);
$actor   = Alamia360::actors()->find('system-agent') ?? Actor::system('system-agent', 'system');

while ($line = fgets(STDIN)) {
    $request = json_decode($line, true);

    if ($request['method'] === 'tools/list') {
        $tools = array_map(fn($t) => $t->toArray(), $adapter->listTools());
        echo json_encode(['result' => ['tools' => $tools]]) . "\n";
    }

    if ($request['method'] === 'tools/call') {
        $result = $adapter->callTool(
            $request['params']['name'],
            $request['params']['arguments'] ?? [],
            $actor
        );
        echo json_encode(['result' => $result]) . "\n";
    }
}
```

---

## Wiring an HTTP + SSE Transport

For browser-based agents or remote integrations, expose the adapter through a Laravel route:

```php
// routes/api.php (pseudocode — adapt to your MCP HTTP library's API)

Route::get('/mcp/tools', function () {
    $adapter = app(McpAdapter::class);
    return response()->json([
        'tools' => array_map(fn($t) => $t->toArray(), $adapter->listTools()),
    ]);
})->middleware('auth:sanctum');

Route::post('/mcp/call', function (Request $request) {
    $actor   = Alamia360::actors()->find((string) Auth::id());
    $adapter = app(McpAdapter::class);

    $result = $adapter->callTool(
        $request->input('name'),
        $request->input('arguments', []),
        $actor
    );

    return response()->json(['result' => $result]);
})->middleware('auth:sanctum');
```

For Server-Sent Events (streaming responses), wrap the route handler in a `StreamedResponse` and push tool results as SSE events.

---

## Enabling via Config

```php
// config/alamia360.php
'mcp' => [
    'enabled' => env('ALAMIA_MCP_ENABLED', false),
],
```

```ini
# .env
ALAMIA_MCP_ENABLED=true
```

When `mcp.enabled` is `false`, `McpAdapter::listTools()` returns an empty array and `callTool()` throws an exception. Disable in environments where MCP access is not needed.

---

> [!CAUTION]
> Never invoke a capability handler directly from an MCP transport handler, bypassing the `McpAdapter` and `CapabilityExecutor`. Doing so skips authorization and audit logging. Always call `$adapter->callTool()`, which delegates to `CapabilityExecutor::execute()`.
