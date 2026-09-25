<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Copilot & LLM Intent Classifier Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for local Ollama (e.g. qwen3.5:4b) or OpenAI-compatible
    | LLM servers used for semantic intent classification, entity extraction,
    | and accounting assistance.
    |
    */

    'enabled' => (bool) env('AI_ENABLED', true),

    'endpoint' => env('AI_ENDPOINT', 'http://localhost:11434'),

    'model' => env('AI_MODEL', 'qwen3.5:4b'),

    'api_key' => env('AI_API_KEY', null),

    'timeout' => (int) env('AI_TIMEOUT', 5),
];
