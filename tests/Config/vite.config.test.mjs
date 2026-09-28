import assert from 'node:assert/strict';
import { mkdir, mkdtemp, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import process from 'node:process';
import test from 'node:test';

import viteConfig from '../../vite.config.ts';

const configEnvironment = (command) => ({
    command,
    isPreview: false,
    isSsrBuild: false,
    mode: command === 'serve' ? 'development' : 'production',
});

const laravelPlugin = (config) => config.plugins.flat().find((plugin) => plugin.name === 'laravel');

test('Vite configuration', async (suite) => {
    const originalAppUrl = process.env.APP_URL;
    const originalHome = process.env.HOME;
    const originalWorkingDirectory = process.cwd();
    const temporaryDirectory = await mkdtemp(path.join(tmpdir(), 'tenancy-starter-vite-config-'));

    process.chdir(temporaryDirectory);

    suite.after(async () => {
        process.chdir(originalWorkingDirectory);

        if (originalAppUrl === undefined) {
            delete process.env.APP_URL;
        } else {
            process.env.APP_URL = originalAppUrl;
        }

        if (originalHome === undefined) {
            delete process.env.HOME;
        } else {
            process.env.HOME = originalHome;
        }

        await rm(temporaryDirectory, { force: true, recursive: true });
    });

    await suite.test('serve mode rejects a missing APP_URL', () => {
        delete process.env.APP_URL;

        assert.throws(
            () => viteConfig(configEnvironment('serve')),
            /APP_URL must be set when running the Vite development server/,
        );
    });

    await suite.test('serve mode configures the APP_URL host and Valet TLS certificate', async () => {
        const appHost = 'acme.test';
        const certificateDirectory = path.join(temporaryDirectory, '.valet', 'Certificates');
        const certificatePath = path.join(certificateDirectory, `${appHost}.crt`);
        const keyPath = path.join(certificateDirectory, `${appHost}.key`);

        await mkdir(certificateDirectory, { recursive: true });
        await writeFile(certificatePath, 'certificate');
        await writeFile(keyPath, 'key');

        process.env.APP_URL = `https://${appHost}`;
        process.env.HOME = temporaryDirectory;

        const config = viteConfig(configEnvironment('serve'));
        const resolvedPluginConfig = laravelPlugin(config).config(config, configEnvironment('serve'));

        assert.deepEqual(config.server, {
            host: '127.0.0.1',
            hmr: { host: appHost },
        });
        assert.equal(resolvedPluginConfig.server.host, '127.0.0.1');
        assert.equal(resolvedPluginConfig.server.hmr.host, appHost);
        assert.equal(resolvedPluginConfig.server.https.cert, certificatePath);
        assert.equal(resolvedPluginConfig.server.https.key, keyPath);
    });

    await suite.test('serve mode configures a tenant host and Herd TLS certificate', async () => {
        const appHost = 'tenant.acme.test';
        const herdDirectory =
            process.platform === 'darwin'
                ? path.join(temporaryDirectory, 'Library', 'Application Support', 'Herd', 'config', 'valet')
                : path.join(temporaryDirectory, '.config', 'herd', 'config', 'valet');
        const certificateDirectory = path.join(herdDirectory, 'Certificates');
        const certificatePath = path.join(certificateDirectory, `${appHost}.crt`);
        const keyPath = path.join(certificateDirectory, `${appHost}.key`);

        await mkdir(certificateDirectory, { recursive: true });
        await writeFile(certificatePath, 'certificate');
        await writeFile(keyPath, 'key');

        process.env.APP_URL = `https://${appHost}`;
        process.env.HOME = temporaryDirectory;

        const config = viteConfig(configEnvironment('serve'));
        const resolvedPluginConfig = laravelPlugin(config).config(config, configEnvironment('serve'));

        assert.deepEqual(config.server, {
            host: '127.0.0.1',
            hmr: { host: appHost },
        });
        assert.equal(resolvedPluginConfig.server.host, '127.0.0.1');
        assert.equal(resolvedPluginConfig.server.hmr.host, appHost);
        assert.equal(resolvedPluginConfig.server.https.cert, certificatePath);
        assert.equal(resolvedPluginConfig.server.https.key, keyPath);
    });

    await suite.test('build mode succeeds without APP_URL', () => {
        delete process.env.APP_URL;

        const config = viteConfig(configEnvironment('build'));
        const resolvedPluginConfig = laravelPlugin(config).config(config, configEnvironment('build'));

        assert.equal(config.server, undefined);
        assert.equal(resolvedPluginConfig.server.https, undefined);
    });
});
