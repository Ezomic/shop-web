<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Services\PaymentCredentials;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->admin = User::factory()->create();
});

it('saves a credential and reads it back', function (): void {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), ['stripe_secret' => 'sk_live_abc'])
        ->assertRedirect();

    expect(app(PaymentCredentials::class)->stripeSecret())->toBe('sk_live_abc');
});

it('stores the value encrypted rather than in plain text', function (): void {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), ['stripe_secret' => 'sk_live_abc']);

    $raw = DB::table('settings')->where('key', 'stripe_secret')->value('value');

    expect($raw)->not->toContain('sk_live_abc')
        ->and(Setting::where('key', 'stripe_secret')->firstOrFail()->value)->toBe('sk_live_abc');
});

it('keeps working with a cached config', function (): void {
    config(['services.stripe.secret' => null]);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), ['stripe_secret' => 'sk_live_abc']);

    // Nothing is written to .env and no config reload happens, so the value has to survive
    // purely through the settings table.
    expect(app(PaymentCredentials::class)->stripeSecret())->toBe('sk_live_abc')
        ->and(config('services.stripe.secret'))->toBeNull();
});

it('leaves the environment value in place when nothing is stored', function (): void {
    config(['services.stripe.secret' => 'sk_from_env']);

    expect(app(PaymentCredentials::class)->stripeSecret())->toBe('sk_from_env');
});

it('prefers a stored value over the environment', function (): void {
    config(['services.stripe.secret' => 'sk_from_env']);

    app(PaymentCredentials::class)->store(['stripe_secret' => 'sk_from_db']);

    expect(app(PaymentCredentials::class)->stripeSecret())->toBe('sk_from_db');
});

it('ignores empty fields so a blank submit does not wipe a credential', function (): void {
    app(PaymentCredentials::class)->store(['stripe_secret' => 'sk_live_abc']);

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), ['stripe_secret' => '', 'mollie_key' => 'test_key']);

    expect(app(PaymentCredentials::class)->stripeSecret())->toBe('sk_live_abc')
        ->and(app(PaymentCredentials::class)->mollieKey())->toBe('test_key');
});

it('refuses to store an unknown key', function (): void {
    app(PaymentCredentials::class)->store(['app_key' => 'nope']);

    expect(Setting::where('key', 'app_key')->exists())->toBeFalse();
});

it('does not write to the environment file', function (): void {
    $before = file_get_contents(base_path('.env'));

    $this->actingAs($this->admin)
        ->put(route('admin.settings.update'), ['stripe_secret' => "sk_live_abc\nAPP_DEBUG=true"]);

    expect(file_get_contents(base_path('.env')))->toBe($before);
});

it('reports which credentials are configured', function (): void {
    config(['services.stripe.secret' => null, 'services.stripe.webhook_secret' => null, 'services.mollie.key' => null]);

    app(PaymentCredentials::class)->store(['mollie_key' => 'test_key']);

    $this->actingAs($this->admin)
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('settings.mollie_key_set', true)
            ->where('settings.stripe_secret_set', false));
});

it('requires an admin', function (): void {
    $this->get(route('admin.settings.edit'))->assertRedirect(route('admin.login'));
});
