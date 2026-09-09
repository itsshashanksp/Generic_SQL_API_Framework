# Backend Roadmap

This roadmap covers only the Generic SQL API backend. Frontend dashboards, grids, charts, report widgets, parsers, and UI work are intentionally excluded. A planned item is not part of the public API until code, validation, tests, and documentation all expose it.

## v1.0.0 — Core API & Advanced SQL — Released

- universal public JSON validation and normalization
- SELECT, DISTINCT, TOP (`limit`), aliases, CASE, arithmetic, allow-listed SQL functions
- prepared filters; INNER/LEFT/RIGHT joins; GROUP BY; aggregate HAVING; sorting
- SQL Server compatibility-aware pagination and window functions
- IN/EXISTS filter subqueries, standard/recursive CTE, UNION/UNION ALL
- stored procedure, scalar function, table-valued function, and metadata actions
- stable success/error envelope, execution timing, row count, query/error logging

Current boundaries remain documented in the [capability matrix](API.md#capability-matrix). In particular, FULL/CROSS joins, window partitioning, public INTERSECT/EXCEPT, general subqueries, and write operations are not released features.

## v1.1.0 — Windows Runtime & Deployment — Current

- bundled `runtime/windows/php/`
- `start-windows.bat` checks PHP, configuration, ODBC, OpenSSL, encrypted-credential readiness, database connectivity, API path, and ports
- automatic OPcache/log directory creation and port selection from 8000 through 8100
- SQL Server/Windows authentication modes and automatic/specific ODBC driver configuration
- cross-platform AES-256-GCM database password protection, environment-managed keys, and one-time Windows setup
- modular `QueryRepository` facade with specialized query builders
- public validation/normalization plus API-contract and ORDER BY/window regression coverage
- database-independent backend test runner and GitHub Actions syntax/logic workflow

## v1.2.0 — CRUD Operations — Planned

- INSERT, UPDATE, DELETE, and upsert design
- write-specific validation and prepared execution
- write response/error contracts and tests

## v1.3.0 — Transactions — Planned

- begin, commit, rollback, failure handling, and transaction-aware service boundaries

## v1.4.0 — Database Metadata — Planned

The five basic metadata actions already exist. This version is for capabilities not yet implemented: primary/foreign keys, indexes, richer types/constraints, discovery improvements, and caching.

## v1.5.0 — API Security — Planned

- authentication and authorization design
- API keys or token-based identity, role enforcement, audit policy, and rate limiting

The current API has no application authentication/authorization layer; an `Authorization` CORS header allowance is not security implementation.

## v1.6.0 — API Improvements — Planned

- versioning, health/status endpoints, OpenAPI description, and more complete option-level validation
- resolution of documented public/internal edge cases such as `TIMEFROMPARTS`

## v1.7.0 — Performance — Planned

- metadata/query caching evaluation, large-result handling, profiling, connection improvements, and log rotation

## v1.8.0 — Additional Database Providers — Planned

- provider-by-provider implementation and tests for selected databases

Existing MySQL, PostgreSQL, Oracle, and SQLite driver files are not selectable production providers and do not make those databases supported.

## v2.0.0 — Platform & Deployment — Future

- supported Linux deployment/runtime guidance
- containerization and environment-based configuration evaluation
- production deployment/process-manager helpers and optional live integration testing

Version targets may change, but released/current/planned status must always follow the implementation.
