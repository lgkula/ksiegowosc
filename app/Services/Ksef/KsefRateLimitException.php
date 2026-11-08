<?php

namespace App\Services\Ksef;

/** KSeF odrzucił zapytanie z powodu limitu (HTTP 429), a czas oczekiwania przekracza dopuszczalny. */
class KsefRateLimitException extends KsefException
{
    public function __construct(string $message, public readonly int $retryAfter)
    {
        parent::__construct($message);
    }
}
