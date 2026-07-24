# Changelog

All notable changes to this project will be documented in this file.

---

# v0.8.0 - Window Functions & Expression Engine
Release Date: 2026-07-24

## Added

### SQL Expression Engine
- Arithmetic expression support
- Generic SQL value builder
- Generic SQL expression builder
- Generic SQL condition builder

### Conditional Functions
- CASE expression
- IIF()
- CHOOSE()

### Window Functions
- ROW_NUMBER()
- RANK()

### Aggregate Functions
- COUNT()
- SUM()
- AVG()
- MIN()
- MAX()

### String Functions
- UPPER()
- LOWER()
- LTRIM()
- RTRIM()
- TRIM()
- LEN()
- LEFT()
- RIGHT()
- SUBSTRING()
- REPLACE()
- CHARINDEX()
- PATINDEX()
- FORMAT()
- CONCAT()
- COALESCE()
- ISNULL()
- NULLIF()
- CAST()
- CONVERT()

### Date Functions
- YEAR()
- MONTH()
- DAY()
- DATEPART()
- DATENAME()
- GETDATE()
- DATEADD()
- DATEDIFF()
- EOMONTH()
- ISDATE()
- DATEFROMPARTS()
- DATETIMEFROMPARTS()
- TIMEFROMPARTS()
- SYSDATETIME()
- CURRENT_TIMESTAMP()

### Mathematical Functions
- ABS()
- ROUND()
- CEILING()
- FLOOR()
- POWER()
- SQRT()
- EXP()
- LOG()

### Query Features
- DISTINCT
- TOP
- WHERE
- GROUP BY
- HAVING
- ORDER BY
- Pagination
- JOIN support
- Alias support
- Expression support

## Improved

- Better SQL validation
- Improved error handling
- Window function framework
- Generic expression engine
- Improved function validation
- Cleaner SQL generation

## Fixed

- Function validation warnings
- Undefined function warnings
- SQL builder stability improvements