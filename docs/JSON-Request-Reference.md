# JSON Request Reference

## Overview

The Generic SQL API Framework is driven entirely by JSON requests. Every operation is described using a structured JSON payload, allowing the framework to dynamically validate requests, generate SQL queries, execute them securely, and return standardized JSON responses.

This document serves as the official specification for the JSON request format supported by the framework.

---

# Request Structure

Every request follows a common structure.

```json
{
    "controller": "Query",
    "action": "select",
    "table": "CustomerTable",
    "columns": []
}
```

---

# Complete Request Schema

```json
{
    "controller": "",
    "action": "",
    "table": "",
    "columns": [],
    "where": [],
    "joins": [],
    "groupBy": [],
    "having": [],
    "orderBy": [],
    "pagination": {}
}
```

All properties except **controller**, **action**, **table**, and **columns** are optional.

---

# JSON Properties

## controller

Determines which controller processes the request.

Type

```
String
```

Required

```
Yes
```

Supported Values

```
Query
```

Example

```json
{
    "controller": "Query"
}
```

---

## action

Defines the operation to perform.

Type

```
String
```

Required

```
Yes
```

Current Version

```
select
```

Future Versions

```
insert
update
delete
upsert
```

Example

```json
{
    "action": "select"
}
```

---

## table

Specifies the target database table.

Type

```
String
```

Required

```
Yes
```

Example

```json
{
    "table": "CustomerTable"
}
```

---

## columns

Specifies which columns should be returned.

Type

```
Array
```

Required

```
Yes
```

Simple Example

```json
{
    "columns": [
        "Cust_Name",
        "Phone"
    ]
}
```

Using SQL Functions

```json
{
    "columns": [
        {
            "function": "SUM",
            "column": "Balance",
            "alias": "TotalBalance"
        }
    ]
}
```

---

## where

Defines filtering conditions.

Type

```
Array
```

Required

```
No
```

Example

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

Properties

| Property | Type | Description |
|----------|------|-------------|
| column | String | Database column |
| operator | String | Comparison operator |
| value | Mixed | Comparison value |

---

## joins

Defines SQL JOIN clauses.

Type

```
Array
```

Required

```
No
```

Example

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

---

## groupBy

Defines GROUP BY columns.

Type

```
Array
```

Required

```
No
```

Example

```json
{
    "groupBy": [
        "City"
    ]
}
```

---

## having

Defines HAVING conditions.

Type

```
Array
```

Required

```
No
```

Example

```json
{
    "having": [
        {
            "function": "SUM",
            "column": "Balance",
            "operator": ">",
            "value": 10000
        }
    ]
}
```

---

## orderBy

Defines sorting.

Type

```
Array
```

Required

```
No
```

Example

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

Supported Directions

```
ASC
DESC
```

---

## pagination

Limits returned records.

Type

```
Object
```

Required

```
No
```

Example

```json
{
    "pagination": {
        "page": 1,
        "pageSize": 25
    }
}
```

---

# Supported SQL Operators

Comparison Operators

- =
- !=
- >
- <
- >=
- <=

Logical Operators

- AND
- OR

Additional Operators

- LIKE
- BETWEEN
- IN
- IS NULL
- IS NOT NULL

---

# Supported SQL Functions

## Aggregate

- SUM
- COUNT
- AVG
- MIN
- MAX

## String

- UPPER
- LOWER
- CONCAT
- LEN
- TRIM

## Date

- GETDATE
- YEAR
- MONTH
- DAY
- DATEADD
- DATEDIFF

## Mathematical

- ABS
- ROUND
- CEILING
- FLOOR

---

# Validation Rules

Every request passes through the Validation Engine before SQL generation.

The framework validates:

- Controller
- Action
- Table
- Columns
- SQL Functions
- Operators
- JOIN clauses
- Aliases

Invalid requests are rejected before SQL generation.

---

# Success Response

Example

```json
{
    "success": true,
    "message": "Query executed successfully.",
    "executionTime": 15.34,
    "rowsReturned": 12,
    "data": []
}
```

---

# Error Response

Example

```json
{
    "success": false,
    "message": "Invalid Table Name"
}
```

Common Errors

- Invalid controller
- Invalid action
- Invalid table
- Invalid column
- Invalid SQL function
- Invalid operator
- Invalid JOIN
- Database connection failed
- Query execution failed

---

# Future Request Formats

Future versions will support:

- INSERT
- UPDATE
- DELETE
- UPSERT
- Stored Procedures
- Transactions

---

# Related Documentation

- API.md
- Architecture.md
- Database-Configuration.md
- Hosting.md

---

# Summary

The JSON Request Reference defines the complete request specification used by the Generic SQL API Framework. Every request submitted to the framework must follow this structure to ensure secure validation, reliable SQL generation, and standardized JSON responses.