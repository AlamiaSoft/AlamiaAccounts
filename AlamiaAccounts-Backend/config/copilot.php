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

    'enabled' => (bool) env('AI_ENABLED', false),

    'endpoint' => env('AI_ENDPOINT', 'http://host.docker.internal:11434'),

    'model' => env('AI_MODEL', 'qwen3.5:4b'),

    'api_key' => env('AI_API_KEY', null),

    'timeout' => (int) env('AI_TIMEOUT', 12),

    /*
    |--------------------------------------------------------------------------
    | Maker-Checker Segregation of Duties Threshold (PKR)
    |--------------------------------------------------------------------------
    | Transactions with amount >= this threshold require secondary approval
    | before posting into the permanent general ledger.
    */
    'maker_checker_threshold' => (float) env('COPILOT_MAKER_CHECKER_THRESHOLD', 100000.0),

    /*
    |--------------------------------------------------------------------------
    | Confidence Threshold for Closed-World Execution
    |--------------------------------------------------------------------------
    */
    'confidence_threshold' => (float) env('COPILOT_CONFIDENCE_THRESHOLD', 0.70),
];

