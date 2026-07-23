# Changelog

All notable changes to this project will be documented in this file.

The format of this changelog is inspired by **Keep a Changelog**, and the project follows **Semantic Versioning** principles for future releases.

---

## [0.5.0] - Initial Public Commit

This marks the first public commit of the Generic Dashboard project.

At this stage, the project has evolved into a functional, modular query engine capable of dynamically generating SQL Server queries using structured JSON requests. The foundation has been designed with extensibility, maintainability, and enterprise integration in mind.

### Added

#### Core Framework

- Project architecture
- Configuration management
- Database connection engine
- Repository-based query engine
- Metadata repository
- Response engine
- Exception handling framework
- Modular project structure

#### Dynamic Query Builder

- Dynamic SELECT statement generation
- Table validation
- Column validation
- Dynamic SQL generation
- Alias resolution
- Pagination support
- DISTINCT
- TOP clause

#### WHERE Clause Support

- Equality operators
- Comparison operators
- AND / OR conditions
- LIKE
- NOT LIKE
- IN
- NOT IN
- BETWEEN
- NOT BETWEEN
- IS NULL
- IS NOT NULL

#### Grouping & Sorting

- GROUP BY
- HAVING
- ORDER BY
- Multiple sorting support

#### JOIN Support

- INNER JOIN
- LEFT JOIN
- RIGHT JOIN
- Table aliases
- Column aliases

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

#### Validation

- Table validation
- Column validation
- SQL function validation
- Operator validation
- Alias validation
- Input validation

#### Error Handling

- Standardized JSON responses
- SQL execution error handling
- Validation error reporting
- Exception handling

---

### Fixed

- Improved alias resolution across dynamic queries.
- Resolved validation issues when using SQL functions.
- Corrected SQL generation for GETDATE().
- Improved mathematical function handling.
- Enhanced query builder stability for complex SELECT statements.

---

## [0.6.0] - 2026-07-23

### Added
- CASE expression support
- COALESCE() function
- ISNULL() function
- CAST() function
- CONVERT() function
- NULLIF() function
- Arithmetic expressions (+, -, *, /, %)
- CONCAT() function
- LEFT() function
- RIGHT() function
- SUBSTRING() function

### Improved
- Refactored function validation using functionsWithoutColumn.

---

### Notes

This release represents the completion of the initial query engine foundation.

The project now provides a configurable SQL generation engine capable of powering dynamic reports without requiring developers to create separate SQL queries or APIs for each report.

Development will continue with advanced SQL capabilities, reporting modules, dashboard components, export functionality, and enterprise features.

---

## Future Releases

### v0.7.0

Reporting Engine

- Stored procedures
- Saved reports
- Dynamic report templates
- Pivot reports
- Parameterized reports

### v0.8.0

Dashboard Engine

- Dashboard APIs
- KPI widgets
- Charts
- Dashboard filters
- Dashboard builder

### v0.9.0

Enterprise Features

- Excel export
- PDF export
- CSV export
- Authentication
- Role-based access control
- Audit logs
- Performance optimization

### v1.0.0

Production-ready Generic Dashboard Platform.
