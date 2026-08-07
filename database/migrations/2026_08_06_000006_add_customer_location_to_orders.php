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
            $table->string('country', 2)->nullable()->after('currency');

            // Which piece of evidence the country came from. Only a provider sourced country is
            // third party evidence in the VAT sense; "fallback" records that there was none.
            $table->string('country_source')->nullable()->after('country');

            // The second piece, stored from day one so crossing the higher evidence threshold
            // later does not need a schema change or a gap in the history.
            $table->string('ip_address', 45)->nullable()->after('country_source');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['country', 'country_source', 'ip_address']);
        });
    }
};
