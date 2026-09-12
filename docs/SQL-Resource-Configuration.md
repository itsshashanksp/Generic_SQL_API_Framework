# SQL Resource Configuration

## Overview

SQL Resource Mode executes a predefined, backend-owned SQL Server query selected by a public resource ID. A client sends `"action": "sql"` and a registered `resource`; it never sends SQL text or a file path.

The two read-query modes have different ownership boundaries:

- **JSON Query Mode** (`action: "select"`) accepts a validated query description and builds SQL through `QueryRepository` and its specialized builders.
- **SQL Resource Mode** (`action: "sql"`) resolves an approved SQL file through `SqlResourceRegistry`, then `SqlRepository` applies the supported runtime filters, sorting, and pagination.

Both paths use `QueryEngine`, the configured SQL Server ODBC connection, and the standard response envelope. The public endpoint and complete action list remain documented in [HTTP API](API.md); this document is for backend developers registering resources.

## When to Use SQL Resources

Use a SQL resource when a report's projection and business logic should be fixed in the backend, especially for joins, aggregates, calculations, `CASE` expressions, window functions, SQL Server-specific expressions, or other queries that are clearer as reviewed `.sql` files.

Use JSON Query Mode when the existing public JSON contract can express the query and the client needs to choose its validated fields, joins, grouping, or functions. SQL Resource Mode does not expose those structural choices: the SQL file owns them.

SQL Resource Mode passes the registered statement to SQL Server rather than implementing or allowlisting individual functions and expressions. It accepts one server-owned read-only `SELECT`, optionally with leading comments or a top-level standard/recursive `WITH` clause. This is intentionally broader than client-composed JSON Query Mode.

## Runtime Flow

```text
POST api/index.php
  -> QueryRequestValidator -> SqlRequestValidator
  -> QueryRequestNormalizer (routes to SQLController::execute)
  -> SQLController -> SqlService -> SqlRepository
  -> SqlResourceRegistry -> config/sql-resources.php -> queries/<file>.sql
  -> QueryEngine::executePrepared -> SQL Server through ODBC
  -> Response
```

`SqlRequestValidator` checks the public shape. `SqlResourceRegistry` validates the selected registry entry and resolves its file. `SqlRepository` loads the file, builds runtime SQL and parameters, and delegates pagination and execution.

## Resource Structure

The registry is the PHP array returned by `config/sql-resources.php`. The existing `customer` resource uses every currently implemented configuration option:

```php
'customer' => [
    'file' => QUERY_PATH . '/reports/customer.sql',
    'columns' => ['Cust_Name', 'TotalCustomers', 'MinimumBill', 'MaximumBill'],
    'filterColumns' => ['Cust_Name', 'StDate'],
    'filterValueTypes' => ['StDate' => 'integer-date'],
    'filterPlacement' => 'source',
    'defaultSort' => [['field' => 'Cust_Name', 'direction' => 'ASC']],
],
```

Configuration fields are:

| Field | Required | Current behavior |
|---|---:|---|
| `file` | yes | Path to an existing file contained beneath `QUERY_PATH` (`Backend/queries`) |
| `columns` | yes | Non-empty list of output names allowed for runtime sorting |
| `defaultSort` | yes | Non-empty list of output fields and `ASC`/`DESC` directions |
| `filterColumns` | no | Fields allowed in runtime filters; defaults to `columns` |
| `filterValueTypes` | no | Per-filter conversion metadata; only `integer-date` is implemented |
| `filterPlacement` | no | `output` by default; `source` enables the controlled filter marker |
| `filters` | no | Alternative logical-field map for explicit output/WHERE/HAVING expressions and optional value type |

The registry rejects an entry with a missing/empty required field, invalid column name, invalid filter metadata, invalid default sort, unavailable file, or incorrect runtime-filter marker.

## Resource Identifier

A public resource ID must match:

```text
^[A-Za-z0-9][A-Za-z0-9_-]*$
```

It must start with a letter or digit. Remaining characters may be letters, digits, underscores, or hyphens. Registry lookup uses an exact PHP array key, so IDs are case-sensitive in practice.

| Valid | Invalid | Reason |
|---|---|---|
| `customer` | `../customer` | slash and dot are not allowed |
| `bill-total-sales` | `bill total sales` | spaces are not allowed |
| `report_2026` | `_report` | first character must be alphanumeric |
| `Customer2` | `customer.sql` | dot is not allowed |

Malformed IDs fail public validation with HTTP 400 and `INVALID_REQUEST`. A well-formed but unregistered or case-mismatched ID fails registry resolution with HTTP 400 and `INVALID_SQL_RESOURCE`.

The client-supplied ID is never interpreted as a path. It can only select an exact registry key.

## SQL File Configuration

`QUERY_PATH` is defined in `config/constants.php` as `Backend/queries`. Registry entries normally construct paths beneath it, for example:

```php
'file' => QUERY_PATH . '/reports/customer.sql',
```

At resolution time, `SqlResourceRegistry` calls `realpath()` for both the file and `QUERY_PATH`, then requires the resolved file to be below the resolved query root. A missing file, broken path, or path outside that root is rejected. This check also resolves symlinks before testing containment.

The public request contract has no property for a path, filename, URL, connection information, or SQL text. Unknown properties such as `sql` or `file` are rejected by `SqlRequestValidator`.

## Columns

`columns` contains stable names produced by the resource's `SELECT` list. For calculated fields, the SQL alias and registry name must agree:

```sql
COUNT(Cust_Name) AS TotalCustomers
```

```php
'columns' => ['Cust_Name', 'TotalCustomers', 'MinimumBill', 'MaximumBill'],
```

Runtime sorting is case-insensitive when matching a request field to this list, then uses the registry's canonical spelling and SQL Server bracket quoting. An undeclared runtime sort field returns HTTP 400 with `INVALID_SQL_RUNTIME_FIELD`.

`columns` is not a response projection or redaction layer. The current backend does not inspect result metadata or remove fields returned by the SQL file. Therefore, keep the SQL `SELECT` list itself limited to safe output and declare every returned alias that clients may sort by. Do not select secrets and assume omission from `columns` will hide them.

### Filter columns

When `filterColumns` is omitted, filters use `columns`. When it is present, filtering uses that separate allowlist, while sorting still uses `columns`.

This separation supports grouped reports such as `customer`: `StDate` can filter source rows even though it is not returned. Field matching is case-insensitive at runtime. The registry validates identifier syntax but does not compare names with live database metadata; SQL Server reports a mismatch at execution time.

`filterValueTypes` currently supports only `integer-date`, and its key must match a `filterColumns` entry. That type accepts a valid `YYYY-MM-DD` string or `YYYYMMDD` string/integer and binds the converted `YYYYMMDD` integer. Invalid dates return `INVALID_SQL_RUNTIME_VALUE`.

### Filter placement

`filterPlacement` has two implemented values:

- `output` (default): `SqlRepository` wraps the resource as `SELECT * FROM (...) AS SqlResource` and applies filters to the wrapped result. The SQL file must contain no runtime-filter marker.
- `source`: the SQL file must contain exactly one `/*__RUNTIME_FILTERS__*/` marker. The repository replaces it with a generated `WHERE` clause, or an empty string when no filters are supplied. This is intended for filtering rows before grouping or aggregation.

The marker is the only implemented SQL-file placeholder. Source filtering always generates a complete `WHERE` clause, not an `AND` fragment; place the marker where a `WHERE` clause is syntactically valid.

### Explicit filter mappings

For complex SQL, use `filters` instead of `filterColumns`, `filterValueTypes`,
and `filterPlacement`:

```php
'filters' => [
    'BillDate' => [
        'expression' => 'BIL.Bill_Date',
        'location' => 'where',
        'valueType' => 'integer-date',
    ],
    'Category' => [
        'expression' => 'CAT.Cat_Desc',
        'location' => 'where',
    ],
    'MinimumSales' => [
        'expression' => 'SUM(BIL.Item_Rate)',
        'location' => 'having',
    ],
]
```

The map key is the logical field accepted from the frontend. `expression` is
trusted backend configuration and never comes from the request. `location` is:

- `output`: filter a registered returned alias on the generated outer wrapper;
- `where`: add the expression to the main SELECT's top-level WHERE, creating
  that clause when absent;
- `having`: add the aggregate expression to the main SELECT's top-level HAVING,
  creating that clause when absent.

Mapped output expressions must name a configured `columns` alias. WHERE/HAVING
expressions may be qualified columns or reviewed expressions. They cannot
contain parameter markers, statement separators, or SQL comments. Values still
use the normal operator allowlist, `?` placeholders, and optional
`integer-date` conversion.

Insertion scans only top-level clause boundaries and ignores CTE bodies,
subqueries, derived tables, and window clauses. It does not attempt to infer a
location: the registry must declare it. WHERE/HAVING insertion into a top-level
UNION/UNION ALL/INTERSECT/EXCEPT is rejected as ambiguous; use an `output`
mapping over the combined result or a dedicated resource for a branch.

`filterLogic: "OR"` is supported when all requested filters use one location.
OR across WHERE, HAVING, or output locations is rejected because splitting it
across query stages would not preserve Boolean semantics. AND may span them.

Mapped filters do not require a placeholder. The legacy
`/*__RUNTIME_FILTERS__*/` marker remains supported only with
`filterPlacement: source`; mapped and legacy configuration cannot be combined.

## Default Sort

Every current registry entry must have a non-empty `defaultSort` array:

```php
'defaultSort' => [
    ['field' => 'Cust_Name', 'direction' => 'ASC'],
],
```

Each `field` must exactly match one of the configured `columns`. Direction may be `ASC` or `DESC` (the registry validates it case-insensitively). Multiple entries are supported.

A non-empty runtime `sort` replaces `defaultSort`. An omitted or empty runtime `sort` normally uses the default. There is one preservation rule: if the authored SQL has a top-level `ORDER BY`, and there are no runtime filters or non-empty runtime sort, the repository may execute and paginate that authored order directly. This preserves existing ordered and `TOP` report resources. Add a deterministic registry default even when the file has an authored order because the default is required and is used whenever the query must be wrapped.

## Runtime Request

The minimum public request is:

```json
{
  "action": "sql",
  "resource": "customer"
}
```

The only optional SQL action properties currently accepted are `filters`, `filterLogic`, `sort`, and `pagination`:

```json
{
  "action": "sql",
  "resource": "customer",
  "filters": [
    { "field": "Cust_Name", "operator": "LIKE", "value": "A%" },
    { "field": "StDate", "operator": "BETWEEN", "value": ["2021-04-01", "2022-03-31"] }
  ],
  "filterLogic": "AND",
  "sort": [{ "field": "MaximumBill", "direction": "DESC" }],
  "pagination": { "page": 1, "pageSize": 25 }
}
```

`filterLogic` defaults to `AND`; `AND` and `OR` are accepted case-insensitively and normalized to uppercase. SQL Resource Mode supports `=`, `!=`, `<>`, `>`, `<`, `>=`, `<=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `BETWEEN`, `NOT BETWEEN`, `IS NULL`, and `IS NOT NULL`. It does not accept the JSON Query Mode subquery operators `EXISTS` and `NOT EXISTS`.

`IN`/`NOT IN` require a non-empty value array. `BETWEEN`/`NOT BETWEEN` require exactly two values. NULL operators omit `value`; other operators require it. All supplied filter values become positional prepared parameters.

Pagination has no defaults: if `pagination` is present, both `page` and `pageSize` must be positive JSON integers. See [SQL Resource Files](SQL-Resource-Files.md#pagination) for execution details and [JSON Request Reference](JSON-Request-Reference.md) for the shared public field shapes.

## Complete Configuration Example

The repository's customer report is registered in `config/sql-resources.php`:

```php
'customer' => [
    'file' => QUERY_PATH . '/reports/customer.sql',
    'columns' => ['Cust_Name', 'TotalCustomers', 'MinimumBill', 'MaximumBill'],
    'filterColumns' => ['Cust_Name', 'StDate'],
    'filterValueTypes' => ['StDate' => 'integer-date'],
    'filterPlacement' => 'source',
    'defaultSort' => [['field' => 'Cust_Name', 'direction' => 'ASC']],
],
```

Its matching `queries/reports/customer.sql` is:

```sql
SELECT
    Cust_Name,
    COUNT(Cust_Name) AS TotalCustomers,
    MIN(Bill_Amt) AS MinimumBill,
    MAX(Bill_Amt) AS MaximumBill
FROM CustomerTable
/*__RUNTIME_FILTERS__*/
GROUP BY Cust_Name
```

A client can call it with:

```json
{
  "action": "sql",
  "resource": "customer",
  "filters": [
    { "field": "StDate", "operator": ">=", "value": "2021-04-01" }
  ],
  "sort": [{ "field": "Cust_Name", "direction": "ASC" }],
  "pagination": { "page": 1, "pageSize": 25 }
}
```

The backend replaces the marker with `WHERE [StDate] >= ?` and binds integer `20210401`; it does not insert the date into the SQL text.

## Security Boundary

The implemented boundary provides these controls:

- request validation rejects unknown SQL action properties and malformed resource, filter, sort, and pagination shapes;
- exact registry lookup prevents a client from choosing an unregistered resource;
- resolved files must remain beneath `Backend/queries`;
- runtime filter and sort fields must match their resource allowlists and are bracket-quoted by the repository;
- sort direction and filter operators come from fixed allowlists;
- runtime filter values use ODBC prepared `?` parameters;
- the client cannot submit SQL, file paths, credentials, or connection settings through this action.

The SQL file itself is trusted backend code. Statement analysis rejects non-SELECT main statements, multiple statements, and top-level `SELECT ... INTO`, but is not a complete SQL parser. Review resource files like application code and use a least-privilege database account. There is currently no application authentication, per-resource authorization, rate limiting, resource cache, or response-column filtering.

## Adding a New SQL Resource

1. Create a `.sql` file under `queries/`, conventionally `queries/reports/<resource-id>.sql`. Follow the statement and wrapping rules in [SQL Resource Files](SQL-Resource-Files.md).
2. Add an exact resource ID key to `config/sql-resources.php` and map `file` with `QUERY_PATH`.
3. Add every stable returned field/alias needed for sorting to `columns`. Ensure the SQL itself returns no sensitive fields.
4. Add a non-empty, deterministic `defaultSort` using fields from `columns`.
5. If filters are allowed, either rely on `columns` or add `filterColumns`. Use `filterPlacement: source` plus exactly one marker only when filtering must occur before aggregation. Add `integer-date` metadata only for integer-backed dates.
6. Run `php tests/run.php`. Add a repository test for the resource's resolution, generated SQL, parameters, and important response aliases. A live database check is still needed for the real schema and SQL Server execution plan.
7. Call `POST /api/index.php` with `{"action":"sql","resource":"<resource-id>"}` and only the runtime properties the resource allows.

## Troubleshooting

| Symptom | Actual response | Check |
|---|---|---|
| Resource ID contains a dot, slash, space, or leading underscore | HTTP 400, `INVALID_REQUEST`; detail path `resource` | Use `^[A-Za-z0-9][A-Za-z0-9_-]*$` |
| ID is valid but absent or has different case | HTTP 400, `INVALID_SQL_RESOURCE`; “Resource is not approved.” | Match the exact key in `config/sql-resources.php` |
| SQL file is missing, outside `queries/`, or a symlink resolves outside it | HTTP 500, generic `QUERY_ERROR` | Correct `file`; the detailed registry exception is written to `logs/` |
| Registry column/default/filter metadata is malformed | HTTP 500, generic `QUERY_ERROR` | Check identifier shapes, non-empty required arrays, default sort membership, types, and placement |
| Sort/filter field has invalid identifier syntax | HTTP 400, `INVALID_REQUEST` | Use an unqualified identifier beginning with a letter or underscore |
| Syntactically valid sort/filter field is not allowlisted | HTTP 400, `INVALID_SQL_RUNTIME_FIELD` | Add the correct output name to `columns` for sort or field to `filterColumns` for filter |
| Sort direction or filter operator/value shape is invalid | HTTP 400, `INVALID_REQUEST` | Use the documented directions/operators and array shapes |
| Integer-backed date is invalid | HTTP 400, `INVALID_SQL_RUNTIME_VALUE` | Send a real date as `YYYY-MM-DD` or `YYYYMMDD` |
| Source marker is missing, duplicated, or used in an output-filter resource | HTTP 500, generic `QUERY_ERROR` | Use exactly one marker for `source`, none for `output` |
| Main statement is not SELECT, the file has multiple statements, or SELECT uses top-level INTO | HTTP 500, generic `QUERY_ERROR` | Keep one read-only SELECT; a leading comment or WITH/CTE is allowed |
| Runtime controls are sent to a resource with authored OFFSET/FETCH | HTTP 400, `INVALID_SQL_PAGINATION` | Remove request filters/sort/pagination or remove authored pagination from the file |
| SQL Server rejects the statement or an alias does not exist | HTTP 500, generic `QUERY_ERROR` | Inspect the dated log in `logs/`, then run the file against the configured schema |
| Statement exceeds its configured execution limit | HTTP 504, `QUERY_ERROR` | Optimize the query or review `DB_QUERY_TIMEOUT_SECONDS` and deployment limits |

Internal exception text is logged but is deliberately not returned in generic query-error responses.
