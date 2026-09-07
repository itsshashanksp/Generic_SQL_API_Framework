# Generic SQL API Framework

Generic SQL API Framework is a backend-only PHP API that turns a validated JSON query description into SQL Server SQL, executes it through ODBC, and returns a stable JSON envelope. It is intended for clients that need reusable read/query endpoints without adding a controller for every report query. Clients never submit raw SQL.

The existing PHP implementation is the source of truth. Microsoft SQL Server through ODBC is the only working provider. Driver stubs for MySQL, PostgreSQL, Oracle, and SQLite are not selectable by `DriverFactory` and are not supported providers.

## Public API

Send JSON to `api/index.php` (normally with `POST` and `Content-Type: application/json`):

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

The public actions are `select`, `sql`, `union`, `unionAll`, `procedure`, `function`, `tableFunction`, and the five metadata actions documented in [API.md](docs/API.md). Public property names such as `source`, `fields`, `field`, `filters`, and `pagination` are normalized to a private execution model. Internal names such as `table`, `columns`, `column`, `where`, `top`, `page`, and `pageSize` are not accepted as public JSON.

`sql` is a controlled report-resource action, not a raw-SQL endpoint. A client
sends an allowlisted resource ID plus optional runtime filters, sorting, and
pagination. The server loads the registered file under `queries/`, validates
runtime fields against that resource's exposed output columns, binds all user
values as prepared parameters, and uses the existing SQL Server connection.

Current query support includes SELECT, DISTINCT, SQL Server TOP through `limit`, aliases, CASE and arithmetic expressions, an allow-list of SQL functions, prepared WHERE values, INNER/LEFT/RIGHT equality joins, GROUP BY, aggregate HAVING, multi-field sorting, pagination, eight window functions, subqueries in selected filters, one CTE (including the recursive form), UNION/UNION ALL, routines, and database metadata reads. See the definitive [JSON request reference](docs/JSON-Request-Reference.md) and [capability matrix](docs/API.md#capability-matrix) for exact boundaries.

## Architecture

The actual HTTP flow is:

```text
Client -> api/index.php -> QueryRequestValidator -> QueryRequestNormalizer
       -> QueryController/QueryRepository or SQLController/SqlRepository
       -> QueryEngine -> Database/ODBC -> SQL Server
```

Results return through the same layers and `Response` creates the public envelope. `QueryRepository` is an execution/orchestration facade; SQL construction remains split across `SelectBuilder`, `WhereBuilder`, `JoinBuilder`, `GroupByBuilder`, `HavingBuilder`, `OrderByBuilder`, `PaginationBuilder`, `WindowFunctionBuilder`, `SqlExpressionBuilder`, `RoutineBuilder`, and `SetOperationBuilder`.

See [Architecture.md](docs/Architecture.md) for responsibilities and request/response flow.

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

For SQL authentication use `"authentication": "sql"` plus `username` and `password`. The host must have a compatible SQL Server ODBC driver. Details and actual defaults are in [Database-Configuration.md](docs/Database-Configuration.md).

## Start the backend

On Windows, run:

```bat
start-windows.bat
```

The repository includes `runtime/windows/php/`, so XAMPP or a separate PHP installation is not required. The launcher validates PHP, `php.ini`, PHP ODBC, the database connection, and the API directory; creates OPcache/log directories; chooses the first free port from 8000 through 8100; then starts PHP's development server. A working database is required to start through this launcher because it deliberately runs `scripts/check-database.php` first.

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

Successful operations return `success`, `message`, `data`, and `meta`. Metadata includes `page`, `pageSize`, `totalRows`, `rowsReturned`, and `executionTime`; there is no query-result column-description metadata. Errors return `success: false`, an `error` object, and an empty `data` array. See [API.md](docs/API.md).

## Project status

- v1.0.0 — Core API and advanced SQL: released
- v1.1.0 — Windows runtime and deployment: current
- v1.2.0 onward — CRUD, transactions, richer metadata, security, API improvements, performance, and additional providers: planned

The roadmap is backend-only. See [Roadmap.md](docs/Roadmap.md) and [CHANGELOG.md](CHANGELOG.md).

## Documentation

- [Introduction](docs/Introduction.md)
- [Architecture](docs/Architecture.md)
- [HTTP API](docs/API.md)
- [JSON request reference](docs/JSON-Request-Reference.md)
- [Query examples](docs/Query-Examples.md)
- [Database configuration](docs/Database-Configuration.md)
- [Hosting](docs/Hosting.md)
- [Roadmap](docs/Roadmap.md)
- [Contributing](CONTRIBUTING.md)
- [Changelog](CHANGELOG.md)

Do not commit database credentials or logs. The project scope is the backend API; dashboards, charts, report widgets, and other frontend features belong to consumers, not this repository's backend roadmap.
