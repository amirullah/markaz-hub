<?php

namespace App\Services\Shopee;

/** Error dari Shopee Open Platform (field "error"/"message" pada respons). */
class ShopeeApiException extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly ?string $requestId = null,
    ) {
        parent::__construct("Shopee API [{$errorCode}]: {$message}" . ($requestId ? " (request_id: {$requestId})" : ''));
    }
}
