<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function index(): Response
    {
        return Inertia::render('shop/Index', [
            'products' => Product::published()->ordered()->get()->map(fn ($p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'description' => $p->description,
                'price' => $p->price,
                'price_formatted' => $p->priceFormatted(),
                'preview_url' => $p->preview_url,
                'in_cart' => $this->cart->has($p->id),
            ]),
        ]);
    }

    public function show(Product $product): Response
    {
        abort_if($product->status !== 'published', 404);

        return Inertia::render('shop/Show', [
            'product' => [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'price_formatted' => $product->priceFormatted(),
                'preview_url' => $product->preview_url,
                'in_cart' => $this->cart->has($product->id),
            ],
        ]);
    }
}
