<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedInteger('vat_rate')->default(0)->after('total');
            $table->unsignedInteger('vat_amount')->default(0)->after('vat_rate'); // cents
            $table->unsignedInteger('net_total')->default(0)->after('vat_amount'); // cents
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedInteger('vat_rate')->default(0)->after('price');
            $table->unsignedInteger('vat_amount')->default(0)->after('vat_rate'); // cents
            $table->unsignedInteger('net_price')->default(0)->after('vat_amount'); // cents
        });

        // Existing rows predate VAT being recorded. Backfilling them at today's rate would invent
        // a split that was never charged, so they keep rate 0 and a net equal to the gross.
        DB::table('orders')->update(['net_total' => DB::raw('total')]);
        DB::table('order_items')->update(['net_price' => DB::raw('price')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['vat_rate', 'vat_amount', 'net_total']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['vat_rate', 'vat_amount', 'net_price']);
        });
    }
};
