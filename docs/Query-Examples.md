# Query Examples

## Overview

This document contains practical examples demonstrating how to use the Generic SQL API Framework.

Each example includes:

- JSON Request
- Generated SQL
- Expected Response where applicable

These examples are intended as a quick reference for developers integrating applications with the API.

For the complete JSON request structure, see [JSON Request Reference](JSON-Request-Reference.md).

For the HTTP API itself, see [API](API.md).

---

# Example 1 – Basic SELECT

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

# Example 2 – WHERE Clause

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

# Example 3 – ORDER BY

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

# Example 4 – GROUP BY

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

# Example 5 – HAVING

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

# Example 6 – INNER JOIN

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

# Example 7 – Pagination

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

The SQL Server query uses pagination based on the requested page and page size.

Conceptually:

```sql
SELECT
    Cust_Name
FROM CustomerTable
ORDER BY <column>
OFFSET 20 ROWS
FETCH NEXT 20 ROWS ONLY;
```

> The actual generated SQL depends on the query builder's ordering and pagination requirements.

---

# Example 8 – Aggregate Functions

### Request

```json
{
    "controller": "Query",
    "action": "select",
    "table": "SalesTable",
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

### Generated SQL

```sql
SELECT
    SUM(Amount) AS TotalSales,
    AVG(Amount) AS AverageSales
FROM SalesTable;
```

---

# Example 9 – Complex Query

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

This request combines:

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

It is useful as an example of combining multiple query components in a single request.

---

# Example 10 – Multiple Query Components

The API request can combine filtering and sorting without requiring separate endpoints.

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
        }
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
    City,
    Balance
FROM CustomerTable
WHERE City = ?
ORDER BY Balance DESC;
```

---

# Prepared Values

Values supplied through conditions are represented as parameters in generated SQL.

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

can produce:

```sql
WHERE City = ?
```

The actual value is supplied separately during query execution.

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

---

# Future Examples

Examples for additional operations will be added when those features are implemented.

Planned areas include:

- INSERT
- UPDATE
- DELETE
- UPSERT
- Stored Procedures
- Transactions

These should only be documented as supported examples after the corresponding backend functionality is implemented.

---

# Related Documentation

- [API](API.md)
- [JSON Request Reference](JSON-Request-Reference.md)
- [Database Configuration](Database-Configuration.md)
- [Architecture](Architecture.md)
- [Hosting](Hosting.md)

---

# Summary

These examples demonstrate the query capabilities currently documented for the Generic SQL API Framework.

The examples should always match the request structure implemented by the current query builder and validation layer.

When the API contract changes, update this document together with [JSON Request Reference](JSON-Request-Reference.md).