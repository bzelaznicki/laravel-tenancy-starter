<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `email_verified_at` is cleared when a user changes their email, so this column
     * keeps the proof that the user verified an address at least once.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('first_verified_at')->nullable();
        });

        DB::table('users')
            ->whereNotNull('email_verified_at')
            ->update(['first_verified_at' => DB::raw('email_verified_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('first_verified_at');
        });
    }
};
