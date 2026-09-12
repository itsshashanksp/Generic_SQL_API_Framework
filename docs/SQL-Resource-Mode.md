# SQL Resource Mode

SQL Resource Mode executes an administrator-approved, server-owned, read-only SQL
file. It is the escape hatch for report SQL that is too complex for JSON Query
Mode without exposing arbitrary SQL to clients.

| JSON Query Mode | SQL Resource Mode |
|---|---|
| Frontend supplies validated query structure. | Backend author owns complete SQL structure. |
| Public function/join/expression allowlists apply. | SQL file may use SQL Server syntax within read-only resource constraints. |
| Physical source/field identifiers are supplied by the client and metadata-checked. | Client supplies an opaque resource ID and approved logical runtime fields. |
| Best for configurable simple/advanced SELECTs. | Best for curated reports, dashboards, CTEs, APPLY/PIVOT, or specialized SQL. |

## Frontend contract

Minimal:

```json
{"action":"sql","resource":"item"}
```

With every runtime control:

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

The only accepted top-level properties are `action`, `resource`, `filters`,
`sort`, `pagination`, and `filterLogic`. The ID must match
`^[A-Za-z0-9][A-Za-z0-9_-]*$` and an exact registry key.

The frontend must not send SQL, SQL expressions, table/schema names, a filename,
a filesystem path, connection settings, runtime filter location, or a marker.

## Backend registry

Resources live in `config/sql-resources.php`. A simple output-filtered entry is:

```php
'item' => [
    'file' => QUERY_PATH . '/reports/item.sql',
    'columns' => ['Item_Code', 'Item_Desc', 'Item_MRP'],
    'filterColumns' => ['Item_Code', 'Item_Desc'],
    'defaultSort' => [
        ['field' => 'Item_Code', 'direction' => 'ASC'],
    ],
],
```

`file` must resolve inside `QUERY_PATH`. `columns` is the exact exposed output
alias allowlist for runtime sort and wrapper references. `defaultSort` is required
and each field must be in `columns`. Legacy `filterColumns` defaults to `columns`.

An integer date mapping adds:

```php
'filterValueTypes' => [
    'Bill_Date' => 'integer-date',
],
```

Only `integer-date` is implemented. It accepts valid `YYYY-MM-DD` or `YYYYMMDD`
input and binds an integer.

## Complex runtime filter mapping

New complex resources can map logical frontend names to backend expressions and
locations:

```php
'customer-summary' => [
    'file' => QUERY_PATH . '/reports/customer-summary.sql',
    'columns' => ['CustomerName', 'TotalBills', 'MinimumBill', 'MaximumBill'],
    'filters' => [
        'CustomerName' => [
            'expression' => 'C.Cust_Name',
            'location' => 'where',
        ],
        'BillDate' => [
            'expression' => 'B.Bill_Date',
            'location' => 'where',
            'valueType' => 'integer-date',
        ],
        'TotalBills' => [
            'expression' => 'COUNT(*)',
            'location' => 'having',
        ],
    ],
    'defaultSort' => [
        ['field' => 'CustomerName', 'direction' => 'ASC'],
    ],
],
```

Mapping keys are the logical `filters[].field` values accepted from clients.
`expression` and `location` are trusted server configuration, never request data.
Locations mean:

- `output`: filter an outer `SqlResource` wrapper by an exposed result alias;
- `where`: inject at the top-level WHERE before GROUP BY/HAVING/ORDER BY;
- `having`: inject at top-level HAVING before ORDER BY.

Mapped and legacy filter settings cannot be mixed in one resource. Expressions
are constrained server configuration: empty values, placeholders, semicolons,
and SQL comment tokens are rejected. OR filters must all resolve to one location.
Top-level set operations reject mapped WHERE/HAVING insertion as ambiguous;
choose output placement or author a dedicated resource.

## Legacy source marker

An existing resource may set `filterPlacement => 'source'` and contain exactly
one backend marker `/*__RUNTIME_FILTERS__*/`. The executor replaces it with a
prepared source WHERE clause. This marker is an internal SQL-file authoring
contract only. Frontends neither generate nor know about it. Output placement
requires no marker. See [SQL Resource files](SQL-Resource-Files.md).

## SQL-file capabilities

The resource analyzer accepts one read-only statement whose first top-level
operation is SELECT, optionally preceded by WITH. It rejects top-level SELECT
INTO and additional statements. Within that server-owned query, authors may use
normal SQL Server SELECT syntax, including:

- standard or recursive CTEs, nested subqueries, and derived tables;
- INNER/LEFT/RIGHT/FULL/CROSS joins, multiple/non-equality predicates, CROSS
  APPLY, and OUTER APPLY;
- UNION, UNION ALL, INTERSECT, and EXCEPT;
- window functions and PARTITION BY;
- CASE, SQL Server scalar/aggregate/window/JSON/XML functions, and complex
  expressions;
- PIVOT/UNPIVOT, TOP, complex GROUP BY/HAVING, and server-authored ORDER BY.

These are capabilities of the approved SQL text, not JSON properties parsed by
the API. Actual syntax and availability still depend on the connected SQL Server
version, compatibility level, permissions, and referenced objects.

The executor separates a WITH prefix and an `OPTION(...)` suffix so count and
pagination transformations remain valid. It detects top-level clauses with
depth-aware scanning. An authored top-level ORDER BY is retained when it can be
executed directly; otherwise resource output is wrapped for approved runtime
controls.

## Runtime sorting and pagination

Sort fields must be resource `columns`; direction is ASC/DESC. When omitted,
`defaultSort` is used. Pagination requires positive page/pageSize and uses a count
plus compatibility-aware OFFSET/FETCH or ROW_NUMBER strategy.

Authored OFFSET/FETCH makes the resource own pagination. Any request filter,
sort, or pagination is then rejected with `INVALID_SQL_PAGINATION`. For a TOP
resource whose whole result fits the requested first page, the executor can run
the authored query directly and infer `totalRows` from returned rows.

## Security and validation

- Registry lookup prevents client-controlled paths and arbitrary SQL.
- Real-path validation confines files to the query directory.
- The analyzer enforces one read-only query and blocks SELECT INTO.
- Output, filter, and sort fields are allowlisted.
- Values are prepared parameters; field expressions remain server-owned.
- Invalid IDs use `INVALID_SQL_RESOURCE`; invalid fields/values/placement or
  pagination use the specific codes in [Validation and errors](Validation-and-Errors.md).

This is defense in depth, not authentication. The endpoint currently has no API
authentication or authorization.

## Example: grouped customer report

Backend SQL can group by customer and expose stable aliases:

```sql
SELECT
    C.Cust_Name AS CustomerName,
    COUNT(*) AS TotalBills,
    MIN(B.Bill_Amt) AS MinimumBill,
    MAX(B.Bill_Amt) AS MaximumBill
FROM CustomerTable AS C
INNER JOIN BillTable AS B ON B.Cust_Code = C.Cust_Code
GROUP BY C.Cust_Name
```

With the mapped registry above, a frontend can request:

```json
{
  "action": "sql",
  "resource": "customer-summary",
  "filters": [
    { "field": "CustomerName", "operator": "LIKE", "value": "%John%" },
    { "field": "BillDate", "operator": "BETWEEN", "value": ["2026-01-01", "2026-12-31"] },
    { "field": "TotalBills", "operator": ">", "value": 2 }
  ],
  "sort": [{ "field": "TotalBills", "direction": "DESC" }],
  "pagination": { "page": 1, "pageSize": 25 },
  "filterLogic": "AND"
}
```

The registry controls where CustomerName/BillDate (WHERE) and TotalBills
(HAVING) are placed. All three values remain prepared. This illustrative resource
must be registered before the request is usable; clients cannot create it.

Existing repository resource IDs and exact authoring rules are documented in
[SQL Resource configuration](SQL-Resource-Configuration.md) and
[SQL Resource files](SQL-Resource-Files.md).
