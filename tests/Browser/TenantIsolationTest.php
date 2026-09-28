<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

it('serves each tenant workspace on its own host', function (): void {
    $user = User::factory()->create();
    $tenantA = Tenant::factory()->withDomain()->withMember($user)->create([
        'name' => 'Hooli',
        'slug' => 'tenant-a',
    ]);
    $tenantB = Tenant::factory()->withDomain()->withMember($user)->create([
        'name' => 'Pied Piper',
        'slug' => 'tenant-b',
    ]);

    $this->actingAs($user);

    $page = visit('/');

    $page->navigate(browserUrl($page, tenantHost($tenantA), '/dashboard'))
        ->assertHostIs(tenantHost($tenantA))
        ->assertSee('Set up Hooli')
        ->assertNoJavaScriptErrors();

    $page->navigate(browserUrl($page, tenantHost($tenantB), '/dashboard'))
        ->assertHostIs(tenantHost($tenantB))
        ->assertSee('Set up Pied Piper')
        ->assertDontSee('Hooli')
        ->assertNoJavaScriptErrors();
});

it('does not carry over authentication between tenants', function (): void {
    Route::get('/test/session-cookie', fn (Request $request): JsonResponse => response()->json([
        'hasSessionCookie' => $request->cookies->has(config('session.cookie')),
    ]));

    $user = User::factory()->create();
    $tenantA = Tenant::factory()->withDomain()->withMember($user)->create(['slug' => 'tenant-a']);
    $tenantAHost = tenantHost($tenantA);

    $tenantB = Tenant::factory()->withDomain()->withMember($user)->create(['slug' => 'tenant-b']);
    $tenantBHost = tenantHost($tenantB);

    $page = visit('/');

    $page->navigate(browserUrl($page, $tenantAHost, '/login'))
        ->assertHostIs($tenantAHost)
        ->assertSee('Log in');

    $page
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->click('@login-button')
        ->assertPathIs('/dashboard')
        ->assertSee('Secure your account');

    $page->navigate(browserUrl($page, $tenantAHost, '/test/session-cookie'))
        ->assertHostIs($tenantAHost)
        ->assertSee('"hasSessionCookie":true');

    $page->navigate(browserUrl($page, $tenantBHost, '/test/session-cookie'))
        ->assertHostIs($tenantBHost)
        ->assertSee('"hasSessionCookie":false');

    // The app runs inside the test process, so the guard still holds the
    // user tenant A authenticated. Forget it so tenant B's request resolves
    // its user from its own (absent) session cookie, as a real server would.
    auth()->logout();
    app('auth')->forgetGuards();
    app('session')->forgetDrivers();

    $page->navigate(browserUrl($page, $tenantBHost, '/dashboard'))
        ->assertHostIs($tenantBHost)
        ->assertPathIs('/login')
        ->assertSee('Log in');
});
