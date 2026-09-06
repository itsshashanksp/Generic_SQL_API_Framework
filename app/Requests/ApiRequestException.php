<?php

class ApiRequestException extends Exception
{
    private string $errorCode;
    private array $details;
    private int $statusCode;

    public function __construct(
        string $message,
        string $errorCode = 'INVALID_REQUEST',
        array $details = [],
        int $statusCode = 400
    ) {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->details = $details;
        $this->statusCode = $statusCode;
    }

    public function getErrorCode(): string { return $this->errorCode; }
    public function getDetails(): array { return $this->details; }
    public function getStatusCode(): int { return $this->statusCode; }
}
