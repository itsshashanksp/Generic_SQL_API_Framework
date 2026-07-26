# Query Examples

## Overview

This document contains practical examples demonstrating how to use the Generic SQL API Framework.

Each example includes:

- JSON Request
- Generated SQL
- Expected Response

These examples serve as a reference for developers integrating applications with the framework.

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

### Response

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
    "controller":"Query",
    "action":"select",
    "table":"CustomerTable",
    "columns":[
        "Cust_Name",
        "City"
    ],
    "where":[
        {
            "column":"City",
            "operator":"=",
            "value":"Bangalore"
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

---

# Example 3 – ORDER BY

### Request

```json
{
    "controller":"Query",
    "action":"select",
    "table":"CustomerTable",
    "columns":[
        "Cust_Name",
        "Balance"
    ],
    "orderBy":[
        {
            "column":"Balance",
            "direction":"DESC"
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
    "controller":"Query",
    "action":"select",
    "table":"SalesTable",
    "columns":[
        "City",
        {
            "function":"COUNT",
            "column":"InvoiceNo",
            "alias":"Invoices"
        }
    ],
    "groupBy":[
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
    "controller":"Query",
    "action":"select",
    "table":"SalesTable",
    "columns":[
        "City",
        {
            "function":"SUM",
            "column":"Amount",
            "alias":"TotalSales"
        }
    ],
    "groupBy":[
        "City"
    ],
    "having":[
        {
            "function":"SUM",
            "column":"Amount",
            "operator":">",
            "value":100000
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

---

# Example 6 – INNER JOIN

### Request

```json
{
    "controller":"Query",
    "action":"select",
    "table":"CustomerTable",
    "columns":[
        "CustomerTable.Cust_Name",
        "InvoiceTable.InvoiceNo"
    ],
    "joins":[
        {
            "type":"INNER",
            "table":"InvoiceTable",
            "on":{
                "left":"CustomerTable.Cust_ID",
                "right":"InvoiceTable.Cust_ID"
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
    "controller":"Query",
    "action":"select",
    "table":"CustomerTable",
    "columns":[
        "Cust_Name"
    ],
    "pagination":{
        "page":2,
        "pageSize":20
    }
}
```

### Generated SQL

```sql
SELECT
    Cust_Name
FROM CustomerTable
OFFSET 20 ROWS
FETCH NEXT 20 ROWS ONLY;
```

---

# Example 8 – Aggregate Functions

### Request

```json
{
    "controller":"Query",
    "action":"select",
    "table":"SalesTable",
    "columns":[
        {
            "function":"SUM",
            "column":"Amount",
            "alias":"TotalSales"
        },
        {
            "function":"AVG",
            "column":"Amount",
            "alias":"AverageSales"
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
    "controller":"Query",
    "action":"select",
    "table":"SalesTable",
    "columns":[
        "City",
        {
            "function":"SUM",
            "column":"Amount",
            "alias":"TotalSales"
        }
    ],
    "where":[
        {
            "column":"Status",
            "operator":"=",
            "value":"Completed"
        }
    ],
    "groupBy":[
        "City"
    ],
    "having":[
        {
            "function":"SUM",
            "column":"Amount",
            "operator":">",
            "value":50000
        }
    ],
    "orderBy":[
        {
            "column":"TotalSales",
            "direction":"DESC"
        }
    ],
    "pagination":{
        "page":1,
        "pageSize":10
    }
}
```

---

# Future Examples

Future releases will include examples for:

- INSERT
- UPDATE
- DELETE
- UPSERT
- Stored Procedures
- Transactions

---

# Summary

These examples demonstrate the supported query capabilities of Version 1 of the Generic SQL API Framework. As new features are introduced, this document will be expanded with additional examples to illustrate their usage.