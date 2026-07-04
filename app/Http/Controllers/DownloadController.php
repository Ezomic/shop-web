<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ProcessDownloadAction;
use App\Models\Download;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    public function get(Request $request, ProcessDownloadAction $process): StreamedResponse
    {
        abort_unless($request->hasValidSignature(), 403);

        $download = Download::where('token', $request->route('token'))
            ->with('orderItem.product.files')
            ->firstOrFail();

        return $process->handle($download);
    }
}
