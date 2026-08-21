# Changelog

All notable changes to this project will be documented in this file.

---

# [Unreleased]

## Added

### Windows Portable PHP Runtime

- Added prebuilt Windows PHP runtime under runtime/windows/php/.
- Added start-windows.bat.
- Added PHP runtime validation.
- Added PHP configuration validation.
- Added automatic OPcache directory creation.
- Added automatic log directory creation.
- Added PHP ODBC extension validation.
- Added database connection startup validation.
- Added API directory validation.
- Added automatic HTTP port detection.
- Added automatic fallback from port 8000 through 8100.
- Added database connection failure guidance.

### SQL Server Driver Improvements

- Added automatic SQL Server ODBC driver detection.
- Added support for multiple Microsoft ODBC Driver generations.
- Added support for older SQL Server ODBC driver names where installed.
- Added SQL Server Native Client compatibility detection.
- Added SQL Server named-instance support.
- Added explicit SQL Server port handling.
- Added SQL Authentication support.
- Added Windows Authentication support.
- Added encryption configuration.
- Added Trust Server Certificate configuration.

### Query Compatibility

- Added SQL Server capability-aware pagination.
- Added ROW_NUMBER() pagination fallback for older SQL Server compatibility levels where OFFSET/FETCH is unavailable.
- Kept the frontend pagination request format independent of SQL Server pagination syntax.

### Deployment

- Windows users can run the framework without manually installing PHP when using the bundled runtime.
- Existing Apache, IIS, Nginx, XAMPP, WAMP, Docker, and PHP deployments remain supported.

---

# [1.0.0] - Initial Release

## Added

### Framework Core

- Dynamic SQL Query Engine
- Validation Engine
- SQL Builder
- Metadata Engine
- Repository Pattern
- Centralized Logging
- Query Execution Statistics
- Standardized JSON Responses
- Global Exception Handling

### SQL Support

- SELECT
- WHERE
- GROUP BY
- HAVING
- ORDER BY
- Pagination
- INNER JOIN
- LEFT JOIN
- RIGHT JOIN

### SQL Functions

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