<?php

declare(strict_types=1);

use App\Actions\CompleteOrderAction;
use App\Models\Customer;
use App\Models\Download;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function paidOrderFor(Product $product): Order
{
    Mail::fake();

    $order = Order::factory()->for(Customer::factory())->create();
    OrderItem::factory()->for($order)->forProduct($product)->create();

    app(CompleteOrderAction::class)->handle($order, 'card');

    return $order->fresh();
}

it('creates one download per product file', function (): void {
    $product = Product::factory()->has(ProductFile::factory()->count(3), 'files')->create();

    paidOrderFor($product);

    expect(Download::count())->toBe(3)
        ->and(Download::pluck('product_file_id')->unique())->toHaveCount(3);
});

it('streams the file the download points at', function (): void {
    Storage::fake('shop');
    Storage::disk('shop')->put('products/script.pdf', 'the script');

    $product = Product::factory()->create();
    ProductFile::factory()->for($product)->create([
        'path' => 'products/script.pdf',
        'original_filename' => 'script.pdf',
    ]);

    paidOrderFor($product);

    $download = Download::firstOrFail();

    $this->get($download->url())
        ->assertOk()
        ->assertDownload('script.pdf');

    expect($download->fresh()->download_count)->toBe(1)
        ->and($download->fresh()->last_downloaded_at)->not->toBeNull();
});

it('rejects a download link without a valid signature', function (): void {
    $product = Product::factory()->has(ProductFile::factory(), 'files')->create();

    paidOrderFor($product);

    $download = Download::firstOrFail();

    $this->get(route('downloads.get', ['token' => $download->token]))->assertForbidden();

    expect($download->fresh()->download_count)->toBe(0);
});

it('404s for an unknown token', function (): void {
    $this->get(URL::signedRoute('downloads.get', ['token' => 'nope']))->assertNotFound();
});

it('keeps serving the bought file after another file is added', function (): void {
    Storage::fake('shop');

    $admin = User::factory()->create();
    $product = Product::factory()->create();
    ProductFile::factory()->for($product)->create([
        'path' => 'products/first.pdf',
        'original_filename' => 'first.pdf',
    ]);
    Storage::disk('shop')->put('products/first.pdf', 'first draft');

    paidOrderFor($product);
    $download = Download::firstOrFail();

    $this->actingAs($admin)->put(route('admin.products.update', $product), [
        'name_en' => 'Stage Fright',
        'name_nl' => 'Plankenkoorts',
        'description_en' => 'Revised.',
        'description_nl' => 'Herzien.',
        'price' => 2000,
        'status' => 'published',
        'files' => [UploadedFile::fake()->create('second.pdf', 10)],
    ])->assertRedirect(route('admin.products.index'));

    $download->refresh();

    // Uploading adds rather than replaces (SHOP-24), and the bought file is untouched either way.
    expect($download->productFile)->not->toBeNull()
        ->and($download->productFile->original_filename)->toBe('first.pdf')
        ->and(Storage::disk('shop')->exists('products/first.pdf'))->toBeTrue()
        ->and($product->fresh()->files()->count())->toBe(2);

    $this->get($download->url())->assertOk()->assertDownload('first.pdf');
});

it('deletes a removed file when nobody bought it', function (): void {
    Storage::fake('shop');

    $admin = User::factory()->create();
    $product = Product::factory()->create();
    $file = ProductFile::factory()->for($product)->create(['path' => 'products/first.pdf']);
    Storage::disk('shop')->put('products/first.pdf', 'first draft');

    $this->actingAs($admin)
        ->delete(route('admin.products.files.destroy', ['product' => $product, 'file' => $file]))
        ->assertRedirect();

    expect(Storage::disk('shop')->exists('products/first.pdf'))->toBeFalse()
        ->and(ProductFile::count())->toBe(0);
});

it('refuses to delete a product that has been ordered', function (): void {
    $admin = User::factory()->create();
    $product = Product::factory()->has(ProductFile::factory(), 'files')->create();

    paidOrderFor($product);

    $this->actingAs($admin)
        ->delete(route('admin.products.destroy', $product))
        ->assertSessionHasErrors('product');

    expect(Product::find($product->id))->not->toBeNull()
        ->and(Download::count())->toBe(1);
});

it('deletes a product that was never ordered along with its file', function (): void {
    Storage::fake('shop');

    $admin = User::factory()->create();
    $product = Product::factory()->create();
    ProductFile::factory()->for($product)->create(['path' => 'products/first.pdf']);
    Storage::disk('shop')->put('products/first.pdf', 'draft');

    $this->actingAs($admin)
        ->delete(route('admin.products.destroy', $product))
        ->assertRedirect(route('admin.products.index'));

    expect(Product::count())->toBe(0)
        ->and(ProductFile::count())->toBe(0)
        ->and(Storage::disk('shop')->exists('products/first.pdf'))->toBeFalse();
});

it('404s when the stored file has gone missing', function (): void {
    Storage::fake('shop');

    $product = Product::factory()->has(ProductFile::factory(), 'files')->create();

    paidOrderFor($product);

    $download = Download::firstOrFail();

    $this->get($download->url())->assertNotFound();

    expect($download->fresh()->download_count)->toBe(0);
});

it('lists every download on the customer order page', function (): void {
    $product = Product::factory()->has(ProductFile::factory()->count(2), 'files')->create();
    $order = paidOrderFor($product);

    $this->actingAs($order->customer, 'customer')
        ->get(route('orders.show', $order))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('order.items.0.downloads', 2));
});
