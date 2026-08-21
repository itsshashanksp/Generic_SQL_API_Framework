# JSON Request Reference

## Overview

The API uses JSON requests to describe database operations.

A request contains the controller and action to execute, along with the information required by the query builder.

This document describes the request structure and the fields currently used by the query layer.

For practical requests, see [Query Examples](Query-Examples.md).

---

## Basic Structure

```json
{
  "controller": "Query",
  "action": "select",
  "table": "CustomerTable",
  "columns": [
    "Cust_Name",
    "Phone"
  ]
}
```

---

## Request Fields

| Field | Type | Description |
|---|---|---|
| `controller` | string | Controller that handles the request |
| `action` | string | Action to execute |
| `table` | string/object | Main table used by the query |
| `columns` | array | Columns or expressions to select |
| `where` | array | Filtering conditions |
| `joins` | array | JOIN definitions |
| `groupBy` | array | GROUP BY columns |
| `having` | array | HAVING conditions |
| `orderBy` | array | Result ordering |
| `page` | integer | Requested page |
| `pageSize` | integer | Number of rows per page |

> The exact accepted structure is determined by the current query builder and validation layer. Do not add fields to a request unless they are supported by the implementation.

---

## controller

Identifies the controller that should process the request.

Example:

```json
{
  "controller": "Query"
}
```

A normal query request uses:

```json
{
  "controller": "Query"
}
```

---

## action

Identifies the operation to execute.

Example:

```json
{
  "action": "select"
}
```

The controller and action are normally supplied together:

```json
{
  "controller": "Query",
  "action": "select"
}
```

---

## table

Defines the main table used by the query.

Simple form:

```json
{
  "table": "CustomerTable"
}
```

When aliases are supported by the request structure, the table can also carry an alias.

Example:

```json
{
  "table": {
    "name": "CustomerTable",
    "alias": "C"
  }
}
```

Use the structure supported by the current request builder.

---

## columns

Defines the columns returned by the query.

Simple columns:

```json
{
  "columns": [
    "Cust_Name",
    "Phone",
    "City"
  ]
}
```

Qualified columns can be used when working with table names or aliases:

```json
{
  "columns": [
    "C.Cust_Name"
  ]
}
```

---

## Column Aliases

A column can be given an output alias.

Example:

```json
{
  "columns": [
    {
      "column": "Cust_Name",
      "alias": "CustomerName"
    }
  ]
}
```

The resulting SQL is conceptually:

```sql
SELECT
    Cust_Name AS CustomerName
FROM CustomerTable;
```

---

## SQL Functions

The query builder supports function-based expressions.

Example:

```json
{
  "columns": [
    {
      "function": "SUM",
      "column": "Amount",
      "alias": "TotalAmount"
    }
  ]
}
```

The resulting SQL is conceptually:

```sql
SELECT
    SUM(Amount) AS TotalAmount
FROM SalesTable;
```

### Aggregate Functions

The current query implementation includes aggregate functions such as:

```text
COUNT
SUM
AVG
MIN
MAX
```

### String Functions

```text
UPPER
LOWER
LTRIM
RTRIM
TRIM
LEN
```

### Date Functions

```text
YEAR
MONTH
DAY
DATEPART
DATENAME
GETDATE
```

### Mathematical Functions

```text
ABS
ROUND
CEILING
FLOOR
POWER
SQRT
EXP
LOG
```

Refer to [Query Examples](Query-Examples.md) for usage examples.

---

## where

`where` defines filtering conditions.

A condition is represented using:

```text
left
operator
right
```

Example:

```json
{
  "where": [
    {
      "left": {
        "column": "Status"
      },
      "operator": "=",
      "right": "Active"
    }
  ]
}
```

Conceptually:

```sql
WHERE Status = 'Active'
```

---

## Multiple Conditions

Multiple conditions can be supplied through the `where` array.

Example:

```json
{
  "where": [
    {
      "left": {
        "column": "Status"
      },
      "operator": "=",
      "right": "Active"
    },
    {
      "left": {
        "column": "City"
      },
      "operator": "=",
      "right": "Bangalore"
    }
  ]
}
```

The exact logical combination of conditions should follow the behavior implemented by the current query builder.

---

## Operators

The query builder validates operators before they are used in generated SQL.

Common SQL comparison operators include:

```text
=
<>
!=
>
<
>=
<=
```

Additional operators should only be used when supported by the current implementation.

---

## Expressions

The query builder supports structured expressions.

Example:

```json
{
  "expression": {
    "left": {
      "column": "Amount"
    },
    "operator": "+",
    "right": 100
  }
}
```

Expressions can be used where the query builder accepts expression objects.

---

## JOINs

JOIN definitions are supplied through `joins`.

Example:

```json
{
  "joins": [
    {
      "type": "INNER",
      "table": "InvoiceTable",
      "on": {
        "left": "CustomerTable.Cust_ID",
        "right": "InvoiceTable.Cust_ID"
      }
    }
  ]
}
```

Conceptually:

```sql
INNER JOIN InvoiceTable
    ON CustomerTable.Cust_ID = InvoiceTable.Cust_ID
```

---

## JOIN Types

The query builder supports JOIN types defined by its validation rules.

Common examples are:

```text
INNER
LEFT
RIGHT
```

Use the exact JOIN type accepted by the current implementation.

---

## Table Aliases

Aliases allow tables to be referenced using shorter qualified names.

Example:

```json
{
  "table": {
    "name": "CustomerTable",
    "alias": "C"
  }
}
```

A column can then be referenced as:

```text
C.Cust_Name
```

Aliases are especially useful when multiple tables contain columns with the same name.

---

## groupBy

`groupBy` defines the columns used for grouping.

Example:

```json
{
  "groupBy": [
    "City"
  ]
}
```

Combined example:

```json
{
  "columns": [
    "City",
    {
      "function": "SUM",
      "column": "Amount",
      "alias": "TotalSales"
    }
  ],
  "groupBy": [
    "City"
  ]
}
```

Conceptually:

```sql
SELECT
    City,
    SUM(Amount) AS TotalSales
FROM SalesTable
GROUP BY City;
```

---

## having

`having` applies conditions to grouped results.

Example:

```json
{
  "having": [
    {
      "left": {
        "function": "SUM",
        "column": "Amount"
      },
      "operator": ">",
      "right": 50000
    }
  ]
}
```

Conceptually:

```sql
HAVING SUM(Amount) > 50000
```

`HAVING` is normally used together with `GROUP BY`.

---

## orderBy

`orderBy` controls the result ordering.

Example:

```json
{
  "orderBy": [
    {
      "column": "Cust_Name",
      "direction": "ASC"
    }
  ]
}
```

Descending order:

```json
{
  "orderBy": [
    {
      "column": "Cust_Name",
      "direction": "DESC"
    }
  ]
}
```

Supported directions are:

```text
ASC
DESC
```

---

## Pagination

Pagination is controlled using:

```text
page
pageSize
```

Example:

```json
{
  "page": 2,
  "pageSize": 25
}
```

The query layer calculates the required offset.

The current SQL Server implementation uses SQL Server pagination syntax internally.

Conceptually:

```sql
ORDER BY ...
OFFSET ... ROWS
FETCH NEXT ... ROWS ONLY
```

The client only needs to provide the page information.

---

## Prepared Values

Values used by query conditions are passed through the query execution layer.

The execution layer supports prepared ODBC statements.

Conceptually:

```text
JSON Request
     |
     v
Query Builder
     |
     +-- SQL
     |
     +-- Values
     |
     v
Prepared Statement
     |
     v
ODBC Execute
```

This keeps values separate from the generated SQL where prepared execution is used.

---

## Complete Request

The following combines several query components:

```json
{
  "controller": "Query",
  "action": "select",
  "table": "SalesTable",
  "columns": [
    "City",
    {
      "function": "SUM",
      "column": "Amount",
      "alias": "TotalSales"
    }
  ],
  "where": [
    {
      "left": {
        "column": "Status"
      },
      "operator": "=",
      "right": "Completed"
    }
  ],
  "groupBy": [
    "City"
  ],
  "having": [
    {
      "left": {
        "function": "SUM",
        "column": "Amount"
      },
      "operator": ">",
      "right": 50000
    }
  ],
  "orderBy": [
    {
      "column": "TotalSales",
      "direction": "DESC"
    }
  ],
  "page": 1,
  "pageSize": 20
}
```

---

## Validation

The request is validated before execution.

Validation can cover:

- Request structure
- Tables
- Columns
- Functions
- Operators
- JOINs
- Aliases
- Sort directions
- Query properties

Invalid requests should be rejected before they reach the database.

---

## Important

This document describes the request contract.

It does not describe how the SQL is internally generated or executed.

For internal implementation details, see:

[Architecture](Architecture.md)

For HTTP usage, see:

[API](API.md)

For copy/paste requests, see:

[Query Examples](Query-Examples.md)

For database connection settings, see:

[Database Configuration](Database-Configuration.md)