<?php

use App\Models\Tenant;
use App\Models\User;
use App\TenantRole;
use Illuminate\Database\QueryException;

use function Pest\Laravel\assertDatabaseMissing;

test('membership round trips both directions', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withMember($user)->create();

    expect($tenant->users)->toHaveCount(1);
    expect($tenant->users->contains($user))->toBeTrue();

    expect($user->fresh()->tenants->contains($tenant))->toBeTrue();

});

it('returns TenantRole enum from the pivot role', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withMember($user, TenantRole::Admin)->create();

    expect($user->fresh()->roleFor($tenant))->toBe(TenantRole::Admin);
});

it('returns null for an user with no membership', function (): void {

    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();

    expect($user->roleFor($tenant))->toBeNull();
});

it('returns true if the owner user is at least an admin', function (): void {

    $user = User::factory()->create();
    $tenant = Tenant::factory()->withMember($user)->create();

    expect($user->fresh()->roleFor($tenant)->atLeast(TenantRole::Admin))->toBeTrue();
});

it('returns false if a member is at least an admin', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withMember($user, TenantRole::Member)->create();

    expect($user->fresh()->roleFor($tenant)->atLeast(TenantRole::Admin))->toBeFalse();
});

it('throws when trying to add the same user to the same tenant twice', function (): void {

    $user = User::factory()->create();
    $tenant = Tenant::factory()->withMember($user)->create();

    $tenant->users()->attach($user, ['role' => TenantRole::Admin->value]);
})->throws(QueryException::class);

it('cascades the tenant_user relationship when the tenant is deleted', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withMember($user)->create();

    $tenant->delete();

    assertDatabaseMissing(
        'tenant_user',
        [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
        ]
    );

});
