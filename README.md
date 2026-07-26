# Generic SQL API Framework

Generic SQL API Framework is a lightweight, JSON-driven PHP framework for building dynamic Microsoft SQL Server APIs.

Instead of writing a separate API for every report or module, the framework accepts structured JSON requests, validates them, generates SQL dynamically, executes parameterized queries, and returns standardized JSON responses.

It is designed for reporting systems, dashboards, ERP applications, POS software, inventory systems, CRM solutions, and other business applications.

---

## Features

### Query Engine

* Dynamic SQL query generation
* JSON-driven request processing
* Prepared statements
* Standardized JSON responses

### Supported Query Features

* SELECT
* WHERE
* GROUP BY
* HAVING
* ORDER BY
* Pagination
* INNER JOIN
* LEFT JOIN
* RIGHT JOIN

### Validation

* Table validation
* Column validation
* SQL function validation
* JOIN validation
* Operator validation
* Alias validation

### SQL Functions

* Aggregate Functions
* String Functions
* Date Functions
* Mathematical Functions

### Monitoring

* Query execution statistics
* Execution time
* Rows returned
* Centralized logging
* Exception logging

---

## Architecture

```text
Client
   │
HTTP/HTTPS
   │
   ▼
Generic SQL API Framework
   │
Validation Engine
   │
SQL Builder
   │
Query Engine
   │
Microsoft SQL Server
   │
JSON Response
```

---

## Technology Stack

* PHP 8+
* Microsoft SQL Server
* Microsoft ODBC Driver
* JSON-based API Design
* Repository Pattern

---

## Current Status

**Version:** v1.0

### Currently Supported

* Dynamic SELECT queries
* SQL function support
* JOIN support
* Query validation
* Pagination
* Logging
* Query statistics
* Metadata engine

### Planned

* INSERT
* UPDATE
* DELETE
* UPSERT
* Stored Procedures
* Transactions
* Multi-database support
* Authentication & Authorization
* Export (Excel, PDF, CSV)

---

## Documentation

Complete documentation is available in the **`docs/`** directory.

| Document                    | Description                             |
| --------------------------- | --------------------------------------- |
| `Introduction.md`           | Framework overview and goals            |
| `Architecture.md`           | Internal architecture and request flow  |
| `API.md`                    | HTTP API reference                      |
| `JSON-Request-Reference.md` | Complete JSON request specification     |
| `Query-Examples.md`         | Practical request and response examples |
| `Database-Configuration.md` | Database configuration guide            |
| `Hosting.md`                | Deployment and hosting instructions     |
| `Roadmap.md`                | Planned features and future releases    |
| `CHANGELOG.md`              | Version history                         |
| `CONTRIBUTING.md`           | Contribution guidelines                 |

---

## Getting Started

1. Clone the repository.
2. Configure your database connection in `database.json`.
3. Deploy the project to your PHP web server.
4. Send JSON requests to the API endpoint.

Refer to the documentation in the `docs/` folder for detailed setup and usage instructions.

---

## License

This project is licensed under the MIT License.

See the `LICENSE` file for more information.
