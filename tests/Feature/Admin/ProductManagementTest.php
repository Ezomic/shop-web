<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\User;

beforeEach(function (): void {
    $this->admin = User::factory()->create();
});

it('stores both locales when creating a product', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), [
            'name_en' => 'Stage Fright',
            'name_nl' => 'Plankenkoorts',
            'description_en' => 'A one act play.',
            'description_nl' => 'Een eenakter.',
            'price' => 1250,
            'status' => 'published',
        ])
        ->assertRedirect(route('admin.products.index'));

    $product = Product::firstOrFail();

    expect($product->getTranslation('name', 'en'))->toBe('Stage Fright')
        ->and($product->getTranslation('name', 'nl'))->toBe('Plankenkoorts')
        ->and($product->getTranslation('description', 'en'))->toBe('A one act play.')
        ->and($product->getTranslation('description', 'nl'))->toBe('Een eenakter.');
});

it('reads the name back after a refresh', function (): void {
    $this->actingAs($this->admin)->post(route('admin.products.store'), [
        'name_en' => 'Stage Fright',
        'name_nl' => 'Plankenkoorts',
        'description_en' => 'A one act play.',
        'description_nl' => 'Een eenakter.',
        'price' => 1250,
        'status' => 'published',
    ]);

    expect(Product::firstOrFail()->fresh()->name)->toBe('Stage Fright');
});

it('resolves the name for the active locale', function (): void {
    $product = Product::factory()->create([
        'name' => ['en' => 'Stage Fright', 'nl' => 'Plankenkoorts'],
    ]);

    expect($product->name)->toBe('Stage Fright');

    app()->setLocale('nl');

    expect($product->name)->toBe('Plankenkoorts');
});

it('updates both locales', function (): void {
    $product = Product::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.products.update', $product), [
            'name_en' => 'Second Draft',
            'name_nl' => 'Tweede Versie',
            'description_en' => 'Revised.',
            'description_nl' => 'Herzien.',
            'price' => 2000,
            'status' => 'draft',
        ])
        ->assertRedirect(route('admin.products.index'));

    $product->refresh();

    expect($product->getTranslation('name', 'en'))->toBe('Second Draft')
        ->and($product->getTranslation('name', 'nl'))->toBe('Tweede Versie')
        ->and($product->price)->toBe(2000)
        ->and($product->status)->toBe('draft');
});

it('lists products in the admin with their english name', function (): void {
    Product::factory()->create(['name' => ['en' => 'Stage Fright', 'nl' => 'Plankenkoorts']]);

    $this->actingAs($this->admin)
        ->get(route('admin.products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/products/Index')
            ->where('products.0.name', 'Stage Fright'));
});

it('shows the product on the storefront', function (): void {
    $product = Product::factory()->create(['name' => ['en' => 'Stage Fright', 'nl' => 'Plankenkoorts']]);

    $this->get(route('shop.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('product.name', 'Stage Fright'));
});
