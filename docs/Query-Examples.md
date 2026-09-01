# Query Examples

## Overview

This document contains practical examples demonstrating how to use the Generic SQL API Framework.

The examples show how JSON API requests are translated into SQL queries by the backend query builder.

Each example includes:

- JSON Request
- Generated SQL
- Expected Response where applicable

These examples are intended as a quick reference for developers integrating applications with the API.

For the complete JSON request structure, see [JSON Request Reference](JSON-Request-Reference.md).

For HTTP API usage, see [API](API.md).

---

# Basic Queries

## Example 1 – Basic SELECT

### Request

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

### Generated SQL

```sql
SELECT
    Cust_Name,
    Phone
FROM CustomerTable;
```

### Example Response

```json
{
    "success": true,
    "rowsReturned": 2,
    "data": [
        {
            "Cust_Name": "ABC Traders",
            "Phone": "9876543210"
        },
        {
            "Cust_Name": "XYZ Enterprises",
            "Phone": "9988776655"
        }
    ]
}
```

---

# Filtering

## Example 2 – WHERE Clause

### Request

```json
{
    "controller": "Query",
    "action": "select",
    "table": "CustomerTable",
    "columns": [
        "Cust_Name",
        "City"
    ],
    "where": [
        {
            "column": "City",
            "operator": "=",
            "value": "Bangalore"
        }
    ]
}
```

### Generated SQL

```sql
SELECT
    Cust_Name,
    City
FROM CustomerTable
WHERE City = ?;
```

The value is passed separately to the prepared statement.

---

## Example 3 – Multiple WHERE Conditions

### Request

```json
{
    "controller": "Query",
    "action": "select",
    "table": "CustomerTable",
    "columns": [
        "Cust_Name",
        "City",
        "Balance"
    ],
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

### Generated SQL

```sql
SELECT
    Cust_Name,
    City,
    Balance
FROM CustomerTable
WHERE City = ?
AND Balance > ?;
```

---

# Sorting

## Example 4 – ORDER BY

### Request

```json
{
    "controller": "Query",
    "action": "select",
    "table": "CustomerTable",
    "columns": [
        "Cust_Name",
        "Balance"
    ],
    "orderBy": [
        {
            "column": "Balance",
            "direction": "DESC"
        }
    ]
}
```

### Generated SQL

```sql
SELECT
    Cust_Name,
    Balance
FROM CustomerTable
ORDER BY Balance DESC;
```

---

## Example 5 – Multiple ORDER BY Columns

### Request

```json
{
    "controller": "Query",
    "action": "select",
    "table": "CustomerTable",
    "columns": [
        "Cust_Name",
        "City",
        "Balance"
    ],
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

### Generated SQL

```sql
SELECT
    Cust_Name,
    City,
    Balance
FROM CustomerTable
ORDER BY
    City ASC,
    Balance DESC;
```

---

# GROUP BY

## Example 6 – GROUP BY

### Request

```json
{
    "controller": "Query",
    "action": "select",
    "table": "SalesTable",
    "columns": [
        "City",
        {
            "function": "COUNT",
            "column": "InvoiceNo",
            "alias": "Invoices"
        }
    ],
    "groupBy": [
        "City"
    ]
}
```

### Generated SQL

```sql
SELECT
    City,
    COUNT(InvoiceNo) AS Invoices
FROM SalesTable
GROUP BY City;
```

---

# HAVING

## Example 7 – HAVING

### Request

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
    "groupBy": [
        "City"
    ],
    "having": [
        {
            "function": "SUM",
            "column": "Amount",
            "operator": ">",
            "value": 100000
        }
    ]
}
```

### Generated SQL

```sql
SELECT
    City,
    SUM(Amount) AS TotalSales
FROM SalesTable
GROUP BY City
HAVING SUM(Amount) > ?;
```

The value is passed separately to the prepared statement.

---

# JOIN

## Example 8 – INNER JOIN

### Request

```json
{
    "controller": "Query",
    "action": "select",
    "table": "CustomerTable",
    "columns": [
        "CustomerTable.Cust_Name",
        "InvoiceTable.InvoiceNo"
    ],
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

### Generated SQL

```sql
SELECT
    CustomerTable.Cust_Name,
    InvoiceTable.InvoiceNo
FROM CustomerTable
INNER JOIN InvoiceTable
ON CustomerTable.Cust_ID = InvoiceTable.Cust_ID;
```

---

# Pagination

## Example 9 – Pagination

### Request

```json
{
    "controller": "Query",
    "action": "select",
    "table": "CustomerTable",
    "columns": [
        "Cust_Name"
    ],
    "pagination": {
        "page": 2,
        "pageSize": 20
    }
}
```

### Generated SQL

Pagination is generated according to the query builder's SQL Server pagination requirements.

Conceptually:

```sql
SELECT
    Cust_Name
FROM CustomerTable
ORDER BY <column>
OFFSET 20 ROWS
FETCH NEXT 20 ROWS ONLY;
```

> SQL Server pagination requires an ordering expression. The actual generated query depends on the request and query builder implementation.

---

# Aggregate Functions

## Example 10 – Multiple Aggregate Functions

### Request

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

### Generated SQL

```sql
SELECT
    Cust_Name,
    COUNT(Cust_Name) AS TotalCustomers,
    MIN(Bill_Amt) AS MinimumBill,
    MAX(Bill_Amt) AS MaximumBill
FROM CustomerTable
GROUP BY Cust_Name;
```

Supported aggregate functions include:

```text
COUNT
SUM
AVG
MIN
MAX
STRING_AGG
```

---

# DISTINCT

## Example 11 – DISTINCT

### Request

```json
{
    "controller": "Query",
    "action": "select",
    "table": "CustomerTable",
    "columns": [
        "City"
    ],
    "distinct": true
}
```

### Generated SQL

```sql
SELECT DISTINCT
    City
FROM CustomerTable;
```

---

# TOP

## Example 12 – TOP

### Request

```json
{
    "controller": "Query",
    "action": "select",
    "table": "CustomerTable",
    "columns": [
        "Cust_Name",
        "Balance"
    ],
    "top": 10
}
```

### Generated SQL

```sql
SELECT TOP 10
    Cust_Name,
    Balance
FROM CustomerTable;
```

---

# CASE Expressions

## Example 13 – CASE Expression

### Request

```json
{
    "controller": "Query",
    "action": "select",
    "table": "CustomerTable",
    "columns": [
        "Cust_Name",
        {
            "case": [
                {
                    "when": {
                        "column": "Balance",
                        "operator": ">=",
                        "value": 100000
                    },
                    "then": "Premium"
                }
            ],
            "else": "Regular",
            "alias": "CustomerType"
        }
    ]
}
```

### Generated SQL

```sql
SELECT
    Cust_Name,
    CASE
        WHEN Balance >= ? THEN ?
        ELSE ?
    END AS CustomerType
FROM CustomerTable;
```

Values are supplied separately during query execution.

---

# Arithmetic Expressions

## Example 14 – Arithmetic Expression

Arithmetic expressions can be used when calculating values from database columns.

### Example SQL

```sql
SELECT
    Quantity,
    UnitPrice,
    Quantity * UnitPrice AS TotalAmount
FROM SalesTable;
```

The expression can be represented using the framework's expression structure where supported.

---

# IN

## Example 15 – IN Condition

### Example SQL

```sql
SELECT
    Cust_Name,
    City
FROM CustomerTable
WHERE City IN (?, ?, ?);
```

The values are supplied separately during execution.

---

# NOT IN

## Example 16 – NOT IN Condition

### Example SQL

```sql
SELECT
    Cust_Name,
    City
FROM CustomerTable
WHERE City NOT IN (?, ?, ?);
```

---

# BETWEEN

## Example 17 – BETWEEN Condition

### Example SQL

```sql
SELECT
    Cust_Name,
    Balance
FROM CustomerTable
WHERE Balance BETWEEN ? AND ?;
```

---

# NOT BETWEEN

## Example 18 – NOT BETWEEN Condition

### Example SQL

```sql
SELECT
    Cust_Name,
    Balance
FROM CustomerTable
WHERE Balance NOT BETWEEN ? AND ?;
```

---

# Subqueries

## Example 19 – Subquery

A subquery can be used where supported by the framework query definition.

### Example SQL

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

The subquery is evaluated by SQL Server as part of the generated query.

---

# EXISTS

## Example 20 – EXISTS

### Example SQL

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

## Example 21 – NOT EXISTS

### Example SQL

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

# Common SQL Functions

The API supports SQL functions through the query definition.

## Example 22 – String Functions

### Example SQL

```sql
SELECT
    UPPER(Cust_Name) AS CustomerName,
    LOWER(City) AS CityName,
    LEN(Cust_Name) AS NameLength
FROM CustomerTable;
```

Supported examples include:

```text
UPPER
LOWER
LTRIM
RTRIM
TRIM
LEN
CONCAT
LEFT
RIGHT
SUBSTRING
REPLACE
CHARINDEX
PATINDEX
FORMAT
```

---

## Example 23 – NULL Handling

### Example SQL

```sql
SELECT
    Cust_Name,
    COALESCE(Phone, 'N/A') AS Phone
FROM CustomerTable;
```

Supported functions include:

```text
COALESCE
ISNULL
NULLIF
```

---

## Example 24 – CAST and CONVERT

### Example SQL

```sql
SELECT
    CAST(Bill_Amt AS DECIMAL(18,2)) AS BillAmount
FROM CustomerTable;
```

Another example:

```sql
SELECT
    CONVERT(VARCHAR(10), InvoiceDate, 120) AS InvoiceDate
FROM InvoiceTable;
```

Supported functions include:

```text
CAST
CONVERT
```

---

# Date and Time Functions

## Example 25 – Date Functions

### Example SQL

```sql
SELECT
    InvoiceDate,
    YEAR(InvoiceDate) AS InvoiceYear,
    MONTH(InvoiceDate) AS InvoiceMonth,
    DAY(InvoiceDate) AS InvoiceDay
FROM InvoiceTable;
```

Supported functions include:

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

---

# Mathematical Functions

## Example 26 – Mathematical Functions

### Example SQL

```sql
SELECT
    Bill_Amt,
    ABS(Bill_Amt) AS AbsoluteAmount,
    ROUND(Bill_Amt, 2) AS RoundedAmount,
    CEILING(Bill_Amt) AS CeilingAmount,
    FLOOR(Bill_Amt) AS FloorAmount
FROM CustomerTable;
```

Supported functions include:

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

---

# Window Functions

## Example 27 – ROW_NUMBER

### Example SQL

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

## Example 28 – RANK

### Example SQL

```sql
SELECT
    Cust_Name,
    Balance,
    RANK() OVER (
        ORDER BY Balance DESC
    ) AS CustomerRank
FROM CustomerTable;
```

---

## Example 29 – LAG and LEAD

### Example SQL

```sql
SELECT
    InvoiceDate,
    Amount,
    LAG(Amount) OVER (
        ORDER BY InvoiceDate
    ) AS PreviousAmount,
    LEAD(Amount) OVER (
        ORDER BY InvoiceDate
    ) AS NextAmount
FROM SalesTable;
```

Supported window functions include:

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

---

# CTE

## Example 30 – Common Table Expression

### Example SQL

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

The CTE is processed as part of the generated SQL query.

---

# Recursive CTE

## Example 31 – Recursive CTE

Recursive CTE support can be used for hierarchical data where supported by the framework.

### Example SQL

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

## Example 32 – UNION

### Example SQL

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

`UNION` combines the result sets and removes duplicate rows.

---

# UNION ALL

## Example 33 – UNION ALL

### Example SQL

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

`UNION ALL` combines the result sets without removing duplicates.

---

# Stored Procedures

## Example 34 – Stored Procedure

Stored procedure execution is supported by the backend.

### Example SQL

```sql
EXEC GetCustomerDetails
    @CustomerId = ?;
```

Parameters are supplied separately during execution.

The exact request structure for stored procedures should follow the API request definition supported by the current backend.

---

# Scalar Functions

## Example 35 – Scalar Function

Scalar database functions can be executed through the backend.

### Example SQL

```sql
SELECT
    dbo.CalculateCustomerBalance(?) AS Balance;
```

Parameters are supplied separately during execution.

---

# Table-Valued Functions

## Example 36 – Table-Valued Function

Table-valued functions can be used as database result sources.

### Example SQL

```sql
SELECT
    CustomerID,
    CustomerName,
    Balance
FROM dbo.GetCustomerDetails(?);
```

---

# Complex Query

## Example 37 – Combined Query

The API can combine multiple supported query components in a single request.

### Request

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
    "pagination": {
        "page": 1,
        "pageSize": 10
    }
}
```

### Query Flow

```text
WHERE
  |
  v
GROUP BY
  |
  v
HAVING
  |
  v
ORDER BY
  |
  v
PAGINATION
```

This allows multiple query components to be combined without creating a separate API endpoint for each combination.

---

# Prepared Values

Values supplied through conditions are represented as parameters in generated SQL.

For example:

### Request

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

### Generated SQL

```sql
WHERE City = ?
```

The value is passed separately during query execution.

This allows the database execution layer to use prepared ODBC statements instead of directly inserting request values into the SQL string.

---

# Query Components

The examples demonstrate the following request components:

| Component | Purpose |
|---|---|
| `controller` | Selects the controller |
| `action` | Selects the operation |
| `table` | Defines the main table |
| `columns` | Defines selected columns and expressions |
| `where` | Filters rows |
| `joins` | Joins tables |
| `groupBy` | Groups results |
| `having` | Filters grouped results |
| `orderBy` | Sorts results |
| `pagination` | Controls result pagination |
| `distinct` | Removes duplicate rows |
| `top` | Limits the number of returned rows |
| `functions` | Represents supported SQL functions |
| `parameters` | Supplies values separately during execution |

---

# Supported SQL Capability Summary

The current API supports the following major SQL capabilities:

## Query Operations

```text
SELECT
DISTINCT
TOP
WHERE
AND / OR
JOIN
GROUP BY
HAVING
ORDER BY
PAGINATION
```

## Advanced Query Features

```text
CASE
Arithmetic Expressions
Subqueries
EXISTS
NOT EXISTS
IN
NOT IN
BETWEEN
NOT BETWEEN
CTE
Recursive CTE
UNION
UNION ALL
```

## Aggregate Functions

```text
COUNT
SUM
AVG
MIN
MAX
STRING_AGG
```

## String Functions

```text
UPPER
LOWER
LTRIM
RTRIM
TRIM
LEN
COALESCE
ISNULL
CAST
CONVERT
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

## Date and Time Functions

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

## Mathematical Functions

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

## Window Functions

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

## Database Objects

```text
Stored Procedures
Scalar Functions
Table-Valued Functions
```

---

# Future Examples

The following examples will be added when the corresponding backend functionality is implemented:

- INSERT
- UPDATE
- DELETE
- UPSERT
- Transactions

These operations are planned for future backend versions and should not be treated as currently supported API operations.

---

# Related Documentation

- [Introduction](Introduction.md)
- [Architecture](Architecture.md)
- [API](API.md)
- [JSON Request Reference](JSON-Request-Reference.md)
- [Database Configuration](Database-Configuration.md)
- [Hosting](Hosting.md)
- [Roadmap](Roadmap.md)

---

# Documentation Maintenance

The examples in this document should match the actual backend query builder and validation implementation.

When a new query capability is added:

1. Add or update the corresponding example.
2. Update the supported capability summary.
3. Update `JSON-Request-Reference.md` if the request structure changes.
4. Update `Roadmap.md` if the feature changes the planned release scope.

The documentation should not list SQL functionality as supported unless it is implemented and tested in the backend.