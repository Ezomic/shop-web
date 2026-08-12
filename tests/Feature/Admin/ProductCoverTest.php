<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\User;
use App\Services\CoverStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('shop');
    $this->admin = User::factory()->create();
});

function coverPayload(array $overrides = []): array
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

it('stores a display image and a thumbnail', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), coverPayload([
            'cover' => UploadedFile::fake()->image('cover.jpg', 2000, 1400),
        ]))
        ->assertRedirect(route('admin.products.index'));

    $product = Product::firstOrFail();

    expect($product->cover_path)->not->toBeNull()
        ->and($product->cover_thumb_path)->not->toBeNull()
        ->and(Storage::disk('public')->exists($product->cover_path))->toBeTrue()
        ->and(Storage::disk('public')->exists($product->cover_thumb_path))->toBeTrue();
});

it('keeps covers on the public disk and never on the private one', function (): void {
    $this->actingAs($this->admin)->post(route('admin.products.store'), coverPayload([
        'cover' => UploadedFile::fake()->image('cover.jpg'),
        'files' => [UploadedFile::fake()->create('script.pdf', 10)],
    ]));

    $product = Product::firstOrFail();

    expect(Storage::disk('public')->exists($product->cover_path))->toBeTrue()
        ->and(Storage::disk('shop')->exists($product->cover_path))->toBeFalse()
        // and the sellable file is the other way round
        ->and(Storage::disk('shop')->exists($product->files->first()->path))->toBeTrue()
        ->and(Storage::disk('public')->allFiles())->not->toContain($product->files->first()->path);
});

it('replaces the old cover rather than leaving it behind', function (): void {
    $this->actingAs($this->admin)->post(route('admin.products.store'), coverPayload([
        'cover' => UploadedFile::fake()->image('first.jpg'),
    ]));

    $product = Product::firstOrFail();
    $firstPath = $product->cover_path;

    $this->actingAs($this->admin)->put(route('admin.products.update', $product), coverPayload([
        'cover' => UploadedFile::fake()->image('second.jpg'),
    ]));

    $product->refresh();

    expect($product->cover_path)->not->toBe($firstPath)
        ->and(Storage::disk('public')->exists($firstPath))->toBeFalse()
        ->and(Storage::disk('public')->exists($product->cover_path))->toBeTrue();
});

it('leaves the cover alone when an update carries no image', function (): void {
    $this->actingAs($this->admin)->post(route('admin.products.store'), coverPayload([
        'cover' => UploadedFile::fake()->image('cover.jpg'),
    ]));

    $product = Product::firstOrFail();
    $path = $product->cover_path;

    $this->actingAs($this->admin)->put(route('admin.products.update', $product), coverPayload());

    expect($product->fresh()->cover_path)->toBe($path)
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});

it('rejects a file that is not an image', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), coverPayload([
            'cover' => UploadedFile::fake()->create('script.pdf', 10),
        ]))
        ->assertSessionHasErrors('cover');

    expect(Product::count())->toBe(0);
});

it('shows the thumbnail on the storefront index and the full cover on the product page', function (): void {
    $this->actingAs($this->admin)->post(route('admin.products.store'), coverPayload([
        'cover' => UploadedFile::fake()->image('cover.jpg'),
    ]));

    $product = Product::firstOrFail();
    $covers = app(CoverStorage::class);

    $this->get(route('shop.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('products.0.cover_url', $covers->url($product->cover_thumb_path)));

    $this->get(route('shop.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('product.cover_url', $covers->url($product->cover_path)));
});

it('renders a product with no cover without a broken image', function (): void {
    $product = Product::factory()->create();

    $this->get(route('shop.show', $product->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('product.cover_url', null));
});

it('cleans up the covers when the product is deleted', function (): void {
    $this->actingAs($this->admin)->post(route('admin.products.store'), coverPayload([
        'cover' => UploadedFile::fake()->image('cover.jpg'),
    ]));

    $product = Product::firstOrFail();
    $paths = [$product->cover_path, $product->cover_thumb_path];

    $this->actingAs($this->admin)->delete(route('admin.products.destroy', $product));

    foreach ($paths as $path) {
        expect(Storage::disk('public')->exists($path))->toBeFalse();
    }
});

it('does not enlarge an image smaller than the target width', function (): void {
    $this->actingAs($this->admin)->post(route('admin.products.store'), coverPayload([
        'cover' => UploadedFile::fake()->image('tiny.jpg', 120, 80),
    ]));

    $product = Product::firstOrFail();
    $bytes = Storage::disk('public')->get($product->cover_path);
    $info = getimagesizefromstring($bytes);

    expect($info[0])->toBe(120);
});
