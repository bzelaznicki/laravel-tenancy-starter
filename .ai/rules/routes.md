---
paths:
  - 'resources/js/routes/**'
---

# Routes

## Regenerate Wayfinder with --with-form
vite.config.ts configures `wayfinder({ formVariants: true })`, but the Artisan command does not read that config. Running plain `php artisan wayfinder:generate` silently strips every `.form` helper from resources/js/routes and resources/js/actions, which breaks `npm run types:check` across ~18 untouched files (login, profile, two-factor, dialogs...) with "Property 'form' does not exist".

Always run `php artisan wayfinder:generate --with-form`. If you see a sudden burst of `.form` type errors in files you never touched, this is the cause — regenerate, don't "fix" the call sites.
