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
            // Covers live on the public disk. Sellable files stay on the private shop disk, and
            // nothing on the public one should ever be a paid asset.
            $table->string('cover_path')->nullable()->after('preview_url');
            $table->string('cover_thumb_path')->nullable()->after('cover_path');
        });

        // preview_url pointed at somebody else's server and could not be resized. There is no
        // production data behind it, so it goes rather than lingering as a second way to do this.
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('preview_url');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('preview_url')->nullable()->after('status');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['cover_path', 'cover_thumb_path']);
        });
    }
};
