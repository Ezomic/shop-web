<?php

declare(strict_types=1);

namespace App\Services;

final readonly class PaymentStatus
{
    public function __construct(
        public ?int $orderId,
        public PaymentState $state,
        public ?string $method,
        public ?string $country = null,
    ) {}

    public function isPaid(): bool
    {
        return $this->state === PaymentState::Paid;
    }

    public function hasFailed(): bool
    {
        return $this->state === PaymentState::Failed;
    }
}
