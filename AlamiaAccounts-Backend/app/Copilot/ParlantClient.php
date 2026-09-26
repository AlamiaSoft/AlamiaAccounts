<?php

namespace App\Copilot;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ParlantClient
{
    protected string $baseUrl;
    protected int $timeout;
    protected bool $enabled;

    public function __construct()
    {
        $this->baseUrl = config('services.parlant.url', env('PARLANT_URL', 'http://copilot-parlant:8800'));
        $this->timeout = (int) env('PARLANT_TIMEOUT', 5);
        $this->enabled = (bool) env('PARLANT_ENABLED', false);
    }

    /**
     * Check if Parlant dialogue sidecar service is available.
     */
    public function isAvailable(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $resp = Http::timeout(2)->get("{$this->baseUrl}/health");
            return $resp->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Send a conversational message to a Parlant session.
     */
    public function sendMessage(string $sessionId, string $message, string $companyCode, array $context = []): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        try {
            $resp = Http::timeout($this->timeout)->post("{$this->baseUrl}/sessions/{$sessionId}/messages", [
                'session_id' => $sessionId,
                'message' => $message,
                'company_code' => $companyCode,
                'context' => $context,
            ]);

            if ($resp->successful()) {
                return $resp->json();
            }

            Log::warning("Parlant sidecar returned HTTP " . $resp->status(), ['body' => $resp->body()]);
            return null;
        } catch (\Throwable $e) {
            Log::error("Failed to communicate with Parlant sidecar: " . $e->getMessage());
            return null;
        }
    }
}
