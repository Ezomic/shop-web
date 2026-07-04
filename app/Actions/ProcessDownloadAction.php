<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Download;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcessDownloadAction
{
    public function handle(Download $download): StreamedResponse|Response
    {
        $item = $download->orderItem;
        $file = $item->product->files()->first();

        if (! $file) {
            abort(404);
        }

        $download->increment('download_count');
        $download->update(['last_downloaded_at' => now()]);

        return Storage::disk($file->disk)->download($file->path, $file->original_filename);
    }
}
