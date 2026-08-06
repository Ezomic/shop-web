<?php

declare(strict_types=1);

use App\Models\Coupon;
use App\Models\Product;

it('lists published products in sort order', function (): void {
    Product::factory()->create(['name' => ['en' => 'Second', 'nl' => 'Tweede'], 'sort_order' => 2]);
    Product::factory()->create(['name' => ['en' => 'First', 'nl' => 'Eerste'], 'sort_order' => 1]);
    Product::factory()->draft()->create(['name' => ['en' => 'Hidden', 'nl' => 'Verborgen']]);

    $this->get(route('shop.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('shop/Index')
            ->has('products', 2)
            ->where('products.0.name', 'First')
            ->where('products.1.name', 'Second'));
});

it('shows a published product', function (): void {
    $product = Product::factory()->create(['price' => 2500]);

    $this->get(route('shop.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('product.price_formatted', '€ 25,00'));
});

it('404s on a draft product', function (): void {
    $product = Product::factory()->draft()->create();

    $this->get(route('shop.show', $product->slug))->assertNotFound();
});

it('adds a product to the cart', function (): void {
    $product = Product::factory()->create();

    $this->from(route('shop.index'))
        ->post(route('cart.add'), ['product_id' => $product->id])
        ->assertRedirect(route('shop.index'));

    $this->get(route('shop.index'))
        ->assertInertia(fn ($page) => $page->where('products.0.in_cart', true));
});

it('refuses to add a draft product to the cart', function (): void {
    $product = Product::factory()->draft()->create();

    $this->post(route('cart.add'), ['product_id' => $product->id])->assertNotFound();
});

it('removes a product from the cart', function (): void {
    $product = Product::factory()->create();

    $this->post(route('cart.add'), ['product_id' => $product->id]);
    $this->from(route('shop.index'))
        ->post(route('cart.remove'), ['product_id' => $product->id])
        ->assertRedirect(route('shop.index'));

    $this->get(route('shop.index'))
        ->assertInertia(fn ($page) => $page->where('products.0.in_cart', false));
});

it('applies a coupon over the api', function (): void {
    $product = Product::factory()->create(['price' => 2000]);
    $coupon = Coupon::factory()->percent(10)->create();

    $this->post(route('cart.add'), ['product_id' => $product->id]);

    $this->postJson(route('cart.coupon'), ['code' => $coupon->code])
        ->assertOk()
        ->assertJson(['discount' => 200, 'total' => 1800]);
});

it('rejects an invalid coupon over the api', function (): void {
    $this->postJson(route('cart.coupon'), ['code' => 'NOPE'])->assertStatus(422);
});

it('returns json rather than a redirect when the coupon code is missing', function (): void {
    $this->postJson(route('cart.coupon'), [])
        ->assertStatus(422)
        ->assertJsonStructure(['error']);
});

it('switches locale and resolves product names in it', function (): void {
    Product::factory()->create(['name' => ['en' => 'Stage Fright', 'nl' => 'Plankenkoorts']]);

    $this->from(route('shop.index'))->post(route('locale.switch', 'nl'))->assertRedirect();

    $this->get(route('shop.index'))
        ->assertInertia(fn ($page) => $page->where('products.0.name', 'Plankenkoorts'));
});

it('ignores an unsupported locale', function (): void {
    $this->from(route('shop.index'))->post(route('locale.switch', 'de'))->assertRedirect();

    expect(session('locale'))->toBeNull();
});
