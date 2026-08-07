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
            // Unique is what actually guarantees the sequence has no duplicates when two
            // webhooks land at the same moment; the allocator retries on the collision.
            $table->string('invoice_number')->nullable()->unique()->after('paid_at');
            $table->timestamp('invoiced_at')->nullable()->after('invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['invoice_number']);
            $table->dropColumn(['invoice_number', 'invoiced_at']);
        });
    }
};
