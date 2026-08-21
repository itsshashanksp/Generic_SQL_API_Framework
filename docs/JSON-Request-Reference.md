# JSON Request Reference

## Overview

The Generic SQL API Framework accepts structured JSON requests.

The request describes the query requirements while the framework handles validation, SQL generation, parameter binding, execution, and response formatting.

---

# Basic Structure

A query request generally contains:

{
    "controller": "Query",
    "action": "select",
    "table": "CustomerTable",
    "columns": [
        "Cust_Name"
    ]
}

---

# controller

Identifies the controller handling the request.

Example:

"controller": "Query"

---

# action

Identifies the operation requested.

Example:

"action": "select"

The currently documented query operation is:

select

Additional operations will be documented when implemented and exposed through the API.

---

# table

Specifies the primary table.

Example:

"table": "CustomerTable"

---

# columns

Specifies the columns returned by the query.

Example:

"columns": [
    "Cust_Name",
    "Cust_Code"
]

---

# Column Alias

Columns can use aliases where supported by the query builder.

Example:

{
    "column": "Cust_Name",
    "alias": "CustomerName"
}

---

# SQL Functions

SQL functions can be represented as structured objects.

Example:

{
    "function": "SUM",
    "column": "Op_Bal",
    "alias": "TotalBalance"
}

Example complete request:

{
    "controller": "Query",
    "action": "select",
    "table": "CustomerTable",
    "columns": [
        "Cust_Name",
        {
            "function": "SUM",
            "column": "Op_Bal",
            "alias": "TotalBalance"
        }
    ],
    "groupBy": [
        "Cust_Name"
    ]
}

---

# WHERE

Filtering is represented through the request's filter/condition structure supported by the current API implementation.

Refer to Query-Examples.md for working examples.

---

# GROUP BY

Example:

{
    "groupBy": [
        "Cust_Name"
    ]
}

---

# HAVING

HAVING is used with grouped queries.

The structure must follow the validation rules implemented by the current query builder.

See Query-Examples.md for examples.

---

# ORDER BY

ORDER BY defines result ordering.

The request can specify the columns and direction supported by the current query builder.

Example concept:

{
    "orderBy": [
        {
            "column": "Cust_Name",
            "direction": "ASC"
        }
    ]
}

Use the exact structure supported by the current implementation.

---

# JOIN

Supported JOIN types include:

- INNER JOIN
- LEFT JOIN
- RIGHT JOIN

JOIN definitions must identify:

- Join type
- Target table
- Join condition

See Query-Examples.md for working examples.

---

# Pagination

Pagination uses:

{
    "page": 1,
    "pageSize": 25
}

Where:

page

Specifies the requested page number.

pageSize

Specifies the number of records returned per page.

---

# Pagination Compatibility

The frontend request does not need to know which SQL Server pagination syntax is supported.

The database/query layer determines the compatible implementation.

Modern SQL Server:

Modern pagination

Older SQL Server compatibility:

ROW_NUMBER() pagination

This keeps database-specific pagination logic inside the backend.

---

# SQL Functions

The framework currently supports SQL function categories including:

## Aggregate

- COUNT
- SUM
- AVG
- MIN
- MAX

## String

- UPPER
- LOWER
- LTRIM
- RTRIM
- TRIM
- LEN

## Date

- YEAR
- MONTH
- DAY
- DATEPART
- DATENAME
- GETDATE

## Mathematical

- ABS
- ROUND
- CEILING
- FLOOR
- POWER
- SQRT
- EXP
- LOG

---

# Parameters

User-provided values must be passed through the request structure supported by the framework.

The query engine uses parameterized execution rather than directly concatenating user values into SQL.

---

# Example

{
    "controller": "Query",
    "action": "select",
    "table": "CustomerTable",
    "columns": [
        "Cust_Name"
    ]
}

---

# Response

Successful response:

{
    "success": true,
    "message": "Data Loaded Successfully",
    "data": []
}

Error response:

{
    "success": false,
    "message": "Error message"
}

---

# Validation

The framework validates request components before execution.

Validation includes areas such as:

- Tables
- Columns
- SQL functions
- Operators
- JOIN definitions
- Aliases
- Pagination

Invalid requests are rejected before database execution.

---

# Related Documentation

For endpoint information:

API.md

For real-world requests:

Query-Examples.md

For framework architecture:

Architecture.md

For database configuration:

Database-Configuration.md