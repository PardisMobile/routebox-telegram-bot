<?php

declare(strict_types=1);

namespace RouteBox\Integrations\Payment;

final class PaymentResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $status,
        public readonly ?string $authority = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $transactionId = null,
        public readonly ?string $message = null,
    ) {
    }

    public static function success(
        string $status = 'verified',
        ?string $authority = null,
        ?string $redirectUrl = null,
        ?string $transactionId = null,
        ?string $message = null,
    ): self {
        return new self(true, $status, $authority, $redirectUrl, $transactionId, $message);
    }

    public static function failure(string $message, string $status = 'failed'): self
    {
        return new self(false, $status, null, null, null, $message);
    }
}
