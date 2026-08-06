<?php

declare(strict_types=1);

namespace App\Services;

enum PaymentState
{
    case Paid;
    case Pending;
    case Failed;
}
