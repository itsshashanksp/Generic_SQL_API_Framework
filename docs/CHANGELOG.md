# Changelog

All notable changes to Generic SQL API Framework are documented in this file.

The project follows [Semantic Versioning](https://semver.org/).

---

## [Unreleased]

Changes that are currently being developed and are not part of a released version.

---

## [1.1.0] - 2026-08-21

### Added

#### Windows PHP Runtime

Added a prebuilt Windows PHP runtime:

```text
runtime/
└── windows/
    └── php/
```

The bundled runtime allows the API to run on Windows without requiring a separate PHP installation.

Added:

```text
start-windows.bat
```

The startup script performs environment checks before starting the API.

---

#### Runtime Checks

The Windows startup process now checks:

- PHP runtime
- `php.ini`
- Runtime directories
- PHP ODBC extension
- Database configuration
- Database connection
- API directory
- Available port

---

#### Automatic Runtime Directories

The startup script automatically creates the required directories when they do not exist:

```text
runtime/windows/php/opcache/
logs/
```

Manual directory creation is not required.

---

#### Database Connection Check

The API now checks the database connection before starting the PHP server.

Successful connection:

```text
[OK] Database Connected
```

Failed connection:

```text
[FAILED] Database connection failed.
```

When the connection fails, API startup is aborted and the user is directed to:

```text
database/config/database.json
```

---

#### Automatic Port Selection

The Windows startup script starts checking from:

```text
8000
```

If the port is already in use, the next available port is searched up to:

```text
8100
```

Example:

```text
8000 -> In use
8001 -> In use
8002 -> Available
```

The selected port is displayed before the API starts.

---

#### SQL Server ODBC Driver Handling

Improved SQL Server connectivity through ODBC.

The database configuration supports automatic driver selection:

```json
{
  "provider": "sqlserver",
  "driver": "auto"
}
```

A specific installed ODBC driver can also be configured.

This allows the backend to work with supported SQL Server ODBC driver versions without requiring the application to depend on one hard-coded driver version.

---

#### SQL Server Authentication

Added support for:

- SQL Server authentication
- Windows authentication

---

#### SQL Server Connection Options

Added configuration support for:

- Server
- Database
- Port
- ODBC driver
- Encryption
- Trust Server Certificate

---

### Improved

#### Windows Deployment

Windows deployment no longer depends on:

- XAMPP
- WAMP
- System-wide PHP installation

when the bundled PHP runtime is used.

Apache, IIS, Nginx, XAMPP, or another PHP hosting environment can still be used when required by the deployment.

---

#### Startup Diagnostics

Improved startup messages to make failures easier to identify.

The startup process now clearly separates:

```text
PHP Runtime
PHP Configuration
Runtime Directories
PHP ODBC
Database Connection
API Directory
Port
API Startup
```

---

#### SQL Server Compatibility

Improved compatibility with different SQL Server ODBC driver installations.

---

## [1.0.0] - Initial Release

### Added

#### API

- JSON-based API requests
- Controller and action handling
- Standardized JSON responses
- Request parsing
- API-level error handling
- CORS handling

---

#### Query Engine

- Dynamic SELECT query generation
- Query execution
- Prepared SQL execution
- Multiple-result execution
- SQL file execution
- Query execution statistics
- Query error handling

---

#### Query Builder

- Column selection
- Column aliases
- Table aliases
- WHERE conditions
- JOINs
- GROUP BY
- HAVING
- ORDER BY
- Pagination
- SQL expressions
- SQL functions
- CTE-related query construction
- Set operations
- Procedure-related query construction

---

#### Validation

Added validation for query components including:

- Tables
- Columns
- Functions
- Operators
- JOIN definitions
- Aliases
- Sort directions
- Request structure

---

#### SQL Functions

Supported function groups include:

### Aggregate

```text
COUNT
SUM
AVG
MIN
MAX
```

### String

```text
UPPER
LOWER
LTRIM
RTRIM
TRIM
LEN
```

### Date

```text
YEAR
MONTH
DAY
DATEPART
DATENAME
GETDATE
```

### Mathematical

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

#### Database

- Microsoft SQL Server support
- ODBC connectivity
- Database configuration through JSON
- Database metadata access
- Database object validation

---

#### Logging

Added backend logging for:

- SQL execution
- Query parameters
- Execution time
- Returned rows
- Database errors
- Exceptions

---

#### Architecture

Established separation between:

```text
API
 |
 v
Controllers
 |
 v
Validation
 |
 v
Query Repository
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

---

## Planned Releases

The following areas are planned for future versions.

### v1.2.0

CRUD operations:

- INSERT
- UPDATE
- DELETE
- UPSERT
- Write-operation validation
- Prepared write operations

---

### v1.3.0

Transactions:

- BEGIN
- COMMIT
- ROLLBACK
- Transaction error handling
- Transaction-aware execution

---

### v1.4.0

Advanced SQL capabilities:

- CASE
- COALESCE
- ISNULL
- CAST
- CONVERT
- UNION
- UNION ALL
- Additional CTE support
- Window functions
- Additional SQL Server expressions

---

### v1.5.0

Database metadata:

- Schema information
- Column information
- Data types
- Primary keys
- Foreign keys
- Index information
- Database object discovery
- Metadata caching

---

### v1.6.0

API security:

- API keys
- Authentication middleware
- JWT authentication
- Role-based access
- Authorization
- Audit logging
- Rate limiting

---

### v1.7.0

API improvements:

- API versioning
- Improved validation errors
- Health endpoint
- API status endpoint
- Improved diagnostics
- OpenAPI documentation

---

### v1.8.0

Performance improvements:

- Query caching
- Metadata caching
- Connection handling improvements
- Query profiling
- Large-result handling
- Performance diagnostics
- Log rotation

---

### v1.9.0

Additional database providers:

```text
MySQL
PostgreSQL
MariaDB
SQLite
```

These providers will be added only when their database-specific implementations are available.

---

### v2.0.0

Platform and deployment improvements:

- Linux runtime/setup
- Linux startup scripts
- Docker support
- Environment-based configuration
- Installation helpers
- Deployment helpers
- Configuration validation
- Deployment diagnostics
- Expanded automated testing

---

## Version Format

The project uses:

```text
MAJOR.MINOR.PATCH
```

### MAJOR

Breaking API or architecture changes.

```text
2.0.0
```

### MINOR

New backward-compatible functionality.

```text
1.2.0
```

### PATCH

Backward-compatible fixes.

```text
1.1.1
```

---

## Scope

This changelog tracks the backend and database API.

Frontend applications, dashboards, charts, report screens, and other UI components are outside the scope of this repository.