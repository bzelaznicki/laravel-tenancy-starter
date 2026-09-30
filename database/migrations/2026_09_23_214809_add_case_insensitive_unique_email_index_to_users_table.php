<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $collisions = DB::table('users')
            ->selectRaw('LOWER(email) AS value')
            ->groupByRaw('LOWER(email)')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('value')
            ->pluck('value');

        if ($collisions->isNotEmpty()) {
            throw new RuntimeException(
                "Cannot lowercase users.email; these values collide: {$collisions->implode(', ')}",
            );
        }

        DB::statement('UPDATE users SET email = LOWER(email)');

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('email_lower')->virtualAs('LOWER(email)');
                $table->unique('email_lower', 'users_email_lower_unique');
            });

            return;
        }

        DB::statement('CREATE UNIQUE INDEX users_email_lower_unique ON users (LOWER(email))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_email_lower_unique');

            if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->dropColumn('email_lower');
            }
        });
    }
};
