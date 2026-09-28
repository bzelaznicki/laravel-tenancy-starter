<?php

/**
 * Re-evaluates config/inertia.php under a fully controlled environment so the
 * SSR settings are tested as a pure function of the env, independent of whatever
 * .env the runner loaded (locally `.env.testing`, on CI a copy of `.env.example`).
 *
 * @param  array<string, string>  $env  Vars to set; any of the SSR keys not listed are cleared.
 * @return array<string, mixed>
 */
function inertiaSsrConfigWith(array $env): array
{
    $keys = ['INERTIA_SSR_ENABLED', 'INERTIA_SSR_URL'];

    $original = [];
    foreach ($keys as $key) {
        $original[$key] = [
            'env' => $_ENV[$key] ?? null,
            'server' => $_SERVER[$key] ?? null,
            'getenv' => getenv($key),
        ];

        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }

    foreach ($env as $key => $value) {
        $_ENV[$key] = $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }

    try {
        return require base_path('config/inertia.php');
    } finally {
        foreach ($original as $key => $values) {
            $values['env'] === null ? $_ENV = array_diff_key($_ENV, [$key => null]) : $_ENV[$key] = $values['env'];
            $values['server'] === null ? $_SERVER = array_diff_key($_SERVER, [$key => null]) : $_SERVER[$key] = $values['server'];
            $values['getenv'] === false ? putenv($key) : putenv("{$key}={$values['getenv']}");
        }
    }
}

it('defaults ssr to enabled on the local worker url', function (): void {
    $config = inertiaSsrConfigWith([]);

    expect($config['ssr']['enabled'])->toBeTrue()
        ->and($config['ssr']['url'])->toBe('http://127.0.0.1:13714');
});

it('resolves ssr settings from the environment', function (): void {
    $config = inertiaSsrConfigWith([
        'INERTIA_SSR_ENABLED' => 'false',
        'INERTIA_SSR_URL' => 'http://ssr.internal:9000',
    ]);

    expect($config['ssr']['enabled'])->toBeFalse()
        ->and($config['ssr']['url'])->toBe('http://ssr.internal:9000');
});
