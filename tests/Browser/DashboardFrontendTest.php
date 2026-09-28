<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The real dashboard is a tenant route behind subdomain resolution + the
 * membership check (covered by tests/Feature/DashboardTest.php). This browser
 * spec only exercises the migrated React page, so it renders the Inertia
 * component via a throwaway web route with fixture props — matching the
 * AccountsFrontendTest convention and keeping the frontend smoke test
 * decoupled from tenancy.
 */
it('checks if logged in user can see the dashboard without JavaScript errors', function (): void {
    Route::middleware('web')->get('/test/dashboard', fn (): Response => Inertia::render('dashboard'));

    $this->actingAs(User::factory()->create());

    visit('/test/dashboard')
        ->assertSee('Secure your account')
        ->assertNoJavaScriptErrors();
});
