# Architecture

## Overview

The Generic SQL API Framework follows a modular, layered architecture designed to separate responsibilities, improve maintainability, and simplify future enhancements.

Each component has a dedicated responsibility and communicates only with adjacent layers. This minimizes dependencies, improves code organization, and allows new features to be introduced without affecting existing components.

The framework processes every request through a well-defined pipeline, from receiving a client request to returning a standardized JSON response.

---

# High-Level Architecture

```text
                        Client
                           │
                           ▼
                    HTTP Request
                           │
                           ▼
                     API Controller
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
                  Database Engine
                           │
                           ▼
                   Driver Factory
                           │
                           ▼
                 SQL Server Driver
                           │
                           ▼
                Microsoft SQL Server
                           │
                           ▼
                    Query Results
                           │
                           ▼
                  JSON Response
                           │
                           ▼
                         Client
```

---

# Request Lifecycle

Every request follows the same execution pipeline.

1. Client sends an HTTP request containing a JSON payload.
2. API Controller receives and parses the request.
3. Validation Engine validates all request components.
4. SQL Builder converts JSON into a parameterized SQL statement.
5. Query Engine prepares the query for execution.
6. Database Engine loads the configured database provider.
7. Driver Factory initializes the appropriate database driver.
8. SQL Server Driver executes the SQL statement.
9. Microsoft SQL Server returns the result set.
10. Query Engine formats the result.
11. API Controller returns a standardized JSON response.

---

# Core Components

## API Controller

Responsibilities:

- Receive HTTP requests
- Parse JSON payloads
- Route requests
- Coordinate framework components
- Return standardized JSON responses
- Handle exceptions

---

## Validation Engine

Responsibilities:

- Validate request structure
- Validate tables
- Validate columns
- Validate SQL functions
- Validate operators
- Validate JOIN clauses
- Validate aliases

Invalid requests are rejected before reaching the database.

---

## SQL Builder

Responsibilities:

- Build SELECT statements
- Build WHERE clauses
- Build GROUP BY clauses
- Build HAVING clauses
- Build ORDER BY clauses
- Build JOIN clauses
- Build Pagination
- Generate parameterized SQL

The SQL Builder only generates SQL and never communicates directly with the database.

---

## Query Engine

Responsibilities:

- Receive generated SQL
- Bind parameters
- Execute prepared statements
- Measure execution time
- Count returned rows
- Return structured query results

---

## Database Engine

Responsibilities:

- Load database configuration
- Read provider information
- Manage database connections
- Delegate operations to drivers
- Provide a common interface for all providers

The rest of the framework communicates only with the Database Engine.

---

## Driver Factory

Responsibilities:

- Read configured provider
- Load the correct database driver
- Create driver instances

Example:

```text
Provider
│
├── sqlserver
├── mysql
├── postgresql
├── sqlite
└── oracle
```

Current Version:

- SQL Server

Future Versions:

- MySQL
- PostgreSQL
- SQLite
- Oracle

---

## SQL Server Driver

Responsibilities:

- Detect ODBC Driver
- Establish SQL Server connection
- Execute parameterized queries
- Return results
- Handle SQL Server errors

All SQL Server-specific logic is isolated inside this driver.

---

## Metadata Engine

Responsibilities:

- Retrieve available databases
- Retrieve tables
- Retrieve columns
- Retrieve schema information

Status:

Planned for future releases.

---

## Logging System

Responsibilities:

- Record executed queries
- Store execution time
- Record returned rows
- Capture exceptions
- Log database errors

Logging assists with debugging, auditing, and performance monitoring.

---

# Layered Architecture

```text
Presentation Layer
        │
        ▼
Controller Layer
        │
        ▼
Validation Layer
        │
        ▼
Query Generation Layer
        │
        ▼
Execution Layer
        │
        ▼
Database Layer
        │
        ▼
Driver Layer
        │
        ▼
Database Server
```

Each layer has a single responsibility and communicates only with neighboring layers.

---

# Design Principles

## Separation of Concerns

Each module performs one specific responsibility.

## Modularity

Components can be developed and maintained independently.

## Reusability

Core framework components can be reused across multiple projects.

## Extensibility

New database providers can be added without changing business logic.

## Maintainability

A clear architecture simplifies debugging and future enhancements.

## Security

All database operations use validated requests and parameterized queries.

---

# Advantages

- Modular architecture
- Layered design
- Reusable components
- Database abstraction
- Provider-based architecture
- Improved maintainability
- Better scalability
- Easier testing
- Frontend independence
- Secure query execution

---

# Summary

The Generic SQL API Framework is built on a modular, provider-based architecture that separates request handling, validation, SQL generation, query execution, and database communication into independent components. This design improves maintainability, scalability, security, and provides a strong foundation for supporting multiple database providers in future releases.