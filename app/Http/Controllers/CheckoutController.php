<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CompleteOrderAction;
use App\Actions\CreateOrderAction;
use App\Services\CartService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Stripe;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CreateOrderAction $createOrder,
        private readonly PaymentService $payment,
    ) {}

    public function index(): Response|RedirectResponse
    {
        if ($this->cart->count() === 0) {
            return redirect()->route('shop.index');
        }

        $totals = $this->cart->totals();

        return Inertia::render('checkout/Index', [
            'items' => $this->cart->contents()->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => $p->price,
                'price_formatted' => $p->priceFormatted(),
            ]),
            'subtotal' => $totals['subtotal'],
            'discount' => $totals['discount'],
            'total' => $totals['total'],
            'coupon' => $totals['coupon'] ? ['code' => $totals['coupon']->code] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'provider' => ['required', 'in:stripe,mollie'],
        ]);

        if ($this->cart->count() === 0) {
            return redirect()->route('shop.index');
        }

        $customer = Auth::guard('customer')->user();
        $order = $this->createOrder->handle($customer, $request->string('provider'));

        $url = $request->input('provider') === 'stripe'
            ? $this->payment->createStripeSession($order)
            : $this->payment->createMolliePayment($order);

        $this->cart->clear();

        return redirect()->away($url);
    }

    public function success(Request $request, CompleteOrderAction $complete): Response|RedirectResponse
    {
        $sessionId = $request->query('session_id');

        if ($sessionId) {
            Stripe::setApiKey(config('services.stripe.secret'));
            $session = StripeSession::retrieve($sessionId);
            $order = \App\Models\Order::find($session->metadata['order_id']);

            if ($order) {
                $complete->handle($order, $session->payment_method_types[0] ?? null);
            }
        }

        return Inertia::render('checkout/Success');
    }

    public function cancel(): Response
    {
        return Inertia::render('checkout/Cancel');
    }
}
