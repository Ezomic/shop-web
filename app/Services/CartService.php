<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class CartService
{
    private const KEY = 'cart';

    private const COUPON_KEY = 'cart_coupon';

    public function add(Product $product): void
    {
        $cart = $this->rawCart();
        $cart[$product->id] = $product->id;
        Session::put(self::KEY, $cart);
    }

    public function remove(int $productId): void
    {
        $cart = $this->rawCart();
        unset($cart[$productId]);
        Session::put(self::KEY, $cart);
    }

    public function contents(): Collection
    {
        $ids = array_keys($this->rawCart());

        return Product::published()->whereIn('id', $ids)->get();
    }

    public function count(): int
    {
        return count($this->rawCart());
    }

    public function has(int $productId): bool
    {
        return isset($this->rawCart()[$productId]);
    }

    public function clear(): void
    {
        Session::forget([self::KEY, self::COUPON_KEY]);
    }

    public function applyCoupon(string $code): ?Coupon
    {
        $coupon = Coupon::where('code', strtoupper($code))->first();

        if (! $coupon || ! $coupon->isValid()) {
            return null;
        }

        Session::put(self::COUPON_KEY, $coupon->id);

        return $coupon;
    }

    public function coupon(): ?Coupon
    {
        $id = Session::get(self::COUPON_KEY);

        if (! $id) {
            return null;
        }

        return Coupon::find($id);
    }

    public function totals(): array
    {
        $products = $this->contents();
        $subtotal = $products->sum('price');
        $coupon = $this->coupon();
        $discount = $coupon ? $coupon->discountFor($subtotal) : 0;
        $total = max(0, $subtotal - $discount);

        return compact('subtotal', 'discount', 'total', 'coupon');
    }

    private function rawCart(): array
    {
        return Session::get(self::KEY, []);
    }
}
