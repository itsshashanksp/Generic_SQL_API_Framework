# v0.9.0 - Advanced Query Engine
Release Date: 2026-07-26

## Added

### Set Operations
- UNION
- UNION ALL

### Advanced Query Features
- Subquery support
- IN (SELECT ...)
- NOT IN (SELECT ...)
- Nested query generation using existing query builder

## Improved

- Reused Query Builder for nested SELECT statements
- Parameter binding support for subqueries
- Enhanced WHERE clause generation

## Fixed

- Improved nested query handling
- Better SQL generation consistency