# Changelog

All notable backend changes are recorded here. The project follows semantic versioning.

## [Unreleased]

### Added

- Public single-object INSERT, UPDATE, DELETE, and UPSERT actions routed through
  dedicated controller/service/repository and write-builder layers.
- Deny-by-default write-resource registry with per-resource action, schema/table,
  writable/filterable column, UPSERT key, and identity allowlists.
- Live SQL Server write metadata validation, mandatory UPDATE/DELETE targeting,
  prepared DML values, affected-row/identity responses, unique-key verification,
  safe constraint error classification, and database-independent CRUD/security tests.

- Cross-platform AES-256-GCM database password encryption with a versioned nonce/ciphertext/authentication-tag format and a Base64-encoded 32-byte key supplied through `GENERIC_SQL_API_ENCRYPTION_KEY`.
- Database credential resolution that preserves plaintext password compatibility while decrypting encrypted passwords before the existing SQL Server ODBC connection path.
- Windows one-time encryption setup using the bundled PHP/OpenSSL runtime, Windows User environment storage, in-place configuration migration, and connection validation without retaining a plaintext backup.
- Database-independent encryption coverage for round trips, random nonces, Unicode and empty values, malformed data, wrong keys, tampering, backward-compatible resolution, and secret-safe logging.
- Database-independent `tests/run.php` entry point and broader logic coverage for every public filter operator, public joins, representative function families, all window functions, pagination counts, set-operation assembly, and response formatting.
- Ubuntu/PHP 8.2 GitHub Actions workflow for backend syntax checks and all standalone tests without SQL Server, ODBC, credentials, or database configuration.
- Definitive backend capability matrix and detailed public JSON field reference.
- Modularized query construction through `SelectBuilder`, `WhereBuilder`, `JoinBuilder`, `GroupByBuilder`, `HavingBuilder`, `OrderByBuilder`, `PaginationBuilder`, `WindowFunctionBuilder`, `SqlExpressionBuilder`, `RoutineBuilder`, and `SetOperationBuilder`, with `QueryRepository` retained as the facade.
- Public validation/normalization and universal API contract regression coverage.

### Changed

- Reconciled README, architecture, API, examples, database configuration, hosting, roadmap, and contribution guidance with the current implementation.
- Replaced the prior Windows/ODBC environment-validation workflow with logic-focused normal CI. Windows runtime and live database validation remain runtime/manual concerns.

### Fixed

- Credential configuration, decryption, and key failures now use safe messages and avoid logging credential exception traces or secret values.
- Resolved internal positional ordering to validated logical fields for window functions and legacy pagination, preventing SQL Server from receiving `ROW_NUMBER() OVER (ORDER BY 1)`.
- Added regression coverage that preserves top-level ordering while protecting window contexts.

## [1.1.0] - 2026-08-21

### Added

- Bundled Windows PHP runtime in `runtime/windows/php/`.
- `start-windows.bat` with PHP/php.ini checks, runtime-directory creation, PHP ODBC validation, database validation, API-path checks, and automatic port selection from 8000 through 8100.
- `scripts/check-database.php` startup connection check.
- SQL and Windows authentication configuration, explicit or automatic SQL Server ODBC driver selection, and encryption/trust options.

### Changed

- Windows users can run the backend without installing PHP/XAMPP when using the bundled runtime; a compatible SQL Server ODBC driver and reachable database remain required.

## [1.0.0] - Initial release

### Added

- JSON API routing, controller/service layers, public request validation/normalization, CORS, and standard responses.
- SQL Server SELECT generation with fields/aliases, DISTINCT/TOP, CASE/arithmetic, allow-listed functions, prepared filters, joins, grouping/HAVING, sorting, and compatibility-aware pagination.
- Window functions, filter subqueries, CTE/recursive CTE, UNION/UNION ALL, stored procedures, scalar functions, table-valued functions, and metadata actions.
- SQL execution timing, returned-row counts, and success/error logging.

### Fixed

- BETWEEN date strings can be converted to `YYYYMMDD` integers for integer-family date columns discovered through metadata.

## Planned

Planned work is maintained in [docs/Roadmap.md](docs/Roadmap.md) and is not part of the current API until implemented and tested.
