# Architecture

## Overview

Generic SQL API Framework follows a modular, layered architecture designed to separate responsibilities, improve maintainability, and simplify future enhancements.

Each component has a dedicated responsibility.

---

# High-Level Architecture

                        Client
                           |
                           v
                    HTTP Request
                           |
                           v
                     API Controller
                           |
                           v
                 Validation Engine
                           |
                           v
                    SQL Builder
                           |
                           v
                   Query Engine
                           |
                           v
                  Database Engine
                           |
                           v
                   Driver Factory
                           |
                           v
                 SQL Server Driver
                           |
                           v
                Microsoft SQL Server
                           |
                           v
                    Query Results
                           |
                           v
                    JSON Response
                           |
                           v
                         Client

---

# Request Lifecycle

Every request follows the same general pipeline.

1. Client sends an HTTP request containing JSON.
2. API Controller receives and parses the request.
3. Validation Engine validates the request.
4. SQL Builder generates the SQL statement.
5. Query Engine prepares and executes the query.
6. Database Engine manages database access.
7. Driver Factory selects the configured database driver.
8. SQL Server Driver communicates with SQL Server.
9. SQL Server returns the result.
10. Query Engine processes the result.
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
- Validate pagination

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
- Build pagination
- Generate parameterized SQL

The SQL Builder generates SQL but does not communicate directly with the database.

---

## Query Engine

Responsibilities:

- Receive generated SQL
- Prepare statements
- Bind parameters
- Execute queries
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
- Provide a common database interface

The rest of the framework communicates with the Database Engine instead of directly managing database connections.

---

## Driver Factory

Responsibilities:

- Read the configured provider
- Select the correct database driver
- Create driver instances

Conceptually:

Provider
|
|-- sqlserver
|-- mysql
|-- postgresql
|-- sqlite
|-- oracle

Current implementation:

SQL Server

Additional providers can be introduced in future versions.

---

## SQL Server Driver

The SQL Server driver contains SQL Server-specific database behavior.

Responsibilities include:

- ODBC driver detection
- Establishing SQL Server connections
- SQL Authentication
- Windows Authentication
- Named instance handling
- Port handling
- Encryption configuration
- Trust Server Certificate configuration
- Query execution
- Transactions
- Result handling

The SQL Server-specific implementation remains isolated from the rest of the framework.

---

## Metadata Engine

The Metadata Engine is responsible for database metadata operations.

Planned responsibilities include:

- Retrieve databases
- Retrieve tables
- Retrieve columns
- Retrieve schema information

---

## Logging System

Responsibilities:

- Record executed queries
- Store execution time
- Record returned rows
- Capture exceptions
- Record database errors

---

# Pagination Compatibility

Pagination is handled by the query/database compatibility layer.

The frontend sends the same pagination information regardless of SQL Server compatibility level.

The framework can select a compatible pagination implementation.

Modern SQL Server capability:

Modern pagination

Older compatibility level where OFFSET/FETCH is unavailable:

ROW_NUMBER() pagination

This keeps database-version-specific pagination logic out of the frontend.

---

# Database Abstraction

The architecture separates:

Application Logic
       |
Database Engine
       |
Database Driver
       |
Database Server

This makes it possible to introduce additional database providers without changing the API/controller layer.

---

# Design Principles

## Separation of Concerns

Each component performs a defined responsibility.

## Modularity

Components can be developed and maintained independently.

## Reusability

Core components can be reused across multiple applications.

## Extensibility

New database providers and framework components can be added without redesigning the entire application.

## Maintainability

The layered structure makes debugging and future development easier.

## Security

Validation and parameterized SQL execution are performed before database operations.

---

# Related Documentation

For the HTTP layer:

API.md

For the JSON request structure:

JSON-Request-Reference.md

For database configuration:

Database-Configuration.md

For deployment:

Hosting.md