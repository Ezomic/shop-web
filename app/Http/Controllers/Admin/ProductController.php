<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/products/Index', [
            'products' => Product::ordered()->get()->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->getTranslation('name', 'en'),
                'slug' => $p->slug,
                'price_formatted' => $p->priceFormatted(),
                'status' => $p->status,
                'sort_order' => $p->sort_order,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/products/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_nl' => ['required', 'string', 'max:255'],
            'description_en' => ['required', 'string'],
            'description_nl' => ['required', 'string'],
            'price' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:draft,published'],
            'preview_url' => ['nullable', 'url'],
            'file' => ['nullable', 'file', 'max:102400'],
        ]);

        $slug = Str::slug($data['name_en']);
        $counter = 1;
        $base = $slug;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$counter++;
        }

        $product = Product::create([
            'slug' => $slug,
            'translations' => [
                'en' => ['name' => $data['name_en'], 'description' => $data['description_en']],
                'nl' => ['name' => $data['name_nl'], 'description' => $data['description_nl']],
            ],
            'price' => $data['price'],
            'status' => $data['status'],
            'preview_url' => $data['preview_url'] ?? null,
            'sort_order' => Product::max('sort_order') + 1,
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('products', 'shop');
            ProductFile::create([
                'product_id' => $product->id,
                'disk' => 'shop',
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ]);
        }

        return redirect()->route('admin.products.index')->with('success', 'Product created.');
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('admin/products/Edit', [
            'product' => [
                'id' => $product->id,
                'name_en' => $product->getTranslation('name', 'en'),
                'name_nl' => $product->getTranslation('name', 'nl'),
                'description_en' => $product->getTranslation('description', 'en'),
                'description_nl' => $product->getTranslation('description', 'nl'),
                'price' => $product->price,
                'status' => $product->status,
                'preview_url' => $product->preview_url,
                'file' => $product->files()->first()?->only('id', 'original_filename', 'size'),
            ],
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_nl' => ['required', 'string', 'max:255'],
            'description_en' => ['required', 'string'],
            'description_nl' => ['required', 'string'],
            'price' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:draft,published'],
            'preview_url' => ['nullable', 'url'],
            'file' => ['nullable', 'file', 'max:102400'],
        ]);

        $product->update([
            'translations' => [
                'en' => ['name' => $data['name_en'], 'description' => $data['description_en']],
                'nl' => ['name' => $data['name_nl'], 'description' => $data['description_nl']],
            ],
            'price' => $data['price'],
            'status' => $data['status'],
            'preview_url' => $data['preview_url'] ?? null,
        ]);

        if ($request->hasFile('file')) {
            $product->files()->each(function (ProductFile $pf): void {
                Storage::disk($pf->disk)->delete($pf->path);
                $pf->delete();
            });

            $file = $request->file('file');
            $path = $file->store('products', 'shop');
            ProductFile::create([
                'product_id' => $product->id,
                'disk' => 'shop',
                'path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ]);
        }

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->files()->each(function (ProductFile $pf): void {
            Storage::disk($pf->disk)->delete($pf->path);
            $pf->delete();
        });

        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $request->validate(['order' => ['required', 'array']]);

        foreach ($request->input('order') as $index => $id) {
            Product::where('id', $id)->update(['sort_order' => $index]);
        }

        return back();
    }
}
