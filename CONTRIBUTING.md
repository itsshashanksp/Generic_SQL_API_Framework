# Contributing

This repository is a backend SQL API. Keep frontend/dashboard/reporting changes outside backend pull requests.

## Structure and flow

Public requests enter at `api/index.php`, pass through `app/Requests/QueryRequestValidator.php` and `QueryRequestNormalizer.php`, then through controllers/services. `app/Repositories/QueryRepository.php` is an orchestration/execution facade. Keep query logic in the specialized files under `app/Repositories/Query/` and in `SetOperationBuilder.php`; do not merge them back into `QueryRepository`.

Database connection/execution belongs in `core/Database.php`, `core/QueryEngine.php`, and `database/`. Public envelope logic belongs in `core/Response.php`. Public contract changes must be made at the validator and normalizer boundary before builder internals are documented as public.

## Local checks

PHP 8.2 or later is the CI baseline. Check syntax:

```bash
find api app config core database scripts tests -type f -name '*.php' -exec php -l {} \;
```

Run all normal backend tests:

```bash
php tests/run.php
```

The runner executes:

- `UniversalApiContractTest.php` for validation, normalization, representative query generation, routines, metadata normalization, and response/error envelopes
- `OrderByWindowRegressionTest.php` for top-level versus window ordering and both pagination strategies
- `BackendLogicTest.php` for filter operators, joins, functions, windows, pagination counts, set operations, and response formatting

These tests must run without SQL Server, an ODBC extension, credentials, or `database/config/database.json`. Use fake `QueryEngine` and `MetadataRepository` subclasses whose constructors do not connect. Do not skip failed logic when a database is absent, hide connection failures, or increase timeouts.

## Live database testing

Real SQL Server testing is separate and currently manual. Create the ignored database JSON, install PHP ODBC and a supported SQL Server ODBC driver, then use `php scripts/check-database.php` before exercising `api/index.php`. Never add fake credentials to normal CI. If an optional integration workflow is introduced later, it must remain separate and explicitly secret-backed.

## Change expectations

- Preserve public behavior unless the change explicitly revises the contract.
- Reject unknown properties and validate identifiers/operators; do not add a raw-SQL escape hatch.
- Keep filter, HAVING, and routine values prepared where the current architecture prepares them.
- Add regression coverage for every bug. Window `ORDER BY` must never emit a bare integer position such as `ROW_NUMBER() OVER (ORDER BY 1)`.
- Test both SQL Server compatibility paths when changing pagination.
- Distinguish public JSON (`fields[].field`, `filters`, `limit`, nested `pagination`) from normalized builder keys.
- Update README, API/reference/examples, roadmap, and changelog together when their claims change.
- Do not claim that placeholder drivers are supported providers.
- Keep credentials, `database/config/database.json`, logs, generated exports/uploads, and OPcache files out of commits.

Before opening a pull request, include a concise description, motivation, tests run, whether any live database test was performed, and any public-contract or deployment impact. Review `git diff` for unrelated changes.
