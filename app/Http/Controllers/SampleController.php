<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Samples are deliberately public and deliberately separate from the paid download path.
 *
 * This controller only ever resolves Product::sample_path. It cannot reach a ProductFile or a
 * Download, so no bug in here can be talked into serving something somebody paid for.
 */
class SampleController extends Controller
{
    public function show(Product $product): StreamedResponse|Response
    {
        abort_if($product->status !== 'published', 404);
        abort_if($product->sample_path === null, 404);
        abort_unless(Storage::disk('shop')->exists($product->sample_path), 404);

        return Storage::disk('shop')->download(
            $product->sample_path,
            $product->sample_filename ?? 'sample.pdf',
        );
    }
}
