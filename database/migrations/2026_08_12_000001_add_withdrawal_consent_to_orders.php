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
            // A claim that consent was given is worth nothing without a record of what was
            // actually agreed to, so the text and its version are stored, not just a boolean.
            $table->text('withdrawal_consent_text')->nullable()->after('ip_address');
            $table->string('withdrawal_consent_version')->nullable()->after('withdrawal_consent_text');
            $table->timestamp('withdrawal_consent_at')->nullable()->after('withdrawal_consent_version');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'withdrawal_consent_text',
                'withdrawal_consent_version',
                'withdrawal_consent_at',
            ]);
        });
    }
};
