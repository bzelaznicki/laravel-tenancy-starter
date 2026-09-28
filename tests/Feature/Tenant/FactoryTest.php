<?php

use App\Models\Tenant;

it('creates a domain using the tenant slug', function (): void {
    $tenant = Tenant::factory()->withDomain()->create();

    expect($tenant->domains)
        ->toHaveCount(1)
        ->first()->domain->toBe($tenant->slug)
        ->and(tenantHost($tenant))->toBe($tenant->slug.'.'.config('app.domain'));
});

it('allows a domain override', function (): void {
    $tenant = Tenant::factory()->withDomain('custom')->create();

    expect($tenant->domains)
        ->toHaveCount(1)
        ->first()->domain->toBe('custom')
        ->and(tenantHost($tenant))->toBe('custom.'.config('app.domain'));
});
