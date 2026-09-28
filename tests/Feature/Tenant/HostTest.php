<?php

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantInvitation;
use App\TenantRole;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Stancl\Tenancy\Events\CreatingDomain;

function lowercaseTenantsMigration(): object
{
    return require database_path('migrations/2026_09_27_092952_lowercase_tenant_slugs_and_domains.php');
}

/**
 * The Domain model lowercases on save, so mixed-case rows can only come from raw inserts.
 */
function insertRawDomain(Tenant $tenant, string $domain): void
{
    DB::table('domains')->insert([
        'domain' => $domain,
        'tenant_id' => $tenant->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('builds the tenant host from the domain record instead of the slug', function (): void {
    $tenant = Tenant::factory()->withDomain('hooli')->create(['slug' => 'piedpiper']);

    expect($tenant->host())->toBe('hooli.'.config('app.domain'))
        ->and($tenant->origin())->toBe('https://hooli.'.config('app.domain'));
});

it('takes the tenant origin scheme from the app url', function (): void {
    config(['app.url' => 'http://'.config('app.domain')]);

    $tenant = Tenant::factory()->withDomain('hooli')->create();

    expect($tenant->origin())->toBe('http://hooli.'.config('app.domain'));
});

it('redirects a new signup to the tenant domain when it differs from the slug', function (): void {
    Event::listen(CreatingDomain::class, function (CreatingDomain $event): void {
        $event->domain->domain = 'hooli';
    });

    $this->post(route('register.store'), [
        'name' => 'Richard Hendricks',
        'email' => 'richard@piedpiper.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'organization' => 'Pied Piper',
        'subdomain' => 'piedpiper',
    ])->assertRedirect('https://hooli.'.config('app.domain').'/login');

    expect(Tenant::query()->sole()->slug)->toBe('piedpiper');
});

it('sends the verification link to the tenant domain when it differs from the slug', function (): void {
    $this->skipUnlessFortifyHas(Features::emailVerification());

    Notification::fake();

    $user = User::factory()->unverified()->create();
    Tenant::factory()->withDomain('hooli')->withMember($user)->create(['slug' => 'piedpiper']);

    $user->notify(new VerifyEmail);

    $url = Notification::sent($user, VerifyEmail::class)->sole()->toMail($user)->actionUrl;

    expect(parse_url($url, PHP_URL_HOST))->toBe('hooli.'.config('app.domain'));
});

it('sends the invitation link to the tenant domain when it differs from the slug', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain('hooli')
        ->withMember($owner, TenantRole::Owner)
        ->create(['slug' => 'piedpiper']);

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.store'), [
            'email' => 'dinesh@piedpiper.com',
            'role' => TenantRole::Member->value,
        ])
        ->assertSessionHasNoErrors();

    $invitation = Invitation::query()->sole();

    Notification::assertSentOnDemand(
        TenantInvitation::class,
        function (TenantInvitation $notification, array $channels, object $notifiable) use ($invitation): bool {
            $mail = $notification->toMail($notifiable);

            expect(parse_url($mail->actionUrl, PHP_URL_HOST))->toBe('hooli.'.config('app.domain'))
                ->and(parse_url($mail->actionUrl, PHP_URL_PATH))->toBe('/invitations/'.$invitation->id.'/accept')
                ->and($mail->viewData['tenantHost'])->toBe('hooli.'.config('app.domain'));

            return true;
        },
    );
});

it('lowercases existing tenant slugs and domains', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'AcmeCorp']);
    insertRawDomain($tenant, 'AcmeCorp');

    lowercaseTenantsMigration()->up();

    expect($tenant->fresh()->slug)->toBe('acmecorp')
        ->and($tenant->domains()->sole()->domain)->toBe('acmecorp');
});

it('refuses to lowercase slugs that would collide', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'AcmeCorp']);
    insertRawDomain($tenant, 'AcmeCorp');
    Tenant::factory()->withDomain('other')->create(['slug' => 'acmecorp']);

    expect(fn () => lowercaseTenantsMigration()->up())
        ->toThrow(RuntimeException::class, 'Cannot lowercase tenants.slug');

    expect($tenant->fresh()->slug)->toBe('AcmeCorp')
        ->and($tenant->domains()->sole()->domain)->toBe('AcmeCorp');
});

it('refuses to lowercase domains that would collide', function (): void {
    insertRawDomain(Tenant::factory()->create(['slug' => 'first']), 'AcmeCorp');
    Tenant::factory()->withDomain('acmecorp')->create(['slug' => 'second']);

    expect(fn () => lowercaseTenantsMigration()->up())
        ->toThrow(RuntimeException::class, 'Cannot lowercase domains.domain');

    expect(DB::table('domains')->where('domain', 'AcmeCorp')->exists())->toBeTrue();
});
