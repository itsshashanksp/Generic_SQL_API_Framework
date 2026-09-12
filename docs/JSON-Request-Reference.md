# JSON Request Reference

This document defines the public JSON accepted by `QueryRequestValidator` and transformed by `QueryRequestNormalizer`. It does not document the private PHP builder arrays.

## Common request

| Name | Type | Required | Allowed/default | Notes |
|---|---|---:|---|---|
| `action` | string | yes | See action table; no default | Case-sensitive |

Unknown top-level properties are rejected for every action.

| Action | Required fields | Optional fields |
|---|---|---|
| `select` | `source`, `fields` | `filters`, `joins`, `groupBy`, `having`, `sort`, `pagination`, `distinct`, `limit`, `filterLogic`, `with` |
| `sql` | `resource` | `filters`, `sort`, `pagination`, `filterLogic` |
| `insert` | `resource`, non-empty `data` object | none |
| `update` | `resource`, non-empty `data` object, non-empty `filters` | `filterLogic` |
| `delete` | `resource`, non-empty `filters` | `filterLogic` |
| `upsert` | `resource`, non-empty `data` object, non-empty `keys` list | none |
| `union`, `unionAll` | non-empty `queries` | none |
| `procedure` | `source.procedure` | `parameters` |
| `function`, `tableFunction` | `source.function` | `parameters` |
| `metadata.columns` | `source.table` | none |
| `metadata.tables`, `metadata.views`, `metadata.procedures`, `metadata.schema` | none beyond `action` | none |

Identifiers use `^[A-Za-z_][A-Za-z0-9_.]*$`: letters/underscore first, then letters, digits, underscores, or dot qualifiers. This is syntax validation; SELECT builders also check tables and columns against live metadata.

The `sql` action overview is documented in [API](API.md#controlled-sql-resource-request).
Backend developers should use [SQL Resource Configuration](SQL-Resource-Configuration.md)
and [SQL Resource Files](SQL-Resource-Files.md) for registry and file details.
Its resource IDs use the stricter `^[A-Za-z0-9][A-Za-z0-9_-]*$` shape and must
also exist in the server registry. Its sort fields are unqualified output aliases;
filter fields come from the resource's `filterColumns` allowlist, which defaults
to its output columns. Neither accepts arbitrary database fields.

These restrictions describe client-composed JSON. A registered SQL Resource is
backend-owned SQL and may use SQL Server functions, CTEs, joins, windows,
subqueries, and set operations that are intentionally not exposed by the JSON
Query function or expression allowlists. The client still supplies only the
resource ID and documented runtime controls, never SQL text.

## CRUD writes

CRUD `resource` IDs use the same restricted ID syntax as SQL resources, but are
resolved from the separate `config/write-resources.php` registry. Clients cannot
send `source`, table/schema names, SQL, expressions, file paths, metadata, or
connection information. `data` is a JSON object keyed by unqualified column
names; every value must be a string, number, boolean, or null. Arrays and nested
objects are not write values.

Each resource separately allowlists which CRUD `actions` are enabled. A valid
resource ID is rejected when that resource does not enable the requested action.

Write columns are case-insensitively matched to their configured canonical names
and live SQL Server metadata. Integer, numeric, bit, string/length, ISO date/time,
UUID, binary-string, nullability, required/default, identity, computed, and
rowversion rules are validated before SQL execution. Unsupported database types
are rejected instead of being guessed. Defaults are used by omitting their
columns; clients cannot request a SQL DEFAULT expression.

UPDATE and DELETE filters use this shape:

| Name | Type | Required | Allowed/default |
|---|---|---:|---|
| `filters` | array | yes | Non-empty; an empty/missing list is `UNSAFE_WRITE` |
| `filters[].field` | unqualified identifier | yes | Must be in the resource `filterColumns` |
| `filters[].operator` | string | yes | `=`, `!=`, `<>`, `>`, `<`, `>=`, `<=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `BETWEEN`, `NOT BETWEEN`, `IS NULL`, `IS NOT NULL` |
| `filters[].value` | scalar/array | except NULL forms | Non-empty list for IN; exactly two values for BETWEEN |
| `filterLogic` | string | no | `AND`; `OR` also accepted |

Write filters do not accept `query`, EXISTS, NOT EXISTS, or dotted fields. A null
comparison must use IS NULL/IS NOT NULL. For UPSERT, `keys` is a unique list of
unqualified column names that must exactly equal the resource's configured key
set; all key values must exist in `data` and be non-null. UPSERT does not accept
filters or `filterLogic`.

All four actions are single-object operations. There is no bulk request shape,
transaction property, begin/commit/rollback action, arbitrary returned-column
selection, or client override for identity insertion.

## SELECT fields

| Name | Type | Required | Allowed/default | Example/notes |
|---|---|---:|---|---|
| `source` | object | yes | Exactly `table`, optional `alias` | `{"table":"Items","alias":"I"}` |
| `source.table` | identifier | yes | No default | Table or matching CTE name |
| `source.alias` | identifier | no | none | Table alias |
| `fields` | array | yes | Non-empty | Strings or field objects |
| `fields[]` string | identifier | no | `*` also allowed | `"I.ItemCode"` |
| `fields[].field` | identifier | conditional | `*` for applicable aggregate | Public name for a selected/function field |
| `fields[].alias` | identifier | no | Plain fields: none; functions: lowercase function name; CASE: `CaseValue`; arithmetic: `Expression` | Explicit aliases are recommended |
| `distinct` | boolean | no | `false` | Adds DISTINCT |
| `limit` | integer | no | none; minimum 1 | SQL Server TOP |

A field object requires at least one of `field`, `function`, `case`, or `expression`. Allowed field-object properties are `field`, `fields`, `function`, `alias`, `sort`, `case`, `expression`, `buckets`, `offset`, `default`, `separator`, `datatype`, `style`, `value`, `values`, `index`, `datepart`, `number`, `start`, `end`, `year`, `month`, `day`, `hour`, `minute`, `second`, `millisecond`, `precision`, `power`, `part`, `length`, `search`, `replace`, `pattern`, `format`, `condition`, `true`, and `false`.

### Aliases and expressions

```json
{
  "fields": [
    { "field": "Description", "alias": "ItemName" },
    { "expression": { "left": "Amount", "operator": "*", "right": 1.18 }, "alias": "Gross" },
    {
      "case": {
        "when": [{ "condition": { "field": "Status", "operator": "=", "value": "A" }, "then": "Active" }],
        "else": "Inactive"
      },
      "alias": "StatusText"
    }
  ]
}
```

Arithmetic permits one number-or-identifier operand on each side and one of `+`, `-`, `*`, `/`, `%`. CASE requires a non-empty `when` list. Each condition permits `=`, `!=`, `<>`, `>`, `<`, `>=`, or `<=`, requires `field` and `value`, and each branch requires `then`; `else` is optional.

## Functions

Every function object uses `function` and normally an `alias`. Requirements below are the usable public forms.

| Functions | Additional public properties |
|---|---|
| `COUNT`, `SUM`, `AVG`, `MIN`, `MAX` | `field` (`COUNT` may use `*`) |
| `STRING_AGG` | `field`, `separator`; optional `sort` |
| `UPPER`, `LOWER`, `LTRIM`, `RTRIM`, `TRIM`, `LEN` | `field` |
| `COALESCE` | non-empty identifier array `fields`; optional literal `default` |
| `ISNULL` | `field`, literal `default` |
| `NULLIF` | `field`, literal `value` |
| `CAST` | `field`, `datatype` |
| `CONVERT` | `field`, `datatype`; optional integer `style` |
| `CONCAT` | at least two identifier entries in `fields` |
| `LEFT`, `RIGHT` | `field`, `length` |
| `SUBSTRING` | `field`, `start`, `length` |
| `REPLACE` | `field`, `search`, `replace` |
| `CHARINDEX` | `field`, `search` |
| `PATINDEX` | `field`, `pattern` |
| `FORMAT` | `field`, `format`; optional `style` |
| `YEAR`, `MONTH`, `DAY` | `field`; builder treats it as integer `YYYYMMDD` via style 112 |
| `DATEPART`, `DATENAME` | `field`, `part`; same integer-date conversion. Parts: YEAR, QUARTER, MONTH, DAYOFYEAR, DAY, WEEK, WEEKDAY, HOUR, MINUTE, SECOND, MILLISECOND |
| `GETDATE`, `SYSDATETIME`, `CURRENT_TIMESTAMP` | no field |
| `DATEADD` | `field`, `datepart`, `number`; optional `style` |
| `DATEDIFF` | `datepart`, `start`, `end`; each endpoint is `{"field":"DateField"}` or `{"function":"GETDATE"}`, with optional `style` on field endpoints |
| `EOMONTH` | `start` endpoint as above; optional `month` offset |
| `ISDATE` | `field`; optional `style` |
| `DATEFROMPARTS` | `year`, `month`, `day` |
| `DATETIMEFROMPARTS` | `year`, `month`, `day`, `hour`, `minute`, `second`, `millisecond` |
| `IIF` | `condition: {left, operator, right}`, `true`, `false` |
| `CHOOSE` | `index`, `values` with at least two values |
| `ABS`, `CEILING`, `FLOOR`, `SQRT`, `EXP`, `LOG` | `field` |
| `ROUND` | `field`; optional `precision` default 0 |
| `POWER` | `field`, `power` |

Function-specific required options are validated before normalization. Lengths, window offsets/buckets, and `CHOOSE.index` are positive integers (`SUBSTRING.length` may be zero); styles and numeric precisions use JSON integers. Date endpoints are either `{"field":"DateField"}` with an optional integer `style`, or `{"function":"GETDATE"}`. Nested field/arithmetic expressions used by conditional and date-part constructors are shape-checked and their referenced columns are validated through metadata.

`datatype` must match a simple type name optionally followed by numeric size/precision, such as `date`, `varchar(50)`, or `decimal(10,2)`. `TIMEFROMPARTS` cannot currently be expressed publicly because the validator rejects its builder-required `fractions` property.

## Filters

| Name | Type | Required | Allowed/default |
|---|---|---:|---|
| `filters` | array | no | empty |
| `filters[].operator` | string | yes | `=`, `!=`, `<>`, `>`, `<`, `>=`, `<=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `BETWEEN`, `NOT BETWEEN`, `IS NULL`, `IS NOT NULL`, `EXISTS`, `NOT EXISTS` |
| `filters[].field` | identifier | except EXISTS forms | none |
| `filters[].value` | any/array | depends on operator | scalar comparisons; non-empty list for IN; exactly two values for BETWEEN; omitted for NULL/EXISTS forms |
| `filters[].query` | SELECT body | IN/NOT IN alternative, required for EXISTS forms | Nested object without a required `action` |
| `filterLogic` | string | no | `AND` (or `OR`) |

Ordinary values, IN lists, BETWEEN bounds, and HAVING values become prepared parameters. A `YYYY-MM-DD` BETWEEN bound is converted to integer `YYYYMMDD` only when live metadata reports an integer-family column.

## Joins, grouping, HAVING, and sorting

| Name | Type | Required | Allowed/default |
|---|---|---:|---|
| `joins` | array | no | empty |
| `joins[].type` | string | yes | `INNER`, `LEFT`, `RIGHT` (case-insensitive during normalization) |
| `joins[].source` | object | yes | `table`, optional `alias` |
| `joins[].on.left/right` | identifier | yes | Logical fields |
| `joins[].on.operator` | string | no | `=` only; defaults to `=` |
| `groupBy` | identifier array | no | empty |
| `having` | array | no | empty; combined with AND |
| `having[].function` | string | yes | `COUNT`, `SUM`, `AVG`, `MIN`, `MAX`, `STRING_AGG` |
| `having[].field` | identifier or `*` | yes | none |
| `having[].operator` | string | yes | `=`, `!=`, `<>`, `>`, `<`, `>=`, `<=` |
| `having[].value` | any | yes | Prepared parameter |
| `sort` | array | no | empty/default builder ordering |
| `sort[].field` | identifier | yes | Logical source field or selected top-level alias; numeric position rejected |
| `sort[].direction` | string | no | `ASC`; also `DESC` |

FULL/CROSS joins, non-equality join predicates, multiple ON predicates, HAVING OR logic, and public positional ordering are unsupported.

## Pagination

| Name | Type | Required | Allowed/default |
|---|---|---:|---|
| `pagination` | object | no | no pagination |
| `pagination.page` | integer | yes when object present | minimum 1 |
| `pagination.pageSize` | integer | yes when object present | minimum 1 |

There is no implicit page or page size. Pagination normally returns a total from a separate count, and its SQL strategy is selected from SQL Server compatibility level. SQL Resource Mode has a documented complete-first-page `TOP` optimization that can infer the total instead.

## Window fields

All window functions require `sort`, whose entries have the same public shape as top-level sorting. `partitionBy` is not accepted.

| Function | Additional properties |
|---|---|
| `ROW_NUMBER`, `RANK`, `DENSE_RANK` | `sort` |
| `NTILE` | positive `buckets`, `sort` |
| `LAG`, `LEAD` | `field`, `sort`; optional positive `offset` default 1 and optional `default` |
| `FIRST_VALUE`, `LAST_VALUE` | `field`, `sort` |

## CTE and subquery bodies

A standard CTE is `"with":{"name":"ActiveItems","query":{...select body...}}`. A recursive CTE is `"with":{"name":"Tree","anchor":{...},"recursive":{...}}`. Only one `with` object is accepted. Each branch uses SELECT fields such as `source` and `fields`; `action` is not accepted. The backend infers the CTE output names from its projection so outer fields, filters, grouping, and ordering are validated without querying `INFORMATION_SCHEMA` for a nonexistent physical table. Recursive branches must return the same number of fields. Top-level pagination is supported and keeps the CTE prefix on both count and data queries.

Subqueries are accepted only as filter `query` values for IN, NOT IN, EXISTS, and NOT EXISTS. IN/NOT IN subqueries must select exactly one explicit field; `*` is rejected. Nested SELECT bodies do not accept `action`, `sort`, `pagination`, or another `with`. General FROM/SELECT-expression and nested-CTE subqueries are not exposed.

## Set operations

```json
{
  "action": "unionAll",
  "queries": [
    { "source": { "table": "Items" }, "fields": ["ItemCode"] },
    { "source": { "table": "ArchivedItems" }, "fields": ["ItemCode"] }
  ]
}
```

`queries` is a non-empty array of SELECT bodies without nested actions, sorting, pagination, or CTEs. Explicit branch projections must have the same field count during public validation; wildcard counts are resolved from metadata before execution. SQL Server remains responsible for checking data-type compatibility between corresponding expressions. The current set-operation contract has no top-level sorting or pagination. Public actions are only `union` and `unionAll`; internal support for INTERSECT/EXCEPT is not public.

## Routines and metadata

Routine `parameters` is an optional positional array and defaults to `[]`:

```json
{ "action": "procedure", "source": { "procedure": "dbo.RunReport" }, "parameters": [2026, true] }
{ "action": "function", "source": { "function": "dbo.Score" }, "parameters": [42] }
{ "action": "tableFunction", "source": { "function": "dbo.RowsForYear" }, "parameters": [2026] }
```

The shared source validator also accepts an optional identifier `source.alias` on routine and `metadata.columns` requests, but the normalizer discards it and it has no execution effect. Do not depend on it. `parameters` is only checked as a decoded PHP array; clients should send a JSON list because routine placeholders are positional.

Metadata requests are exactly `{"action":"metadata.tables"}`, `metadata.views`, `metadata.procedures`, or `metadata.schema`. Columns uses `{"action":"metadata.columns","source":{"table":"Items"}}`.

## Public versus internal names

| Public JSON | Private normalized builder key |
|---|---|
| `source.table` / `source.alias` | `table` / `alias` |
| `fields` / field-object `field` | `columns` / `column` |
| `filters` / filter `field` / filter `query` | `where` / `column` / `subquery` |
| `filterLogic` | `condition` |
| `limit` | `top` |
| `sort[].field` | `sort[].column` |
| window field `sort` | `orderBy` |
| `pagination.page`, `pagination.pageSize` | top-level `page`, `pageSize` |
| `with.query` | `cte.query` |
| recursive `with` | `recursiveCte` |
| routine `parameters` | `params` |

Clients must use the left column only.
