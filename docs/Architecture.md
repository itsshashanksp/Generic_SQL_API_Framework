# Architecture

## Runtime flow

The two query request flows implemented today are:

```text
Client
  -> api/index.php (CORS, JSON decode, middleware)
  -> QueryRequestValidator
  -> QueryRequestNormalizer
  -> QueryController -> QueryService -> QueryRepository -> specialized builders
     OR SQLController -> SqlService -> SqlRepository -> approved SQL resource
  -> QueryEngine
  -> Database -> DriverFactory -> SqlServerDriver
  -> PHP ODBC -> SQL Server
```

Validation and normalization occur in `api/index.php` before controller dispatch. The controller performs a final internal required-field check and delegates to the service. On return, `BaseController` calls `Response`, which converts engine results into the public JSON envelope. Exceptions reach the global `ExceptionHandler`: request exceptions become 400 responses, database statement timeouts become a safe 504 `QUERY_ERROR`, and other failures become a generic 500 `QUERY_ERROR` while details are logged.

## Layer responsibilities

| Layer | Current responsibility |
|---|---|
| `api/index.php` | CORS/preflight, JSON parsing, validation, normalization, routing, exception registration |
| `QueryRequestValidator` | Dispatches validation for JSON-query actions and controlled SQL resources |
| `SqlRequestValidator` | Restricts SQL mode to a resource ID and supported filter/sort/pagination runtime shapes |
| `QueryRequestNormalizer` | Converts public JSON terminology and routes `sql` to `SQLController` |
| Controllers | Select/SQL-resource operation and shared response message; no SQL construction |
| Services | Thin delegation to query or metadata repositories |
| `QueryRepository` | Execution/orchestration facade for SELECT, set operations, and routines |
| `SqlResourceRegistry` | Maps exact approved IDs to backend files, exposed output aliases, and default sorting; enforces path containment |
| `SqlRepository` | Loads a registered SELECT, wraps it, safely applies supported runtime state, and reuses pagination/execution infrastructure |
| `ScopedMetadataRepository` | Adds request-local inferred CTE output metadata while delegating physical table/column checks to `MetadataRepository` |
| Query builders | Validate database objects and construct SQL fragments/parameters |
| `QueryEngine` | ODBC execution, result collection, timing, row counts, execution logs |
| Database layer | Reads local JSON configuration, resolves plain or encrypted credentials, chooses the SQL Server driver, and opens/closes the ODBC connection |
| `Response` | Stable success and error payload construction |

## Modular query construction

`QueryRepository` is not a monolithic SQL builder. It owns a `SelectBuilder`, `RoutineBuilder`, and `SetOperationBuilder`, executes their output through `QueryEngine`, and attaches pagination totals.

`SqlRepository` is deliberately separate from the Universal JSON builders. Its
base SQL is trusted application code selected only through the registry. The
client cannot supply SQL or a file path. Runtime sort and filter fields are checked
against per-resource allowlists, identifiers are quoted by the repository, and
values are passed to `QueryEngine::executePrepared`. Both paths converge on the
same `QueryEngine`, database connection, exception handling, and `Response`
envelope. Existing `QueryController` behavior is unchanged.

SQL resources may define `filterColumns` separately from returned `columns`.
For example, a grouped report can return `Cust_Name` and aggregate aliases
while allowing source filters on `Cust_Name` and `StDate`. If that database
date is stored as an integer, `filterValueTypes: ['StDate' => 'integer-date']`
converts ISO UI dates to validated integer parameters.
Normal resources filter the wrapped output. An aggregate resource that must
filter source rows declares `filterPlacement: source` and contains exactly one
controlled `/*__RUNTIME_FILTERS__*/` marker. The registry validates the field
allowlist and marker placement; the repository replaces the marker only with
quoted allowlisted identifiers and prepared placeholders before aggregation.
Sorting remains restricted to returned columns.

| Builder | Role |
|---|---|
| `SelectBuilder` | Orchestrates SELECT/CTE construction and delegates clauses; renders allow-listed field functions |
| `WhereBuilder` | Validates filter columns/operators, builds prepared placeholders, subqueries, and AND/OR combination |
| `JoinBuilder` | Builds INNER/LEFT/RIGHT equality joins and validates joined tables |
| `GroupByBuilder` | Validates and renders grouping fields |
| `HavingBuilder` | Builds parameterized aggregate comparisons joined with AND |
| `OrderByBuilder` | Validates fields, aliases/directions, internal positions, and resolves real columns for window contexts |
| `PaginationBuilder` | Counts rows and chooses OFFSET/FETCH (compatibility 110+) or a ROW_NUMBER fallback |
| `WindowFunctionBuilder` | Builds the eight exposed window functions and delegates order validation |
| `SqlExpressionBuilder` | Tracks table aliases and renders controlled values, arithmetic expressions, and conditions |
| `RoutineBuilder` | Builds prepared procedure, scalar-function, and table-valued-function calls |
| `SetOperationBuilder` | Combines built SELECT statements and merges their parameters |

`OrderByBuilder` deliberately distinguishes top-level positional ordering from a window `ORDER BY`. SQL Server permits top-level `ORDER BY 1`, but rejects integer indexes inside `ROW_NUMBER() OVER (...)`. Window and legacy-pagination paths resolve positions to actual validated projections. The public validator rejects numeric sort fields entirely, so public clients always send logical fields.

Nested SELECT construction snapshots and restores the expression alias scope, so a filter subquery cannot replace the outer query's aliases before GROUP BY or ORDER BY is built. CTE projections are registered as request-local virtual metadata: recursive branches can resolve their self-reference, outer clauses validate against projected names, and paginated count SQL receives the same `WITH` prefix as the data query.

## Database and metadata

`DriverFactory` currently creates only `SqlServerDriver`. A `Database` construction immediately connects, which is why production repositories are connection-backed. `QueryRepository` passes its request-owned `QueryEngine` to `MetadataRepository`, so metadata and data use one connection in sequence instead of opening a second connection. Other requests construct different engines and connections. Statements are freed in `finally`, including after failures, and the engine closes its connection at the end of its lifetime. `MetadataRepository` queries `INFORMATION_SCHEMA` to validate tables and columns and to determine types used by integer-backed date-range handling.

Database password protection is a configuration-layer concern:

```text
database/config/database.json
  -> DatabaseCredentialResolver
  -> plain string: use unchanged
     OR encrypted object: authenticate and decrypt with AES-256-GCM
        using GENERIC_SQL_API_ENCRYPTION_KEY
  -> SqlServerDriver
  -> odbc_connect
```

The resolver obtains a Base64-encoded 32-byte key from the process environment only for an encrypted password. It validates the versioned object and resolves the plaintext password in memory immediately before the existing SQL Server connection path. Credential failures use safe messages, and credential exception traces are omitted from logs so password objects and key material are not exposed. This layer does not add an endpoint, authentication, authorization, or any change to the public request/response contract.

Pagination normally performs a count query when both page values are present, then asks SQL Server for its compatibility level. Compatibility level 110 or newer uses `OFFSET/FETCH`; older levels wrap the projection and use `ROW_NUMBER()`. A complete first-page SQL resource whose authored `TOP` limit fits the requested page has a tested fast path that executes directly and infers the total from returned rows.

## Request isolation and cancellation

Controllers, services, repositories, `QueryEngine`, and the ODBC connection are constructed within each PHP request. The application contains no global query queue, cancellation flag, or SQL execution lock, so cancellation state is not shared between users. Concurrent execution depends on the hosting process model: a production FastCGI/Apache/IIS deployment can use independent workers, while PHP's built-in development server is single-threaded by default and can make a second request wait behind a slow first request.

A browser abort stops waiting for the HTTP response, but this synchronous PHP ODBC execution path exposes no safe statement-cancellation hook while `odbc_execute` is blocked. Client disconnect therefore must not be described as guaranteed SQL Server cancellation. The PHP request and ODBC resources finish or time out normally; the frontend must discard any obsolete result. No speculative cross-request SQL cancellation is implemented.

## Timing and timeout model

Each request has a random correlation ID. Dated logs distinguish request receipt, validation, normalization, SQL generation, connection setup, statement preparation, execution, row fetching, response construction, and request total. Query entries label pagination counts, compatibility metadata, and main data queries separately. Logs include normalized SQL with literals redacted plus parameter count/types, never parameter values or credentials.

The default database statement timeout is 45 seconds and can be set with `DB_QUERY_TIMEOUT_SECONDS`. `QueryEngine` applies it with ODBC `SQL_QUERY_TIMEOUT` before every execution. It is deliberately below the bundled PHP `max_execution_time` of 60 seconds so the exception handler normally has time to return a 504 `QUERY_ERROR`. The PHP limit remains a last-resort request guard; a shutdown handler converts that fatal timeout to the same safe payload. Neither setting controls the browser, reverse proxy, load balancer, or database connection/login timeout. Configure those deployment-specific HTTP timeouts slightly above the PHP request limit, and configure login behavior in the ODBC/host environment.

This timeout controls duration and failure behavior; it does not make an inefficient query fast. The observed expensive grouped aggregates and derived-table counts still need SQL Server execution-plan, index, statistics, and blocking analysis using the new phase logs.

## Test boundary

Database-independent tests instantiate builders with fake `QueryEngine` and `MetadataRepository` subclasses whose constructors do not connect. They test public validation -> normalization -> SQL/parameter generation and response formatting, count/data sequencing, independent executor state, parameter isolation, timeout conversion, cleanup, and recovery after failure. A live SQL Server remains necessary for execution-plan and real ODBC timeout integration, but not for normal CI.

See [API](API.md), [JSON request reference](JSON-Request-Reference.md),
[SQL resource configuration](SQL-Resource-Configuration.md),
[SQL resource files](SQL-Resource-Files.md), and
[Database configuration](Database-Configuration.md).
