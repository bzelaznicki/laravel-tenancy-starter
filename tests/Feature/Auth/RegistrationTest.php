<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\Tenant;
use App\Models\User;
use App\TenantRole;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Events\CreatingDomain;

test('registration screen can be rendered', function (): void {
    $response = $this->get(route('register'));

    $response->assertOk();
});

it('creates the user with the tenant', function (): void {
    $email = 'richard@piedpiper.com';
    $response = $this->post(route('register.store'), [
        'name' => 'Richard Hendricks',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
        'organization' => 'Pied Piper',
        'subdomain' => 'piedpiper',
    ]);
    $response->assertRedirect(
        'https://piedpiper.'.config('app.domain').'/login',
    );

    $this->assertGuest();
    $this->assertDatabaseHas('users', [
        'email' => $email,
    ]);

    $this->assertDatabaseHas('tenants', [
        'name' => 'Pied Piper',
    ]);

    $this->assertDatabaseHas('domains', [
        'domain' => 'piedpiper',
    ]);

    $user = User::query()->where('email', $email)->sole();
    $tenant = Tenant::query()
        ->where('slug', 'piedpiper')
        ->sole();

    expect($tenant->name)->toBe('Pied Piper')
        ->and($tenant->domains()->sole()->domain)->toBe('piedpiper');

    expect($user->tenants()->sole()->is($tenant))->toBeTrue()
        ->and($tenant->users()->sole()->is($user))->toBeTrue()
        ->and($user->roleFor($tenant))->toBe(TenantRole::Owner);
});

it('returns an external tenant location for Inertia registration', function (): void {
    $response = $this
        ->withHeader('X-Inertia', 'true')
        ->post(route('register.store'), [
            'name' => 'Dinesh Chugtai',
            'email' => 'dinesh@piedpiper.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization' => 'Pied Piper',
            'subdomain' => 'piedpiper',
        ]);

    $response
        ->assertStatus(409)
        ->assertHeader(
            'X-Inertia-Location',
            'https://piedpiper.'.config('app.domain').'/login',
        );

    $this->assertGuest();
});

it('sends the initial verification email with a tenant URL', function (): void {
    Notification::fake();

    $this
        ->post(route('register.store'), [
            'name' => 'Bertram Gilfoyle',
            'email' => 'gilfoyle@piedpiper.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'organization' => 'Pied Piper',
            'subdomain' => 'piedpiper',
        ]);

    $user = User::query()->where('email', 'gilfoyle@piedpiper.com')->sole();
    $tenant = Tenant::query()->where('slug', 'piedpiper')->sole();
    $tenantHost = tenantHost($tenant);

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user, $tenantHost): bool {
        $url = $notification->toMail($user)->actionUrl;

        expect(parse_url($url, PHP_URL_HOST))->toBe($tenantHost);

        return true;
    });
});

it('normalizes the submitted subdomain', function (): void {
    $this->post(route('register.store'), [
        'name' => 'Jared Dunn',
        'email' => 'jared@piedpiper.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'organization' => 'Pied Piper',
        'subdomain' => '       PiEdPIPeR       ',
    ]);

    $tenant = Tenant::query()->where('slug', 'piedpiper')->sole();

    expect($tenant->slug)->toBe('piedpiper');
});

it('rejects invalid registration data', function (array $invalidData, string $invalidField): void {
    $payload = [
        'name' => 'Nelson Bighetti',
        'email' => 'bighead@piedpiper.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'organization' => 'Pied Piper',
        'subdomain' => 'piedpiper',
    ];

    $response = $this->post(
        route('register.store'),
        array_replace($payload, $invalidData),
    );

    $response->assertInvalid([$invalidField]);

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('tenants', 0);
    $this->assertDatabaseCount('domains', 0);
    $this->assertDatabaseCount('tenant_user', 0);
})->with(
    [
        'missing organization' => [
            ['organization' => null],
            'organization',
        ],
        'missing subdomain' => [
            ['subdomain' => null],
            'subdomain',
        ],
        'non-string subdomain' => [
            ['subdomain' => ['piedpiper']],
            'subdomain',
        ],
        'reserved subdomain' => [
            ['subdomain' => 'admin'],
            'subdomain',
        ],
        'subdomain containing an underscore' => [
            ['subdomain' => 'pied_piper'],
            'subdomain',
        ],
        'subdomain beginning with a hyphen' => [
            ['subdomain' => '-piedpiper'],
            'subdomain',
        ],
        'subdomain ending with a hyphen' => [
            ['subdomain' => 'piedpiper-'],
            'subdomain',
        ],
        'subdomain longer than 63 characters' => [
            ['subdomain' => str_repeat('a', 64)],
            'subdomain',
        ],
    ],

);

it('rejects an existing tenant subdomain', function (): void {
    Tenant::factory()->create([
        'slug' => 'piedpiper',
    ]);
    $response = $this->post(route('register.store'), [
        'name' => 'Erlich Bachman',
        'email' => 'erlich@piedpiper.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'organization' => 'Pied Piper',
        'subdomain' => 'PIEDPIPER',
    ]);

    $response->assertInvalid(['subdomain']);

    $this->assertDatabaseMissing('users', [
        'email' => 'erlich@piedpiper.com',
    ]);
});

it('rejects an existing domain', function (): void {
    Tenant::factory()->withDomain('piedpiper')->create(['slug' => 'hooli']);
    $response = $this->post(route('register.store'), [
        'name' => 'Monica Hall',
        'email' => 'monica@piedpiper.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'organization' => 'Pied Piper',
        'subdomain' => 'piedpiper',
    ]);

    $response->assertInvalid(['subdomain']);

    $this->assertDatabaseMissing('users', [
        'email' => 'monica@piedpiper.com',
    ]);
});

it('rejects an existing email with login guidance', function (): void {
    User::factory()->create(['email' => 'monica@piedpiper.com']);

    $response = $this->post(route('register.store'), [
        'name' => 'Monica Hall',
        'email' => 'monica@piedpiper.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'organization' => 'Pied Piper',
        'subdomain' => 'piedpiper',
    ]);

    $response->assertInvalid([
        'email' => 'An account with this email already exists. Log in or accept your invitation instead.',
    ]);
    $this->assertGuest();
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('tenants', 0);
    $this->assertDatabaseCount('domains', 0);
    $this->assertDatabaseCount('tenant_user', 0);
});

it('rolls back the losing registration when subdomains collide concurrently', function (): void {
    if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
        $this->markTestSkipped('This test requires the PCNTL and sockets extensions.');
    }

    $connection = DB::connection();
    $connection->commit();

    $subdomain = 'piedpiper-concurrent';
    $emails = [
        'richard.concurrent@piedpiper.com',
        'dinesh.concurrent@piedpiper.com',
    ];
    $children = [];

    try {
        foreach ($emails as $index => $email) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

            if ($sockets === false) {
                throw new RuntimeException('Unable to create a socket pair.');
            }

            $processId = pcntl_fork();

            if ($processId === -1) {
                throw new RuntimeException('Unable to fork a registration process.');
            }

            if ($processId === 0) {
                fclose($sockets[0]);

                foreach ($children as $child) {
                    fclose($child['socket']);
                }

                runConcurrentRegistrationAttempt($sockets[1], [
                    'name' => $index === 0 ? 'Richard Hendricks' : 'Dinesh Chugtai',
                    'email' => $email,
                    'password' => 'password',
                    'password_confirmation' => 'password',
                    'organization' => $index === 0 ? 'Pied Piper' : 'Pied Piper Labs',
                    'subdomain' => $subdomain,
                ]);

                exit(1);
            }

            fclose($sockets[1]);
            stream_set_timeout($sockets[0], 10);

            $children[] = [
                'processId' => $processId,
                'socket' => $sockets[0],
            ];
        }

        foreach ($children as $child) {
            expect(trim((string) fgets($child['socket'])))->toBe('ready');
        }

        foreach ($children as $child) {
            fwrite($child['socket'], "go\n");
        }

        $results = [];

        foreach ($children as $child) {
            $results[] = json_decode(trim((string) fgets($child['socket'])), true, flags: JSON_THROW_ON_ERROR);
            fclose($child['socket']);
            pcntl_waitpid($child['processId'], $status);

            expect(pcntl_wexitstatus($status))->toBe(0);
        }

        expect(array_column($results, 'status'))
            ->toHaveCount(2)
            ->toContain('success')
            ->toContain('failure')
            ->and(collect($results)->firstWhere('status', 'failure')['exception'])
            ->toBe(UniqueConstraintViolationException::class);

        $winningUser = User::query()->whereIn('email', $emails)->sole();
        $tenant = Tenant::query()->where('slug', $subdomain)->sole();

        expect($winningUser->tenants()->sole()->is($tenant))->toBeTrue()
            ->and($tenant->users()->sole()->is($winningUser))->toBeTrue()
            ->and($tenant->domains()->sole()->domain)->toBe($subdomain);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('domains', 1);
        $this->assertDatabaseCount('tenant_user', 1);
    } finally {
        foreach ($children as $child) {
            if (is_resource($child['socket'])) {
                fclose($child['socket']);
            }

            pcntl_waitpid($child['processId'], $status, WNOHANG);
        }

        $tenantIds = DB::table('tenants')->where('slug', $subdomain)->pluck('id');
        $userIds = DB::table('users')->whereIn('email', $emails)->pluck('id');

        DB::table('tenant_user')->whereIn('tenant_id', $tenantIds)->delete();
        DB::table('domains')->whereIn('tenant_id', $tenantIds)->delete();
        DB::table('tenants')->whereIn('id', $tenantIds)->delete();
        DB::table('users')->whereIn('id', $userIds)->delete();

        $connection->beginTransaction();
    }
});

it('performs a rollback if registration fails', function (): void {
    Notification::fake();
    $this->withoutExceptionHandling();

    $failNextDomainCreation = true;

    Event::listen(CreatingDomain::class, function () use (&$failNextDomainCreation): void {
        if (! $failNextDomainCreation) {
            return;
        }

        $failNextDomainCreation = false;

        throw new RuntimeException('Forced domain failure.');
    });

    expect(fn () => $this->post(route('register.store'), [
        'name' => 'Nelson Bighetti',
        'email' => 'bighead@piedpiper.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'organization' => 'Pied Piper',
        'subdomain' => 'piedpiper',
    ]))->toThrow(RuntimeException::class);
    Notification::assertNothingSent();
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('tenants', 0);
    $this->assertDatabaseCount('domains', 0);
    $this->assertDatabaseCount('tenant_user', 0);
});

/**
 * @param  resource  $socket
 * @param  array<string, string>  $input
 */
function runConcurrentRegistrationAttempt($socket, array $input): never
{
    $exitCode = 0;

    try {
        DB::purge();

        DB::connection()->beforeStartingTransaction(function () use ($socket): void {
            fwrite($socket, "ready\n");

            if (trim((string) fgets($socket)) !== 'go') {
                throw new RuntimeException('The concurrent registration was not released.');
            }
        });

        try {
            app(CreateNewUser::class)->create($input);

            $result = ['status' => 'success'];
        } catch (Throwable $exception) {
            $result = [
                'status' => 'failure',
                'exception' => $exception::class,
            ];
        }

        fwrite($socket, json_encode($result, JSON_THROW_ON_ERROR)."\n");
    } catch (Throwable $exception) {
        $exitCode = 1;

        try {
            fwrite($socket, json_encode([
                'status' => 'failure',
                'exception' => $exception::class,
            ], JSON_THROW_ON_ERROR)."\n");
        } catch (Throwable) {
            // The child must still terminate if it cannot report its failure.
        }
    } finally {
        if (is_resource($socket)) {
            fclose($socket);
        }

        exit($exitCode);
    }
}

test('the create user action rejects a case variant of an existing user email when called directly', function (): void {
    User::factory()->create(['email' => 'monica@piedpiper.com']);

    expect(fn () => app(CreateNewUser::class)->create([
        'name' => 'Monica Hall',
        'email' => ' Monica@PiedPiper.com ',
        'password' => 'password',
        'password_confirmation' => 'password',
        'organization' => 'Pied Piper',
        'subdomain' => 'piedpiper',
    ]))->toThrow(ValidationException::class, 'An account with this email already exists.');

    $this->assertDatabaseCount('users', 1);
});

test('registration rejects a case variant of an existing user email', function (): void {
    User::factory()->create(['email' => 'monica@piedpiper.com']);

    $response = $this->post(route('register.store'), [
        'name' => 'Monica Hall',
        'email' => 'Monica@PiedPiper.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'organization' => 'Pied Piper',
        'subdomain' => 'piedpiper',
    ]);

    $response->assertInvalid([
        'email' => 'An account with this email already exists. Log in or accept your invitation instead.',
    ]);

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('tenants', 0);
});
