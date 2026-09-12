# HTTP API

## Endpoint and transport

The filesystem entry point is `api/index.php`. When the repository root is the
web document root, use:

```http
POST /api/index.php
Content-Type: application/json
```

The bundled/local command uses `-t api`, making that same file available as
`POST /index.php`. The deployed URL therefore depends on web-server document-root
mapping; it is one entry script, not two API routes.

The script advertises `GET, POST, OPTIONS` for CORS and returns 200 immediately for `OPTIONS`. It does not otherwise enforce the HTTP method, but `POST` is the supported client convention because every operation requires a JSON request body. Allowed browser origins are currently hard-coded to `http://127.0.0.1:5173` and `http://localhost:5173`.

## Request flow and actions

The body must be one JSON object. The required `action` is one of:

- `select`
- `sql`
- `insert`, `update`, `delete`, `upsert`
- `union`, `unionAll`
- `procedure`, `function`, `tableFunction`
- `metadata.tables`, `metadata.columns`, `metadata.views`, `metadata.procedures`, `metadata.schema`

These 16 actions, including minimal/full requests, validation, responses, and
errors, are documented in [Action reference](Action-Reference.md). The exact
accepted field schema is [JSON request reference](JSON-Request-Reference.md).

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
default sort. Existing resources may use a separate `filterColumns` allowlist;
new complex resources may instead map logical filter names to server-owned SQL
expressions and explicit `output`, `where`, or `having` locations. An
`integer-date` mapping converts a validated `YYYY-MM-DD` UI value to its
`YYYYMMDD` integer parameter. SQL runtime filters support
comparisons, LIKE, IN, BETWEEN, and NULL checks. Filter values are prepared
parameters. Runtime sort remains limited to exposed output aliases and
`ASC`/`DESC`; pagination reuses the SQL Server compatibility-aware pagination
builder. The resource-file guide documents the implemented `TOP` optimization.

The frontend never sends those SQL expressions or locations. It sends only the
logical field, operator, and value. WHERE/HAVING expressions come from the
registered resource, and values remain prepared parameters. OR logic cannot
span multiple SQL locations because that would change its meaning.

Backend registration and file-authoring details are documented separately in
[SQL Resource Configuration](SQL-Resource-Configuration.md) and
[SQL Resource Files](SQL-Resource-Files.md).

The registered SQL owns static projections, joins, grouping, HAVING, CTEs,
subqueries, set operations, windows, and SQL Server-specific functions. It is
not constrained by JSON Query Mode's function/expression allowlists. Dynamic
query structure and free-text SQL are not SQL action properties; clients send
only the runtime properties documented above.

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

See [Response reference](Response-Reference.md) for exact per-action messages and
write response presence rules.

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

The complete code/status/handling table and current security boundary are in
[Validation, errors, and security](Validation-and-Errors.md).

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

`pagination` requires positive integer `page` and `pageSize`. SQL Server compatibility level 110+ uses `OFFSET/FETCH`; older compatibility levels use a `ROW_NUMBER()` wrapper. The backend normally runs a count query before the page query; the complete-first-page SQL-resource `TOP` optimization described above is the exception. SQL resources with authored OFFSET/FETCH run directly without runtime controls; combining authored and request pagination is rejected explicitly.

Public sorting uses validated logical fields or a selected alias and `ASC`/`DESC`; numeric positions such as `"1"` are rejected. Window functions likewise require a logical sort field. This prevents invalid SQL Server output such as `ROW_NUMBER() OVER (ORDER BY 1)`. If top-level `sort` is omitted, the builder supplies an order based on the first usable projection (or table metadata when needed); grouped requests default to the first group field.

## Capability and limitation references

The authoritative cross-mode comparison is [Capability matrix](Capability-Matrix.md).
Use [Current limitations](Limitations.md) for intentional public boundaries and
[Query function reference](Query-Functions.md) for the exact usable function set,
including the internal-only `TIMEFROMPARTS` mismatch.

The shared source validator accepts `source.alias` for routines and
`metadata.columns`, but normalization ignores it; clients should omit it.
Routine `parameters` should be a JSON list because placeholders are positional.
