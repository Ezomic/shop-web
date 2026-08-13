<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly, before the working day, so a bad morning still has last night's copy.
Schedule::command('shop:backup')->dailyAt('03:20')->withoutOverlapping();

Schedule::command('shop:expire-orders')->dailyAt('03:40');

// Weekly is plenty: the threshold is an annual total, and the warning fires well before it bites.
Schedule::command('shop:check-vat-threshold')->weeklyOn(1, '08:00');
