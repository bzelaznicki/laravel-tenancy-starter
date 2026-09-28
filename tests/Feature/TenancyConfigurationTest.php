<?php

it('reserves platform subdomains', function (): void {
    expect(config('tenancy.reserved_subdomains'))
        ->toContain(
            'www',
            'api',
            'app',
            'admin',
            'mail',
            'staging',
        );
});
