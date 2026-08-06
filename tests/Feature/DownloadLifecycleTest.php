<?php

declare(strict_types=1);

use App\Actions\CompleteOrderAction;
use App\Mail\OrderPaidMail;
use App\Models\Customer;
use App\Models\Download;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductFile;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

function paidOrderWithFile(): Order
{
    Mail::fake();
    Storage::fake('shop');
    Storage::disk('shop')->put('products/script.pdf', 'the script');

    $product = Product::factory()->create();
    ProductFile::factory()->for($product)->create([
        'path' => 'products/script.pdf',
        'original_filename' => 'script.pdf',
    ]);

    $order = Order::factory()->for(Customer::factory())->create();
    OrderItem::factory()->for($order)->forProduct($product)->create();

    app(CompleteOrderAction::class)->handle($order, 'card');

    return $order->fresh();
}

it('issues a link that expires', function (): void {
    config(['shop.downloads.link_ttl_days' => 14]);

    $order = paidOrderWithFile();
    $download = Download::firstOrFail();

    $emailedUrl = $download->url();

    expect($emailedUrl)->toContain('expires=');

    $this->get($emailedUrl)->assertOk();

    $this->travel(15)->days();

    $this->get($emailedUrl)
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page->component('downloads/Unavailable')->where('reason', 'expired'));
});

it('never expires the link when the ttl is disabled', function (): void {
    config(['shop.downloads.link_ttl_days' => 0]);

    paidOrderWithFile();
    $download = Download::firstOrFail();

    $emailedUrl = $download->url();

    expect($emailedUrl)->not->toContain('expires=');

    $this->travel(400)->days();

    $this->get($emailedUrl)->assertOk();
});

it('stops serving a link once it hits the use cap', function (): void {
    config(['shop.downloads.max_uses' => 2]);

    paidOrderWithFile();
    $download = Download::firstOrFail();

    $this->get($download->url())->assertOk();
    $this->get($download->url())->assertOk();

    $this->get($download->url())
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page->where('reason', 'exhausted'));

    expect($download->fresh()->download_count)->toBe(2);
});

it('does not cap downloads when max uses is disabled', function (): void {
    config(['shop.downloads.max_uses' => 0]);

    paidOrderWithFile();
    $download = Download::firstOrFail();

    foreach (range(1, 5) as $ignored) {
        $this->get($download->url())->assertOk();
    }

    expect($download->fresh()->usesLeft())->toBeNull();
});

it('lets the customer re-issue their own exhausted link', function (): void {
    config(['shop.downloads.max_uses' => 1]);

    $order = paidOrderWithFile();
    $download = Download::firstOrFail();
    $oldToken = $download->token;

    $this->get($download->url())->assertOk();
    $this->get($download->url())->assertForbidden();

    $this->actingAs($order->customer, 'customer')
        ->post(route('orders.downloads.reissue', ['order' => $order, 'download' => $download]))
        ->assertRedirect();

    $download->refresh();

    expect($download->token)->not->toBe($oldToken)
        ->and($download->download_count)->toBe(0);

    $this->get($download->url())->assertOk();
});

it('refuses to re-issue a download on another customer order', function (): void {
    $order = paidOrderWithFile();
    $download = Download::firstOrFail();

    $this->actingAs(Customer::factory()->create(), 'customer')
        ->post(route('orders.downloads.reissue', ['order' => $order, 'download' => $download]))
        ->assertForbidden();

    expect($download->fresh()->token)->toBe($download->token);
});

it('404s when the download does not belong to the given order', function (): void {
    $order = paidOrderWithFile();
    $download = Download::firstOrFail();
    $otherOrder = Order::factory()->for($order->customer)->create();

    $this->actingAs($order->customer, 'customer')
        ->post(route('orders.downloads.reissue', ['order' => $otherOrder, 'download' => $download]))
        ->assertNotFound();
});

it('lets an admin resend the order email', function (): void {
    $order = paidOrderWithFile();
    Mail::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('admin.orders.resend', $order))
        ->assertRedirect();

    Mail::assertQueued(OrderPaidMail::class);
});

it('refuses to resend the email for an unpaid order', function (): void {
    Mail::fake();
    $order = Order::factory()->for(Customer::factory())->create();

    $this->actingAs(User::factory()->create())
        ->post(route('admin.orders.resend', $order))
        ->assertStatus(422);

    Mail::assertNothingQueued();
});

it('lets an admin regenerate a link and kills the old one', function (): void {
    $order = paidOrderWithFile();
    $download = Download::firstOrFail();
    $oldUrl = $download->url();

    $this->actingAs(User::factory()->create())
        ->post(route('admin.orders.downloads.reissue', ['order' => $order, 'download' => $download]))
        ->assertRedirect();

    $this->get($oldUrl)->assertNotFound();
    $this->get($download->fresh()->url())->assertOk();
});
