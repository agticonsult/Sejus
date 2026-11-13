<?php

namespace App\Services\GoogleMeet\Exceptions;

use Exception;
use Throwable;

class GoogleMeetApiException extends Exception
{
    protected int $httpStatusCode;
    protected array $responseBody;

    public function __construct(
        string $message = '',
        int $httpStatusCode = 0,
        ?Throwable $previous = null,
        array $responseBody = []
    ) {
        parent::__construct($message, $httpStatusCode, $previous);
        $this->httpStatusCode = $httpStatusCode;
        $this->responseBody = $responseBody;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getResponseBody(): array
    {
        return $this->responseBody;
    }
}
