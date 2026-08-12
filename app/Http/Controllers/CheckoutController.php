<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CompleteOrderAction;
use App\Actions\CreateOrderAction;
use App\Actions\ResolveCheckoutCustomerAction;
use App\Models\Customer;
use App\Models\Order;
use App\Services\CartService;
use App\Services\PaymentService;
use App\Services\VatCalculator;
use App\Services\WithdrawalConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CreateOrderAction $createOrder,
        private readonly PaymentService $payment,
        private readonly VatCalculator $vat,
        private readonly WithdrawalConsent $consent,
        private readonly ResolveCheckoutCustomerAction $resolveCustomer,
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
            'withdrawal_consent_text' => $this->consent->text(),
            'isGuest' => $this->currentCustomer() === null,
            'vat_rate' => $this->vat->rate(),
            'vat_amount' => $this->vat->vatOn($totals['total']),
            'coupon' => $totals['coupon'] ? ['code' => $totals['coupon']->code] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->cart->count() === 0) {
            return redirect()->route('shop.index');
        }

        $customer = $this->currentCustomer();

        $request->validate([
            'provider' => ['required', 'in:stripe,mollie'],
            // Only a buyer with no session has to say who they are.
            'email' => [$customer ? 'nullable' : 'required', 'email', 'max:255'],
            'name' => [$customer ? 'nullable' : 'required', 'string', 'max:255'],
            // Not a formality: without this the buyer keeps a 14 day right of withdrawal even
            // after downloading. See SHOP-22.
            'withdrawal_consent' => ['accepted'],
        ], [
            'withdrawal_consent.accepted' => __('shop.withdrawal_consent_required'),
        ]);

        $customer ??= $this->resolveCustomer->handle(
            $request->string('email')->lower()->trim()->toString(),
            $request->string('name')->trim()->toString(),
        );

        $order = $this->createOrder->handle($customer, $request->string('provider')->toString(), $request->ip());

        // A guest has no account to look the order up under, so the session that created it is
        // what authorises the return pages. Nothing else is granted by it.
        if ($this->currentCustomer() === null) {
            $request->session()->push('guest_order_ids', $order->id);
        }

        $order->forceFill([
            'withdrawal_consent_text' => $this->consent->text(),
            'withdrawal_consent_version' => $this->consent->version(),
            'withdrawal_consent_at' => now(),
        ])->save();

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

        $customer = $order?->customer;

        return Inertia::render('checkout/Success', [
            'paid' => $order?->fresh()->isPaid() ?? false,
            // Offered only for an order that bought under a passwordless guest row. An address
            // that already has a real account must never be claimable this way.
            'claimable' => $order !== null
                && $this->currentCustomer() === null
                && $customer !== null
                && $customer->isGuest(),
            'orderId' => $order?->id,
            'email' => $customer?->email,
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
        $customer = $this->currentCustomer();

        if ($customer !== null && $order->customer_id === $customer->id) {
            return;
        }

        abort_unless(
            in_array($order->id, $request->session()->get('guest_order_ids', []), strict: true),
            403,
        );
    }

    private function currentCustomer(): ?Customer
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();

        return $customer;
    }

    /**
     * Turn the passwordless guest row into a real account by setting a password on it.
     */
    public function claim(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        abort_unless($order->customer->isGuest(), 403);

        $request->validate(['password' => ['required', 'confirmed', Password::defaults()]]);

        $order->customer->forceFill([
            'password' => $request->string('password')->toString(),
        ])->save();

        Auth::guard('customer')->login($order->customer);

        return redirect()->route('orders.index')->with('success', 'Your account is ready.');
    }

    public function cancel(): Response
    {
        return Inertia::render('checkout/Cancel');
    }
}
