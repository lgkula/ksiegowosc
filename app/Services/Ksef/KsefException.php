<?php

namespace App\Services\Ksef;

use Illuminate\Http\Client\Response;
use RuntimeException;

class KsefException extends RuntimeException
{
    public static function fromResponse(string $operation, Response $response): self
    {
        $json = $response->json();
        $details = $json['exception']['exceptionDetailList'][0]['exceptionDescription']
            ?? $json['detail']
            ?? $json['title']
            ?? $json['status']['description']
            ?? mb_substr($response->body(), 0, 300);

        return new self("KSeF: {$operation} nie powiodło się (HTTP {$response->status()}): {$details}");
    }
}
