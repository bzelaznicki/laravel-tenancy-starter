---
paths:
  - 'database/migrations/**'
---

# Migrations

## Domain models use ULID primary keys; infra tables stay bigint
Project-wide ID shape: all DOMAIN models (users, tenants, and everything tenant-scoped) use ULID primary keys — `HasUlids` trait on the model, `ulid('id')->primary()` and `foreignUlid()` in migrations. Rationale: non-guessable IDs for a multi-tenant SaaS whose IDs appear in URLs/APIs (defense in depth, not a replacement for per-request membership checks), ULID keeps index/write locality (time-ordered) unlike UUIDv4, and ULIDs are string keys so they satisfy stancl/tenancy's default string tenant-key contract with no model-config override. Do NOT mix bigint and ULID for domain FKs. Pure framework/infra tables (jobs, cache, sessions, password_reset_tokens) stay bigint/default — they never leave the server. This reverses an earlier bigint choice for `tenants`; decided project-wide 2026-08-12.
