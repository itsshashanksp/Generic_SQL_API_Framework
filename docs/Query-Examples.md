# Query Examples

Every example below uses the public JSON contract accepted by the current validator.

## Select, alias, filter, and pagination

```json
{
  "action": "select",
  "source": { "table": "Items", "alias": "I" },
  "fields": ["I.ItemCode", { "field": "I.Description", "alias": "ItemName" }],
  "filters": [
    { "field": "I.Status", "operator": "=", "value": "Active" },
    { "field": "I.Amount", "operator": "BETWEEN", "value": [10, 100] }
  ],
  "filterLogic": "AND",
  "sort": [
    { "field": "I.ItemCode", "direction": "ASC" },
    { "field": "I.Amount", "direction": "DESC" }
  ],
  "pagination": { "page": 2, "pageSize": 25 }
}
```

## Join, aggregate, grouping, and HAVING

```json
{
  "action": "select",
  "source": { "table": "Orders", "alias": "O" },
  "fields": [
    "C.CustomerName",
    { "function": "SUM", "field": "O.Amount", "alias": "TotalAmount" }
  ],
  "joins": [
    {
      "type": "INNER",
      "source": { "table": "Customers", "alias": "C" },
      "on": { "left": "O.CustomerId", "operator": "=", "right": "C.CustomerId" }
    }
  ],
  "groupBy": ["C.CustomerName"],
  "having": [
    { "function": "SUM", "field": "O.Amount", "operator": ">", "value": 1000 }
  ],
  "sort": [{ "field": "TotalAmount", "direction": "DESC" }]
}
```

## DISTINCT, limit, CASE, and arithmetic

```json
{
  "action": "select",
  "source": { "table": "Items" },
  "distinct": true,
  "limit": 100,
  "fields": [
    "ItemCode",
    {
      "case": {
        "when": [
          { "condition": { "field": "Status", "operator": "=", "value": "A" }, "then": "Active" }
        ],
        "else": "Inactive"
      },
      "alias": "StatusText"
    },
    { "expression": { "left": "Amount", "operator": "*", "right": 1.18 }, "alias": "GrossAmount" }
  ]
}
```

## Functions and null handling

```json
{
  "action": "select",
  "source": { "table": "Items" },
  "fields": [
    { "function": "UPPER", "field": "Description", "alias": "UpperDescription" },
    { "function": "COALESCE", "fields": ["ItemCode", "Description"], "default": "", "alias": "DisplayName" },
    { "function": "CAST", "field": "Amount", "datatype": "decimal(10,2)", "alias": "AmountValue" },
    { "function": "DATEADD", "field": "NumericDate", "datepart": "day", "number": 7, "style": 112, "alias": "DueDate" },
    { "function": "ROUND", "field": "Amount", "precision": 2, "alias": "RoundedAmount" }
  ]
}
```

## IN subquery and EXISTS

```json
{
  "action": "select",
  "source": { "table": "Items" },
  "fields": ["ItemCode"],
  "filters": [
    {
      "field": "GroupId",
      "operator": "IN",
      "query": {
        "source": { "table": "Groups" },
        "fields": ["GroupId"],
        "filters": [{ "field": "Active", "operator": "=", "value": 1 }]
      }
    },
    {
      "operator": "EXISTS",
      "query": { "source": { "table": "Stock" }, "fields": ["ItemCode"] }
    }
  ]
}
```

## Window functions

```json
{
  "action": "select",
  "source": { "table": "Sales" },
  "fields": [
    "SaleId",
    { "function": "ROW_NUMBER", "sort": [{ "field": "SaleId", "direction": "ASC" }], "alias": "RowNo" },
    { "function": "LAG", "field": "Amount", "offset": 1, "default": 0, "sort": [{ "field": "SaleId" }], "alias": "PreviousAmount" }
  ]
}
```

Window sort values are logical fields, never numeric positions. `partitionBy` is not currently supported.

## CTE and recursive CTE

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

```json
{
  "action": "select",
  "source": { "table": "ItemTree" },
  "fields": ["ItemId"],
  "with": {
    "name": "ItemTree",
    "anchor": { "source": { "table": "RootItems" }, "fields": ["ItemId"] },
    "recursive": { "source": { "table": "ItemTree" }, "fields": ["ItemId"] }
  }
}
```

## UNION ALL

```json
{
  "action": "unionAll",
  "queries": [
    { "source": { "table": "CurrentItems" }, "fields": ["ItemCode"] },
    { "source": { "table": "ArchivedItems" }, "fields": ["ItemCode"] }
  ]
}
```

Use `union` for duplicate-removing UNION. INTERSECT and EXCEPT are not public actions.

## Routines and metadata

```json
{ "action": "procedure", "source": { "procedure": "dbo.RunReport" }, "parameters": [2026] }
```

```json
{ "action": "function", "source": { "function": "dbo.Score" }, "parameters": [42] }
```

```json
{ "action": "tableFunction", "source": { "function": "dbo.RowsForYear" }, "parameters": [2026] }
```

```json
{ "action": "metadata.columns", "source": { "table": "Items" } }
```

See [JSON Request Reference](JSON-Request-Reference.md) for all accepted properties,
[Query function reference](Query-Functions.md) for every usable function, and
[Capability matrix](Capability-Matrix.md) for mode boundaries.
