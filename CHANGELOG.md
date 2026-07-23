## [0.7.0] - 2026-07-23

This release significantly expands the SQL Server function library supported by the Generic Dashboard Query Engine, bringing it closer to feature parity with native SQL Server queries while maintaining JSON-driven dynamic query generation.

### Added

#### Date Functions

- DATEADD()
- DATEDIFF()
- EOMONTH()
- ISDATE()
- DATEFROMPARTS()
- DATETIMEFROMPARTS()

#### String Functions

- REPLACE()
- CHARINDEX()
- PATINDEX()
- FORMAT()

#### Query Engine

- Support for functions without direct column dependencies
- Dynamic parameter validation for date construction functions
- Automatic handling of SQL date expressions
- Enhanced expression builder for multi-parameter SQL functions

### Improved

- Refactored SQL function validation pipeline
- Improved date function processing
- Improved dynamic SQL generation for multi-argument functions
- Improved expression parsing for nested function calls
- Enhanced alias handling across generated expressions
- Better validation messages for invalid function parameters
- Improved maintainability of QueryRepository function handlers

### Fixed

- Fixed DATEDIFF() validation logic
- Fixed DATEDIFF() SQL generation
- Fixed date conversion handling for SQL Server
- Fixed function resolution for functions without column arguments
- Fixed edge cases in dynamic SQL builder for date expressions
- Improved SQL generation stability for complex function combinations

---

### Notes

This release focuses on expanding SQL Server compatibility by introducing advanced date and string functions frequently used in enterprise reporting systems.

The query engine now supports complex date calculations, date construction, string searching, formatting, and replacement while continuing to generate fully dynamic SQL from structured JSON requests.

Development will continue with advanced SQL capabilities, reporting features, stored procedures, window functions, JSON support, and dashboard components.