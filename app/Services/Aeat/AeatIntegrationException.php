<?php

namespace App\Services\Aeat;

use RuntimeException;
use Throwable;

class AeatIntegrationException extends RuntimeException
{
    /**
     * Create a new exception instance.
     */
    public function __construct(
        string $message,
        protected string $stage,
        protected ?string $errorCode = null,
        protected array $context = [],
        protected bool $retryable = false,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the AEAT integration stage that failed.
     */
    public function stage(): string
    {
        return $this->stage;
    }

    /**
     * Get the upstream error code, when available.
     */
    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Get the structured error context.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * Determine whether the failure can be retried automatically.
     */
    public function retryable(): bool
    {
        return $this->retryable;
    }
}
