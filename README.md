# Generic Dashboard

A configurable SQL Server reporting and dashboard platform built with PHP.

Generic Dashboard is designed to eliminate the need for writing custom reports for every client requirement. Instead of creating individual SQL queries and dedicated APIs for each report, the system accepts a structured JSON request, validates it, dynamically generates SQL queries, executes them securely, and returns standardized JSON responses.

The goal is to provide a reusable reporting engine that can power dashboards, MIS reports, ERP systems, POS software, inventory management, accounting systems, and other business applications.

---

# Why this project?

Most business applications contain hundreds of reports.

Traditional applications usually require developers to:

- Write a new SQL query
- Create a new API endpoint
- Validate parameters
- Maintain duplicate code
- Modify reports whenever business requirements change

This approach becomes difficult to maintain as the application grows.

Generic Dashboard solves this problem by introducing a single configurable query engine capable of generating reports dynamically through JSON requests.

Instead of creating hundreds of APIs, one engine can generate thousands of different reports.

---

# Project Objectives

The primary objectives of this project are:

- Build a reusable reporting engine
- Reduce development time
- Eliminate duplicate SQL code
- Standardize report generation
- Support configurable dashboards
- Provide enterprise-ready reporting APIs
- Simplify maintenance of large business applications

---

# Target Use Cases

Generic Dashboard can be integrated into:

- ERP Systems
- POS Software
- Accounting Applications
- Inventory Management Systems
- CRM Platforms
- Courier & Logistics Software
- Healthcare Applications
- Educational Management Systems
- Manufacturing Systems
- Business Intelligence Dashboards

---

# Architecture

The application follows a modular architecture.

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

Query Repository

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
   ▼

SQL Server

   │
   ▼

JSON Response
```

Each layer has a dedicated responsibility, making the project modular, maintainable, and extensible.

---

# Core Features

## Dynamic Query Builder

Supports dynamic SQL generation using JSON without requiring developers to write SQL for every report.

---

## Validation Engine

The engine validates:

- Tables
- Columns
- SQL Functions
- Operators
- JOINs
- Aliases

before executing any query.

---

## Query Features

Current supported features include:

### SELECT

- Dynamic Columns
- Column Aliases
- DISTINCT
- TOP

### WHERE

- AND
- OR
- LIKE
- NOT LIKE
- IN
- NOT IN
- BETWEEN
- NOT BETWEEN
- IS NULL
- IS NOT NULL

### GROUPING

- GROUP BY
- HAVING

### SORTING

- ORDER BY
- Pagination

### JOIN

- INNER JOIN
- LEFT JOIN
- RIGHT JOIN
- Table Aliases
- Column Aliases

---

# Supported SQL Functions

## Aggregate Functions

- COUNT
- SUM
- AVG
- MIN
- MAX

## String Functions

- UPPER
- LOWER
- LTRIM
- RTRIM
- TRIM
- LEN

## Date Functions

- YEAR
- MONTH
- DAY
- DATEPART
- DATENAME
- GETDATE

## Mathematical Functions

- ABS
- ROUND
- CEILING
- FLOOR
- POWER
- SQRT
- EXP
- LOG

---

# Technology Stack

Backend

- PHP 8+
- ODBC Driver
- Microsoft SQL Server

Architecture

- Repository Pattern
- Modular Design
- JSON Driven Query Builder

---

# Example Request

```json
{
    "controller":"Query",
    "action":"select",
    "table":"CustomerTable",
    "columns":[
        "Cust_Name",
        {
            "function":"SUM",
            "column":"Op_Bal",
            "alias":"TotalBalance"
        }
    ],
    "groupBy":[
        "Cust_Name"
    ]
}
```

---

# Example Response

```json
{
    "success": true,
    "message": "Data Loaded Successfully",
    "data": [
        {
            "Cust_Name": "ABC Traders",
            "TotalBalance": 254000
        }
    ]
}
```

---

# Current Progress

Current Milestone

**Phase 1 Completed**

- Query Engine
- Validation Engine
- Dynamic SQL Builder
- SQL Function Support
- Join Engine
- Metadata Engine

Overall Progress

**53 / 100 Planned Features**

---

# Future Development

The next phase of development will include:

- CASE Expressions
- COALESCE / ISNULL
- CAST / CONVERT
- UNION
- Common Table Expressions (CTE)
- Window Functions
- Stored Procedure Support
- Dashboard Builder
- Chart Engine
- Report Templates
- Export Engine (Excel, PDF, CSV)
- User Management
- Role-Based Permissions
- Audit Logs
- Performance Optimization

---

# Project Status

**Active Development**

Current Version:

**Phase 1 – Query Engine**

Next Milestone:

**Advanced SQL Engine**

---

# License

MIT License
