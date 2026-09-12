# SQL Resource configuration

SQL Resource Mode is discovery-first. A reviewed `.sql` file beneath the fixed
resource root is executable through its safe relative identifier without a
per-file PHP registry entry.

## Global discovery settings

`config/sql-resources.php` contains global discovery settings only:

```php
return [
    'root' => QUERY_PATH,
    'exclude' => ['system'],
];
```

| Setting | Default | Meaning |
|---|---|---|
| `root` | `QUERY_PATH` | Fixed server-owned directory recursively scanned for SQL Resources. |
| `exclude` | `['system']` | Directory segment names omitted from public discovery. |

The root must exist and resolve to a directory. Exclusions are simple path
segments, not globs or client input. Internal metadata queries under
`queries/system` are therefore not exposed as public SQL Resources.

If `config/sql-resources.php` is absent, discovery still defaults to `QUERY_PATH`
with `system` excluded. Individual resources are never registered in this file.

## Adding a resource

Create a SQL file under the configured root:

```text
queries/reports/customer-balance.sql
```

Its preferred public ID is:

```text
reports/customer-balance
```

Call it directly:

```json
{
  "action": "sql",
  "resource": "reports/customer-balance"
}
```

There is no registry edit. The extension is omitted from public JSON.

Resource segments may contain letters, digits, `_`, and `-`, and each segment
must begin with a letter or digit. `/` separates directories. These are valid:

```text
reports/customer
dashboard/top-products
custom/report_2026
```

These are rejected:

```text
../reports/customer
/reports/customer
C:/reports/customer
reports\customer
reports/customer.sql
reports//customer
```

The resolver uses `realpath`, confirms the file remains under the configured
root, accepts only regular `.sql` files, rejects directories and other
extensions, and never places an absolute path in a public error. Resource IDs
are case-sensitive for exact lookup; identities differing only by case are
rejected during discovery so behavior remains deterministic across operating
systems.

## Public execution metadata

SQL projection aliases cannot be derived reliably from arbitrary SQL Server text
without a general parser or database execution. When runtime output controls are
needed, the frontend's reviewed report definition supplies them:

```json
{
  "action": "sql",
  "resource": "reports/item",
  "execution": {
    "columns": ["Item_Code", "Item_Desc", "Item_MRP"],
    "defaultSort": [
      { "field": "Item_Code", "direction": "ASC" }
    ]
  },
  "filters": [
    { "field": "Item_Desc", "operator": "LIKE", "value": "%pen%" }
  ],
  "pagination": { "page": 1, "pageSize": 25 }
}
```

### `execution.columns`

A non-empty, case-insensitively unique list of unqualified output identifiers.
It is used for:

- validating runtime `sort`;
- validating `execution.defaultSort`;
- automatically permitting outer/output filters on those aliases;
- bracket-quoting generated outer references.

It does not select, remove, rename, or redact response fields. The SQL file owns
the projection. A simple request without filters/sort/pagination can omit the
entire `execution` object.

### `execution.defaultSort`

A non-empty list of `{field, direction}` entries. `field` must be in
`execution.columns`; direction is ASC or DESC. Runtime `sort` replaces it.
Pagination requires a runtime/default sort or authored top-level ORDER BY because
every pagination path needs deterministic, non-positional ordering.

### `execution.filters`

An object keyed by the logical filter name later used in `filters[].field`:

```json
{
  "execution": {
    "columns": ["Category", "Sales"],
    "filters": {
      "BillDate": {
        "expression": "BIL.Bill_Date",
        "placement": "source",
        "valueType": "integer-date"
      },
      "MinimumSales": {
        "expression": "SUM(BIL.Item_Rate)",
        "placement": "having"
      }
    },
    "defaultSort": [
      { "field": "Sales", "direction": "DESC" }
    ]
  }
}
```

This fragment illustrates the `execution` member; the API request must also
contain `action: "sql"` and `resource`.

| Filter property | Required | Validation and behavior |
|---|---:|---|
| map key | yes | Unique unqualified logical identifier. |
| `placement` | no | `output` default, `source`, or `having`. |
| `expression` | output: no; otherwise yes | Constrained grammar described below. |
| `valueType` | no | Only `integer-date`. |

Execution metadata is request data, even when it originates in a reviewed
frontend report definition. It is never concatenated as arbitrary SQL:

- output expression: one identifier and a member of `execution.columns`;
- source expression: one identifier, optionally dot-qualified;
- HAVING expression: COUNT/SUM/AVG/MIN/MAX over one identifier or `*`.

The validator rejects comments, semicolons, placeholders, Boolean expressions,
operators, nested functions, and other SQL fragments. Authors needing a more
complex mapping must use a dedicated SQL file whose output supports safe outer filtering.

`source` maps internally to depth-aware top-level WHERE insertion. It creates a
WHERE clause or appends with AND before GROUP BY/HAVING/ORDER BY. `having` does
the equivalent at HAVING. `output` uses a generated outer resource wrapper.

`integer-date` accepts a real calendar date as `YYYY-MM-DD`, an eight-digit
string, or an eight-digit integer and binds it as `YYYYMMDD`. It never interpolates
the date into SQL.

## Runtime values

Execution metadata declares permitted structure; runtime `filters` contains
values:

```json
{
  "action": "sql",
  "resource": "reports/customer",
  "execution": {
    "columns": ["Cust_Name", "TotalCustomers", "MinimumBill", "MaximumBill"],
    "filters": {
      "StDate": {
        "expression": "StDate",
        "placement": "source",
        "valueType": "integer-date"
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
    }
  ],
  "pagination": { "page": 1, "pageSize": 25 }
}
```

Operators remain the fixed SQL Resource allowlist. Every ordinary/list/range
value becomes a positional prepared parameter. The SQL file, resource root,
placement enum, expression grammar, identifier syntax, operator list, sort
direction, and value conversion remain backend-controlled.

## Migration

For each former registered resource:

1. Keep the SQL file beneath the configured root.
2. Change frontend `resource` to its relative ID without `.sql`.
3. Move output aliases and default sort into the report's `execution` object when
   runtime controls require them.
4. Represent simple source identifiers or supported aggregate HAVING mappings in
   `execution.filters`.
5. Remove runtime-filter markers; source/HAVING placement is now inserted by the
   statement transformer.
6. Remove the per-resource PHP entry. Short IDs remain usable only when their
   discovered basename is unique.

## Security checklist

- Treat every discovered SQL file as reviewed backend code.
- Keep internal/non-public directories in the global `exclude` list.
- Use stable, explicit aliases for output controls.
- Never accept report SQL, paths, credentials, or raw clauses from a frontend.
- Keep public source/HAVING mappings within the validator grammar.
- Use least-privilege database credentials; discovery is not authorization.
- Remember that `execution.columns` is not response redaction.
- Test the real SQL, aliases, schema, and plan against SQL Server after structural
  database-independent tests pass.

See [SQL Resource Mode](SQL-Resource-Mode.md) for the client contract and
[SQL Resource Files](SQL-Resource-Files.md) for transformation constraints.
