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

        abort_if($env === false, 500, 'Unable to read the environment file.');

        foreach ([
            'STRIPE_SECRET' => $request->string('stripe_secret')->toString(),
            'STRIPE_WEBHOOK_SECRET' => $request->string('stripe_webhook_secret')->toString(),
            'MOLLIE_KEY' => $request->string('mollie_key')->toString(),
        ] as $key => $value) {
            if ($value !== '') {
                $env = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $env) ?? $env;
            }
        }

        file_put_contents(base_path('.env'), $env);
        Artisan::call('config:clear');

        return back()->with('success', 'Settings saved.');
    }
}
