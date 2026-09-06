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
| `union`, `unionAll` | non-empty `queries` | none |
| `procedure` | `source.procedure` | `parameters` |
| `function`, `tableFunction` | `source.function` | `parameters` |
| `metadata.columns` | `source.table` | none |
| `metadata.tables`, `metadata.views`, `metadata.procedures`, `metadata.schema` | none beyond `action` | none |

Identifiers use `^[A-Za-z_][A-Za-z0-9_.]*$`: letters/underscore first, then letters, digits, underscores, or dot qualifiers. This is syntax validation; SELECT builders also check tables and columns against live metadata.

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
| `DATEPART`, `DATENAME` | `field`, `part`; same integer-date conversion |
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

There is no implicit page or page size. Pagination returns a total from a separate count. SQL strategy is selected from SQL Server compatibility level.

## Window fields

All window functions require `sort`, whose entries have the same public shape as top-level sorting. `partitionBy` is not accepted.

| Function | Additional properties |
|---|---|
| `ROW_NUMBER`, `RANK`, `DENSE_RANK` | `sort` |
| `NTILE` | positive `buckets`, `sort` |
| `LAG`, `LEAD` | `field`, `sort`; optional positive `offset` default 1 and optional `default` |
| `FIRST_VALUE`, `LAST_VALUE` | `field`, `sort` |

## CTE and subquery bodies

A standard CTE is `"with":{"name":"ActiveItems","query":{...select body...}}`. A recursive CTE is `"with":{"name":"Tree","anchor":{...},"recursive":{...}}`. Only one `with` object is accepted. Each branch uses SELECT fields such as `source` and `fields`; an `action` is not required.

Subqueries are accepted only as filter `query` values for IN, NOT IN, EXISTS, and NOT EXISTS. General FROM/SELECT-expression subqueries are not exposed.

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

`queries` is a non-empty array of SELECT bodies without nested actions. Public actions are only `union` and `unionAll`; internal support for INTERSECT/EXCEPT is not public.

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
