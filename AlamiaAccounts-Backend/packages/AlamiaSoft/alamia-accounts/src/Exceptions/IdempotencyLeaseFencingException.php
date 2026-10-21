<?php

namespace AlamiaSoft\AlamiaAccounts\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdempotencyLeaseFencingException extends Exception
{
    public function __construct(
        string $message = "Idempotency lease lost or preempted by another worker. Ledger transaction aborted to prevent orphan postings.",
        int $code = 409,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Render the exception as an HTTP JSON response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'lease_preempted',
            'message' => $this->getMessage(),
        ], 409);
    }
}
