# Contributing

Thanks for contributing to Generic SQL API Framework.

This repository is focused on the **backend API and database layer**. Changes should keep HTTP handling, query construction, database access, and deployment concerns separated.

---

## Before You Start

Read the project documentation first:

- [Introduction](docs/Introduction.md)
- [Architecture](docs/Architecture.md)
- [API](docs/API.md)
- [JSON Request Reference](docs/JSON-Request-Reference.md)
- [Database Configuration](docs/Database-Configuration.md)
- [Hosting](docs/Hosting.md)
- [Roadmap](docs/Roadmap.md)

This helps avoid introducing changes that conflict with the current architecture.

---

## Development Environment

### Windows

The repository includes a prebuilt Windows PHP runtime.

Start the backend with:

```bat
start-windows.bat
```

The startup script checks:

- PHP runtime
- `php.ini`
- Required runtime directories
- ODBC extension
- Database configuration
- Database connection
- API directory
- Available port

---

## Database Setup

Create your local configuration:

```text
database/config/database.json
```

Do not commit the real configuration file.

Example:

```json
{
  "provider": "sqlserver",
  "driver": "auto",
  "server": "localhost\\SQLEXPRESS",
  "database": "TestDB",
  "authentication": "windows",
  "port": 1433
}
```

For the complete configuration format, see:

[Database Configuration](docs/Database-Configuration.md)

---

## Project Structure

Keep changes in the appropriate part of the project.

```text
api/
    HTTP entry point

database/
    Database configuration and database-specific components

docs/
    Project documentation

runtime/
    Bundled runtime files

scripts/
    Utility and startup-related scripts

```

The exact project structure may change as the framework grows.

---

## Architecture Rules

### Keep HTTP Logic Separate

Controllers and API entry points should handle HTTP-related responsibilities.

Avoid placing SQL generation directly inside the API entry point.

---

### Keep SQL Generation Separate

Query construction belongs to the query/repository layer.

Do not duplicate SQL-building logic across controllers.

---

### Keep Database Logic Separate

Database connection and execution logic should remain in the database/query execution layer.

Avoid creating direct database connections inside individual controllers.

---

### Keep Database-Specific Code Isolated

SQL Server-specific behavior should remain in the SQL Server/database layer where possible.

This makes future database providers easier to add.

---

## Query Changes

When modifying the query builder, test existing functionality before adding new behavior.

At minimum, check the areas affected by the change:

```text
SELECT
WHERE
JOIN
GROUP BY
HAVING
ORDER BY
Pagination
Functions
Aliases
Expressions
```

If a new JSON field is introduced, update:

```text
docs/JSON-Request-Reference.md
docs/Query-Examples.md
```

---

## Database Changes

When modifying SQL Server connectivity, test the relevant connection scenarios.

Where applicable, test:

- SQL Authentication
- Windows Authentication
- Automatic ODBC driver selection
- Explicit ODBC driver selection
- Named SQL Server instances
- Explicit SQL Server ports
- Encryption
- Trust Server Certificate
- Failed connections

---

## Windows Runtime Changes

Changes to:

```text
runtime/windows/php/
```

can affect every Windows deployment.

Changes to:

```text
start-windows.bat
```

should also be tested carefully.

Test at least:

```text
PHP runtime missing
php.ini missing
ODBC missing
database.json missing
Database connection failed
Database connection successful
Port 8000 available
Port 8000 already in use
No available port
API starts successfully
```

---

## Security

Never commit secrets.

Do not commit:

```text
database/config/database.json
```

when it contains real credentials.

Also do not commit:

```text
.env
logs/
runtime/windows/php/opcache/
```

or other generated/local files.

Never add passwords, API keys, tokens, or production connection strings to source code.

---

## JSON Request Changes

The JSON request format is part of the public API contract.

Before changing it:

1. Check the current query builder.
2. Check validation logic.
3. Check existing examples.
4. Consider backward compatibility.
5. Update the relevant documentation.

Do not document a field merely because it would be useful.

The implementation must support the documented request structure.

---

## Documentation Rules

Keep documentation focused.

Each document has a specific purpose:

| File | Purpose |
|---|---|
| `docs/Introduction.md` | What the project is and what it does |
| `docs/Architecture.md` | How the backend is structured |
| `docs/API.md` | HTTP API usage |
| `docs/JSON-Request-Reference.md` | JSON request contract |
| `docs/Query-Examples.md` | Practical requests |
| `docs/Database-Configuration.md` | Database setup |
| `docs/Hosting.md` | Running and deploying the backend |
| `docs/Roadmap.md` | Future backend work |
| `CHANGELOG.md` | Released changes |

Avoid copying the same explanation into multiple files.

Instead, link to the document that owns the topic.

---

## Adding a New Feature

For a new backend feature:

### 1. Understand the Current Flow

Check:

```text
Request
  |
  v
Controller
  |
  v
Validation
  |
  v
Repository / Query Builder
  |
  v
Execution
  |
  v
Database
```

### 2. Implement It in the Correct Layer

Avoid shortcuts that mix responsibilities.

### 3. Test Existing Behavior

Make sure existing requests continue working.

### 4. Add Examples

If the feature changes the JSON request format, add a practical example.

### 5. Update Documentation

Update only the documents affected by the change.

### 6. Update the Changelog

Add the change under the appropriate release or `Unreleased` section.

---

## Commit Messages

Keep commits short and focused.

Good examples:

```text
feat: add update query support
```

```text
feat: add transaction support
```

```text
fix: handle SQL Server pagination correctly
```

```text
fix: improve ODBC driver detection
```

```text
docs: update database configuration
```

```text
docs: document Windows runtime
```

Avoid vague messages such as:

```text
update
```

```text
changes
```

```text
final
```

---

## Branches

Use a separate branch for a feature or fix.

Examples:

```text
feature/crud-support
feature/transaction-support
feature/api-authentication
fix/odbc-driver-detection
fix/pagination
docs/update-hosting
```

Keep unrelated changes out of the same branch.

---

## Pull Requests

A pull request should clearly explain:

### What Changed

Describe the actual implementation.

### Why

Explain the problem being solved.

### Testing

Mention what was tested and the environment used.

Example:

```text
Tested on:
- Windows 11
- Bundled PHP runtime
- PHP ODBC
- SQL Server Express
- ODBC Driver 18
```

---

## Testing Database Features

When possible, test against a real SQL Server instance.

For query changes, test both successful and failure cases.

Example:

```text
Valid request
      |
      v
Expected result

Invalid request
      |
      v
Expected validation error

Database failure
      |
      v
Expected database error
```

---

## Handling Errors

Do not hide database or query failures just to make a request appear successful.

Errors should:

- Be detected
- Be logged where appropriate
- Be returned through the API's error handling
- Avoid exposing sensitive database credentials

---

## Performance Changes

If a change is intended to improve performance, test the behavior before and after the change where possible.

Useful measurements include:

```text
Execution time
Rows returned
Number of database operations
```

Avoid optimizing by bypassing validation or security checks.

---

## Before Committing

Run through this checklist:

```text
[ ] Feature works
[ ] Existing functionality still works
[ ] Error handling checked
[ ] Database behavior checked
[ ] No credentials committed
[ ] No generated files committed
[ ] Documentation updated
[ ] Changelog updated when required
[ ] Commit message describes the change
```

---

## Reporting a Bug

Include as much useful technical information as possible:

- Framework version
- Operating system
- PHP version
- SQL Server version
- ODBC driver version
- Request body
- Expected result
- Actual result
- Error message
- Relevant log information

Remove passwords and other sensitive information before sharing logs or configuration.

---

## Feature Requests

For a new feature, describe:

1. The problem.
2. The proposed behavior.
3. The expected API/request format.
4. Any database-specific requirements.
5. Whether it changes the existing API contract.

Check the [Roadmap](docs/Roadmap.md) before proposing functionality that may already be planned.

---

## Scope

Contributions should remain relevant to the backend project.

This repository focuses on:

- API
- Query engine
- SQL generation
- Database connectivity
- Database execution
- Validation
- Metadata
- Logging
- Performance
- Security
- Deployment

Frontend UI, dashboards, charts, and report screens belong to consuming applications and should not be added to this repository.

---

## License

By contributing to this project, you agree that your contributions will be licensed under the project's MIT License.