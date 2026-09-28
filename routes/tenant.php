<?php

declare(strict_types=1);

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\TenantHomeController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'web',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
    'auth',
    'tenant.member',
])->group(function (): void {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware([
    'web',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
    'auth',
    'verified',
    'tenant.member',
])->group(function (): void {
    Route::get('/settings/members', [MembershipController::class, 'index'])->name('memberships.index');

    Route::patch('/settings/members/{membership}', [MembershipController::class, 'update'])
        ->name('memberships.update');
    Route::delete('/settings/members/{membership}', [MembershipController::class, 'destroy'])->name('memberships.destroy');

    Route::post('/settings/members/invitations', [InvitationController::class, 'store'])->name('invitations.store');

    Route::post('/settings/members/invitations/{invitation}/resend', [InvitationController::class, 'resend'])
        ->name('invitations.resend');

    Route::delete('/settings/members/invitations/{invitation}', [InvitationController::class, 'destroy'])
        ->name('invitations.destroy');

    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::middleware([
    'web',
    InitializeTenancyBySubdomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function (): void {
    Route::get('/', TenantHomeController::class)
        ->name('tenant.home');

    Route::get('/invitations/{invitation}/accept', [
        InvitationController::class,
        'show',
    ])->name('invitations.accept');

    Route::post('/invitations/{invitation}/accept', [
        InvitationController::class,
        'accept',
    ])->name('invitations.accept.store');

    Route::get('.well-known/passkey-endpoints', function (): JsonResponse {
        abort_unless(Features::canManagePasskeys(), 404);

        return response()->json([
            'enroll' => route('security.edit'),
            'manage' => route('security.edit'),
        ]);
    })->name('well-known.passkeys');
});
