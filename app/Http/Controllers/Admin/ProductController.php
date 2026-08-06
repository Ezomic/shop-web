<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
            'name' => ['en' => $data['name_en'], 'nl' => $data['name_nl']],
            'description' => ['en' => $data['description_en'], 'nl' => $data['description_nl']],
            'price' => $data['price'],
            'status' => $data['status'],
            'preview_url' => $data['preview_url'] ?? null,
            'sort_order' => Product::max('sort_order') + 1,
        ]);

        if ($request->hasFile('file')) {
            $this->storeFile($product, $request->file('file'));
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
            'name' => ['en' => $data['name_en'], 'nl' => $data['name_nl']],
            'description' => ['en' => $data['description_en'], 'nl' => $data['description_nl']],
            'price' => $data['price'],
            'status' => $data['status'],
            'preview_url' => $data['preview_url'] ?? null,
        ]);

        if ($request->hasFile('file')) {
            $this->releaseFiles($product);
            $this->storeFile($product, $request->file('file'));
        }

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        // order_items.product_id is restrictOnDelete, so a sold product cannot be removed without
        // taking the order history with it. Unpublishing is the way to retire one.
        if ($product->orderItems()->exists()) {
            return back()->withErrors([
                'product' => 'This product has been ordered. Set it to draft instead of deleting it.',
            ]);
        }

        $this->releaseFiles($product);

        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    /**
     * Drop the product's files, except any a paid download still points at. Those are detached
     * from the product instead of deleted, so earlier buyers keep the file they actually bought.
     */
    private function releaseFiles(Product $product): void
    {
        $product->files()->each(function (ProductFile $file): void {
            if ($file->downloads()->exists()) {
                $file->update(['product_id' => null]);

                return;
            }

            Storage::disk($file->disk)->delete($file->path);
            $file->delete();
        });
    }

    private function storeFile(Product $product, UploadedFile $upload): void
    {
        ProductFile::create([
            'product_id' => $product->id,
            'disk' => 'shop',
            'path' => $upload->store('products', 'shop'),
            'original_filename' => $upload->getClientOriginalName(),
            'size' => $upload->getSize(),
        ]);
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
