<?php

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\TenantRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'invited_by' => User::factory(),
            'email' => Str::lower(fake()->unique()->safeEmail()),
            'role' => TenantRole::Member,
            'message' => null,
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'revoked_at' => null,
            'expired_at' => null,
        ];
    }

    public function forTenant(Tenant $tenant, User $inviter): static
    {
        return $this
            ->for($tenant)
            ->for($inviter, 'inviter');
    }

    public function withMessage(?string $message = null): static
    {
        return $this->state(fn (): array => [
            'message' => $message ?? fake()->sentence(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subMinute(),
            'expired_at' => now()->subMinute(),
            'accepted_at' => null,
            'revoked_at' => null,
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'revoked_at' => null,
            'accepted_at' => now(),
            'expired_at' => null,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'revoked_at' => now(),
            'accepted_at' => null,
            'expired_at' => null,
        ]);
    }
}
