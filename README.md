# Laravel Tenancy Starter

A starter kit for multi-tenant SaaS apps on Laravel. It's the official Laravel React starter kit with subdomain tenancy, tenant-aware authentication, memberships, roles and invitations already built and tested.

## What you get

- **Subdomain per tenant.** Each workspace lives at `{slug}.your-domain.com`. Laravel resolves the tenant from the subdomain, then checks the signed-in user's membership on every tenant request. A user who doesn't belong to the resolved tenant gets a `403`.
- **Single database, row-scoped.** Tenants share one PostgreSQL database. Tenant-owned models use `tenant_id` and stancl/tenancy's `BelongsToTenant` scope. This is not a database-per-tenant setup.
- **Per-subdomain login.** Sessions are not shared across subdomains. A central "find your workspace" page sends users to the right login.
- **Signup creates a workspace.** Registration creates the tenant, its domain and an owner membership in one step. Reserved platform subdomains (`www`, `api`, `admin`, and so on) are rejected, and abandoned unverified signups are pruned so their subdomains free up.
- **Memberships and roles.** `owner`, `admin`, `member` and `viewer`, with policies for who can invite, promote, demote and remove whom. The last owner of a workspace can't delete their account.
- **Invitations.** Email invitations with expiry, resend cooldown, revoke, and acceptance flows for new users, signed-in users and signed-out existing users.
- **Auth.** Laravel Fortify with email verification, two-factor authentication, passkeys and password confirmation. Email identity is case-insensitive.
- **Tests.** Pest feature tests for tenant isolation, auth and membership rules, plus Playwright-backed browser tests.

## Stack

- PHP 8.5 and Laravel 13
- Inertia 3, React 19 and TypeScript
- Tailwind CSS 4 and shadcn/ui components
- PostgreSQL
- Laravel Fortify and Laravel Passkeys
- `stancl/tenancy` for subdomain identification
- Laravel Wayfinder for typed frontend route functions
- Pest 5 and Playwright-backed browser tests

## Starting a new app

1. Create the project with the Laravel installer:

   ```bash
   laravel new myapp --using=https://github.com/bzelaznicki/laravel-tenancy-starter --database=pgsql
   ```

   Pass the full GitHub URL. The short `--using=bzelaznicki/laravel-tenancy-starter` form needs the package on Packagist, and it isn't published there. Keep `--database=pgsql`. The starter is built and tested on PostgreSQL, and without the flag the installer switches `.env` to SQLite.

   You can also click **Use this template** on GitHub, or clone the repository.
2. Rename the app:
   - `APP_NAME`, `APP_URL`, `APP_DOMAIN` and `DB_DATABASE` in `.env.example` (and `.env` if the installer created one)
   - the defaults in `config/app.php`
   - `name` in `herd.yml` and `composer.json`
   - the database name and domain in `.github/workflows/tests.yml`
3. Adjust the roles in `app/TenantRole.php` and `resources/js/types/tenant.ts` if `member` doesn't fit your product.
4. Replace the project context at the bottom of `AGENTS.md` and `CLAUDE.md` with your product's.

## Local setup

You'll need PHP 8.5, Composer, Node.js, npm and PostgreSQL. The setup script also installs Chromium for the browser test suite.

1. Create an empty PostgreSQL database named `tenancy_starter`.
2. Run the setup script:

   ```bash
   composer setup
   ```

3. Check `.env` and update the `DB_*` values if your PostgreSQL credentials differ from the defaults.
4. Configure wildcard DNS for `*.tenancy-starter.test` as described below.
5. Start Vite and the Laravel development processes:

   ```bash
   composer dev
   ```

The central app uses `https://tenancy-starter.test` by default. Tenant pages use hosts such as `https://acme.tenancy-starter.test`.

### Wildcard local domains

A normal `/etc/hosts` entry can't resolve every tenant subdomain. Local development needs wildcard resolution for `*.tenancy-starter.test`.

On macOS, use Laravel Herd. The repository includes [herd.yml](herd.yml), and Herd provides wildcard `.test` routing and local TLS.

Herd isn't available on Linux. Use Valet Linux Plus, or configure dnsmasq and Caddy for `*.tenancy-starter.test`. You can also use an `sslip.io` or `nip.io` domain for an HTTP-only setup. If you change the local domain, update both `APP_URL` and `APP_DOMAIN` in `.env`.

Don't add tenant names to `TENANCY_RESERVED_SUBDOMAINS`. That setting is for platform hosts such as `www`, `api`, `admin` and `staging` that must never become tenant slugs. If you run a staging or preview host on the production apex, add it there.

## Common commands

```bash
# Run PHP and frontend development processes
composer dev

# Run the PHP test suite, Pint, and PHPStan
composer test

# Run the browser suite against a production frontend build
composer test:browser

# Run frontend linting, formatting checks, type checks, and PHP checks
composer ci:check

# Build frontend assets
npm run build
```

## Project layout

Laravel owns the routes and data loading. Inertia maps controller responses to React pages, so there's no client-side file router.

```text
app/                    Laravel application code
database/               Migrations, factories, and seeders
resources/js/pages/     Inertia page components
resources/js/components Shared React and shadcn/ui components
routes/web.php          Central-domain routes
routes/tenant.php       Tenant-subdomain routes
tests/Feature/          Application and HTTP tests
tests/Browser/          Playwright-backed browser tests
```

Use named Laravel routes in backend code and Wayfinder imports from `@/actions` or `@/routes` in frontend code. Hardcoded URLs break as soon as tenant subdomains are involved. Build tenant URLs with `Tenant::host()` / `Tenant::origin()`, never from `slug`.

## Working on the project

Read [AGENTS.md](AGENTS.md) before making changes. It records the architecture decisions and conventions. More specific rules live under `.ai/rules`.
