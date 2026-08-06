<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Download;
use Illuminate\Support\Str;

class ReissueDownloadAction
{
    /**
     * Mint a new token and clear the use count. Any link already out in the world stops working.
     */
    public function handle(Download $download): void
    {
        $download->update([
            'token' => Str::uuid()->toString(),
            'download_count' => 0,
        ]);
    }
}
