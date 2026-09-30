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
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS invitations_one_open_per_tenant_email');
        }

        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropForeign(['invited_by']);
            $table->ulid('invited_by')->nullable()->change();
            $table->foreign('invited_by')->references('id')->on('users')->nullOnDelete();
        });

        $this->restoreSqliteOpenInvitationIndex();
    }

    /**
     * Reverse the migrations.
     *
     * Refuses to run while any invitation has no inviter, since the column can't
     * be made NOT NULL again until those rows are reassigned or deleted.
     */
    public function down(): void
    {

        if (DB::table('invitations')->whereNull('invited_by')->exists()) {
            throw new RuntimeException('Cannot restore NOT NULL on invitations.invited_by: some invitations have no inviter. Reassign or delete them first.');
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS invitations_one_open_per_tenant_email');
        }

        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropForeign(['invited_by']);
            $table->ulid('invited_by')->nullable(false)->change();
            $table->foreign('invited_by')->references('id')->on('users');
        });

        $this->restoreSqliteOpenInvitationIndex();
    }

    /**
     * SQLite rebuilds the table when changing foreign keys. Laravel recreates
     * its indexes without their WHERE clauses, so restore the partial index.
     */
    private function restoreSqliteOpenInvitationIndex(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX invitations_one_open_per_tenant_email ON invitations (tenant_id, email)
WHERE accepted_at IS NULL
AND revoked_at IS NULL
AND expired_at IS NULL'
        );
    }
};
