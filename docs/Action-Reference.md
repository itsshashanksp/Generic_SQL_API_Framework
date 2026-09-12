# Public action reference

This is the index of every public `action` accepted by the Universal JSON
Contract. Requests are JSON objects sent to the deployed `api/index.php` entry
script with `Content-Type: application/json`. Unknown properties are rejected.

Common response fields are defined once in [Response reference](Response-Reference.md).
Unless a section says otherwise, request-shape failures are HTTP 400
`INVALID_REQUEST` and execution failures are HTTP 500 `QUERY_ERROR`.

## `select`

### Purpose

Build and execute a validated SQL Server SELECT from public JSON Query Mode.

### Request

Required: `action`, `source`, `fields`. Optional: `filters`, `joins`, `groupBy`,
`having`, `sort`, `pagination`, `distinct`, `limit`, `filterLogic`, `with`.

### Minimal example

```json
{"action":"select","source":{"table":"Items"},"fields":["ItemCode","Description"]}
```

### Full example

```json
{
  "action": "select",
  "source": { "table": "Items", "alias": "I" },
  "fields": [
    "I.ItemCode",
    { "field": "I.Description", "alias": "ItemName" },
    { "function": "COUNT", "field": "*", "alias": "RowCount" }
  ],
  "filters": [{ "field": "I.Status", "operator": "=", "value": "Active" }],
  "groupBy": ["I.ItemCode", "I.Description"],
  "having": [{ "function": "COUNT", "field": "*", "operator": ">", "value": 0 }],
  "sort": [{ "field": "ItemName", "direction": "ASC" }],
  "pagination": { "page": 1, "pageSize": 25 },
  "filterLogic": "AND"
}
```

### Parameters

| Field | Type | Required | Description |
|---|---|---:|---|
| `source` | object | yes | `table` and optional `alias`. |
| `fields` | non-empty array | yes | Identifiers or validated field/function/expression objects. |
| `filters`, `joins`, `having`, `sort` | arrays | no | Validated query clauses. |
| `groupBy` | identifier array | no | Grouping fields. |
| `pagination` | object | no | Positive integer `page` and `pageSize`. |
| `distinct` | boolean | no | Adds DISTINCT; default false. |
| `limit` | positive integer | no | Adds TOP. |
| `filterLogic` | string | no | `AND` or `OR`; default AND. |
| `with` | object | no | One standard or recursive CTE. |

### Validation

Identifiers and expression shapes are allowlisted, and physical tables/columns
are checked against live metadata. See [JSON request reference](JSON-Request-Reference.md).

### Response

Returns selected rows in `data`, message `Data Loaded Successfully`, and query
metadata. Pagination supplies a total count.

### Errors

Invalid shapes/identifiers use `INVALID_REQUEST`; metadata, generation, or SQL
failures use the non-disclosing `QUERY_ERROR`.

### Notes

This is structured query composition, not arbitrary SQL. See [JSON Query Mode](Query-Mode.md).

## `sql`

### Purpose

Discover and execute a server-owned, read-only SQL Resource.

### Request

Required: `action`, `resource`. Optional: `execution`, `filters`, `sort`,
`pagination`, and `filterLogic`.

### Minimal example

```json
{"action":"sql","resource":"reports/item"}
```

### Full example

```json
{
  "action": "sql",
  "resource": "reports/customer",
  "execution": {
    "columns": [
      "Cust_Name",
      "TotalCustomers",
      "MinimumBill",
      "MaximumBill"
    ],
    "filters": {
      "StDate": {
        "expression": "StDate",
        "placement": "source",
        "valueType": "integer-date"
      },
      "MinimumCustomers": {
        "expression": "COUNT(*)",
        "placement": "having"
      }
    },
    "defaultSort": [
      { "field": "Cust_Name", "direction": "ASC" }
    ]
  },
  "filters": [
    {
      "field": "StDate",
      "operator": "BETWEEN",
      "value": ["2021-04-01", "2022-03-31"]
    },
    {
      "field": "MinimumCustomers",
      "operator": ">=",
      "value": 2
    }
  ],
  "pagination": { "page": 1, "pageSize": 25 },
  "filterLogic": "AND"
}
```

### Parameters

| Field | Type | Required | Description |
|---|---|---:|---|
| `resource` | string | yes | Safe relative SQL-file ID without `.sql`. |
| `execution` | object | no | Validated output/filter/default-sort metadata. |
| `execution.columns` | non-empty identifier array | conditional | Output aliases used by runtime output filtering/sorting. |
| `execution.filters` | object | no | Logical mappings with `expression`, `placement`, and optional `valueType`. |
| `execution.defaultSort` | non-empty sort array | no | Default ordering; requires `execution.columns`. |
| `filters` | array | no | Runtime values for permitted output/mapped fields. |
| `sort` | array | no | Runtime order using execution output columns. |
| `pagination` | object | no | Positive page/pageSize; requires an approved sort. |
| `filterLogic` | string | no | AND or OR; default AND. |

### Validation

The resolver confines discovery to the configured root, excludes internal
directories, accepts only slash-separated logical IDs, and resolves only SQL
files. Execution expressions use a narrow identifier/aggregate grammar. Output
fields, operators, values, directions, placement, and integer dates are checked.

### Response

Returns resource rows with `Data Loaded Successfully` in the standard query envelope.

### Errors

In addition to common errors: `INVALID_SQL_RESOURCE`,
`INVALID_SQL_RUNTIME_FIELD`, `INVALID_SQL_RUNTIME_VALUE`,
`INVALID_SQL_RUNTIME_FILTER`, and `INVALID_SQL_PAGINATION` (HTTP 400).

### Notes

A new file such as `queries/reports/new-report.sql` works as
`reports/new-report` without registration. Legacy registry IDs continue to
work. The backend does not parse general SQL projections; supply
`execution.columns` when runtime controls need output-name validation. Clients
never send SQL text or filesystem paths. See [SQL Resource Mode](SQL-Resource-Mode.md).


## `insert`

### Purpose

Insert one object through an approved Write resource.

### Request

Required fields are `action`, `resource`, and `data`; no optional properties.

### Minimal example

```json
{"action":"insert","resource":"crud-test","data":{"CustomerCode":"C001","Name":"John"}}
```

### Full example

```json
{
  "action": "insert",
  "resource": "crud-test",
  "data": {
    "CustomerCode": "C001",
    "Name": "John",
    "Email": "john@example.com",
    "Age": 30,
    "Status": "Active"
  }
}
```

### Parameters

| Field | Type | Required | Description |
|---|---|---:|---|
| `resource` | string | yes | Approved Write resource ID. |
| `data` | non-empty object | yes | One scalar/null value per configured writable column. |

### Validation

The resource must enable INSERT. Live metadata enforces types, lengths,
nullability, required fields, defaults, and generated-column protection.

### Response

`data` contains `operation: "insert"` and `affectedRows`; `generatedId` is
conditional as described in [Response reference](Response-Reference.md).

### Errors

Write-specific validation and conflict codes are documented in
[CRUD](CRUD.md).

### Notes

Bulk inserts and client-selected return columns are unsupported.

## `update`

### Purpose

Update one or more matching rows through an approved Write resource.

### Request

Required fields are `action`, `resource`, `data`, and non-empty `filters`;
`filterLogic` is optional.

### Minimal example

```json
{"action":"update","resource":"crud-test","data":{"Status":"Inactive"},"filters":[{"field":"Id","operator":"=","value":42}]}
```

### Full example

```json
{
  "action": "update",
  "resource": "crud-test",
  "data": { "Email": "new@example.com", "Status": "Active" },
  "filters": [
    { "field": "CustomerCode", "operator": "=", "value": "C001" },
    { "field": "Status", "operator": "!=", "value": "Deleted" }
  ],
  "filterLogic": "AND"
}
```

### Parameters

| Field | Type | Required | Description |
|---|---|---:|---|
| `resource` | string | yes | Resource enabling UPDATE. |
| `data` | non-empty object | yes | Configured writable fields. |
| `filters` | non-empty array | yes | Configured filter fields; never optional in practice. |
| `filterLogic` | string | no | AND or OR; default AND. |

### Validation

The resource/action/columns and live types must be valid. Missing/empty targeting
is HTTP 400 `UNSAFE_WRITE`.

### Response

Success returns operation `update` and the actual `affectedRows`.

### Errors

See [CRUD](CRUD.md) for write validation and conflict codes.

### Notes

No full-table UPDATE fallback exists; filters may legitimately match multiple rows.

## `delete`

### Purpose

Delete matching rows through an approved Write resource.

### Request

Required fields are `action`, `resource`, and non-empty `filters`; `filterLogic`
is optional. `data` is not accepted.

### Minimal example

```json
{"action":"delete","resource":"crud-test","filters":[{"field":"Id","operator":"=","value":42}]}
```

### Full example

```json
{
  "action": "delete",
  "resource": "crud-test",
  "filters": [
    { "field": "Status", "operator": "=", "value": "Inactive" },
    { "field": "Age", "operator": ">=", "value": 18 }
  ],
  "filterLogic": "AND"
}
```

### Parameters

| Field | Type | Required | Description |
|---|---|---:|---|
| `resource` | string | yes | Resource enabling DELETE. |
| `filters` | non-empty array | yes | Required targeting conditions. |
| `filterLogic` | string | no | AND or OR; default AND. |

### Validation

The resource/action/filter columns and live values must be valid. Missing/empty
targeting is `UNSAFE_WRITE`.

### Response

Success returns operation `delete` and `affectedRows`.

### Errors

See [CRUD](CRUD.md) for write validation and conflict codes.

### Notes

No full-table DELETE fallback exists; filters may match multiple rows.

## `upsert`

### Purpose

Update an existing row or insert a new row by the configured unique key set.

### Request

Required fields are `action`, `resource`, `data`, and `keys`; no filters or other
optional properties are accepted.

### Minimal example

```json
{"action":"upsert","resource":"crud-test","data":{"CustomerCode":"C001","Name":"John"},"keys":["CustomerCode"]}
```

### Full example

```json
{
  "action": "upsert",
  "resource": "crud-test",
  "data": {
    "CustomerCode": "C001",
    "Name": "John",
    "Email": "john@example.com",
    "Age": 30,
    "Status": "Active"
  },
  "keys": ["CustomerCode"]
}
```

### Parameters

| Field | Type | Required | Description |
|---|---|---:|---|
| `resource` | string | yes | Resource enabling UPSERT with configured keys. |
| `data` | non-empty object | yes | Includes every key and at least one non-key value. |
| `keys` | non-empty string list | yes | Must exactly match the server-configured set. |

### Validation

Keys must be unique, configured, present and non-null in `data`, and backed by
an exact unfiltered UNIQUE/PRIMARY KEY index discovered from live metadata.

### Response

Returns operation `upsert` and affected count. An inserting path can include a
configured identity `generatedId`.

### Errors

Key failures use `INVALID_UPSERT_KEY`; see [CRUD](CRUD.md) for other write codes.

### Notes

The implementation is one SQL Server `MERGE ... WITH (HOLDLOCK)` statement; it
does not open an API transaction and retains SQL Server MERGE operational
considerations.

## `union` and `unionAll`

### Purpose

Combine JSON Query Mode SELECT bodies with UNION (deduplicating) or UNION ALL.

### Request

Both actions accept only `action` and required non-empty `queries`.

### Minimal example

```json
{"action":"union","queries":[{"source":{"table":"Items"},"fields":["ItemCode"]}]}
```

### Full example

```json
{
  "action": "unionAll",
  "queries": [
    { "source": { "table": "Items" }, "fields": ["ItemCode", "Description"] },
    { "source": { "table": "ArchivedItems" }, "fields": ["ItemCode", "Description"] }
  ]
}
```

### Parameters

| Field | Type | Required | Description |
|---|---|---:|---|
| `queries` | non-empty array | yes | SELECT bodies without `action`. |

### Validation

Branches must have compatible projection counts. Branch actions, sort,
pagination, and CTEs are rejected.

### Response

Results use `Data Loaded Successfully` and the standard query envelope.

### Errors

Invalid branch shape/count uses `INVALID_REQUEST`; execution uses `QUERY_ERROR`.

### Notes

There is no top-level sorting/pagination and no public INTERSECT/EXCEPT. See
[Set operations](Set-Operations.md).

## `procedure`

### Purpose

Execute a named SQL Server stored procedure with positional parameters.

### Request

Required: `action`, `source.procedure`. Optional: `parameters`.

### Minimal example

```json
{"action":"procedure","source":{"procedure":"dbo.RefreshReport"}}
```

### Full example

```json
{"action":"procedure","source":{"procedure":"dbo.RunReport"},"parameters":[2026,true]}
```

### Parameters

| Field | Type | Required | Description |
|---|---|---:|---|
| `source.procedure` | identifier | yes | Procedure name, optionally schema-qualified. |
| `parameters` | array | no | Positional values; default empty. Use a JSON list. |

### Validation

The identifier is shape-validated and parameter values are prepared.

### Response

Returned result rows use `Procedure Executed Successfully`.

### Errors

Invalid shape is `INVALID_REQUEST`; database/signature failure is `QUERY_ERROR`.

### Notes

The API does not discover parameter names, support named/output parameters, or
independently authorize routine names.

## `function`

### Purpose

Execute a SQL Server scalar function as `SELECT function(...) AS Result`.

### Request

Required: `action`, `source.function`. Optional: `parameters`.

### Minimal example

```json
{"action":"function","source":{"function":"dbo.CurrentScore"}}
```

### Full example

```json
{"action":"function","source":{"function":"dbo.Score"},"parameters":[42]}
```

### Parameters

| Field | Type | Required | Description |
|---|---|---:|---|
| `source.function` | identifier | yes | Function name, optionally schema-qualified. |
| `parameters` | array | no | Positional prepared values; default empty. |

### Validation

The function identifier is shape-validated and parameter values are prepared.

### Response

Returns a row with database column `Result` and message
`Function Executed Successfully`.

### Errors

Invalid shape is `INVALID_REQUEST`; routine existence/signature failure becomes
generic `QUERY_ERROR`.

### Notes

There is no routine allowlist beyond identifier validation.

## `tableFunction`

### Purpose

Execute a table-valued SQL Server function as `SELECT * FROM function(...)`.

### Request

Required: `action`, `source.function`. Optional: `parameters`.

### Minimal example

```json
{"action":"tableFunction","source":{"function":"dbo.ActiveRows"}}
```

### Full example

```json
{"action":"tableFunction","source":{"function":"dbo.RowsForYear"},"parameters":[2026]}
```

### Parameters

The fields are the same as `function`: required `source.function` and optional
positional `parameters` list.

### Validation

The function identifier is shape-validated and parameter values are prepared.

### Response

Returns function rows with `Table Function Executed Successfully`.

### Errors

Invalid shape is `INVALID_REQUEST`; database/signature failure is `QUERY_ERROR`.

### Notes

No runtime filter, sort, or pagination fields are accepted. See
[Metadata and routines](Metadata-and-Routines.md).

## `metadata.tables`

### Purpose

List SQL Server base-table names.

### Request

Only `action` is accepted.

### Minimal example

```json
{"action":"metadata.tables"}
```

### Full example

The full request is identical to the minimal request; there are no options.

### Parameters

| Field | Type | Required | Description |
|---|---|---:|---|
| `action` | string | yes | Exactly `metadata.tables`. |

### Validation

Any additional property is rejected.

### Response

Rows are shaped as `{"TABLE_NAME":"Items"}`; message:
`Tables Loaded Successfully`.

### Errors

Invalid shape is `INVALID_REQUEST`; database failure is `QUERY_ERROR`.

### Notes

Only `INFORMATION_SCHEMA.TABLES` rows with type `BASE TABLE` are returned.

## `metadata.columns`

### Purpose

List a named table's columns in ordinal order.

### Request

Required: `action` and `source.table`. No other top-level fields.

### Minimal example

```json
{"action":"metadata.columns","source":{"table":"Items"}}
```

### Full example

The full request is identical; there are no output-selection options.

### Parameters

| Field | Type | Required | Description |
|---|---|---:|---|
| `source.table` | identifier | yes | Table name bound to the metadata query. |

### Validation

The source must have a valid table identifier. `source.alias` technically
passes shared validation but is discarded and should be omitted.

### Response

Rows contain `COLUMN_NAME`, `DATA_TYPE`, and `IS_NULLABLE`; message:
`Columns Loaded Successfully`.

### Errors

Invalid/missing source is `INVALID_REQUEST`; database failure is
`QUERY_ERROR`.

### Notes

This describes a physical table, not an arbitrary query result.

## `metadata.views`

### Purpose

List SQL Server view names.

### Request

Only `action` is accepted.

### Minimal example

```json
{"action":"metadata.views"}
```

### Full example

The full request is identical.

### Parameters

| Field | Type | Required | Description |
|---|---|---:|---|
| `action` | string | yes | Exactly `metadata.views`. |

### Validation

Any additional property is rejected.

### Response

Rows contain `TABLE_NAME`; message: `Views Loaded Successfully`.

### Errors

Invalid shape is `INVALID_REQUEST`; database failure is `QUERY_ERROR`.

### Notes

View definitions and columns are not returned.

## `metadata.procedures`

### Purpose

List SQL Server stored-procedure names.

### Request

Only `action` is accepted.

### Minimal example

```json
{"action":"metadata.procedures"}
```

### Full example

The full request is identical.

### Parameters

| Field | Type | Required | Description |
|---|---|---:|---|
| `action` | string | yes | Exactly `metadata.procedures`. |

### Validation

Any additional property is rejected.

### Response

Rows contain `ROUTINE_NAME`; message:
`Stored Procedures Loaded Successfully`.

### Errors

Invalid shape is `INVALID_REQUEST`; database failure is `QUERY_ERROR`.

### Notes

Signatures and parameters are not returned.

## `metadata.schema`

### Purpose

List all table/column/type/ordinal rows.

### Request

Only `action` is accepted.

### Minimal example

```json
{"action":"metadata.schema"}
```

### Full example

The full request is identical.

### Parameters

| Field | Type | Required | Description |
|---|---|---:|---|
| `action` | string | yes | Exactly `metadata.schema`. |

### Validation

Any additional property is rejected.

### Response

Rows contain `TABLE_NAME`, `COLUMN_NAME`, `DATA_TYPE`, and
`ORDINAL_POSITION`; message: `Schema Loaded Successfully`.

### Errors

Invalid shape is `INVALID_REQUEST`; database failure is `QUERY_ERROR`.

### Notes

The result is a flat ordered row list, not a nested schema document. Metadata
actions expose catalog names to every caller because the API currently has no
authentication/authorization. See
[Metadata and routines](Metadata-and-Routines.md).
