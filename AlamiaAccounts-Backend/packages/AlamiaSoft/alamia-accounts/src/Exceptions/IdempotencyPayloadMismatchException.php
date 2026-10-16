<?php

namespace AlamiaSoft\AlamiaAccounts\Exceptions;

use Exception;

class IdempotencyPayloadMismatchException extends Exception
{
    public function __construct(
        protected string $idempotencyKey,
        protected string $originalHash,
        protected string $attemptedHash,
        protected string $domainUuid,
        protected string $companyCode,
        protected ?string $sourceTransactionId = null,
        protected array $differences = [],
        string $message = "",
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        if (empty($message)) {
            $message = "Idempotency conflict: key '{$idempotencyKey}' was already committed with a different payload fingerprint (Original: {$originalHash}, Attempted: {$attemptedHash}). Reusing the same identity with altered financial parameters is rejected.";
        }
        parent::__construct($message, $code, $previous);
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getOriginalHash(): string
    {
        return $this->originalHash;
    }

    public function getAttemptedHash(): string
    {
        return $this->attemptedHash;
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

    public function getDifferences(): array
    {
        return $this->differences;
    }

    public function getStatusCode(): int
    {
        return 422;
    }
}
