---
paths:
  - app/Models/Tenant.php
---

# Models

## Store only the subdomain in domains.domain; build tenant URLs from it
`domains.domain` holds only the lowercase subdomain (`acme`), never the full host. The apex comes from `config('app.domain')`, so switching environments never touches data. `InitializeTenancyBySubdomain` looks tenants up by this column, not `tenants.slug`. Always build tenant hosts/URLs with `Tenant::host()` / `Tenant::origin()` (tests: `tenantHost()`/`tenantUrl()`), never from `slug`.
