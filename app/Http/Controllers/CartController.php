<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function add(Request $request): RedirectResponse
    {
        $product = Product::published()->findOrFail((int) $request->input('product_id'));
        $this->cart->add($product);

        return back();
    }

    public function remove(Request $request): RedirectResponse
    {
        $this->cart->remove((int) $request->input('product_id'));

        return back();
    }

    public function applyCoupon(Request $request): JsonResponse
    {
        // Validated by hand: this endpoint is consumed by fetch(), and the app only renders
        // validation exceptions as JSON under api/*, so a thrown one would come back as a redirect.
        $code = $request->string('code')->trim()->toString();

        $coupon = $code === '' ? null : $this->cart->applyCoupon($code);

        if (! $coupon) {
            return response()->json(['error' => __('shop.coupon_invalid')], 422);
        }

        $totals = $this->cart->totals();

        return response()->json([
            'discount' => $totals['discount'],
            'total' => $totals['total'],
            'coupon' => ['code' => $coupon->code, 'type' => $coupon->type, 'amount' => $coupon->amount],
        ]);
    }
}
