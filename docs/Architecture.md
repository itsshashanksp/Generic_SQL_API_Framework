# Architecture

## Overview

Generic SQL API Framework uses a modular architecture designed to separate request validation, SQL generation, database access, configuration, and response handling.

The architecture allows the framework to remain generic while database-specific behavior is isolated inside provider/driver components.

---

# High-Level Architecture

Frontend / Client
       |
       v
api/index.php
       |
       v
Request / Controller Layer
       |
       v
Validation Engine
       |
       v
SQL Builder
       |
       v
Repository / Database Layer
       |
       v
Database Driver
       |
       v
Microsoft SQL Server

---

# Request Flow

A typical request follows:

JSON Request
     |
     v
Request Validation
     |
     v
Controller
     |
     v
SQL Builder
     |
     v
Repository
     |
     v
Database Driver
     |
     v
SQL Server
     |
     v
Result
     |
     v
Standard JSON Response

---

# Configuration Layer

Database configuration is loaded from:

database/config/database.json

The configuration determines:

- Provider
- ODBC driver
- Server
- Database
- Authentication
- Username
- Password
- Port
- Encryption
- Certificate trust behavior

This keeps environment-specific connection information outside the core query engine.

---

# Database Driver Architecture

The framework uses a database driver interface.

The SQL Server implementation is responsible for SQL Server-specific connection behavior.

The driver handles:

- ODBC connection creation
- Driver detection
- Authentication mode
- Server/instance handling
- Port handling
- Encryption options
- Trust Server Certificate
- Query execution
- Transactions
- Result fetching

This allows additional database drivers to be introduced without redesigning the rest of the framework.

---

# ODBC Driver Detection

When:

"driver": "auto"

is configured, the SQL Server driver tests compatible installed ODBC driver names.

Detection is performed from newer to older supported driver generations.

The framework can therefore work across environments with different installed SQL Server ODBC driver versions.

This avoids making the API dependent on a single hard-coded ODBC driver version.

---

# Authentication

The SQL Server driver supports:

SQL Authentication
Windows Authentication

The connection logic selects the authentication method from:

"authentication": "sql"

or:

"authentication": "windows"

---

# Server and Port Handling

The database configuration supports both:

localhost

and named instances such as:

localhost\SQLEXPRESS

An explicit port can also be supplied:

"port": 1433

The driver constructs the SQL Server connection target from the configured server and port.

---

# Query Compatibility Layer

Pagination is handled by the database layer instead of the frontend.

The framework can detect SQL Server capability and select a compatible pagination implementation.

SQL Server capability
        |
        +---- Modern capability
        |        |
        |        v
        |    Modern pagination
        |
        +---- Older compatibility
                 |
                 v
             ROW_NUMBER()

This prevents the frontend from needing database-version-specific SQL logic.

The frontend continues to send:

{
    "pagination": {
        "page": 1,
        "pageSize": 25
    }
}

---

# Validation Engine

The Validation Engine validates incoming request data before SQL generation.

It validates:

- Controller
- Action
- Table
- Columns
- SQL functions
- Operators
- JOIN definitions
- Aliases
- Pagination input

Invalid requests are rejected before SQL generation.

---

# SQL Builder

The SQL Builder converts validated JSON requests into SQL statements.

It supports:

- SELECT
- WHERE
- JOIN
- GROUP BY
- HAVING
- ORDER BY
- Pagination
- SQL functions

Database-specific pagination behavior remains in the database/query compatibility layer.

---

# Repository Layer

The Repository Pattern separates database operations from controllers and request processing.

Repositories communicate with the configured database driver instead of directly coupling application logic to ODBC.

---

# Logging

The framework provides centralized logging for:

- Successful queries
- Failed queries
- Exceptions
- Execution time
- Rows returned
- Query parameters

The Windows launcher also configures the PHP error log under the project logs/ directory.

---

# Windows Runtime Architecture

Windows can use the bundled PHP runtime:

start-windows.bat
        |
        v
runtime/windows/php/php.exe
        |
        v
PHP ODBC
        |
        v
SQL Server ODBC Driver
        |
        v
SQL Server

The launcher performs environment checks before starting the API.

It also creates the required OPcache and logging directories automatically.

---

# Port Selection

The Windows startup script begins with:

8000

If the port is occupied, it searches sequentially up to:

8100

The selected port is then passed to PHP's built-in server.

---

# Security Architecture

Security is enforced through:

- Request validation
- Parameterized SQL execution
- Centralized database access
- CORS configuration
- Exception handling
- Controlled database configuration
- Avoidance of direct SQL exposure to frontend clients

Production deployments should also use HTTPS and protect database credentials.

---

# Extensibility

The architecture is designed for future expansion.

Potential future components include:

- Additional database providers
- CRUD operations
- Stored procedures
- Authentication
- Authorization
- Export systems
- Dashboard services
- API documentation
- Caching
- Monitoring