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

beforeEach(function (): void {
    Storage::fake('shop');
    $this->admin = User::factory()->create();
});

function productPayload(array $overrides = []): array
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

it('attaches several files when creating a product', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), productPayload([
            'files' => [
                UploadedFile::fake()->create('script.pdf', 10),
                UploadedFile::fake()->create('score.pdf', 10),
                UploadedFile::fake()->create('cast-list.pdf', 10),
            ],
        ]))
        ->assertRedirect(route('admin.products.index'));

    expect(Product::firstOrFail()->files)->toHaveCount(3);
});

it('creates a product with no files at all', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), productPayload())
        ->assertRedirect(route('admin.products.index'));

    expect(Product::firstOrFail()->files)->toHaveCount(0);
});

it('adds files on update rather than replacing what is there', function (): void {
    $product = Product::factory()->has(ProductFile::factory(), 'files')->create();

    $this->actingAs($this->admin)
        ->put(route('admin.products.update', $product), productPayload([
            'files' => [UploadedFile::fake()->create('score.pdf', 10)],
        ]))
        ->assertRedirect(route('admin.products.index'));

    expect($product->fresh()->files)->toHaveCount(2);
});

it('leaves the files alone when an update carries no upload', function (): void {
    $product = Product::factory()->has(ProductFile::factory()->count(2), 'files')->create();

    $this->actingAs($this->admin)
        ->put(route('admin.products.update', $product), productPayload());

    expect($product->fresh()->files)->toHaveCount(2);
});

it('removes a single file without touching the others', function (): void {
    $product = Product::factory()->has(ProductFile::factory()->count(3), 'files')->create();
    $file = $product->files->first();

    $this->actingAs($this->admin)
        ->delete(route('admin.products.files.destroy', ['product' => $product, 'file' => $file]))
        ->assertRedirect();

    expect($product->fresh()->files)->toHaveCount(2)
        ->and(ProductFile::find($file->id))->toBeNull();
});

it('detaches rather than deletes a file a paid order still points at', function (): void {
    Mail::fake();

    $product = Product::factory()->create();
    $file = ProductFile::factory()->for($product)->create(['path' => 'products/bought.pdf']);
    Storage::disk('shop')->put('products/bought.pdf', 'the script');

    $order = Order::factory()->for(Customer::factory())->create();
    OrderItem::factory()->for($order)->forProduct($product)->create();
    app(CompleteOrderAction::class)->handle($order, 'card');

    $download = Download::firstOrFail();

    $this->actingAs($this->admin)
        ->delete(route('admin.products.files.destroy', ['product' => $product, 'file' => $file]))
        ->assertRedirect();

    expect(ProductFile::find($file->id))->not->toBeNull()
        ->and(ProductFile::find($file->id)->product_id)->toBeNull()
        ->and($product->fresh()->files)->toHaveCount(0)
        ->and(Storage::disk('shop')->exists('products/bought.pdf'))->toBeTrue();

    $this->get($download->fresh()->url())->assertOk();
});

it('404s when removing a file that belongs to another product', function (): void {
    $product = Product::factory()->create();
    $other = Product::factory()->has(ProductFile::factory(), 'files')->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.products.files.destroy', ['product' => $product, 'file' => $other->files->first()]))
        ->assertNotFound();

    expect($other->fresh()->files)->toHaveCount(1);
});

it('keeps file removal behind the admin guard', function (): void {
    $product = Product::factory()->has(ProductFile::factory(), 'files')->create();

    $this->delete(route('admin.products.files.destroy', ['product' => $product, 'file' => $product->files->first()]))
        ->assertRedirect(route('admin.login'));

    expect($product->fresh()->files)->toHaveCount(1);
});

it('lists the attached files on the edit page', function (): void {
    $product = Product::factory()->has(ProductFile::factory()->count(2), 'files')->create();

    $this->actingAs($this->admin)
        ->get(route('admin.products.edit', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('product.files', 2));
});

it('sells a multi file product and delivers a link per file', function (): void {
    Mail::fake();

    $this->actingAs($this->admin)->post(route('admin.products.store'), productPayload([
        'files' => [
            UploadedFile::fake()->create('script.pdf', 10),
            UploadedFile::fake()->create('score.pdf', 10),
        ],
    ]));

    $product = Product::firstOrFail();
    $order = Order::factory()->for(Customer::factory())->create();
    OrderItem::factory()->for($order)->forProduct($product)->create();

    app(CompleteOrderAction::class)->handle($order, 'card');

    expect(Download::count())->toBe(2);
});

it('rejects a file over the size cap', function (): void {
    $this->actingAs($this->admin)
        ->post(route('admin.products.store'), productPayload([
            'files' => [UploadedFile::fake()->create('huge.pdf', 200000)],
        ]))
        ->assertSessionHasErrors('files.0');

    expect(Product::count())->toBe(0);
});
