<?php

declare(strict_types=1);

namespace SIQA\Agent\Exceptions;

class ApiException extends SIQAException
{
    private int $statusCode;

    public function __construct(string $message, int $statusCode, ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
