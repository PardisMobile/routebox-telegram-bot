<?php

declare(strict_types=1);

namespace RouteBox\Integrations\Payment;

interface PaymentGatewayInterface
{
    /** Create a gateway payment and return the redirect/payment URL. */
    public function createPayment(array $order): PaymentResult;

    /** Verify a callback/transaction and return the verified payment result. */
    public function verifyPayment(array $payload): PaymentResult;

    /** Return a stable machine-readable gateway identifier. */
    public function name(): string;
}
