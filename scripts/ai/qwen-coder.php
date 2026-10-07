<?php
/**
 * Reusable PHP Helper to generate code via local Ollama qwen2.5-coder:7b.
 *
 * Usage:
 *   require_once __DIR__ . '/qwen-coder.php';
 *   $code = QwenCoder::generate('Write a Laravel controller for vouchers');
 */

class QwenCoder
{
    public static function generate(
        string $prompt,
        string $system = 'You are an elite expert software engineer. Return only clean, production-ready code or structured output.',
        string $model = 'qwen2.5-coder:7b',
        bool $json = false,
        float $temperature = 0.2
    ): string {
        $host = getenv('OLLAMA_HOST') ?: 'http://127.0.0.1:11434';
        if ($host === '0.0.0.0' || !str_starts_with($host, 'http')) {
            $host = 'http://127.0.0.1:11434';
        }

        $url = rtrim($host, '/') . '/api/generate';
        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'system' => $system,
            'stream' => false,
            'options' => [
                'temperature' => $temperature,
            ]
        ];

        if ($json) {
            $payload['format'] = 'json';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 60,
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            throw new \RuntimeException("Ollama connection failed: {$err}");
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("Ollama returned HTTP {$httpCode}: {$response}");
        }

        $data = json_decode($response, true);
        return trim($data['response'] ?? '');
    }
}

// CLI usage
if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    $prompt = $argv[1] ?? null;
    if (!$prompt) {
        fwrite(STDERR, "Usage: php qwen-coder.php \"<prompt>\"\n");
        exit(1);
    }
    try {
        echo QwenCoder::generate($prompt) . "\n";
    } catch (\Throwable $e) {
        fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
        exit(1);
    }
}
