<?php

namespace AlamiaSoft\AlamiaAccounts\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConcurrentPostingInProgressException extends Exception
{
    public function __construct(
        protected string $idempotencyKey,
        protected string $domainUuid,
        protected string $companyCode,
        protected ?string $sourceTransactionId = null,
        protected int $retryAfter = 2,
        string $message = "",
        int $code = 409,
        ?\Throwable $previous = null
    ) {
        if (empty($message)) {
            $message = "A financial posting for idempotency key '{$idempotencyKey}' is currently in progress by a concurrent worker. Please retry after {$retryAfter} seconds.";
        }
        parent::__construct($message, $code, $previous);
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getDomainUuid(): string
    {
        return $this->domainUuid;
    }

    public function getCompanyCode(): string
    {
        return $this->companyCode;
    }

    public function getSourceTransactionId(): ?string
    {
        return $this->sourceTransactionId;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * Render the exception as an HTTP JSON response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'posting_in_progress',
            'message' => $this->getMessage(),
            'idempotency_key' => $this->idempotencyKey,
            'retry_after' => $this->retryAfter,
        ], 409, [
            'Retry-After' => (string) $this->retryAfter,
        ]);
    }
}
