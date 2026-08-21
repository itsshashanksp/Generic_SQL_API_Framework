# Changelog

All notable changes to this project will be documented in this file.

The format follows the principles of Keep a Changelog and Semantic Versioning.

---

# [Unreleased]

## Added

### Windows Runtime

- Added prebuilt Windows PHP runtime.
- Added start-windows.bat.
- Added automatic PHP runtime validation.
- Added automatic PHP configuration validation.
- Added automatic OPcache directory creation.
- Added automatic log directory creation.
- Added PHP ODBC extension validation.
- Added database connection startup validation.
- Added API directory validation.
- Added automatic HTTP port detection.
- Added automatic port fallback from 8000 through 8100.
- Added database connection failure guidance.

### SQL Server Driver

- Added automatic SQL Server ODBC driver detection.
- Added support for multiple Microsoft ODBC Driver generations.
- Added support for older supported SQL Server ODBC driver names.
- Added SQL Server Native Client compatibility detection.
- Added SQL Server named-instance support.
- Added explicit SQL Server port support.
- Added SQL Authentication support.
- Added Windows Authentication support.
- Added encryption configuration.
- Added Trust Server Certificate configuration.

### Query Compatibility

- Added SQL Server capability-aware pagination.
- Added ROW_NUMBER() pagination fallback for older SQL Server compatibility levels where OFFSET/FETCH is unavailable.
- Kept the frontend pagination request format independent of the SQL Server pagination implementation.

---

## Improved

### Database Connectivity

- Improved SQL Server connection handling.
- Improved ODBC driver compatibility.
- Improved database startup validation.
- Improved connection error reporting.

### Deployment

- Improved Windows deployment experience.
- Removed the requirement for a separate PHP installation when using the bundled Windows runtime.
- Improved startup diagnostics.
- Added automatic runtime directory creation.

### Query Compatibility

- Improved pagination compatibility across SQL Server environments.
- Improved handling of older SQL Server compatibility levels.

---

## Fixed

### SQL Server

- Fixed SQL Server connections requiring explicit port handling.
- Fixed database connection issues with supported older ODBC driver environments.
- Fixed Windows Authentication connection handling.

### Pagination

- Fixed OFFSET/FETCH errors on older SQL Server compatibility levels by providing a compatible ROW_NUMBER() pagination path.

---

# [1.0.0] - Initial Release

## Added

### Query Engine

- Dynamic SELECT query execution
- SQL execution using ODBC
- Prepared statement support
- SQL file execution support
- Standardized JSON responses

### Validation Engine

- Table validation
- Column validation
- SQL function validation
- JOIN validation
- Operator validation
- Alias validation
- Request validation before query execution

### SQL Builder

- Dynamic SQL generation from JSON requests
- Dynamic column selection
- WHERE clause generation
- GROUP BY support
- HAVING support
- ORDER BY support
- Pagination support
- JOIN query generation

### SQL Function Support

#### Aggregate Functions

- COUNT()
- SUM()
- AVG()
- MIN()
- MAX()

#### String Functions

- UPPER()
- LOWER()
- LTRIM()
- RTRIM()
- TRIM()
- LEN()

#### Date Functions

- YEAR()
- MONTH()
- DAY()
- DATEPART()
- DATENAME()
- GETDATE()

#### Mathematical Functions

- ABS()
- ROUND()
- CEILING()
- FLOOR()
- POWER()
- SQRT()
- EXP()
- LOG()

### Metadata Engine

- Database metadata validation
- Table existence verification
- Column existence verification

### Logging

- Centralized logging system
- Daily log file generation
- Automatic log directory creation
- Successful query logging
- Failed query logging
- Exception logging
- Execution time logging
- Rows returned logging
- SQL parameter logging

### Statistics

- Query execution time
- Rows returned
- Standardized execution statistics

### Error Handling

- Global exception handler
- Standardized error responses
- Centralized exception logging

### Architecture

- Modular project structure
- Repository Pattern implementation
- Configurable framework architecture
- JSON-driven request processing

---

## Security

- Parameterized SQL execution
- SQL validation before execution
- Exception handling
- Centralized logging
- Input validation

---

## Documentation

- Project README
- CHANGELOG
- Framework documentation structure
- Architecture overview

---

## Initial Release Notes

Version 1.0.0 provides the foundation of the Generic SQL API Framework, including a dynamic query engine, validation system, centralized logging, metadata validation, execution statistics, and a modular architecture designed for future expansion.

Future releases will introduce INSERT, UPDATE, DELETE, transactions, stored procedure support, authentication, export modules, and dashboard integration.