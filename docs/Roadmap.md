# Roadmap

This document describes the planned development direction of the Generic SQL API Framework.

The roadmap may change as the framework evolves and new requirements are identified.

---

# Current Release - v1.0

## Framework Core

- Dynamic SQL Query Engine
- Validation Engine
- SQL Builder
- Repository Pattern
- Database Engine
- Driver Factory
- Centralized Logging
- Query Execution Statistics
- Standardized JSON Responses
- Global Exception Handling

## SQL Server

- SELECT
- WHERE
- GROUP BY
- HAVING
- ORDER BY
- Pagination
- INNER JOIN
- LEFT JOIN
- RIGHT JOIN
- SQL Functions

## SQL Server Connectivity

- ODBC connectivity
- Automatic ODBC driver detection
- Multiple supported ODBC driver generations
- SQL Authentication
- Windows Authentication
- Named SQL Server instances
- Explicit SQL Server ports
- Encryption options
- Trust Server Certificate options

## Compatibility

- SQL Server capability-aware pagination
- ROW_NUMBER() fallback for older compatibility levels
- Frontend-independent pagination

## Windows Deployment

- Bundled Windows PHP runtime
- start-windows.bat
- Automatic PHP checks
- Automatic ODBC checks
- Automatic database connection check
- Automatic runtime directory creation
- Automatic port selection

---

# v1.1 - CRUD Operations

## Database Operations

- INSERT
- UPDATE
- DELETE
- UPSERT

## Validation

- Insert validation
- Update validation
- Delete validation

## Transactions

- Begin transaction
- Commit
- Rollback
- Transaction error handling

---

# v1.2 - Advanced SQL

## SQL Features

- Stored procedures
- UNION
- UNION ALL
- CASE
- COALESCE
- ISNULL
- CAST
- CONVERT
- Common Table Expressions
- Window functions

## Operations

- Bulk insert
- Batch operations
- Improved transaction management

---

# v1.3 - Reporting

## Report Management

- Report templates
- Saved queries
- Report configuration
- Report scheduling

## Export

- Excel
- PDF
- CSV
- JSON

---

# v2.0 - Security

## Authentication

- Login API
- API Keys
- JWT authentication
- Token validation
- OAuth support

## Authorization

- Role-Based Access Control
- User permissions
- Resource permissions

## Audit

- Audit logs
- User activity logs
- API request logs

---

# v2.1 - Dashboard Engine

## Dashboard

- Dashboard API
- Dashboard configuration
- Widget support
- Layout configuration

## Charts

- Bar charts
- Line charts
- Pie charts
- Area charts
- KPI cards

---

# v2.2 - Performance

## Optimization

- Query caching
- Metadata caching
- Performance monitoring
- Query profiling

## Logging

- Log levels
- Log rotation
- Log cleanup
- Improved structured logging

---

# v3.0 - Multi-Database Support

## Planned Providers

- Microsoft SQL Server
- MySQL
- PostgreSQL
- MariaDB

## Database Abstraction

- Database Driver Interface
- Driver Factory
- Query Compatibility Layer
- Provider-specific SQL handling

---

# v3.1 - Developer Experience

## API

- Interactive API documentation
- OpenAPI / Swagger
- Postman collection

## Development Tools

- CLI tools
- Project generator
- Configuration wizard

---

# v4.0 - Enterprise Features

## Administration

- User management
- Organization management
- Multi-tenant support

## Monitoring

- Health checks
- Metrics
- System monitoring
- Performance dashboard

## Integration

- REST API improvements
- Webhooks
- Event system

---

# Long-Term Vision

The goal is to provide a reusable backend platform that simplifies database-driven application development.

The framework will continue to focus on:

- Database abstraction
- Secure API development
- Dynamic reporting
- Enterprise reporting
- Dashboard integration
- Developer productivity
- Maintainability
- Extensibility

---

# Documentation Path

For the current framework:

README.md
    |
    v
Introduction.md
    |
    v
Architecture.md
    |
    v
API.md
    |
    v
JSON-Request-Reference.md
    |
    v
Query-Examples.md
    |
    v
Database-Configuration.md
    |
    v
Hosting.md
    |
    v
Roadmap.md
    |
    v
CHANGELOG.md