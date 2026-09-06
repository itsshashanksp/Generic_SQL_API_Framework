# Universal JSON API Contract

## Overview

The Generic SQL API accepts query intent as JSON and always returns JSON. Consumers do not send SQL and do not need to know how SQL generation, parameters, ODBC, or pagination are implemented.

Send a `POST` request with `Content-Type: application/json` to the configured API endpoint.

## SELECT request

```json
{
  "action": "select",
  "source": {
    "table": "CustomerTable",
    "alias": "C"
  },
  "fields": [
    "C.Cust_Name",
    {
      "function": "COUNT",
      "field": "C.Cust_Name",
      "alias": "TotalCustomers"
    }
  ],
  "filters": [
    {
      "field": "C.City",
      "operator": "=",
      "value": "Pune"
    }
  ],
  "joins": [],
  "groupBy": ["C.Cust_Name"],
  "having": [
    {
      "function": "COUNT",
      "field": "C.Cust_Name",
      "operator": ">",
      "value": 1
    }
  ],
  "sort": [
    {
      "field": "C.Cust_Name",
      "direction": "ASC"
    }
  ],
  "pagination": {
    "page": 1,
    "pageSize": 50
  }
}
```

Only `action`, `source`, and `fields` are required for SELECT.

## Response

Every successful operation uses the same envelope:

```json
{
  "success": true,
  "message": "Data Loaded Successfully",
  "data": [],
  "meta": {
    "page": 1,
    "pageSize": 50,
    "totalRows": 0,
    "rowsReturned": 0,
    "executionTime": 12.34
  }
}
```

`page`, `pageSize`, and `executionTime` are `null` when they do not apply. `data` is always an array.

## Error response

```json
{
  "success": false,
  "message": "Invalid request.",
  "error": {
    "code": "INVALID_REQUEST",
    "details": [
      {
        "path": "pagination.page",
        "message": "Must be a positive integer."
      }
    ]
  },
  "data": []
}
```

Public error codes include `INVALID_JSON`, `INVALID_REQUEST`, and `QUERY_ERROR`. Database driver messages, stack traces, credentials, paths, and PHP implementation details are not returned. Detailed failures are written to backend logs.

## Source

SELECT supports a table source and optional alias:

```json
{ "source": { "table": "Items", "alias": "I" } }
```

Routine actions use the same source concept:

```json
{ "action": "procedure", "source": { "procedure": "RunReport" }, "parameters": [1] }
{ "action": "function", "source": { "function": "dbo.Score" }, "parameters": [1] }
{ "action": "tableFunction", "source": { "function": "dbo.Rows" }, "parameters": [1] }
```

Routine parameters are positional and are executed as prepared values.

## Fields and aliases

Plain fields are strings. Aliases and functions use an object:

```json
{
  "fields": [
    "ItemCode",
    { "field": "Description", "alias": "ItemName" },
    { "function": "SUM", "field": "Amount", "alias": "TotalAmount" }
  ]
}
```

`"*"` is supported as a plain field. Identifiers and aliases must use letters, numbers, underscores, and optional table qualifiers; identifiers cannot start with a number.

Supported functions match the query implementation:

- Aggregates: `COUNT`, `SUM`, `AVG`, `MIN`, `MAX`, `STRING_AGG`
- String/conversion: `UPPER`, `LOWER`, `LTRIM`, `RTRIM`, `TRIM`, `LEN`, `COALESCE`, `ISNULL`, `CAST`, `CONVERT`, `NULLIF`, `CONCAT`, `LEFT`, `RIGHT`, `SUBSTRING`, `REPLACE`, `CHARINDEX`, `PATINDEX`, `FORMAT`, `CHOOSE`
- Date/time: `YEAR`, `MONTH`, `DAY`, `DATEPART`, `DATENAME`, `GETDATE`, `DATEADD`, `DATEDIFF`, `EOMONTH`, `ISDATE`, `DATEFROMPARTS`, `DATETIMEFROMPARTS`, `TIMEFROMPARTS`, `SYSDATETIME`, `CURRENT_TIMESTAMP`, `IIF`
- Math: `ABS`, `ROUND`, `CEILING`, `FLOOR`, `POWER`, `SQRT`, `EXP`, `LOG`
- Window: `ROW_NUMBER`, `RANK`, `DENSE_RANK`, `NTILE`, `LAG`, `LEAD`, `FIRST_VALUE`, `LAST_VALUE`

Function-specific options use the names supported by the function, such as `datepart`, `number`, `start`, `end`, `datatype`, `style`, `separator`, `offset`, `default`, `buckets`, or `values`. Functions that accept several fields use `fields`.

CASE and arithmetic expressions use the same public field terminology:

```json
{
  "fields": [
    {
      "case": {
        "when": [
          {
            "condition": { "field": "Status", "operator": "=", "value": "Active" },
            "then": "Open"
          }
        ],
        "else": "Closed"
      },
      "alias": "StatusLabel"
    },
    {
      "expression": { "left": "Amount", "operator": "+", "right": 1 },
      "alias": "AdjustedAmount"
    }
  ]
}
```

Arithmetic operators are limited to `+`, `-`, `*`, `/`, and `%`. Expression fields, CASE fields, operators, and aliases are validated before SQL generation; literal values are safely encoded by the expression layer.

## Filters

```json
{
  "filters": [
    { "field": "Status", "operator": "=", "value": "Active" },
    { "field": "Id", "operator": "IN", "value": [1, 2, 3] },
    { "field": "Created", "operator": "BETWEEN", "value": ["2026-01-01", "2026-01-31"] },
    { "field": "DeletedAt", "operator": "IS NULL" }
  ],
  "filterLogic": "AND"
}
```

Supported operators are `=`, `!=`, `<>`, `>`, `<`, `>=`, `<=`, `LIKE`, `NOT LIKE`, `IN`, `NOT IN`, `BETWEEN`, `NOT BETWEEN`, `IS NULL`, `IS NOT NULL`, `EXISTS`, and `NOT EXISTS`. `filterLogic` is `AND` or `OR` and defaults to `AND`. Values continue through prepared parameter handling.

## Subqueries and EXISTS

Use `query` for a nested SELECT:

```json
{
  "filters": [
    {
      "field": "GroupId",
      "operator": "IN",
      "query": {
        "source": { "table": "Groups" },
        "fields": ["Id"],
        "filters": [{ "field": "Active", "operator": "=", "value": 1 }]
      }
    }
  ]
}
```

For `EXISTS` and `NOT EXISTS`, omit `field` and provide `query`.

## Joins

The current implementation supports one equality condition per `INNER`, `LEFT`, or `RIGHT` join:

```json
{
  "joins": [
    {
      "type": "LEFT",
      "source": { "table": "Orders", "alias": "O" },
      "on": {
        "left": "C.Id",
        "operator": "=",
        "right": "O.CustomerId"
      }
    }
  ]
}
```

Other join operators and multiple conditions per join are not currently supported.

## Grouping and HAVING

`groupBy` contains logical field names. HAVING supports aggregate functions and comparison operators:

```json
{
  "groupBy": ["Category"],
  "having": [
    { "function": "SUM", "field": "Amount", "operator": ">", "value": 1000 }
  ]
}
```

## Sorting

```json
{
  "sort": [
    { "field": "ItemCode", "direction": "ASC" },
    { "field": "Description", "direction": "DESC" }
  ]
}
```

Sort entries always identify logical fields or selected aliases. Numeric SQL positions such as `1` are rejected by the public contract. The server validates and resolves fields for both normal and window-based pagination ordering.

## Pagination

```json
{ "pagination": { "page": 2, "pageSize": 50 } }
```

Both values must be positive integers. SQL Server compatibility detection and the choice between `OFFSET/FETCH` and `ROW_NUMBER` are private implementation details.

## DISTINCT and row limits

```json
{
  "distinct": true,
  "limit": 100
}
```

`limit` maps to the supported SQL Server TOP behavior and must be a positive integer.

## Window functions

Window ordering uses logical public fields:

```json
{
  "fields": [
    "ItemCode",
    {
      "function": "ROW_NUMBER",
      "alias": "RowNumber",
      "sort": [{ "field": "ItemCode", "direction": "ASC" }]
    }
  ]
}
```

## CTEs

A non-recursive CTE uses `with.query`:

```json
{
  "action": "select",
  "source": { "table": "ActiveItems" },
  "fields": ["ItemCode"],
  "with": {
    "name": "ActiveItems",
    "query": {
      "source": { "table": "Items" },
      "fields": ["ItemCode"],
      "filters": [{ "field": "Active", "operator": "=", "value": 1 }]
    }
  }
}
```

Recursive CTEs use `with.name`, `with.anchor`, and `with.recursive`, where both branches are SELECT request bodies.

## UNION and UNION ALL

```json
{
  "action": "unionAll",
  "queries": [
    { "source": { "table": "CurrentItems" }, "fields": ["ItemCode"] },
    { "source": { "table": "ArchivedItems" }, "fields": ["ItemCode"] }
  ]
}
```

Use `action: "union"` for duplicate-removing UNION. Each query uses the SELECT body without a nested `action` property.

## Metadata actions

Existing metadata operations use these public actions:

- `metadata.tables`
- `metadata.columns` with `source.table`
- `metadata.views`
- `metadata.procedures`
- `metadata.schema`

## Validation and security

Requests are validated before normalization and SQL construction. Public terminology is translated to an internal query model that is not part of this contract. Source names, fields, aliases, sort fields, functions, operators, joins, and pagination are validated. Filter and HAVING values remain parameterized. Raw SQL is not accepted anywhere in the public contract.
