<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\TenantRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $name = fake()->unique()->company(),
            'slug' => str($name)->slug()->toString(),
        ];
    }

    public function withMember(User $user, TenantRole $role = TenantRole::Owner): static
    {
        return $this->hasAttached($user, ['role' => $role->value]);
    }

    public function withDomain(?string $domain = null): static
    {
        return $this->afterCreating(function (Tenant $tenant) use ($domain): void {
            $tenant->domains()->create([
                'domain' => $domain ?? $tenant->slug,
            ]);
        });
    }
}
