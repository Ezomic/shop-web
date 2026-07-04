<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/Settings', [
            'settings' => [
                'stripe_key' => config('services.stripe.key'),
                'mollie_key' => config('services.mollie.key'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'stripe_secret' => ['nullable', 'string'],
            'stripe_webhook_secret' => ['nullable', 'string'],
            'mollie_key' => ['nullable', 'string'],
        ]);

        $env = file_get_contents(base_path('.env'));

        foreach ([
            'STRIPE_SECRET' => $request->input('stripe_secret'),
            'STRIPE_WEBHOOK_SECRET' => $request->input('stripe_webhook_secret'),
            'MOLLIE_KEY' => $request->input('mollie_key'),
        ] as $key => $value) {
            if ($value !== null) {
                $env = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $env);
            }
        }

        file_put_contents(base_path('.env'), $env);
        Artisan::call('config:clear');

        return back()->with('success', 'Settings saved.');
    }
}
