<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CompleteOrderAction;
use App\Actions\CreateOrderAction;
use App\Models\Order;
use App\Services\CartService;
use App\Services\PaymentService;
use App\Services\VatCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CreateOrderAction $createOrder,
        private readonly PaymentService $payment,
        private readonly VatCalculator $vat,
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
            'vat_rate' => $this->vat->rate(),
            'vat_amount' => $this->vat->vatOn($totals['total']),
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
        $order = $this->createOrder->handle($customer, $request->string('provider')->toString(), $request->ip());

        $url = $request->input('provider') === 'stripe'
            ? $this->payment->createStripeSession($order)
            : $this->payment->createMolliePayment($order);

        $this->cart->clear();

        return redirect()->away($url);
    }

    public function success(Request $request, CompleteOrderAction $complete): Response|RedirectResponse
    {
        $sessionId = $request->query('session_id');
        $order = null;

        if (is_string($sessionId) && $sessionId !== '') {
            $status = $this->payment->stripeSessionStatus($sessionId);
            $order = $status?->orderId === null ? null : Order::find($status->orderId);

            if ($order) {
                $this->authorizeOrder($request, $order);

                // The webhook stays the source of truth; this only shortens the wait for the
                // customer who is already looking at the page.
                if ($status->isPaid()) {
                    $complete->handle($order, $status->method, $status->country);
                }
            }
        }

        if ($order === null && $request->filled('order')) {
            $order = Order::find($request->integer('order'));

            if ($order) {
                $this->authorizeOrder($request, $order);
            }
        }

        return Inertia::render('checkout/Success', [
            'paid' => $order?->fresh()->isPaid() ?? false,
        ]);
    }

    public function mollieReturn(Request $request, Order $order, CompleteOrderAction $complete): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        $status = $order->payment_id === null
            ? null
            : $this->payment->molliePaymentStatus($order->payment_id);

        if ($status?->isPaid()) {
            $complete->handle($order, $status->method, $status->country);
        }

        if ($status?->hasFailed() && ! $order->isPaid()) {
            return redirect()->route('checkout.cancel');
        }

        return redirect()->route('checkout.success', ['order' => $order->id]);
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_if($order->customer_id !== $request->user('customer')->id, 403);
    }

    public function cancel(): Response
    {
        return Inertia::render('checkout/Cancel');
    }
}
