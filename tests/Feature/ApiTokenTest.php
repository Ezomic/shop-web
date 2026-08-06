<?php

declare(strict_types=1);

use App\Models\User;

// The admin group runs 'auth' before 'admin', so an unauthenticated request is
// bounced by the auth middleware to the storefront login, not to admin.login.
it('redirects guests from the token pages to the admin login', function () {
    $this->get(route('admin.api-tokens.index'))->assertRedirect(route('admin.login'));
    $this->post(route('admin.api-tokens.store'), ['name' => 'CI'])->assertRedirect(route('admin.login'));
});

it('lists the users tokens without exposing the secret', function () {
    $user = User::factory()->create();
    $user->createToken('existing');

    $this->actingAs($user)
        ->get(route('admin.api-tokens.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/ApiTokens')
            ->has('tokens', 1)
            ->where('tokens.0.name', 'existing')
            ->missing('tokens.0.token')
            ->where('createdToken', null)
        );
});

it('only lists the acting users own tokens', function () {
    $user = User::factory()->create();
    $user->createToken('mine');
    User::factory()->create()->createToken('theirs');

    $this->actingAs($user)
        ->get(route('admin.api-tokens.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('tokens', 1)->where('tokens.0.name', 'mine'));
});

it('creates a token and reveals the plaintext exactly once', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.api-tokens.store'), ['name' => 'CI pipeline'])
        ->assertRedirect(route('admin.api-tokens.index'));

    expect($user->tokens()->count())->toBe(1);

    // The redirect target reveals the plaintext once.
    $this->actingAs($user)
        ->get(route('admin.api-tokens.index'))
        ->assertInertia(fn ($page) => $page
            ->where('createdToken.name', 'CI pipeline')
            ->where('createdToken.plainText', fn (string $value): bool => str_contains($value, '|')));

    // A subsequent load no longer carries the secret.
    $this->actingAs($user)
        ->get(route('admin.api-tokens.index'))
        ->assertInertia(fn ($page) => $page->where('createdToken', null));
});

it('never persists the plaintext token', function () {
    $user = User::factory()->create();
    $plainText = $user->createToken('CI')->plainTextToken;

    $this->assertDatabaseMissing('personal_access_tokens', ['token' => $plainText]);
});

it('requires a token name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.api-tokens.store'), ['name' => ''])
        ->assertSessionHasErrors('name');

    expect($user->tokens()->count())->toBe(0);
});

it('rejects a duplicate token name for the same user', function () {
    $user = User::factory()->create();
    $user->createToken('CI');

    $this->actingAs($user)
        ->post(route('admin.api-tokens.store'), ['name' => 'CI'])
        ->assertSessionHasErrors('name');

    expect($user->tokens()->count())->toBe(1);
});

it('allows the same token name for different users', function () {
    User::factory()->create()->createToken('CI');
    $second = User::factory()->create();

    $this->actingAs($second)
        ->post(route('admin.api-tokens.store'), ['name' => 'CI'])
        ->assertSessionHasNoErrors();

    expect($second->tokens()->count())->toBe(1);
});

it('revokes the users own token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('CI')->accessToken;

    $this->actingAs($user)
        ->delete(route('admin.api-tokens.destroy', $token->getKey()))
        ->assertRedirect(route('admin.api-tokens.index'));

    expect($user->tokens()->count())->toBe(0);
});

it('cannot revoke another users token', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherToken = $other->createToken('theirs')->accessToken;

    $this->actingAs($user)
        ->delete(route('admin.api-tokens.destroy', $otherToken->getKey()))
        ->assertRedirect(route('admin.api-tokens.index'));

    expect($other->tokens()->count())->toBe(1);
});

it('authenticates an api request with a created token', function () {
    $user = User::factory()->create();
    $plainText = $user->createToken('CI')->plainTextToken;

    $this->getJson('/api/user')->assertUnauthorized();

    $this->getJson('/api/user', ['Authorization' => 'Bearer '.$plainText])
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});
