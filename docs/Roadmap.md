# Roadmap

This roadmap covers the backend and database API only.

Frontend applications, dashboards, charts, reporting screens, and UI components are outside the scope of this repository.

---

## v1.0.0 — Core API

**Status:** Released

The initial release established the basic backend and database query architecture.

### Included

- JSON-based API requests
- Controller and action handling
- Dynamic SQL query generation
- Query execution
- Request validation
- Table validation
- Column validation
- SQL function validation
- Operator validation
- JOIN support
- GROUP BY
- HAVING
- ORDER BY
- Pagination
- Column aliases
- SQL expressions
- Prepared SQL execution
- Multiple-result query execution
- SQL file execution
- Database metadata access
- Query logging
- Query execution statistics
- Exception handling
- Microsoft SQL Server connectivity through ODBC

---

## v1.1.0 — Windows Runtime & SQL Server Connectivity

**Status:** Current

This release focuses on making the backend easier to deploy on Windows and improving SQL Server connectivity.

### Windows Runtime

Added a prebuilt Windows PHP runtime:

```text
runtime/
└── windows/
    └── php/
```

Windows users can run the backend without installing PHP separately.

### Startup Script

Added:

```text
start-windows.bat
```

The startup script performs environment checks before starting the API.

It checks:

- PHP runtime
- `php.ini`
- Required runtime directories
- PHP ODBC extension
- Database configuration
- Database connection
- API directory
- Available port

### Automatic Directories

The launcher automatically creates:

```text
runtime/windows/php/opcache/
logs/
```

No manual directory creation is required.

### Port Handling

The launcher starts at:

```text
8000
```

If the port is already in use, it searches for an available port up to:

```text
8100
```

### Database Startup Check

The API now verifies the configured database connection before starting.

If the connection fails:

```text
[FAILED] Database connection failed.
```

The API startup is aborted.

Users are directed to:

```text
database/config/database.json
```

for configuration.

### SQL Server ODBC

The database layer supports SQL Server through ODBC.

The configuration can use automatic driver selection:

```json
{
  "provider": "sqlserver",
  "driver": "auto"
}
```

A specific installed ODBC driver can also be selected.

### Authentication

SQL Server configurations can use:

- SQL Authentication
- Windows Authentication

### Connection Options

The configuration supports SQL Server connection settings such as:

- Server
- Database
- Port
- ODBC driver
- Encryption
- Trust Server Certificate

---

## v1.2.0 — CRUD Operations

**Status:** Planned

Expand the query API beyond read operations.

### Planned

- INSERT
- UPDATE
- DELETE
- UPSERT
- Write-operation validation
- Prepared write operations
- Standardized write responses
- Write-operation error handling

---

## v1.3.0 — Transactions

**Status:** Planned

Add transaction support for operations that require multiple database changes to succeed or fail together.

### Planned

- Begin transaction
- Commit
- Rollback
- Transaction error handling
- Transaction-aware query execution
- Transaction logging

Example flow:

```text
Begin
  |
  v
Operation 1
  |
  v
Operation 2
  |
  +---- Error ----> Rollback
  |
  v
Commit
```

---

## v1.4.0 — Advanced SQL

**Status:** Planned

Expand the SQL capabilities available through the JSON request format.

### Planned

- CASE expressions
- COALESCE
- ISNULL
- CAST
- CONVERT
- UNION
- UNION ALL
- Additional CTE support
- Window functions
- Additional SQL Server expressions

The API request format should remain structured rather than requiring clients to send arbitrary SQL.

---

## v1.5.0 — Database Metadata

**Status:** Planned

Expand database inspection and metadata capabilities.

### Planned

- Schema information
- Table information
- Column information
- Data types
- Primary keys
- Foreign keys
- Index information
- Database object discovery
- Metadata caching

---

## v1.6.0 — API Security

**Status:** Planned

Add authentication and authorization to the backend API.

### Planned

- API keys
- Authentication middleware
- JWT authentication
- Role-based access
- Permission checks
- Authorization middleware
- Audit logging
- Rate limiting

---

## v1.7.0 — API Improvements

**Status:** Planned

Improve the API contract and developer experience.

### Planned

- API versioning
- Consistent error responses
- Improved validation messages
- Health endpoint
- API status endpoint
- Better diagnostics
- OpenAPI documentation

---

## v1.8.0 — Performance

**Status:** Planned

Focus on performance and larger workloads.

### Planned

- Query caching
- Metadata caching
- Connection handling improvements
- Query profiling
- Large-result handling
- Performance diagnostics
- Log rotation
- Additional execution statistics

---

## v1.9.0 — Additional Database Providers

**Status:** Planned

Expand the database layer beyond SQL Server.

Potential providers:

```text
MySQL
PostgreSQL
MariaDB
SQLite
```

Each provider will require its own database-specific implementation.

The goal is to keep the HTTP API and request structure as consistent as possible between providers.

---

## v2.0.0 — Platform & Deployment

**Status:** Future

Improve deployment support across different environments.

### Planned

- Linux runtime/setup
- Linux startup scripts
- Docker deployment
- Environment-based configuration
- Installation helpers
- Deployment helpers
- Configuration validation
- Deployment diagnostics
- Expanded automated testing

---

## Testing

Testing will continue alongside feature development.

Important areas include:

- JSON request validation
- SQL generation
- SQL Server connectivity
- ODBC driver compatibility
- Pagination
- CRUD operations
- Transactions
- Error handling
- Authentication
- Authorization
- Database providers
- Windows startup
- Future Linux startup

---

## Project Scope

### This repository handles

- Backend API
- JSON request processing
- SQL generation
- Query execution
- Database connectivity
- Database validation
- Database metadata
- Request validation
- Backend logging
- Query statistics
- Authentication and authorization in future versions
- Deployment support

### This repository does not handle

- Frontend applications
- Dashboards
- Charts
- Reporting UI
- Frontend routing
- Frontend state management
- Website design

Those belong to applications that consume this API.

---

## Current Development Direction

```text
v1.1.0
Windows Runtime + SQL Server Connectivity
        |
        v
v1.2.0
CRUD
        |
        v
v1.3.0
Transactions
        |
        v
v1.4.0
Advanced SQL
        |
        v
v1.5.0
Database Metadata
        |
        v
v1.6.0
API Security
        |
        v
v1.7.0
API Improvements
        |
        v
v1.8.0
Performance
        |
        v
v1.9.0
Additional Database Providers
        |
        v
v2.0.0
Platform & Deployment
```

---

## Versioning

The project follows semantic versioning:

```text
MAJOR.MINOR.PATCH
```

### MAJOR

Breaking API or architecture changes.

Example:

```text
2.0.0
```

### MINOR

New backward-compatible functionality.

Example:

```text
1.2.0
```

### PATCH

Backward-compatible fixes.

Example:

```text
1.1.1
```

---

## Related Documentation

- [Introduction](Introduction.md)
- [Architecture](Architecture.md)
- [API](API.md)
- [Database Configuration](Database-Configuration.md)
- [Hosting](Hosting.md)
- [Changelog](../CHANGELOG.md)