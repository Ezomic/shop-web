<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('payment_failed_at')->nullable()->after('paid_at');
            // Separate from the failure itself, so a provider that reports the same failure twice
            // cannot turn into two emails.
            $table->timestamp('failure_notified_at')->nullable()->after('payment_failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['payment_failed_at', 'failure_notified_at']);
        });
    }
};
