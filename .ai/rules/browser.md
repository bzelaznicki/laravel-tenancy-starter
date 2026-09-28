---
paths:
  - 'tests/Browser/**'
---

# Browser

## Run browser tests in the supported environment
On Linux/CachyOS, run Pest browser tests directly on the host with `php artisan test tests/Browser/...`. On macOS 13, run them through Sail with `./vendor/bin/sail artisan test tests/Browser/...` because host Playwright cannot run there.
