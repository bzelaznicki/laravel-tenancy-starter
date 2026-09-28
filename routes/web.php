<?php

use App\Http\Controllers\RedirectToTenantController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\RegisteredUserController;

Route::domain(config('app.domain'))->group(function (): void {
    Route::inertia('/', 'welcome')->name('home');

    Route::inertia('/login', 'tenant/find')->name('tenant.find');

    Route::post('/tenants/find', RedirectToTenantController::class)
        ->name('tenant.redirect');

    Route::middleware('guest')->group(function (): void {
        Route::get('/register', [RegisteredUserController::class, 'create'])
            ->name('register');

        Route::post('/register', [RegisteredUserController::class, 'store'])
            ->name('register.store');
    });
});
Route::middleware(['auth', 'verified'])->group(function (): void {});
