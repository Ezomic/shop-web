<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PaymentCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private readonly PaymentCredentials $credentials) {}

    public function edit(): Response
    {
        return Inertia::render('admin/Settings', [
            'settings' => [
                'stripe_key' => config('services.stripe.key'),
                'stripe_secret_set' => $this->credentials->stripeSecret() !== null,
                'stripe_webhook_secret_set' => $this->credentials->stripeWebhookSecret() !== null,
                'mollie_key_set' => $this->credentials->mollieKey() !== null,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'stripe_secret' => ['nullable', 'string', 'max:255'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255'],
            'mollie_key' => ['nullable', 'string', 'max:255'],
        ]);

        $this->credentials->store($data);

        return back()->with('success', 'Settings saved.');
    }
}
