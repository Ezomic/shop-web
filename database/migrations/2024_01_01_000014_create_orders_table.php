<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending'); // pending|paid|refunded
            $table->string('currency', 3)->default('EUR');
            $table->unsignedInteger('subtotal'); // cents
            $table->unsignedInteger('discount')->default(0); // cents
            $table->unsignedInteger('total'); // cents
            $table->string('payment_provider'); // stripe|mollie
            $table->string('payment_id')->nullable();
            $table->string('payment_method')->nullable(); // card|ideal|etc
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
