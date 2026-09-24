<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Copilot\CopilotService;
use Alamia360\Facades\Alamia360;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CopilotController extends Controller
{
    protected CopilotService $copilot;

    public function __construct(CopilotService $copilot)
    {
        $this->copilot = $copilot;
    }

    /**
     * Handle conversational message to Copilot.
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'prompt' => 'required_without:context.action|string|nullable',
            'company_code' => 'nullable|string',
            'context' => 'nullable|array',
        ]);

        $prompt = (string) ($request->input('prompt') ?? '');
        $companyCode = $request->input('company_code') ?? $request->header('X-Company-Code');
        $context = $request->input('context') ?? [];

        try {
            $response = $this->copilot->handleChat($prompt, $companyCode, $context);
            return response()->json([
                'success' => true,
                'data' => $response,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'sender' => 'Taliya',
                'message' => 'An error occurred while executing this capability: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get registered capabilities for the Copilot.
     */
    public function capabilities(): JsonResponse
    {
        $registry = app(\Alamia360\Capabilities\CapabilityRegistry::class);
        $caps = $registry->all();
        $list = [];

        foreach ($caps as $cap) {
            $list[] = [
                'name' => $cap->name(),
                'description' => $cap->description(),
                'side_effect' => $cap->sideEffect(),
                'allowed_actor_types' => $cap->allowedActorTypes(),
                'input_schema' => $cap->inputSchema(),
                'output_schema' => $cap->outputSchema(),
            ];
        }

        return response()->json([
            'success' => true,
            'capabilities' => $list,
        ]);
    }

    /**
     * Get active operational situations.
     */
    public function situations(): JsonResponse
    {
        $situations = Alamia360::situations()->open();
        $list = [];

        foreach ($situations as $s) {
            $list[] = [
                'type' => $s->type,
                'summary' => $s->summary,
                'priority' => $s->priority->value,
                'status' => $s->status()->value,
                'subject_type' => $s->subjectType,
                'subject_id' => $s->subjectId,
                'recommended_action' => $s->recommendedAction(),
                'detected_at' => $s->detectedAt()->toIso8601String(),
            ];
        }

        return response()->json([
            'success' => true,
            'situations' => $list,
            'count' => count($list),
        ]);
    }
}
