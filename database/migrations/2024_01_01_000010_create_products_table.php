<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('translations'); // {en:{name,description}, nl:{name,description}}
            $table->unsignedInteger('price'); // cents
            $table->string('currency', 3)->default('EUR');
            $table->string('status')->default('draft'); // draft|published
            $table->string('preview_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
