<?php

namespace AlamiaSoft\AlamiaAccounts\Exceptions;

use InvalidArgumentException;

class PostingValidationException extends InvalidArgumentException
{
    /**
     * @param string $message
     * @param int $code
     * @param array $context
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message,
        int $code = 422,
        protected array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get additional structured error context.
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
