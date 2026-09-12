# Generic SQL API Framework

Generic SQL API Framework is a backend-only PHP API that turns validated JSON
query or CRUD descriptions into SQL Server SQL, executes them through ODBC, and
returns a stable JSON envelope. It provides reusable read/query endpoints and
explicitly configured write resources without a controller per table or report.
Clients never submit raw SQL.

The existing PHP implementation is the source of truth. Microsoft SQL Server through ODBC is the only working provider. Driver stubs for MySQL, PostgreSQL, Oracle, and SQLite are not selectable by `DriverFactory` and are not supported providers.

## Public API

Send JSON to the `api/index.php` entry script, normally with `POST` and
`Content-Type: application/json`. It is `/api/index.php` when the repository root
is served, or `/index.php` when `api/` is the document root used by the bundled
launcher:

```json
{
  "action": "select",
  "source": { "table": "Items", "alias": "I" },
  "fields": [
    "I.ItemCode",
    { "field": "I.Description", "alias": "ItemName" }
  ],
  "filters": [
    { "field": "I.Active", "operator": "=", "value": 1 }
  ],
  "sort": [{ "field": "I.ItemCode", "direction": "ASC" }],
  "pagination": { "page": 1, "pageSize": 50 }
}
```

The public actions are `select`, `sql`, `insert`, `update`, `delete`, `upsert`, `union`, `unionAll`, `procedure`, `function`, `tableFunction`, and the five metadata actions documented in [API.md](docs/API.md). Public property names such as `source`, `fields`, `field`, `resource`, `data`, `filters`, and `pagination` are normalized to a private execution model. Internal names such as `table`, `columns`, `column`, `where`, `top`, `page`, and `pageSize` are not accepted as public JSON.

`sql` is a controlled report-resource action, not a raw-SQL endpoint. The server
recursively discovers reviewed `.sql` files under `queries/`; for example,
`queries/reports/customer.sql` is `reports/customer`. A simple resource needs no
per-file registry entry. Optional, strictly validated `execution` metadata
declares output aliases, filter mappings, and default sorting when runtime UI
controls need them. All runtime values remain prepared parameters.

Because that SQL is backend-owned and reviewed, SQL Resource Mode supports
complex SQL Server SELECT/CTE queries, including joins/APPLY, subqueries,
windows, JSON/XML expressions, and set operations, without using JSON Query
Mode's client-facing function and expression allowlists. Clients still cannot
submit SQL text, tables, paths, credentials, or connection settings.

Current query support includes SELECT, DISTINCT, SQL Server TOP through `limit`, aliases, CASE and arithmetic expressions, an allow-list of SQL functions, prepared WHERE values, INNER/LEFT/RIGHT equality joins, GROUP BY, aggregate HAVING, multi-field sorting, pagination, eight window functions, subqueries in selected filters, one CTE (including the recursive form), UNION/UNION ALL, routines, and database metadata reads. See the definitive [JSON request reference](docs/JSON-Request-Reference.md) and [capability matrix](docs/Capability-Matrix.md) for exact boundaries.

CRUD uses a separate deny-by-default write-resource registry. It supports
single-object INSERT, targeted UPDATE/DELETE, and SQL Server UPSERT with live
metadata validation, prepared values, affected-row reporting, and optional safe
identity output. A deployment must explicitly configure approved write targets.

## Architecture

The actual HTTP flow is:

```text
Client -> api/index.php -> QueryRequestValidator -> QueryRequestNormalizer
       -> QueryController/QueryRepository, SQLController/SqlRepository,
          or WriteController/WriteRepository
       -> QueryEngine -> Database/ODBC -> SQL Server
```

Results return through the same layers and `Response` creates the public envelope. `QueryRepository` is an execution/orchestration facade; SQL construction remains split across `SelectBuilder`, `WhereBuilder`, `JoinBuilder`, `GroupByBuilder`, `HavingBuilder`, `OrderByBuilder`, `PaginationBuilder`, `WindowFunctionBuilder`, `SqlExpressionBuilder`, `RoutineBuilder`, and `SetOperationBuilder`.

See [Architecture.md](docs/Architecture.md) for responsibilities and request/response flow.
Backend developers adding controlled SQL reports should also read
[SQL resource configuration](docs/SQL-Resource-Configuration.md) and
[SQL resource files](docs/SQL-Resource-Files.md). CRUD deployments should read
[write resource configuration](docs/Write-Resource-Configuration.md).

## Configure SQL Server

Create the ignored local file `database/config/database.json`:

```json
{
  "provider": "sqlserver",
  "driver": "auto",
  "server": "localhost\\SQLEXPRESS",
  "database": "ApplicationDb",
  "authentication": "windows",
  "options": {
    "encrypt": false,
    "trustServerCertificate": true
  }
}
```

For SQL authentication use `"authentication": "sql"` plus `username` and `password`. Plaintext configuration remains supported for compatibility, but deployments can seal the complete configuration—including server, database, username, password, driver, and connection options—as one AES-256-GCM authenticated payload. The Base64-encoded 32-byte key comes from `GENERIC_SQL_API_ENCRYPTION_KEY` and must remain separate from `database.json`.

Windows users can perform the one-time migration of an existing plaintext configuration with:

```bat
setup-database-encryption.bat
```

The setup saves the key in the Windows User environment, encrypts the complete `database.json`, creates an ignored plaintext recovery backup, and validates the connection. Protect or remove the backup after verification. Open a new terminal before later startup so it inherits the saved variable. Do not commit the key, configuration, or backups. See [Database Configuration](docs/Database-Configuration.md) for the encrypted format, cross-platform manual setup, failure behavior, and security limitations.

## Start the backend

On Windows, run:

```bat
start-windows.bat
```

The repository includes `runtime/windows/php/`, so XAMPP or a separate PHP installation is not required. The launcher validates PHP, `php.ini`, PHP ODBC, PHP OpenSSL, any required encrypted-configuration environment key, the database connection, and the API directory; creates OPcache/log directories; chooses the first free port from 8000 through 8100; then starts PHP's development server. A working database is required to start through this launcher because it deliberately runs `scripts/check-database.php` first.

With another PHP installation, after configuring the database:

```bash
php -S 127.0.0.1:8000 -t api
```

The built-in server is suitable for local use, not production. See [Hosting.md](docs/Hosting.md).

## Test without a database

Normal backend tests use fakes for query execution and metadata, so they require no SQL Server, ODBC extension, credentials, running server, or `database/config/database.json`:

```bash
php tests/run.php
```

Syntax-check the maintained backend source with:

```bash
find api app config core database scripts tests -type f -name '*.php' -exec php -l {} \;
```

`.github/workflows/backend-tests.yml` runs both checks on every push and pull request using PHP 8.2. There is no mandatory live-database integration workflow.

## Responses

Successful operations return `success`, `message`, `data`, and `meta`. Metadata includes `page`, `pageSize`, `totalRows`, `rowsReturned`, and `executionTime`; writes additionally include `affectedRows`. There is no query-result column-description metadata. Errors return `success: false`, an `error` object, and an empty `data` array. See [API.md](docs/API.md).

## Project status

- v1.0.0 — Core API and advanced SQL: released
- v1.1.0 — Windows runtime and deployment: released
- v1.2.0 — CRUD operations: current
- v1.3.0 onward — transactions, richer metadata, API authentication/authorization, API improvements, performance, and additional providers: planned

The roadmap is backend-only. See [Roadmap.md](docs/Roadmap.md) and [CHANGELOG.md](CHANGELOG.md).

## Documentation

Start at the [backend documentation map](docs/README.md).

Getting started:

- [Introduction](docs/Introduction.md)
- [HTTP API and Universal JSON Contract](docs/API.md)
- [All public actions](docs/Action-Reference.md)
- [JSON request reference](docs/JSON-Request-Reference.md)
- [Response reference](docs/Response-Reference.md)
- [Frontend integration](docs/Frontend-Integration.md)

Querying and resources:

- [JSON Query Mode](docs/Query-Mode.md)
- [Query functions](docs/Query-Functions.md)
- [Filtering, sorting, and pagination](docs/Filtering-Sorting-Pagination.md)
- [Set operations](docs/Set-Operations.md)
- [SQL Resource Mode](docs/SQL-Resource-Mode.md)
- [SQL Resource configuration](docs/SQL-Resource-Configuration.md)
- [SQL Resource files](docs/SQL-Resource-Files.md)

Writes, metadata, boundaries, and operations:

- [CRUD / Write API](docs/CRUD.md)
- [Write resource configuration](docs/Write-Resource-Configuration.md)
- [Metadata and routines](docs/Metadata-and-Routines.md)
- [Validation, errors, and security](docs/Validation-and-Errors.md)
- [Capability matrix](docs/Capability-Matrix.md)
- [Current limitations](docs/Limitations.md)
- [Architecture](docs/Architecture.md)
- [Database configuration](docs/Database-Configuration.md)
- [Hosting](docs/Hosting.md)
- [Roadmap](docs/Roadmap.md), [contributing](CONTRIBUTING.md), and [changelog](CHANGELOG.md)

Do not commit database credentials, `GENERIC_SQL_API_ENCRYPTION_KEY`, or logs. The project scope is the backend API; dashboards, charts, report widgets, and other frontend features belong to consumers, not this repository's backend roadmap.
