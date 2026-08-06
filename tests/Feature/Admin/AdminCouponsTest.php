<?php

declare(strict_types=1);

use App\Models\Coupon;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->create();
});

it('creates a coupon', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.coupons.store'), [
            'code' => 'summer',
            'type' => 'percent',
            'amount' => 15,
            'max_uses' => 100,
            'expires_at' => null,
            'active' => true,
        ])
        ->assertRedirect(route('admin.coupons.index'));

    $coupon = Coupon::firstOrFail();

    expect($coupon->code)->toBe('SUMMER')
        ->and($coupon->type)->toBe('percent')
        ->and($coupon->amount)->toBe(15)
        ->and($coupon->uses_count)->toBe(0);
});

it('rejects a duplicate code', function (): void {
    Coupon::factory()->create(['code' => 'SUMMER']);

    $this->actingAs($this->admin)
        ->post(route('admin.coupons.store'), [
            'code' => 'SUMMER',
            'type' => 'percent',
            'amount' => 15,
            'active' => true,
        ])
        ->assertSessionHasErrors('code');

    expect(Coupon::count())->toBe(1);
});

it('updates a coupon', function (): void {
    $coupon = Coupon::factory()->create(['amount' => 10]);

    $this->actingAs($this->admin)
        ->put(route('admin.coupons.update', $coupon), [
            'code' => $coupon->code,
            'type' => 'fixed',
            'amount' => 500,
            'max_uses' => null,
            'expires_at' => null,
            'active' => false,
        ])
        ->assertRedirect(route('admin.coupons.index'));

    $coupon->refresh();

    expect($coupon->type)->toBe('fixed')
        ->and($coupon->amount)->toBe(500)
        ->and($coupon->active)->toBeFalse();
});

it('deletes a coupon', function (): void {
    $coupon = Coupon::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.coupons.destroy', $coupon))
        ->assertRedirect(route('admin.coupons.index'));

    expect(Coupon::count())->toBe(0);
});

it('keeps the coupon pages behind the admin guard', function (): void {
    $this->get(route('admin.coupons.index'))->assertRedirect(route('admin.login'));
    $this->post(route('admin.coupons.store'), [])->assertRedirect(route('admin.login'));
});
