# Architecture

## Runtime flow

The exact query request flow implemented today is:

```text
Client
  -> api/index.php (CORS, JSON decode, middleware)
  -> QueryRequestValidator
  -> QueryRequestNormalizer
  -> QueryController
  -> QueryService
  -> QueryRepository
  -> specialized builders
  -> QueryEngine
  -> Database -> DriverFactory -> SqlServerDriver
  -> PHP ODBC -> SQL Server
```

Validation and normalization occur in `api/index.php` before controller dispatch. The controller performs a final internal required-field check and delegates to the service. On return, `BaseController` calls `Response`, which converts engine results into the public JSON envelope. Exceptions reach the global `ExceptionHandler`: request exceptions become 400 responses and other failures become a generic 500 `QUERY_ERROR` while details are logged.

## Layer responsibilities

| Layer | Current responsibility |
|---|---|
| `api/index.php` | CORS/preflight, JSON parsing, validation, normalization, routing, exception registration |
| `QueryRequestValidator` | Public action/property allow-lists, shapes, identifiers, functions, operators, joins, pagination |
| `QueryRequestNormalizer` | Converts public JSON terminology into the private builder model |
| Controllers | Select operation and response message; no SQL construction |
| Services | Thin delegation to query or metadata repositories |
| `QueryRepository` | Execution/orchestration facade for SELECT, set operations, and routines |
| Query builders | Validate database objects and construct SQL fragments/parameters |
| `QueryEngine` | ODBC execution, result collection, timing, row counts, execution logs |
| Database layer | Reads local JSON configuration, chooses SQL Server driver, opens/closes ODBC connection |
| `Response` | Stable success and error payload construction |

## Modular query construction

`QueryRepository` is not a monolithic SQL builder. It owns a `SelectBuilder`, `RoutineBuilder`, and `SetOperationBuilder`, executes their output through `QueryEngine`, and attaches pagination totals.

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

## Database and metadata

`DriverFactory` currently creates only `SqlServerDriver`. A `Database` construction immediately connects, which is why production repositories are connection-backed. `MetadataRepository` queries `INFORMATION_SCHEMA` to validate tables and columns and to determine types used by integer-backed date-range handling.

Pagination performs a count query when both page values are present, then asks SQL Server for its compatibility level. Compatibility level 110 or newer uses `OFFSET/FETCH`; older levels wrap the projection and use `ROW_NUMBER()`.

## Test boundary

Database-independent tests instantiate builders with fake `QueryEngine` and `MetadataRepository` subclasses whose constructors do not connect. This tests public validation -> normalization -> SQL/parameter generation and response formatting without changing production behavior or requiring database configuration. A live SQL Server remains necessary for actual execution and metadata integration, but not for normal CI.

See [API](API.md), [JSON request reference](JSON-Request-Reference.md), and [Database configuration](Database-Configuration.md).
