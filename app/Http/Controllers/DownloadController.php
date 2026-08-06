<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ProcessDownloadAction;
use App\Models\Download;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    public function get(Request $request, ProcessDownloadAction $process): StreamedResponse|Response|SymfonyResponse
    {
        if (! $request->hasValidSignature()) {
            return $this->unavailable($request, 'expired');
        }

        $download = Download::where('token', $request->route('token'))
            ->with('productFile')
            ->firstOrFail();

        if ($download->isExhausted()) {
            return $this->unavailable($request, 'exhausted');
        }

        return $process->handle($download);
    }

    private function unavailable(Request $request, string $reason): SymfonyResponse
    {
        return Inertia::render('downloads/Unavailable', [
            'reason' => $reason,
            'signedIn' => $request->user('customer') !== null,
        ])->toResponse($request)->setStatusCode(403);
    }
}
