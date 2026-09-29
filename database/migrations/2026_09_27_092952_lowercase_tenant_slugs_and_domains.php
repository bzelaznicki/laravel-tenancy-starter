<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Lowercase tenant slugs and domains so they match the lowercase hosts browsers send.
     * Fails before changing anything if lowercasing would collide with an existing value.
     */
    public function up(): void
    {
        foreach (['tenants' => 'slug', 'domains' => 'domain'] as $table => $column) {
            $collisions = DB::table($table)
                ->selectRaw("LOWER({$column}) AS value")
                ->groupByRaw("LOWER({$column})")
                ->havingRaw('COUNT(*) > 1')
                ->pluck('value');

            if ($collisions->isNotEmpty()) {
                throw new RuntimeException(
                    "Cannot lowercase {$table}.{$column}; these values collide: {$collisions->implode(', ')}",
                );
            }
        }

        DB::statement('UPDATE tenants SET slug = LOWER(slug)');
        DB::statement('UPDATE domains SET domain = LOWER(domain)');
    }

    /**
     * Reverse the migrations. The original casing isn't kept, so there is nothing to restore.
     */
    public function down(): void {}
};
