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
        // A product can be deleted from the catalogue while paid orders still point at its file,
        // so the file outlives the product rather than cascading away with it.
        Schema::table('product_files', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
        });

        Schema::table('product_files', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        Schema::table('downloads', function (Blueprint $table): void {
            $table->foreignId('product_file_id')->nullable()->after('order_item_id')
                ->constrained()->restrictOnDelete();
        });

        $files = DB::table('order_items')
            ->join('product_files', 'product_files.product_id', '=', 'order_items.product_id')
            ->select('order_items.id as order_item_id', 'product_files.id as product_file_id')
            ->get()
            ->groupBy('order_item_id');

        foreach ($files as $orderItemId => $rows) {
            DB::table('downloads')
                ->where('order_item_id', $orderItemId)
                ->whereNull('product_file_id')
                ->update(['product_file_id' => $rows->first()->product_file_id]);
        }
    }

    public function down(): void
    {
        Schema::table('downloads', function (Blueprint $table): void {
            $table->dropForeign(['product_file_id']);
            $table->dropColumn('product_file_id');
        });

        DB::table('product_files')->whereNull('product_id')->delete();

        Schema::table('product_files', function (Blueprint $table): void {
            $table->dropForeign(['product_id']);
        });

        Schema::table('product_files', function (Blueprint $table): void {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
        });
    }
};
