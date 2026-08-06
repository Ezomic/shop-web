<?php

declare(strict_types=1);

namespace App\Services;

final readonly class PaymentStatus
{
    public function __construct(
        public ?int $orderId,
        public bool $paid,
        public ?string $method,
    ) {}
}
