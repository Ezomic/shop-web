<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // Uploaded rather than generated from the source file. Generating the first N pages
            // means a PDF dependency and no answer for a product that is not a PDF, while an
            // uploaded sample always works and lets the author choose what to show.
            $table->string('sample_path')->nullable()->after('cover_thumb_path');
            $table->string('sample_filename')->nullable()->after('sample_path');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['sample_path', 'sample_filename']);
        });
    }
};
