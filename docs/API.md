# HTTP API

## Endpoint and transport

The entry point is `api/index.php`. Use:

```http
POST /api/index.php
Content-Type: application/json
```

The script advertises `GET, POST, OPTIONS` for CORS and returns 200 immediately for `OPTIONS`. It does not otherwise enforce the HTTP method, but `POST` is the supported client convention because every operation requires a JSON request body. Allowed browser origins are currently hard-coded to `http://127.0.0.1:5173` and `http://localhost:5173`.

## Request flow and actions

The body must be one JSON object. The required `action` is one of:

- `select`
- `sql`
- `insert`, `update`, `delete`, `upsert`
- `union`, `unionAll`
- `procedure`, `function`, `tableFunction`
- `metadata.tables`, `metadata.columns`, `metadata.views`, `metadata.procedures`, `metadata.schema`

For SELECT, `source.table` and a non-empty `fields` array are also required. Refer to [JSON-Request-Reference.md](JSON-Request-Reference.md) for every field and default.

```json
{
  "action": "select",
  "source": { "table": "Items", "alias": "I" },
  "fields": ["I.ItemCode", { "field": "I.Description", "alias": "ItemName" }],
  "sort": [{ "field": "I.ItemCode", "direction": "ASC" }],
  "pagination": { "page": 1, "pageSize": 25 }
}
```

Unknown properties are rejected. Raw SQL, arbitrary SELECT parameters, client-supplied controller names, and internal query-builder keys are not part of the public contract.

### Controlled SQL resource request

```json
{
  "action": "sql",
  "resource": "item",
  "filters": [{ "field": "Item_Desc", "operator": "LIKE", "value": "%pen%" }],
  "sort": [{ "field": "Item_Code", "direction": "DESC" }],
  "pagination": { "page": 1, "pageSize": 25 },
  "filterLogic": "AND"
}
```

`resource` must match the exact ID of an entry in `config/sql-resources.php`;
paths, filenames, URLs, SQL text, connection settings, and unknown properties
are rejected. Each entry maps an internal file to exposed output aliases and a
default sort. Runtime filters may use a separate `filterColumns` allowlist; an
`integer-date` entry in `filterValueTypes` converts a validated `YYYY-MM-DD` UI
value to its `YYYYMMDD` integer parameter. SQL runtime filters support
comparisons, LIKE, IN, BETWEEN, and NULL checks. Filter values are prepared
parameters. Runtime sort remains limited to exposed output aliases and
`ASC`/`DESC`; pagination reuses the SQL Server compatibility-aware pagination
builder. The resource-file guide documents the implemented `TOP` optimization.

Backend registration and file-authoring details are documented separately in
[SQL Resource Configuration](SQL-Resource-Configuration.md) and
[SQL Resource Files](SQL-Resource-Files.md).

The registered SQL owns static projections, joins, grouping, HAVING, and other
business logic. Dynamic grouping and free-text search are not SQL action
properties. Clients should send only the runtime properties documented above.

### CRUD write requests

Writes use an exact ID from `config/write-resources.php`; they never accept a
table or schema name from the client. The shipped registry is empty so a new
deployment denies every write until an administrator explicitly maps a resource
to a table and its writable/filterable columns.

```json
{
  "action": "insert",
  "resource": "customers",
  "data": { "customerCode": "C001", "name": "John", "email": null }
}
```

```json
{
  "action": "update",
  "resource": "customers",
  "data": { "email": "new@example.com" },
  "filters": [{ "field": "id", "operator": "=", "value": 10 }]
}
```

```json
{
  "action": "delete",
  "resource": "customers",
  "filters": [{ "field": "id", "operator": "=", "value": 10 }]
}
```

```json
{
  "action": "upsert",
  "resource": "customers",
  "data": { "customerCode": "C001", "name": "John" },
  "keys": ["customerCode"]
}
```

Each request changes one input object; bulk writes are not implemented. INSERT
and UPSERT require every non-nullable, non-generated column that lacks a default.
Identity, computed, timestamp, and rowversion columns cannot be supplied.
UPDATE and DELETE require a non-empty, valid `filters` array and never fall back
to a full-table statement. Write filters support comparisons, LIKE, IN, BETWEEN,
and NULL operators, but not subqueries or EXISTS. Columns, types, nullability,
lengths, defaults, and generated status are checked against SQL Server metadata.
All data, key, and filter values are prepared parameters.

UPSERT `keys` must exactly match the server-configured key set and each key value
must be present and non-null. The implementation is one SQL Server `MERGE` with
`HOLDLOCK`; a matching unfiltered UNIQUE/PRIMARY KEY index is verified from live
metadata. It does not
open a transaction, and SQL Server MERGE-specific operational caveats still
apply. See [Write Resource Configuration](Write-Resource-Configuration.md).

## Success response

All controllers use the same envelope:

```json
{
  "success": true,
  "message": "Data Loaded Successfully",
  "data": [{ "ItemCode": "A001", "ItemName": "Example" }],
  "meta": {
    "page": 1,
    "pageSize": 25,
    "totalRows": 37,
    "rowsReturned": 1,
    "executionTime": 2.41
  }
}
```

- `data` is always an array.
- `page` and `pageSize` copy the public pagination request, or are `null`.
- Paginated SELECT and SQL-resource requests normally obtain `totalRows` with a separate count query. A complete first-page `TOP` resource can infer it from `rowsReturned`; without pagination it also defaults to `rowsReturned`.
- `executionTime` is elapsed database execution time in milliseconds, rounded to two decimals, or `null` if the underlying result did not supply it.
- `rowsReturned` counts rows collected across the executed result.
- Write responses keep `rowsReturned: 0`, add `meta.affectedRows`, and put one
  operation summary in `data`. INSERT and an inserting UPSERT also include
  `generatedId` when the resource declares a verified identity column.
- Query results do not include a separate column-schema/column-metadata property. The `metadata.columns` action returns column rows as ordinary `data`.
- SELECT/UNION messages are `Data Loaded Successfully`; routine actions use their corresponding executed-successfully message; metadata actions use their loaded-successfully message.
- Write messages are `Data Inserted Successfully`, `Data Updated Successfully`,
  `Data Deleted Successfully`, and `Data Upserted Successfully`.

A successful INSERT with a configured identity is represented as:

```json
{
  "success": true,
  "message": "Data Inserted Successfully",
  "data": [{ "operation": "insert", "affectedRows": 1, "generatedId": 42 }],
  "meta": {
    "page": null,
    "pageSize": null,
    "totalRows": 0,
    "rowsReturned": 0,
    "executionTime": 1.27,
    "affectedRows": 1
  }
}
```

## Error responses

Malformed JSON is HTTP 400:

```json
{
  "success": false,
  "message": "Invalid JSON request.",
  "error": { "code": "INVALID_JSON", "details": [] },
  "data": []
}
```

Contract validation failures are HTTP 400 and include one or more path/message details:

```json
{
  "success": false,
  "message": "Invalid request.",
  "error": {
    "code": "INVALID_REQUEST",
    "details": [
      { "path": "pagination.page", "message": "Must be a positive integer." }
    ]
  },
  "data": []
}
```

Unhandled builder, metadata, connection, or execution failures are HTTP 500:

```json
{
  "success": false,
  "message": "Query execution failed.",
  "error": { "code": "QUERY_ERROR", "details": [] },
  "data": []
}
```

The response does not expose the underlying exception. The exception handler writes details to the dated file in `logs/`.

Write validation additionally uses `INVALID_WRITE_RESOURCE`,
`INVALID_WRITE_COLUMN`, `INVALID_WRITE_VALUE`, `MISSING_REQUIRED_FIELD`,
`INVALID_UPSERT_KEY`, and `UNSAFE_WRITE`, all as HTTP 400. Recognized duplicate
key and other constraint failures are safe HTTP 409 responses with
`DUPLICATE_KEY` or `CONSTRAINT_VIOLATION`. Other database failures remain the
generic HTTP 500 `QUERY_ERROR`; no SQL Server message is returned.

## Pagination and ordering

`pagination` requires positive integer `page` and `pageSize`. SQL Server compatibility level 110+ uses `OFFSET/FETCH`; older compatibility levels use a `ROW_NUMBER()` wrapper. The backend normally runs a count query before the page query; the complete-first-page SQL-resource `TOP` optimization described above is the exception.

Public sorting uses validated logical fields or a selected alias and `ASC`/`DESC`; numeric positions such as `"1"` are rejected. Window functions likewise require a logical sort field. This prevents invalid SQL Server output such as `ROW_NUMBER() OVER (ORDER BY 1)`. If top-level `sort` is omitted, the builder supplies an order based on the first usable projection (or table metadata when needed); grouped requests default to the first group field.

## Capability matrix

`Supported` means the feature passes the public validator/normalizer and has a current builder/execution path. SQL Server capabilities that are not exposed remain unsupported by this API.

| Feature | Backend support | Public JSON representation | Validation | Notes |
|---|---|---|---|---|
| SELECT | Supported | `action: "select"`, `source`, `fields` | Table/field identifier shape, then live metadata | Existing JSON query action |
| Controlled SQL resource | Supported | `action: "sql"`, `resource` | Explicit resource registry plus sort/filter field allowlists | No raw SQL or client paths |
| INSERT | Supported | `action: "insert"`, `resource`, `data` | Write registry plus live column metadata | Single object; prepared values; safe identity output |
| UPDATE | Supported | `action: "update"`, `resource`, `data`, non-empty `filters` | Writable/filterable allowlists plus live types | Full-table UPDATE rejected |
| DELETE | Supported | `action: "delete"`, `resource`, non-empty `filters` | Filter allowlist plus live types | Full-table DELETE rejected |
| UPSERT | Supported | `action: "upsert"`, `resource`, `data`, `keys` | Exact configured key set plus live types | SQL Server MERGE/HOLDLOCK; database unique constraint required |
| DISTINCT | Supported | `distinct: true` | Boolean | Default `false` |
| TOP | Supported | `limit: 10` | Positive integer | Normalizes to internal `top` |
| Column/table aliases | Supported | field `alias`; `source.alias` | Identifier | Selected aliases may be used by top-level sort |
| CASE | Supported | field object with `case.when`, optional `else`, `alias` | Comparison conditions only | CASE values are rendered as controlled literals |
| Arithmetic expressions | Supported | `expression: {left, operator, right}` | Operands are numbers/identifiers; `+ - * / %` | One binary expression level in public shape |
| COUNT/SUM/AVG/MIN/MAX | Supported | field `function`, `field`, optional `alias` | Function allow-list and metadata | `COUNT` accepts `*` |
| STRING_AGG | Supported | plus `separator`, optional `sort` | Separator and ordering validated publicly; field checked through metadata | SQL Server syntax |
| String functions | Supported | function field object | Allow-list plus function-specific required-option validation | UPPER, LOWER, LTRIM, RTRIM, TRIM, LEN, CONCAT, LEFT, RIGHT, SUBSTRING, REPLACE, CHARINDEX, PATINDEX, FORMAT |
| Date/time functions | Supported, except TIMEFROMPARTS | function field object | Allow-list, date-part, endpoint, numeric-part, and style validation | YEAR, MONTH, DAY convert integer `YYYYMMDD` values using style 112; see JSON reference |
| Math functions | Supported | function field object | Allow-list | ABS, ROUND, CEILING, FLOOR, POWER, SQRT, EXP, LOG |
| Conditional functions | Supported | `IIF`, `CHOOSE` field objects | Public validation covers condition/value expression shapes and required options | CASE is also supported |
| CAST/CONVERT | Supported | `datatype`, optional CONVERT `style` | Datatype pattern allow-list | No free-form SQL datatype expression |
| NULL functions | Supported | COALESCE/ISNULL/NULLIF field objects | Function allow-list plus public required-option/expression validation | COALESCE public `fields` are identifiers |
| WHERE comparisons | Supported | `filters[]` | `= != <> > < >= <=` | Values use prepared placeholders |
| LIKE/NOT LIKE | Supported | `filters[]` | Operator allow-list | Pattern is a prepared value |
| IN/NOT IN | Supported | array `value` or `query` | Non-empty array or valid nested SELECT | Prepared list values |
| BETWEEN/NOT BETWEEN | Supported | two-element `value` | Exactly two values | Integer date columns convert `YYYY-MM-DD` to `YYYYMMDD` |
| IS NULL/IS NOT NULL | Supported | filter without value | Operator allow-list | No placeholder |
| EXISTS/NOT EXISTS | Supported | filter `query`, no `field` required | Nested SELECT required | Filter subquery only |
| INNER/LEFT/RIGHT JOIN | Supported | `joins[]` | Valid source plus live metadata checks for both logical columns; equality only | One `on` equality per join |
| FULL/CROSS JOIN | Not supported | None | Rejected join type | Not exposed |
| GROUP BY | Supported | `groupBy[]` | Identifier plus metadata | Array of fields |
| HAVING | Supported | `having[]` | Aggregate + comparison + value | Conditions are combined with AND |
| ORDER BY | Supported | `sort[]` | Logical field/alias and ASC/DESC | Multiple fields supported; direction defaults ASC |
| Positional ORDER BY | Internal compatibility only | None | Numeric public sort fields rejected | Internal positions are resolved to real fields in window contexts |
| Pagination | Supported | `pagination.page/pageSize` | Both positive integers | Count + OFFSET/FETCH or ROW_NUMBER fallback |
| Window functions | Supported | field `function` plus `sort` | Function allow-list and mandatory sort | ROW_NUMBER, RANK, DENSE_RANK, NTILE, LAG, LEAD, FIRST_VALUE, LAST_VALUE |
| Window PARTITION BY | Not supported | None | `partitionBy` rejected | Only window ORDER BY is exposed |
| Filter subqueries | Supported | IN/NOT IN/EXISTS/NOT EXISTS `query` | Nested SELECT validation; IN forms require one explicit field | Nested action/sort/pagination/CTE are rejected; no general subqueries |
| CTE | Supported | `with: {name, query}` | One named SELECT body; inferred output fields are validated | Count pagination keeps the CTE in scope |
| Recursive CTE | Supported | `with: {name, anchor, recursive}` | Both SELECT bodies and compatible field counts required | Recursive self-reference uses the anchor projection and UNION ALL |
| UNION/UNION ALL | Supported | top-level `action` plus `queries` | At least one SELECT body; branch field counts must match | Branch actions/sort/pagination/CTEs are rejected; SQL Server validates type compatibility |
| INTERSECT/EXCEPT | Internal builder only | None | Public action rejected | Not a public API feature |
| Stored procedure | Supported | `procedure` action, `source.procedure`, `parameters` | Identifier and array parameters | Positional prepared parameters |
| Scalar function | Supported | `function` action, `source.function`, `parameters` | Identifier and array parameters | Returns `Result` column |
| Table-valued function | Supported | `tableFunction` action | Identifier and array parameters | Executes `SELECT * FROM function(...)` |
| SELECT parameters | Values only | filter/HAVING values | Prepared by builders | No arbitrary public `parameters` on SELECT |
| Metadata | Supported | five `metadata.*` actions | Action allow-list; columns requires source table | Database-backed |
| Column description metadata | Not supported in query envelope | None | N/A | Use `metadata.columns` separately |
| Validation/error envelope | Supported | N/A | Unknown properties and invalid shapes rejected | 400 contract errors; generic 500 query errors |
| Transactions | Not supported | None | N/A | Planned for Phase 3; CRUD actions execute independently |

Known contract boundary: `TIMEFROMPARTS` is named in the function allow-list and exists in the internal builder, but its required `fractions` property is not accepted by the public field-property allow-list. It is therefore not a usable public feature and is not shown as a supported example.

The source validator technically accepts `source.alias` for routines and `metadata.columns`; normalization ignores that alias, so it has no public effect. Routine `parameters` should be a JSON list because placeholders are positional.
