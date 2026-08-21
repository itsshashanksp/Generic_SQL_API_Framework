# Architecture

## Overview

Generic SQL API Framework is structured as a set of separate layers.

Each layer has a specific responsibility so that HTTP handling, validation, SQL generation, query execution, and database connectivity do not become tightly coupled.

---

## Request Flow

A normal query moves through the application roughly like this:

```text
Client
  |
  | HTTP request
  v
API Entry Point
  |
  v
Controller
  |
  v
Request Validation
  |
  v
Query Repository
  |
  v
Query Builder
  |
  v
Query Engine
  |
  v
Database Layer
  |
  v
ODBC
  |
  v
SQL Server
```

The result follows the execution path back to the API and is returned to the client as JSON.

---

## Project Layers

The main responsibilities are separated into:

```text
API
 |
 +-- Controllers
 |
 +-- Validation
 |
 +-- Repositories
 |
 +-- Query Building
 |
 +-- Query Execution
 |
 +-- Database
 |
 +-- Logging
```

The exact directory structure may evolve, but the separation of responsibilities should remain.

---

## API Layer

The API layer is the HTTP entry point.

It handles things such as:

- Reading HTTP requests
- Reading the JSON request body
- Parsing JSON
- Handling CORS
- Resolving controllers
- Resolving actions
- Returning JSON responses

The API layer should not contain database-specific query-building logic.

---

## Controller Layer

Controllers provide the entry point for application operations.

A controller receives the validated request and delegates the actual database work to the appropriate repository or service.

Conceptually:

```text
HTTP Request
     |
     v
Controller
     |
     +-- Validate
     |
     +-- Call Repository
     |
     v
Response
```

Keeping this logic outside the controller makes the query engine reusable.

---

## Validation

Requests are validated before they are passed to the database execution layer.

Validation is responsible for checking things such as:

- Required request fields
- Table names
- Column names
- SQL functions
- Operators
- JOIN definitions
- Aliases
- Sort directions
- Query structure

The purpose is to reject invalid requests before they become executable database queries.

---

## Query Repository

The Query Repository is responsible for turning the structured request into a SQL query.

Depending on the request, it can build parts such as:

- SELECT columns
- Column aliases
- Table aliases
- WHERE conditions
- JOINs
- Expressions
- GROUP BY
- HAVING
- ORDER BY
- Pagination
- CTE-related queries
- Set operations
- Procedure-related queries

The repository focuses on query construction rather than HTTP communication.

---

## Query Builder

The query-building layer converts the structured request into SQL syntax.

For example, a request such as:

```json
{
  "table": "CustomerTable",
  "columns": [
    "Cust_Name",
    "Phone"
  ]
}
```

can be represented internally as:

```sql
SELECT
    Cust_Name,
    Phone
FROM CustomerTable;
```

The query builder is responsible for constructing the SQL structure while the execution layer is responsible for running it.

---

## Query Engine

The Query Engine is responsible for executing generated SQL.

It handles operations such as:

- Normal SQL execution
- Prepared SQL execution
- Multiple-result execution
- SQL file execution
- Connection closing
- Query execution statistics
- Database errors

Prepared statements use ODBC functions such as:

```php
odbc_prepare();
odbc_execute();
```

The separation means the query repository does not need to know how the SQL is actually executed.

---

## Database Layer

The database layer handles database connectivity.

The architecture separates:

```text
Query Construction
       |
       v
Query Execution
       |
       v
Database Connection
```

This prevents database connection code from being scattered across controllers and query builders.

---

## ODBC

The current database implementation communicates with SQL Server through ODBC.

The flow is:

```text
PHP
 |
 v
ODBC Extension
 |
 v
SQL Server ODBC Driver
 |
 v
SQL Server
```

The installed ODBC driver is selected according to the database configuration.

See [Database Configuration](Database-Configuration.md).

---

## SQL Server

Microsoft SQL Server is the current supported database provider.

SQL Server-specific behavior belongs in the database layer rather than in the generic API request handling.

This allows the higher-level API to remain independent from the connection implementation.

---

## Metadata

The database metadata layer is used when the framework needs information about database objects.

Examples include:

- Tables
- Columns
- Schemas
- Column validation
- Database object lookup

This information can be used by the query repository before constructing or executing a query.

---

## Logging

Query execution and database errors are handled through the logging layer.

Logging can contain information such as:

- SQL statements
- Parameters
- Execution time
- Returned row count
- Exceptions
- Database errors

Logs are useful when diagnosing failed queries or unexpected execution behavior.

---

## Statistics

The execution layer can collect query statistics such as:

```text
Execution Time
Rows Returned
```

These statistics are kept with the backend execution flow rather than being calculated by the frontend.

---

## Database Provider Separation

The database architecture is intended to allow additional providers without changing the API contract.

Conceptually:

```text
                    Generic SQL API
                           |
                    Database Layer
                           |
             +-------------+-------------+
             |             |             |
             v             v             v
        SQL Server       MySQL      PostgreSQL
         Current          Future       Future
```

SQL Server is the current implementation.

Other providers are future work and should not be treated as currently supported unless implemented.

---

## Frontend Independence

The API does not depend on React, Vue, Angular, or any other frontend framework.

A client can simply send an HTTP request:

```text
Frontend / Client
       |
       | JSON + HTTP
       v
Generic SQL API
       |
       v
SQL Server
```

This allows the same backend to be consumed by different applications.

---

## Error Flow

Errors should remain within the layer where they can be handled correctly and then be propagated to the API response.

```text
Database Error
      |
      v
Query Engine
      |
      v
Exception / Error Handling
      |
      v
API Response
      |
      v
Client
```

The client receives a JSON response instead of needing to understand the internal database implementation.

---

## Runtime

The Windows deployment includes a bundled PHP runtime.

The runtime is outside the query architecture itself, but provides the environment required to execute the backend.

```text
Windows
  |
  +-- Bundled PHP Runtime
  |
  +-- PHP ODBC
  |
  +-- Generic SQL API
  |
  +-- SQL Server
```

See [Hosting](Hosting.md) for deployment details.

---

## Related Documentation

- [Introduction](Introduction.md)
- [API](API.md)
- [JSON Request Reference](JSON-Request-Reference.md)
- [Database Configuration](Database-Configuration.md)
- [Hosting](Hosting.md)