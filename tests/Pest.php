<?php

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Uri;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Api\Webpage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

/*
| The browser plugin pins the URL generator to its own http://127.0.0.1:port
| origin. A page served on a tenant host would then load its scripts from
| 127.0.0.1, which the browser blocks as cross-origin, leaving a blank page.
| Unpin on every in-process request so URLs follow the host the browser
| actually requested, as they would on a real server.
*/
pest()->in('Browser')->beforeEach(function (): void {
    app()->rebinding('request', function (): void {
        app('url')->useOrigin(null);
        app('url')->useAssetOrigin(null);
    });
});

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/
function tenantHost(Tenant $tenant): string
{
    return $tenant->host();
}

function tenantUrl(Tenant $tenant, string $path = '/'): string
{
    return sprintf(
        'https://%s/%s',
        tenantHost($tenant),
        ltrim($path, '/'),
    );
}

/**
 * Build a URL on the given host that still reaches the in-process browser
 * test server, taking its scheme and port from the page's current URL.
 *
 * Browser tests reach tenant hosts only through URLs like this. The server
 * passes the browser's own Host header through to Laravel, so tenancy and URL
 * generation follow whichever host each request really targets, including
 * cross-host redirects. Don't pin a host with `pest()->browser()->withHost()`
 * or override the URL generator with `url()->useOrigin()`: both set global
 * state that goes stale the moment the browser changes host.
 *
 * @param  array<string, mixed>  $query
 */
function browserUrl(
    PendingAwaitablePage|AwaitableWebpage|Webpage $page,
    string $host,
    string $path = '/',
    array $query = [],
): string {
    return (string) Uri::of($page->url())
        ->withHost($host)
        ->withPath($path)
        ->replaceQuery($query);
}

function tenantRoute(
    Tenant $tenant,
    string $route,
    array $parameters = [],
): string {
    return tenant_route(
        tenantHost($tenant),
        $route,
        $parameters,
    );
}
