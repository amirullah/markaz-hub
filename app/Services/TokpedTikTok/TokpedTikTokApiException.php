<?php

namespace App\Services\TokpedTikTok;

class TokpedTikTokApiException extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly ?string $requestId = null,
    ) {
        parent::__construct("TikTok Shop API [{$errorCode}]: {$message}" . ($requestId ? " (request_id: {$requestId})" : ''));
    }
}