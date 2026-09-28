<?php

use App\Actions\Fortify\CreateNewUser;
use App\Console\Commands\PruneAbandonedSignups;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\TenantRole;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

function abandonedSignup(int $daysOld = 15): User
{
    $user = User::factory()->unverified()->create(['created_at' => now()->subDays($daysOld)]);

    Tenant::factory()->withDomain()->withMember($user)->create();

    return $user;
}

it('deletes an abandoned signup with its tenant, domain, and membership', function (): void {
    $user = abandonedSignup();
    $tenant = $user->tenants()->sole();

    $this->artisan(PruneAbandonedSignups::class)->assertSuccessful();

    $this->assertModelMissing($user);
    $this->assertModelMissing($tenant);
    $this->assertDatabaseMissing('domains', ['tenant_id' => $tenant->id]);
    $this->assertDatabaseMissing('tenant_user', ['tenant_id' => $tenant->id]);
});

it('frees the subdomain of a pruned signup', function (): void {
    $tenant = abandonedSignup()->tenants()->sole();

    $this->artisan(PruneAbandonedSignups::class)->assertSuccessful();

    $user = app(CreateNewUser::class)->create([
        'name' => 'Richard Hendricks',
        'email' => 'richard@piedpiper.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'organization' => 'Pied Piper',
        'subdomain' => $tenant->slug,
    ]);

    expect($user->tenants()->sole()->slug)->toBe($tenant->slug);
});

it('keeps an unverified signup that is younger than the age limit', function (): void {
    $user = abandonedSignup(daysOld: 13);

    $this->artisan(PruneAbandonedSignups::class)->assertSuccessful();

    $this->assertModelExists($user);
});

it('reads the age limit from config', function (): void {
    config(['signups.prune_unverified_after_days' => 30]);
    $recent = abandonedSignup(daysOld: 20);
    $old = abandonedSignup(daysOld: 31);

    $this->artisan(PruneAbandonedSignups::class)->assertSuccessful();

    $this->assertModelExists($recent);
    $this->assertModelMissing($old);
});

it('keeps a verified user', function (): void {
    $user = User::factory()->create(['created_at' => now()->subDays(15)]);
    Tenant::factory()->withDomain()->withMember($user)->create();

    $this->artisan(PruneAbandonedSignups::class)->assertSuccessful();

    $this->assertModelExists($user);
});

it('keeps a user who verified once and then changed their email', function (): void {
    $user = User::factory()->pendingEmailChange()->create(['created_at' => now()->subDays(15)]);
    Tenant::factory()->withDomain()->withMember($user)->create();

    $this->artisan(PruneAbandonedSignups::class)->assertSuccessful();

    $this->assertModelExists($user);
});

it('keeps a signup whose tenant has another member', function (): void {
    $user = abandonedSignup();
    $user->tenants()->sole()->users()->attach(User::factory()->create(), ['role' => TenantRole::Member->value]);

    $this->artisan(PruneAbandonedSignups::class)->assertSuccessful();

    $this->assertModelExists($user);
});

it('keeps a signup whose tenant has an invitation', function (): void {
    $user = abandonedSignup();
    Invitation::factory()->revoked()->forTenant($user->tenants()->sole(), User::factory()->create())->create();

    $this->artisan(PruneAbandonedSignups::class)->assertSuccessful();

    $this->assertModelExists($user);
});

it('keeps an unverified user who belongs to more than one tenant', function (): void {
    $user = abandonedSignup();
    Tenant::factory()->withDomain()->withMember(User::factory()->create())->create()
        ->users()->attach($user, ['role' => TenantRole::Member->value]);

    $this->artisan(PruneAbandonedSignups::class)->assertSuccessful();

    $this->assertModelExists($user);
});

it('keeps an unverified user who is not the owner of their tenant', function (): void {
    $user = User::factory()->unverified()->create(['created_at' => now()->subDays(15)]);
    Tenant::factory()->withDomain()->withMember($user, TenantRole::Admin)->create();

    $this->artisan(PruneAbandonedSignups::class)->assertSuccessful();

    $this->assertModelExists($user);
});

it('schedules the prune command daily', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'signups:prune-abandoned'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *');
});
