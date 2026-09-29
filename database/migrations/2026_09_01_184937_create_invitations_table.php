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
        Schema::create('invitations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('invited_by')->constrained('users');
            $table->string('email');
            $table->string('role');
            $table->text('message')->nullable();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'email']);

            if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $table->unsignedTinyInteger('open_slot')->nullable()->virtualAs(
                    'CASE WHEN accepted_at IS NULL AND revoked_at IS NULL AND expired_at IS NULL THEN 1 ELSE NULL END'
                );
                $table->unique(['tenant_id', 'email', 'open_slot'], 'invitations_one_open_per_tenant_email');
            }
        });

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX invitations_one_open_per_tenant_email ON invitations (tenant_id, email)
WHERE accepted_at IS NULL
AND revoked_at IS NULL
AND expired_at IS NULL'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
