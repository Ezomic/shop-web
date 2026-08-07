<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Mail\VatThresholdMail;
use App\Services\VatThresholdMonitor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckVatThresholdCommand extends Command
{
    protected $signature = 'shop:check-vat-threshold';

    protected $description = 'Warn when cross border sales approach the EU VAT threshold';

    public function handle(VatThresholdMonitor $monitor): int
    {
        $year = now()->year;
        $total = $monitor->crossBorderTotal($year);

        $this->line('Cross border sales in '.$year.': € '.number_format($total / 100, 2, ',', '.'));

        $recipient = $monitor->recipient();
        $hardLimit = (int) config('shop.vat.threshold');

        foreach ($monitor->unannouncedThresholds($year) as $threshold) {
            if ($recipient === null) {
                $this->warn('No recipient configured, cannot send the warning.');

                return self::FAILURE;
            }

            Mail::to($recipient)->send(new VatThresholdMail(
                threshold: $threshold,
                total: $total,
                year: $year,
                isHardLimit: $threshold >= $hardLimit,
            ));

            // Marked immediately so a second run in the same year stays quiet even if more sales
            // land in between.
            $monitor->markAnnounced($year, $threshold);

            $this->info('Warned about the € '.number_format($threshold / 100, 2, ',', '.').' threshold.');
        }

        return self::SUCCESS;
    }
}
