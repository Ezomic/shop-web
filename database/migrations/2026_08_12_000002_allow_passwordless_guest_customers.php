<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A guest buyer gets a Customer row with no password. That row cannot be logged into,
        // which is the point: it holds the order and the email, nothing more. The buyer can claim
        // it later by setting a password, either right after paying or via forgot-password.
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('password')->nullable(false)->change();
        });
    }
};
