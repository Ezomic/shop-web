<?php

declare(strict_types=1);

namespace App\Services;

/**
 * One order line after the discount has been shared out. All amounts are cents, and
 * gross === net + vat always holds.
 */
final readonly class VatLine
{
    public function __construct(
        public int $gross,
        public int $net,
        public int $vat,
    ) {}
}
