# Generic SQL API Framework

Generic SQL API Framework is a PHP-based backend framework for building dynamic SQL Server APIs.

The framework accepts JSON requests, validates them, generates SQL queries dynamically, executes them using parameterized statements, and returns standardized JSON responses.

It is designed to reduce repetitive API development by providing a reusable query engine that can be integrated into reporting systems, dashboards, ERP applications, POS software, mobile applications, and other business systems.

---

## Features

### Query Engine

- Dynamic SELECT queries
- JSON-driven request processing
- Prepared statement support
- Standardized JSON responses

### Validation

- Table validation
- Column validation
- SQL function validation
- JOIN validation
- Operator validation
- Alias validation

### Query Support

- SELECT
- WHERE
- GROUP BY
- HAVING
- ORDER BY
- Pagination
- INNER JOIN
- LEFT JOIN
- RIGHT JOIN
- Aggregate functions
- String functions
- Date functions
- Mathematical functions

### Monitoring

- Query execution statistics
- Execution time
- Rows returned
- Centralized logging
- Exception logging

---

## Architecture

```
Client
   │
   ▼
JSON Request
   │
   ▼
Controller
   │
   ▼
Validation Engine
   │
   ▼
SQL Builder
   │
   ▼
Query Engine
   │
   ├── Logger
   │
   ▼
Microsoft SQL Server
   │
   ▼
JSON Response
```

---

## Technology Stack

- PHP 8+
- Microsoft SQL Server
- ODBC Driver
- Repository Pattern
- JSON-based API Design

---

## Project Structure

```
api/
app/
config/
connection/
core/
docs/
logs/

README.md
CHANGELOG.md
LICENSE
index.php
```

---

## Example Request

```json
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
```

---

## Example Response

```json
{
    "success": true,
    "message": "Data Loaded Successfully",
    "executionTime": 18.42,
    "rowsReturned": 5,
    "data": [
        {
            "Cust_Name": "ABC Traders",
            "TotalBalance": 250000
        }
    ]
}
```

---

## Current Status

### Completed

- Query Engine
- Validation Engine
- SQL Builder
- Metadata Engine
- JOIN Engine
- Query Statistics
- Centralized Logging

---

## Planned Features

### CRUD

- INSERT
- UPDATE
- DELETE
- UPSERT

### Database

- Stored Procedures
- Transactions
- Bulk Operations

### Export

- Excel
- PDF
- CSV

### Security

- Authentication
- Authorization
- API Keys

### Dashboard

- Dashboard Builder
- Charts
- Report Designer

---

## License

This project is licensed under the MIT License.