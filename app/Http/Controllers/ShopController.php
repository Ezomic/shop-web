<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use App\Services\CoverStorage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CoverStorage $covers,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->string('q')->trim()->toString();
        $sort = $request->string('sort')->toString();

        return Inertia::render('shop/Index', [
            'filters' => ['q' => $search, 'sort' => $sort ?: 'default'],
            'products' => Product::published()->search($search)->sorted($sort)->get()->map(fn ($p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'description' => $p->description,
                'price' => $p->price,
                'price_formatted' => $p->priceFormatted(),
                'cover_url' => $this->covers->url($p->cover_thumb_path),
                'has_sample' => $p->sample_path !== null,
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
                'cover_url' => $this->covers->url($product->cover_path),
                'has_sample' => $product->sample_path !== null,
                'in_cart' => $this->cart->has($product->id),
            ],
        ]);
    }
}
