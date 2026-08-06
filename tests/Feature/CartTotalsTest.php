<?php

declare(strict_types=1);

use App\Models\Coupon;
use App\Models\Product;
use App\Services\CartService;

function cart(): CartService
{
    return app(CartService::class);
}

it('sums the published products in the cart', function (): void {
    $cart = cart();
    $cart->add(Product::factory()->create(['price' => 1500]));
    $cart->add(Product::factory()->create(['price' => 2500]));

    expect($cart->totals())
        ->subtotal->toBe(4000)
        ->discount->toBe(0)
        ->total->toBe(4000);
});

it('ignores a product that is no longer published', function (): void {
    $cart = cart();
    $published = Product::factory()->create(['price' => 1500]);
    $draft = Product::factory()->draft()->create(['price' => 2500]);

    $cart->add($published);
    $cart->add($draft);

    expect($cart->totals()['subtotal'])->toBe(1500);
});

it('applies a percentage discount', function (): void {
    $cart = cart();
    $cart->add(Product::factory()->create(['price' => 2000]));
    $cart->applyCoupon(Coupon::factory()->percent(25)->create()->code);

    expect($cart->totals())
        ->discount->toBe(500)
        ->total->toBe(1500);
});

it('rounds a percentage discount to whole cents', function (): void {
    $cart = cart();
    $cart->add(Product::factory()->create(['price' => 1999]));
    $cart->applyCoupon(Coupon::factory()->percent(10)->create()->code);

    expect($cart->totals()['discount'])->toBe(200);
});

it('applies a fixed discount', function (): void {
    $cart = cart();
    $cart->add(Product::factory()->create(['price' => 2000]));
    $cart->applyCoupon(Coupon::factory()->fixed(750)->create()->code);

    expect($cart->totals())
        ->discount->toBe(750)
        ->total->toBe(1250);
});

it('never discounts below zero with an oversized fixed coupon', function (): void {
    $cart = cart();
    $cart->add(Product::factory()->create(['price' => 1000]));
    $cart->applyCoupon(Coupon::factory()->fixed(5000)->create()->code);

    expect($cart->totals())
        ->discount->toBe(1000)
        ->total->toBe(0);
});

it('matches a coupon code case insensitively', function (): void {
    $coupon = Coupon::factory()->create(['code' => 'SUMMER']);

    expect(cart()->applyCoupon('summer')?->id)->toBe($coupon->id);
});

it('rejects an expired coupon', function (): void {
    expect(cart()->applyCoupon(Coupon::factory()->expired()->create()->code))->toBeNull();
});

it('rejects an inactive coupon', function (): void {
    expect(cart()->applyCoupon(Coupon::factory()->inactive()->create()->code))->toBeNull();
});

it('rejects a coupon that has hit max uses', function (): void {
    expect(cart()->applyCoupon(Coupon::factory()->exhausted()->create()->code))->toBeNull();
});

it('rejects an unknown coupon code', function (): void {
    expect(cart()->applyCoupon('NOPE'))->toBeNull();
});

it('treats a coupon under its max uses as valid', function (): void {
    $coupon = Coupon::factory()->create(['max_uses' => 5, 'uses_count' => 4]);

    expect($coupon->isValid())->toBeTrue();
});

it('clears the cart and the applied coupon together', function (): void {
    $cart = cart();
    $cart->add(Product::factory()->create(['price' => 2000]));
    $cart->applyCoupon(Coupon::factory()->create()->code);

    $cart->clear();

    expect($cart->count())->toBe(0)
        ->and($cart->coupon())->toBeNull();
});
