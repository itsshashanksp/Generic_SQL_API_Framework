# Roadmap

This roadmap covers the Generic SQL API Framework backend and database API only.

The project is focused on providing a reusable backend layer for applications that need structured access to database operations.

Frontend applications, dashboards, charts, reporting interfaces, and UI components are outside the scope of this repository.

---

# v1.0.0 — Core API & Advanced SQL

**Status:** Released

The initial release established the core API, query builder, validation, database execution layer, SQL Server integration, and advanced SQL capabilities.

## API

- JSON-based API requests
- Controller and action handling
- JSON responses
- Request parsing
- API-level error handling
- CORS handling

---

## Query Engine

- Dynamic SQL query generation
- Query execution
- Prepared SQL execution
- Multiple-result query execution
- SQL file execution
- Query execution statistics
- Query error handling

---

## Query Builder

- Column selection
- Column aliases
- Table aliases
- WHERE conditions
- AND / OR conditions
- JOINs
- GROUP BY
- HAVING
- ORDER BY
- Pagination
- SQL expressions
- SQL functions
- Subqueries
- CTE
- Recursive CTE
- Set operations
- Procedure-related query construction

---

## Validation

- Request structure validation
- Table validation
- Column validation
- Function validation
- Operator validation
- JOIN validation
- Alias validation
- Sort direction validation
- Query component validation

---

## Advanced SQL Support

The following capabilities were implemented and tested as part of v1.0.0.

### Query Features

- SELECT
- DISTINCT
- TOP
- WHERE
- AND / OR conditions
- JOINs
- GROUP BY
- HAVING
- ORDER BY
- Pagination
- Column aliases
- Table aliases
- Arithmetic expressions
- CASE expressions
- Subqueries
- EXISTS
- NOT EXISTS
- IN
- NOT IN
- BETWEEN
- NOT BETWEEN
- CTE
- Recursive CTE
- UNION
- UNION ALL

---

## Aggregate Functions

Implemented and tested:

```text
COUNT
SUM
AVG
MIN
MAX
STRING_AGG
```

---

## String Functions

Implemented and tested:

```text
UPPER
LOWER
LTRIM
RTRIM
TRIM
LEN
COALESCE
ISNULL
CAST
CONVERT
NULLIF
CONCAT
LEFT
RIGHT
SUBSTRING
REPLACE
CHARINDEX
PATINDEX
FORMAT
```

---

## Date and Time Functions

Implemented and tested:

```text
YEAR
MONTH
DAY
DATEPART
DATENAME
GETDATE
DATEADD
DATEDIFF
EOMONTH
ISDATE
DATEFROMPARTS
DATETIMEFROMPARTS
TIMEFROMPARTS
SYSDATETIME
CURRENT_TIMESTAMP
IIF
```

---

## Mathematical Functions

Implemented and tested:

```text
ABS
ROUND
CEILING
FLOOR
POWER
SQRT
EXP
LOG
```

---

## Window Functions

Implemented and tested:

```text
ROW_NUMBER
RANK
DENSE_RANK
NTILE
LAG
LEAD
FIRST_VALUE
LAST_VALUE
```

---

## Database Object Execution

Implemented and tested:

- Stored procedure execution
- Scalar function execution
- Table-valued function execution

---

## Database Support

The initial release provides:

- Microsoft SQL Server support
- ODBC connectivity
- Database configuration through JSON
- Database metadata access
- Database object validation
- Database error handling

---

## Logging

Added logging for:

- SQL execution
- Query parameters
- Execution time
- Returned rows
- Database errors
- Exceptions

---

# v1.1.0 — Windows Runtime & Deployment

**Status:** Current

This release focuses on making the backend easier to install, configure, and run on Windows.

The Advanced SQL functionality belongs to v1.0.0 and is not repeated as part of this release.

---

## Prebuilt Windows PHP Runtime

Added a bundled Windows PHP runtime:

```text
runtime/
└── windows/
    └── php/
```

The bundled runtime allows the API to run on Windows without requiring a separate PHP installation.

Users do not need to manually install PHP when using the bundled runtime.

---

## Windows Startup Script

Added:

```text
start-windows.bat
```

The startup script checks the required environment before starting the API.

### Startup Flow

```text
PHP Runtime
    ↓
PHP Configuration
    ↓
Runtime Directories
    ↓
PHP ODBC
    ↓
Database Configuration
    ↓
Database Connection
    ↓
API Directory
    ↓
Available Port
    ↓
Start API
```

---

## PHP Runtime Validation

The startup script checks:

- PHP runtime
- `php.ini`
- PHP ODBC extension
- API directory

If a required component is missing, startup is stopped with an error message.

---

## Automatic Runtime Directories

The startup script creates required directories automatically when they do not exist.

```text
runtime/windows/php/opcache/
logs/
```

No manual directory creation is required.

---

## OPcache

The Windows runtime is configured to use a file cache for OPcache.

The startup script also ensures that the OPcache directory exists before starting PHP.

---

## PHP Configuration

The bundled PHP configuration is:

```text
runtime/windows/php/php.ini
```

The startup script explicitly loads this configuration when running the database check and API.

---

## ODBC Validation

The startup script checks whether PHP ODBC is available.

Example check:

```bat
runtime\windows\php\php.exe -m | findstr /i odbc
```

Expected output:

```text
odbc
```

The connection architecture is:

```text
Generic SQL API
       |
       v
PHP ODBC Extension
       |
       v
SQL Server ODBC Driver
       |
       v
Microsoft SQL Server
```

---

## SQL Server Driver Support

The backend uses ODBC for SQL Server connectivity.

The configuration can use automatic driver selection:

```json
{
    "provider": "sqlserver",
    "driver": "auto"
}
```

A specific installed driver can also be configured.

Example:

```json
{
    "provider": "sqlserver",
    "driver": "ODBC Driver 18 for SQL Server"
}
```

The actual driver must be installed on the host machine.

---

## Database Configuration

Database configuration is stored separately from the application code:

```text
database/config/database.json
```

This keeps database credentials and environment-specific connection details outside the API source code.

Example:

```json
{
    "provider": "sqlserver",
    "driver": "auto",
    "server": "localhost\\SQLEXPRESS",
    "database": "TestDB",
    "authentication": "windows",
    "port": 1433
}
```

---

## Database Connection Check

The startup script checks the configured database connection before starting the API.

Successful connection:

```text
Checking database connection...

[OK] Database Connected
```

Failed connection:

```text
Checking database connection...

[FAILED] Database connection failed.
```

If the connection fails, the API startup is aborted.

The user is directed to configure:

```text
database/config/database.json
```

---

## Authentication

The database configuration supports:

- SQL Server authentication
- Windows authentication

---

## SQL Server Connection Options

The database configuration supports:

- Provider
- ODBC driver
- Server
- Database
- Authentication
- Username
- Password
- Port
- Encryption
- Trust Server Certificate

---

## Automatic Port Selection

The Windows startup script starts checking from:

```text
8000
```

If the port is already in use, it checks the next available port.

The current range is:

```text
8000 - 8100
```

Example:

```text
8000 → In use
8001 → In use
8002 → Available
```

The selected port is displayed before the API starts.

---

## Windows Deployment

The bundled runtime removes the requirement for a separate:

- PHP installation
- XAMPP installation
- WAMP installation

when the bundled PHP runtime is used.

The backend can still be hosted using an existing PHP environment such as:

- Apache
- IIS
- Nginx
- XAMPP

if required by the deployment environment.

---

# v1.2.0 — CRUD Operations

**Status:** Planned

The next major backend phase is database write support.

## Planned

- INSERT
- UPDATE
- DELETE
- UPSERT
- Write-operation validation
- Prepared write operations
- Standardized write responses
- Write-operation error handling

The existing query architecture will be extended to support write operations.

---

# v1.3.0 — Transactions

**Status:** Planned

Add transaction support for operations that need multiple database changes to succeed or fail together.

## Planned

- Begin transaction
- Commit
- Rollback
- Transaction error handling
- Transaction-aware query execution
- Transaction logging

### Expected Flow

```text
BEGIN
  |
  v
Operation 1
  |
  v
Operation 2
  |
  +---- Error ----> ROLLBACK
  |
  v
COMMIT
```

---

# v1.4.0 — Database Metadata

**Status:** Planned

Expand database metadata and schema inspection capabilities.

## Planned

- Schema information
- Table information
- Column information
- Data types
- Primary keys
- Foreign keys
- Index information
- Database object discovery
- Metadata caching

The goal is to allow applications to retrieve database structure through the API instead of implementing their own database inspection logic.

---

# v1.5.0 — API Security

**Status:** Planned

Add authentication and authorization to the backend API.

## Planned

- API keys
- Authentication middleware
- JWT authentication
- Role-based access
- Permission checks
- Authorization middleware
- Audit logging
- Rate limiting

The goal is to allow multiple applications to use the API while controlling access to database operations.

---

# v1.6.0 — API Improvements

**Status:** Planned

Improve the API contract, diagnostics, and developer experience.

## Planned

- API versioning
- Consistent error response structure
- Improved validation messages
- Health endpoint
- API status endpoint
- Improved diagnostics
- OpenAPI documentation

---

# v1.7.0 — Performance

**Status:** Planned

Improve performance for larger databases, larger result sets, and higher API usage.

## Planned

- Query caching
- Metadata caching
- Connection handling improvements
- Query profiling
- Large-result handling
- Performance diagnostics
- Log rotation
- Additional execution statistics

Performance improvements should not bypass validation or security controls.

---

# v1.8.0 — Additional Database Providers

**Status:** Planned

The current database implementation is focused on Microsoft SQL Server.

Future versions may add additional database providers.

Potential providers include:

```text
MySQL
PostgreSQL
MariaDB
SQLite
```

Each provider will require its own database-specific implementation.

The goal is to keep the API request structure and application-facing behavior as consistent as possible between providers.

---

# v2.0.0 — Platform & Deployment

**Status:** Future

Expand deployment support beyond the current Windows runtime.

---

## Linux

Planned:

- Linux runtime/setup
- Linux startup script
- Linux deployment documentation

Linux support is intentionally deferred until the Windows deployment and backend features are further developed.

---

## Docker

Planned:

- Docker image
- Container configuration
- Environment-based configuration
- Container deployment documentation

---

## Deployment Improvements

Planned:

- Installation helpers
- Deployment helpers
- Configuration validation
- Deployment diagnostics
- Production deployment improvements

---

# Testing

The currently implemented query, SQL function, database object, SQL Server, and Windows runtime features have been tested during development.

Testing will continue as new functionality is added.

Future testing work includes:

- CRUD operations
- Transactions
- Authentication
- Authorization
- Additional database providers
- Regression testing
- Automated testing
- Production deployment testing
- Future Linux deployment testing
- Docker deployment testing

---

# Project Scope

This repository is focused on the backend database API.

## Included

- Backend API
- JSON request processing
- Query generation
- SQL execution
- Database connectivity
- Database validation
- Advanced SQL
- SQL functions
- Database metadata
- Database object execution
- Logging
- Query statistics
- Authentication and authorization in future versions
- Runtime and deployment support

## Not Included

The following belong to applications that consume this API and are outside the scope of this repository:

- Frontend applications
- Dashboards
- Charts
- Reporting UI
- Frontend routing
- Frontend state management
- Website design
- UI components

---

# Development Direction

```text
v1.0.0
Core API + Advanced SQL
        |
        v
v1.1.0
Windows Runtime + Deployment
        |
        v
v1.2.0
CRUD Operations
        |
        v
v1.3.0
Transactions
        |
        v
v1.4.0
Database Metadata
        |
        v
v1.5.0
API Security
        |
        v
v1.6.0
API Improvements
        |
        v
v1.7.0
Performance
        |
        v
v1.8.0
Additional Database Providers
        |
        v
v2.0.0
Platform & Deployment
```

---

# Versioning

The project follows Semantic Versioning:

```text
MAJOR.MINOR.PATCH
```

## MAJOR

Breaking API or architecture changes.

Example:

```text
2.0.0
```

## MINOR

New backward-compatible functionality.

Example:

```text
1.2.0
```

## PATCH

Backward-compatible fixes.

Example:

```text
1.1.1
```

---

# Related Documentation

- [Introduction](Introduction.md) — project purpose and scope
- [Architecture](Architecture.md) — backend architecture and internal flow
- [API](API.md) — HTTP API usage
- [JSON Request Reference](JSON-Request-Reference.md) — JSON request structure
- [Query Examples](Query-Examples.md) — practical query examples
- [Database Configuration](Database-Configuration.md) — SQL Server and ODBC configuration
- [Hosting](Hosting.md) — running and deploying the backend
- [Contributing](../CONTRIBUTING.md) — development guidelines
- [Changelog](../CHANGELOG.md) — released changes