# Changelog

All notable changes to this project will be documented in this file.

The format follows the principles of Keep a Changelog and Semantic Versioning.

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