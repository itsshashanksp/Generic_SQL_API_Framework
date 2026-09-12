# CRUD / Write API

The Write API performs registered, single-object INSERT, UPDATE, DELETE, and
UPSERT operations. It is deny-by-default: the shipped `config/write-resources.php`
is empty, so no table is writable until an administrator adds an allowlisted
resource.

## Resource contract

```php
'crud-test' => [
    'schema' => 'dbo',
    'table' => 'ApiCrudTest',
    'actions' => ['insert', 'update', 'delete', 'upsert'],
    'columns' => ['CustomerCode', 'Name', 'Email', 'Age', 'Status'],
    'filterColumns' => ['Id', 'CustomerCode', 'Name', 'Email', 'Age', 'Status'],
    'keys' => ['CustomerCode'],
    'identityColumn' => 'Id',
],
```

`schema` defaults to `dbo`. `actions` is a non-empty subset of the four actions.
`columns` is the writable allowlist; `filterColumns` defaults to it. `keys` must
be a unique subset of `columns`, and an UPSERT-enabled resource must have at least
one. `identityColumn` is null or a configured column. All names are unqualified
SQL identifiers and lists are case-insensitively unique.

The resolved resource is checked against live SQL Server metadata on every write.
Configured columns must exist. The declared identity must really be an identity.
The backend blocks identity, computed, generated-always, hidden, timestamp, and
rowversion writes. Required columns, nullability, type/range/format, and lengths
are enforced; database defaults are used by omitting a field.

## INSERT

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

INSERT accepts no filters, keys, source, or return-field selection. Data is a
non-empty object of scalar/null values. Success:

```json
{
  "success": true,
  "message": "Data Inserted Successfully",
  "data": [{ "operation": "insert", "affectedRows": 1, "generatedId": 42 }],
  "meta": {
    "page": null, "pageSize": null, "totalRows": 0,
    "rowsReturned": 0, "executionTime": 1.27, "affectedRows": 1
  }
}
```

`generatedId` appears only when a verified configured identity is returned.

## UPDATE

```json
{
  "action": "update",
  "resource": "crud-test",
  "data": { "Email": "new@example.com", "Status": "Active" },
  "filters": [{ "field": "CustomerCode", "operator": "=", "value": "C001" }],
  "filterLogic": "AND"
}
```

Both `data` and `filters` must be non-empty. Success data is
`[{"operation":"update","affectedRows":1}]`, and `meta.affectedRows` matches.
The count may be 0 or more; filters do not promise one-row targeting.

## DELETE

```json
{
  "action": "delete",
  "resource": "crud-test",
  "filters": [{ "field": "Id", "operator": "=", "value": 42 }]
}
```

DELETE has no `data`. A non-empty `filters` list is mandatory. Success data is
`[{"operation":"delete","affectedRows":1}]`.

## UPSERT

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

The request `keys` list is mandatory and must exactly equal the configured key
set (case-insensitive and order-insensitive). Every key must have a non-null data
value. Data must include at least one non-key update value. Live metadata must
show an exact, unfiltered UNIQUE or PRIMARY KEY index for the configured set.

The builder generates one SQL Server `MERGE ... WITH (HOLDLOCK)` with prepared
source values, matched UPDATE, not-matched INSERT, and OUTPUT information used to
derive affected count and optional inserted identity. The API does not open a
transaction around it and does not promise broader transaction semantics.

## Write filter operators

UPDATE and DELETE allow `=`, `!=`, `<>`, `>`, `<`, `>=`, `<=`, `LIKE`,
`NOT LIKE`, `IN`, `NOT IN`, `BETWEEN`, `NOT BETWEEN`, `IS NULL`, and
`IS NOT NULL`. IN lists must be non-empty; BETWEEN has exactly two values; NULL
operators omit `value`. Subqueries and EXISTS are unsupported. Multiple filters
use AND by default or top-level `filterLogic: "OR"`.

## Validation and errors

| Code | HTTP | Typical cause |
|---|---:|---|
| `INVALID_REQUEST` | 400 | Bad action shape, unknown property, malformed `data`, `keys`, or filters. |
| `INVALID_WRITE_RESOURCE` | 400 | Unknown resource or action not enabled. |
| `UNSAFE_WRITE` | 400 | UPDATE/DELETE filters missing or empty. |
| `INVALID_WRITE_COLUMN` | 400 | Data/filter column not allowed or generated. |
| `INVALID_WRITE_VALUE` | 400 | Wrong type/range/format/length/null or key-only UPSERT data. |
| `MISSING_REQUIRED_FIELD` | 400 | Required no-default insert/upsert column omitted. |
| `INVALID_UPSERT_KEY` | 400 | Disabled/mismatched/missing/null key or missing exact unique index. |
| `DUPLICATE_KEY` | 409 | SQL Server duplicate key error 2601/2627. |
| `CONSTRAINT_VIOLATION` | 409 | Recognized foreign/check/reference constraint failure. |
| `QUERY_ERROR` | 500 | Configuration, metadata, or unclassified database failure. |

The client receives no SQL Server error text. All values are prepared parameters;
schema/table/action/columns/keys/identity are server-controlled. See
[Write resource configuration](Write-Resource-Configuration.md) for deployment
details and [Validation and errors](Validation-and-Errors.md) for the envelope.

## Unsupported write behavior

There is no bulk array input, multi-action transaction contract, client-selected
table/schema, identity insertion override, arbitrary SQL expression value,
RETURNING column selection, soft-delete behavior, or optimistic concurrency
token contract. Each HTTP request executes one configured operation.
