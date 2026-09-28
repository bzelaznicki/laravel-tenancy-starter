<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\Notification;

it('registers on the apex and lands on the edited tenant login page', function (): void {
    Notification::fake();

    $organization = 'Pied Piper Registration Test';
    $suggestedSlug = 'pied-piper-registration-test';
    $editedSlug = 'registration-test';
    $editedHost = $editedSlug.'.'.config('app.domain');
    $email = 'gilfoyle@piedpiper.com';

    $page = visit('/');

    $page->navigate(browserUrl($page, config('app.domain'), '/register'));

    $page
        ->fill('#organization', $organization)
        ->assertValue('#subdomain', $suggestedSlug)
        ->fill('#subdomain', $editedSlug)
        ->assertValue('#subdomain', $editedSlug)
        ->fill('name', 'Bertram Gilfoyle')
        ->fill('email', $email)
        ->fill('password', 'password')
        ->fill('password_confirmation', 'password');

    $page
        ->click('@register-user-button')
        ->assertHostIs($editedHost)
        ->assertPathIs('/login')
        ->assertSee('Log in')
        ->assertNoJavaScriptErrors();

    $tenant = Tenant::query()
        ->where('slug', $editedSlug)
        ->sole();

    expect($tenant->name)->toBe($organization);
});
