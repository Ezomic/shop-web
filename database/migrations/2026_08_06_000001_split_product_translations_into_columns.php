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
        Schema::table('products', function (Blueprint $table): void {
            $table->json('name')->nullable()->after('slug');
            $table->json('description')->nullable()->after('name');
        });

        foreach (DB::table('products')->select('id', 'translations')->get() as $product) {
            $translations = json_decode((string) $product->translations, true);

            if (! is_array($translations)) {
                continue;
            }

            DB::table('products')->where('id', $product->id)->update([
                'name' => json_encode($this->pluck($translations, 'name')),
                'description' => json_encode($this->pluck($translations, 'description')),
            ]);
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('translations');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->json('translations')->nullable()->after('slug');
        });

        foreach (DB::table('products')->select('id', 'name', 'description')->get() as $product) {
            $names = json_decode((string) $product->name, true) ?: [];
            $descriptions = json_decode((string) $product->description, true) ?: [];

            $translations = [];

            foreach (array_unique([...array_keys($names), ...array_keys($descriptions)]) as $locale) {
                $translations[$locale] = [
                    'name' => $names[$locale] ?? '',
                    'description' => $descriptions[$locale] ?? '',
                ];
            }

            DB::table('products')->where('id', $product->id)->update([
                'translations' => json_encode($translations),
            ]);
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['name', 'description']);
        });
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array<string, string>
     */
    private function pluck(array $translations, string $key): array
    {
        $values = [];

        foreach ($translations as $locale => $fields) {
            if (is_array($fields) && isset($fields[$key]) && is_string($fields[$key])) {
                $values[$locale] = $fields[$key];
            }
        }

        return $values;
    }
};
