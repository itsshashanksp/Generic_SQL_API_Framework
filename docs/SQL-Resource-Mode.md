# SQL Resource Mode

SQL Resource Mode discovers and executes server-owned, read-only SQL files. It
supports curated SQL Server reports without exposing arbitrary SQL or filesystem
paths to clients.

## Auto-discovery

The default discovery root is `queries/`. SQL files below it receive a relative
logical ID with the `.sql` extension removed:

```text
queries/reports/customer.sql                 -> reports/customer
queries/reports/item.sql                     -> reports/item
queries/widgets/bill-top-10-categories.sql   -> widgets/bill-top-10-categories
```

Create `queries/reports/new-report.sql`, then call:

```json
{"action":"sql","resource":"reports/new-report"}
```

No per-resource entry in `config/sql-resources.php` is required. Discovery is
recursive and deterministic. `queries/system` is excluded by default because it
contains internal metadata statements, not public SQL Resources.

Logical IDs use slash-separated segments matching
`[A-Za-z0-9][A-Za-z0-9_-]*`. They cannot contain `.`, `..`, empty segments,
backslashes, absolute roots, drive prefixes, null bytes, extensions, or URL/path
syntax. Only real `.sql` files contained by the resolved server root are eligible;
directories, other extensions, escaped symlinks, and nonexistent IDs fail with
`INVALID_SQL_RESOURCE`. Case-insensitive identity collisions fail as server
configuration errors instead of resolving unpredictably.

## Minimal execution

A simple resource needs only its SQL file and resource ID:

```sql
SELECT
    Item_Code,
    Item_Desc,
    Item_MRP
FROM ItemMasterTable
```

```json
{"action":"sql","resource":"reports/item"}
```

No output parser or metadata query is required. This is the least fragile choice
for arbitrary server-owned SQL Server syntax.

## Execution metadata

Runtime output filtering, sorting, and deterministic pagination need names that
can be validated before executing the query. Supply those once in the frontend's
reviewed report definition as the SQL action's `execution` object:

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

`execution.columns` contains unqualified stable output aliases. Each becomes an
automatically permitted outer/output filter and an allowed runtime/default sort
field. It does not change projection or redact response fields. The SQL SELECT
list remains authoritative for returned data.

The backend does not attempt to parse general SQL Server projections. Aliases can
be nested inside CTEs, derived tables, PIVOT, JSON/XML expressions, windows, and
set operations; a general parser would be fragile. Consequently:

- a resource with no runtime controls needs no `execution.columns`;
- output filters and sort need the relevant names in `execution.columns`;
- `execution.defaultSort` requires `execution.columns` and uses only those fields;
- pagination requires an approved runtime/default sort or authored top-level order.

## Custom filters

Use `execution.filters` when the frontend logical name differs from an output
alias or filtering must occur before aggregation:

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
  "filterLogic": "AND",
  "pagination": { "page": 1, "pageSize": 25 }
}
```

The example matches the repository's actual `customer.sql`: it has `StDate` on
`CustomerTable`; there is no invented `BIL` alias. `source` inserts a top-level
WHERE predicate before GROUP BY. `having` inserts a top-level HAVING predicate.
`output` (the default) filters the generated outer `SqlResource` wrapper.

### Execution filter schema

| Property | Required | Accepted behavior |
|---|---:|---|
| logical filter key | yes | Unqualified identifier sent later as `filters[].field`. |
| `placement` | no | `output` (default), `source`, or `having`. |
| `expression` | conditional | Defaults to logical key for output; required otherwise. |
| `valueType` | no | Only `integer-date`. |

Execution metadata arrives over the public request and is therefore not treated
as arbitrary trusted SQL. The validator permits only:

- output expressions that exactly name an `execution.columns` identifier;
- source expressions that are identifiers, optionally qualified, such as
  `BIL.Bill_Date`;
- HAVING expressions using COUNT, SUM, AVG, MIN, or MAX over one identifier or `*`.

Semicolons, comments, placeholders, Boolean clauses, function nesting, operators,
and free-form SQL fragments cannot pass that grammar. More complex mappings must
be expressed through the supported grammar or a dedicated SQL Resource.

## Runtime filters

Runtime filters remain separate from execution metadata:

```json
{
  "field": "StDate",
  "operator": "BETWEEN",
  "value": ["2021-04-01", "2022-03-31"]
}
```

Supported operators are `=`, `!=`, `<>`, `>`, `<`, `>=`, `<=`, `LIKE`,
`NOT LIKE`, `IN`, `NOT IN`, `BETWEEN`, `NOT BETWEEN`, `IS NULL`, and
`IS NOT NULL`. Values become positional prepared parameters. `integer-date`
validates real `YYYY-MM-DD` or `YYYYMMDD` input and binds an integer.

AND may span output/source/HAVING stages. OR is accepted only when all requested
filters resolve to one SQL stage; otherwise `INVALID_SQL_RUNTIME_FILTER` prevents
a semantic rewrite. Source/HAVING injection on a top-level set operation is also
rejected as ambiguous; use output filtering or a dedicated resource.

## Sorting and pagination

Runtime and default sorts accept only an approved output column with ASC or DESC.
No expression or numeric positional ordering is accepted. A non-empty runtime
sort overrides `execution.defaultSort`.

Pagination preserves the existing count plus SQL Server compatibility behavior:
compatibility 110+ uses OFFSET/FETCH; older versions use ROW_NUMBER. It does not
add a page-size cap. Pagination without runtime/default or authored ordering is rejected.

Authored OFFSET/FETCH remains exclusive: any runtime filters, sort, or pagination
produce `INVALID_SQL_PAGINATION`. Authored TOP/ORDER BY preservation and the
complete-first-page TOP optimization remain unchanged.

## Short-ID compatibility

A short basename such as `item` is accepted only if
exactly one discovered SQL file has that basename. If several directories contain
`item.sql`, the caller must use the full relative ID. This eases migration without
making resolution nondeterministic.

SQL files do not require a runtime-filter marker: source predicates use
depth-aware top-level WHERE insertion.

See [SQL Resource configuration](SQL-Resource-Configuration.md) and
[SQL Resource files](SQL-Resource-Files.md) for migration and authoring details.

## SQL capabilities and security

The statement analyzer still requires one server-owned read-only SELECT,
optionally beginning with WITH. It rejects top-level SELECT INTO and additional
statements. Approved files may use CTEs, subqueries, derived tables, all SQL
Server join/APPLY forms, set operations, windows/PARTITION BY, CASE, JSON/XML,
PIVOT/UNPIVOT, functions, grouping, HAVING, TOP, and ordering.

This broader SQL belongs only in the file. Clients cannot send SQL text, paths,
filenames, extensions, clauses, database credentials, or arbitrary expressions.
Real-path containment, excluded directories, strict identifiers, constrained
execution expressions, fixed operator/placement/direction enums, prepared values,
and the read-only statement analyzer preserve the security boundary. The API
still has no authentication or authorization, so production network controls and
least-privilege database permissions remain required.
