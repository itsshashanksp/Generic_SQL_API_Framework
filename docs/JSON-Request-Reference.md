# JSON Request Reference

## Overview

The Generic SQL API uses JSON requests to describe database operations.

The request is interpreted by the backend query layer, validated, converted into SQL, and executed against the configured database.

The request structure is designed to provide a common representation for database queries without requiring the client to construct SQL directly.

This document describes the JSON structures currently used by the API.

For practical examples, see [Query Examples](Query-Examples.md).

For HTTP/API usage, see [API](API.md).

For the internal processing flow, see [Architecture](Architecture.md).

---

# Basic Request

A basic SELECT request can be represented as:

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

The request identifies:

```text
controller
    ↓
action
    ↓
table
    ↓
columns
    ↓
query builder
    ↓
SQL execution
```

---

# Request Fields

The following fields are used by the query request structure.

| Field | Type | Purpose |
|---|---|---|
| `controller` | string | Identifies the controller handling the request |
| `action` | string | Identifies the operation |
| `table` | string/object | Defines the main table or table source |
| `columns` | array | Defines selected columns and expressions |
| `where` | array | Defines row-level filtering conditions |
| `joins` | array | Defines table joins |
| `groupBy` | array | Defines grouping columns |
| `having` | array | Defines conditions applied to grouped results |
| `orderBy` | array | Defines result ordering |
| `page` | integer | Defines the requested page |
| `pageSize` | integer | Defines the number of rows requested per page |

> The exact accepted structure is determined by the current backend query builder and validation layer.

---

# controller

`controller` identifies the backend controller responsible for processing the request.

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

The controller is normally supplied together with `action`.

Example:

```json
{
    "controller": "Query",
    "action": "select"
}
```

---

# action

`action` identifies the operation requested from the controller.

Example:

```json
{
    "action": "select"
}
```

A standard SELECT request therefore begins with:

```json
{
    "controller": "Query",
    "action": "select"
}
```

Other database operations will be documented when they are exposed through the API.

---

# table

`table` defines the main table used by the query.

Simple table:

```json
{
    "table": "CustomerTable"
}
```

Example:

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

# columns

`columns` defines the values returned by the query.

## Simple Columns

```json
{
    "columns": [
        "Cust_Name",
        "Phone",
        "City"
    ]
}
```

The corresponding SQL is:

```sql
SELECT
    Cust_Name,
    Phone,
    City
FROM CustomerTable;
```

---

## Qualified Columns

Columns can be qualified with a table name or alias when required.

Example:

```json
{
    "columns": [
        "CustomerTable.Cust_Name",
        "InvoiceTable.InvoiceNo"
    ]
}
```

This is useful when multiple tables contain columns with the same name.

---

# Column Expressions

The `columns` array can contain structured expressions in addition to simple column names.

For example:

```json
{
    "columns": [
        "Cust_Name",
        {
            "function": "SUM",
            "column": "Bill_Amt",
            "alias": "TotalBill"
        }
    ]
}
```

Conceptually:

```sql
SELECT
    Cust_Name,
    SUM(Bill_Amt) AS TotalBill
FROM CustomerTable;
```

---

# Column Aliases

An expression can define an output alias.

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

Generated SQL:

```sql
SELECT
    SUM(Amount) AS TotalAmount
FROM SalesTable;
```

Aliases are useful when the generated result needs a readable or application-specific field name.

---

# SQL Functions

The query layer supports function-based expressions.

Example:

```json
{
    "columns": [
        {
            "function": "COUNT",
            "column": "Cust_Name",
            "alias": "TotalCustomers"
        }
    ]
}
```

Generated SQL:

```sql
SELECT
    COUNT(Cust_Name) AS TotalCustomers
FROM CustomerTable;
```

---

# Aggregate Functions

The currently supported aggregate functions include:

```text
COUNT
SUM
AVG
MIN
MAX
STRING_AGG
```

Example:

```json
{
    "columns": [
        {
            "function": "SUM",
            "column": "Amount",
            "alias": "TotalSales"
        },
        {
            "function": "AVG",
            "column": "Amount",
            "alias": "AverageSales"
        }
    ]
}
```

---

# String Functions

The current implementation supports string-related functions including:

```text
UPPER
LOWER
LTRIM
RTRIM
TRIM
LEN
COALESCE
ISNULL
NULLIF
CONCAT
LEFT
RIGHT
SUBSTRING
REPLACE
CHARINDEX
PATINDEX
FORMAT
```

Example SQL:

```sql
SELECT
    UPPER(Cust_Name) AS CustomerName
FROM CustomerTable;
```

---

# Conversion Functions

The query layer supports:

```text
CAST
CONVERT
```

Example SQL:

```sql
SELECT
    CAST(Bill_Amt AS DECIMAL(18,2)) AS BillAmount
FROM CustomerTable;
```

---

# Date and Time Functions

The current implementation supports:

```text
YEAR
MONTH
DAY
DATEPART
DATENAME
GETDATE
DATEADD
DATEDIFF
EOMONTH
ISDATE
DATEFROMPARTS
DATETIMEFROMPARTS
TIMEFROMPARTS
SYSDATETIME
CURRENT_TIMESTAMP
IIF
```

Example SQL:

```sql
SELECT
    InvoiceDate,
    YEAR(InvoiceDate) AS InvoiceYear
FROM InvoiceTable;
```

---

# Mathematical Functions

The current implementation supports:

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

Example SQL:

```sql
SELECT
    Amount,
    ROUND(Amount, 2) AS RoundedAmount
FROM SalesTable;
```

---

# Window Functions

The query layer supports window functions including:

```text
ROW_NUMBER
RANK
DENSE_RANK
NTILE
LAG
LEAD
FIRST_VALUE
LAST_VALUE
```

Example SQL:

```sql
SELECT
    Cust_Name,
    Balance,
    ROW_NUMBER() OVER (
        ORDER BY Balance DESC
    ) AS RowNumber
FROM CustomerTable;
```

---

# WHERE

`where` defines conditions used to filter rows.

Example:

```json
{
    "where": [
        {
            "column": "City",
            "operator": "=",
            "value": "Bangalore"
        }
    ]
}
```

Conceptually:

```sql
WHERE City = ?
```

The value is supplied separately during query execution.

---

# Multiple WHERE Conditions

Multiple conditions can be supplied through the `where` array.

Example:

```json
{
    "where": [
        {
            "column": "City",
            "operator": "=",
            "value": "Bangalore"
        },
        {
            "column": "Balance",
            "operator": ">",
            "value": 50000
        }
    ]
}
```

Conceptually:

```sql
WHERE City = ?
AND Balance > ?
```

The exact logical combination follows the current query builder implementation.

---

# Comparison Operators

The query layer validates operators before they are included in generated SQL.

Common comparison operators include:

```text
=
<>
!=
>
<
>=
<=
```

Additional operators supported by the query layer include:

```text
IN
NOT IN
BETWEEN
NOT BETWEEN
```

Use only operators accepted by the current validation rules.

---

# IN

`IN` can be used when a value needs to be compared against multiple values.

Conceptually:

```sql
WHERE City IN (?, ?, ?)
```

The values are supplied separately during query execution.

---

# NOT IN

Example:

```sql
WHERE City NOT IN (?, ?, ?)
```

The values are supplied separately during query execution.

---

# BETWEEN

Example:

```sql
WHERE Balance BETWEEN ? AND ?
```

The lower and upper values are supplied separately.

---

# NOT BETWEEN

Example:

```sql
WHERE Balance NOT BETWEEN ? AND ?
```

---

# JOINs

`joins` defines additional tables that participate in the query.

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

# JOIN Types

JOIN types are validated by the backend.

Common supported JOIN types include:

```text
INNER
LEFT
RIGHT
```

Use the exact value accepted by the current implementation.

---

# GROUP BY

`groupBy` defines the columns used to group results.

Example:

```json
{
    "groupBy": [
        "City"
    ]
}
```

A grouped query can combine normal columns with aggregate functions.

Example:

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

Generated SQL:

```sql
SELECT
    City,
    SUM(Amount) AS TotalSales
FROM SalesTable
GROUP BY City;
```

---

# HAVING

`having` defines conditions applied after grouping.

Example:

```json
{
    "having": [
        {
            "function": "SUM",
            "column": "Amount",
            "operator": ">",
            "value": 50000
        }
    ]
}
```

Conceptually:

```sql
HAVING SUM(Amount) > ?
```

`HAVING` is normally used with `GROUP BY`.

---

# ORDER BY

`orderBy` controls the order of returned rows.

Example:

```json
{
    "orderBy": [
        {
            "column": "Balance",
            "direction": "DESC"
        }
    ]
}
```

Generated SQL:

```sql
ORDER BY Balance DESC
```

---

# Multiple ORDER BY Columns

Multiple ordering definitions can be supplied.

Example:

```json
{
    "orderBy": [
        {
            "column": "City",
            "direction": "ASC"
        },
        {
            "column": "Balance",
            "direction": "DESC"
        }
    ]
}
```

Conceptually:

```sql
ORDER BY
    City ASC,
    Balance DESC
```

Supported directions:

```text
ASC
DESC
```

---

# Pagination

Pagination is represented using:

```text
page
pageSize
```

Example:

```json
{
    "page": 2,
    "pageSize": 20
}
```

For page 2 with a page size of 20, the query layer calculates the corresponding offset.

Conceptually:

```sql
ORDER BY ...
OFFSET 20 ROWS
FETCH NEXT 20 ROWS ONLY
```

The actual SQL generated depends on the query builder and ordering supplied by the request.

---

# DISTINCT

The query layer supports distinct result selection.

Conceptually:

```sql
SELECT DISTINCT
    City
FROM CustomerTable;
```

The exact request representation should follow the current query builder structure.

---

# TOP

The query layer supports limiting the number of returned rows using SQL Server `TOP`.

Conceptually:

```sql
SELECT TOP 10
    Cust_Name,
    Balance
FROM CustomerTable;
```

The exact request representation should follow the current query builder structure.

---

# CASE Expressions

CASE expressions are supported for conditional SQL expressions.

Conceptually:

```sql
SELECT
    Cust_Name,
    CASE
        WHEN Balance >= ? THEN ?
        ELSE ?
    END AS CustomerType
FROM CustomerTable;
```

CASE expressions can be used where supported by the query expression structure.

---

# Arithmetic Expressions

Arithmetic expressions can be used to calculate values from database columns.

Example SQL:

```sql
SELECT
    Quantity,
    UnitPrice,
    Quantity * UnitPrice AS TotalAmount
FROM SalesTable;
```

Expressions are represented using the structured expression format supported by the query builder.

---

# Subqueries

The query layer supports subquery-based expressions where supported by the request structure.

Example:

```sql
SELECT
    Cust_Name,
    Balance
FROM CustomerTable
WHERE Balance > (
    SELECT AVG(Balance)
    FROM CustomerTable
);
```

---

# EXISTS

Example:

```sql
SELECT
    CustomerTable.Cust_Name
FROM CustomerTable
WHERE EXISTS (
    SELECT 1
    FROM InvoiceTable
    WHERE InvoiceTable.Cust_ID = CustomerTable.Cust_ID
);
```

---

# NOT EXISTS

Example:

```sql
SELECT
    CustomerTable.Cust_Name
FROM CustomerTable
WHERE NOT EXISTS (
    SELECT 1
    FROM InvoiceTable
    WHERE InvoiceTable.Cust_ID = CustomerTable.Cust_ID
);
```

---

# CTE

Common Table Expressions are supported by the query layer.

Example:

```sql
WITH CustomerTotals AS (
    SELECT
        Cust_ID,
        SUM(Bill_Amt) AS TotalBill
    FROM CustomerTable
    GROUP BY Cust_ID
)
SELECT
    Cust_ID,
    TotalBill
FROM CustomerTotals;
```

---

# Recursive CTE

Recursive CTE support is available for hierarchical query scenarios.

Example:

```sql
WITH EmployeeHierarchy AS (
    SELECT
        EmployeeID,
        EmployeeName,
        ManagerID,
        0 AS Level
    FROM EmployeeTable
    WHERE ManagerID IS NULL

    UNION ALL

    SELECT
        E.EmployeeID,
        E.EmployeeName,
        E.ManagerID,
        H.Level + 1
    FROM EmployeeTable E
    INNER JOIN EmployeeHierarchy H
        ON E.ManagerID = H.EmployeeID
)
SELECT
    EmployeeID,
    EmployeeName,
    ManagerID,
    Level
FROM EmployeeHierarchy;
```

---

# UNION

The query layer supports `UNION` operations where supported by the query definition.

Example SQL:

```sql
SELECT
    Cust_Name,
    City
FROM CustomerTable

UNION

SELECT
    CustomerName,
    City
FROM ArchivedCustomerTable;
```

---

# UNION ALL

Example SQL:

```sql
SELECT
    Cust_Name,
    City
FROM CustomerTable

UNION ALL

SELECT
    CustomerName,
    City
FROM ArchivedCustomerTable;
```

`UNION` removes duplicate rows while `UNION ALL` retains them.

---

# Prepared Values

Values used by query conditions are passed separately from generated SQL when prepared execution is used.

For example:

```json
{
    "where": [
        {
            "column": "City",
            "operator": "=",
            "value": "Bangalore"
        }
    ]
}
```

can result in:

```sql
WHERE City = ?
```

The value is supplied separately to the prepared ODBC statement.

Conceptually:

```text
JSON Request
     |
     v
Query Builder
     |
     +------ SQL
     |
     +------ Values
     |
     v
Prepared Statement
     |
     v
ODBC
     |
     v
Database
```

---

# Stored Procedures

The backend supports stored procedure execution.

A stored procedure can receive parameters through the supported procedure request structure.

Conceptually:

```sql
EXEC GetCustomerDetails
    @CustomerId = ?;
```

The exact JSON structure for procedure execution should follow the request structure implemented by the backend.

---

# Scalar Functions

The backend supports scalar database function execution.

Example SQL:

```sql
SELECT
    dbo.CalculateCustomerBalance(?) AS Balance;
```

Parameters are supplied separately during execution.

---

# Table-Valued Functions

The backend supports table-valued database functions.

Example SQL:

```sql
SELECT
    CustomerID,
    CustomerName,
    Balance
FROM dbo.GetCustomerDetails(?);
```

---

# Complete SELECT Request

The following example combines filtering, aggregation, grouping, sorting, and pagination.

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
            "column": "Status",
            "operator": "=",
            "value": "Completed"
        }
    ],
    "groupBy": [
        "City"
    ],
    "having": [
        {
            "function": "SUM",
            "column": "Amount",
            "operator": ">",
            "value": 50000
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

Conceptually, the query flow is:

```text
WHERE
  ↓
GROUP BY
  ↓
HAVING
  ↓
ORDER BY
  ↓
PAGINATION
```

---

# Example – Customer Aggregation

A request such as:

```json
{
    "controller": "Query",
    "action": "select",
    "table": "CustomerTable",
    "columns": [
        "Cust_Name",
        {
            "function": "COUNT",
            "column": "Cust_Name",
            "alias": "TotalCustomers"
        },
        {
            "function": "MIN",
            "column": "Bill_Amt",
            "alias": "MinimumBill"
        },
        {
            "function": "MAX",
            "column": "Bill_Amt",
            "alias": "MaximumBill"
        }
    ],
    "groupBy": [
        "Cust_Name"
    ],
    "where": [],
    "page": 1,
    "pageSize": 50
}
```

produces the equivalent SQL:

```sql
SELECT
    Cust_Name,
    COUNT(Cust_Name) AS TotalCustomers,
    MIN(Bill_Amt) AS MinimumBill,
    MAX(Bill_Amt) AS MaximumBill
FROM CustomerTable
GROUP BY Cust_Name;
```

---

# Validation

Requests are validated before execution.

Validation includes the structures and query components supported by the current backend implementation.

Validation can include:

- Request structure
- Controller
- Action
- Tables
- Columns
- Functions
- Operators
- JOIN definitions
- Aliases
- Sort directions
- Query expressions
- Pagination values

Invalid requests should be rejected before being sent to the database.

---

# Unsupported Operations

The current API documentation primarily covers SELECT/query functionality.

The following database write operations are planned for future versions:

```text
INSERT
UPDATE
DELETE
UPSERT
```

Transaction support is also planned.

These should not be treated as currently supported API operations until implemented and documented.

---

# Security Considerations

The JSON request should not be treated as trusted SQL.

The backend validates query components before generating and executing SQL.

Applications should not construct requests in a way that attempts to bypass validation.

Values should be supplied through the request structure and handled by the backend execution layer rather than concatenating user input into SQL.

---

# Request Processing Flow

The backend processes a request through the following general flow:

```text
JSON Request
     |
     v
Request Parsing
     |
     v
Validation
     |
     v
Query Builder
     |
     v
Generated SQL + Parameters
     |
     v
Database Execution
     |
     v
JSON Response
```

---

# JSON vs SQL

The JSON request format is the application's structured representation of a query.

It is not intended to expose every possible SQL syntax construct directly.

The backend converts supported request structures into SQL through the existing query builder.

For developers who prefer SQL authoring, a future SQL-to-definition converter can translate supported SQL into the same internal request/definition structure.

The converter must use the same validation and query capabilities rather than creating a separate execution path.

---

# Related Documentation

- [Introduction](Introduction.md)
- [Architecture](Architecture.md)
- [API](API.md)
- [Query Examples](Query-Examples.md)
- [Database Configuration](Database-Configuration.md)
- [Hosting](Hosting.md)
- [Roadmap](Roadmap.md)

---

# Documentation Maintenance

This document represents the JSON request contract exposed by the backend.

When the request structure changes:

1. Update this document.
2. Update the relevant examples in `Query-Examples.md`.
3. Update the API documentation if the HTTP contract changes.
4. Update the SQL-to-definition converter specification if the converter depends on the changed structure.
5. Update `Roadmap.md` when the change introduces or completes a planned feature.

The documentation should remain aligned with the actual backend validation and query-builder implementation.