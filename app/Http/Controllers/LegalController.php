<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\WithdrawalConsent;
use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function __construct(private readonly WithdrawalConsent $consent) {}

    public function terms(): Response
    {
        return Inertia::render('legal/Terms', [
            'supplier' => $this->supplier(),
            'withdrawalConsentText' => $this->consent->text(),
        ]);
    }

    public function privacy(): Response
    {
        return Inertia::render('legal/Privacy', ['supplier' => $this->supplier()]);
    }

    public function contact(): Response
    {
        return Inertia::render('legal/Contact', ['supplier' => $this->supplier()]);
    }

    /**
     * @return array<string, string|null>
     */
    private function supplier(): array
    {
        /** @var array<string, string|null> $supplier */
        $supplier = config('shop.supplier');

        return $supplier;
    }
}
