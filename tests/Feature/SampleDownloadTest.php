<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('shop');
    Storage::fake('public');
    $this->admin = User::factory()->create();
});

function samplePayload(array $overrides = []): array
{
    return array_merge([
        'name_en' => 'Stage Fright',
        'name_nl' => 'Plankenkoorts',
        'description_en' => 'A one act play.',
        'description_nl' => 'Een eenakter.',
        'price' => 2500,
        'status' => 'published',
    ], $overrides);
}

function productWithSample(array $overrides = []): Product
{
    $product = Product::factory()->create($overrides);

    Storage::disk('shop')->put('samples/first-pages.pdf', 'the first few pages');

    $product->forceFill([
        'sample_path' => 'samples/first-pages.pdf',
        'sample_filename' => 'first-pages.pdf',
    ])->save();

    return $product;
}

it('downloads a sample without an account', function (): void {
    $product = productWithSample();

    $this->get(route('shop.sample', $product->slug))
        ->assertOk()
        ->assertDownload('first-pages.pdf');

    $this->assertGuest('customer');
});

it('404s for a product with no sample', function (): void {
    $product = Product::factory()->create();

    $this->get(route('shop.sample', $product->slug))->assertNotFound();
});

it('404s for a draft product', function (): void {
    $product = productWithSample(['status' => 'draft']);

    $this->get(route('shop.sample', $product->slug))->assertNotFound();
});

it('404s when the sample file has gone missing', function (): void {
    $product = productWithSample();
    Storage::disk('shop')->delete('samples/first-pages.pdf');

    $this->get(route('shop.sample', $product->slug))->assertNotFound();
});

it('cannot be talked into serving the paid file', function (): void {
    $product = productWithSample();

    Storage::disk('shop')->put('products/the-real-thing.pdf', 'the whole script');
    ProductFile::factory()->for($product)->create([
        'path' => 'products/the-real-thing.pdf',
        'original_filename' => 'the-real-thing.pdf',
    ]);

    $response = $this->get(route('shop.sample', $product->slug));

    $response->assertOk()->assertDownload('first-pages.pdf');

    expect($response->streamedContent())->toBe('the first few pages')
        ->not->toBe('the whole script');
});

it('serves the sample from the private disk, never the public one', function (): void {
    $this->actingAs($this->admin)->post(route('admin.products.store'), samplePayload([
        'sample' => UploadedFile::fake()->create('sample.pdf', 10),
    ]));

    $product = Product::firstOrFail();

    expect(Storage::disk('shop')->exists($product->sample_path))->toBeTrue()
        ->and(Storage::disk('public')->exists($product->sample_path))->toBeFalse();
});

it('uploads a sample with the product', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), samplePayload([
            'sample' => UploadedFile::fake()->create('first-pages.pdf', 10),
        ]))
        ->assertRedirect(route('admin.products.index'));

    $product = Product::firstOrFail();

    expect($product->sample_path)->not->toBeNull()
        ->and($product->sample_filename)->toBe('first-pages.pdf');
});

it('replaces the sample rather than leaving the old one behind', function (): void {
    $product = productWithSample();
    $firstPath = $product->sample_path;

    $this->actingAs($this->admin)->put(route('admin.products.update', $product), samplePayload([
        'sample' => UploadedFile::fake()->create('second.pdf', 10),
    ]));

    $product->refresh();

    expect($product->sample_filename)->toBe('second.pdf')
        ->and(Storage::disk('shop')->exists($firstPath))->toBeFalse();
});

it('removes the sample on request', function (): void {
    $product = productWithSample();
    $path = $product->sample_path;

    $this->actingAs($this->admin)
        ->delete(route('admin.products.sample.destroy', $product))
        ->assertRedirect();

    $product->refresh();

    expect($product->sample_path)->toBeNull()
        ->and($product->sample_filename)->toBeNull()
        ->and(Storage::disk('shop')->exists($path))->toBeFalse();

    $this->get(route('shop.sample', $product->slug))->assertNotFound();
});

it('keeps sample removal behind the admin guard', function (): void {
    $product = productWithSample();

    $this->delete(route('admin.products.sample.destroy', $product))->assertRedirect(route('admin.login'));

    expect($product->fresh()->sample_path)->not->toBeNull();
});

it('tells the storefront which products have a sample', function (): void {
    $withSample = productWithSample();
    Product::factory()->create();

    $this->get(route('shop.show', $withSample->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('product.has_sample', true));

    $this->get(route('shop.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('products', 2));
});

it('cleans the sample up when the product is deleted', function (): void {
    $product = productWithSample();
    $path = $product->sample_path;

    $this->actingAs($this->admin)->delete(route('admin.products.destroy', $product));

    expect(Storage::disk('shop')->exists($path))->toBeFalse();
});
