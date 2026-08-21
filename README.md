# Generic SQL API Framework

A reusable PHP backend framework for building database APIs using structured JSON requests.

The framework handles request processing, validation, SQL query construction, database execution, and JSON responses.

The current database implementation is focused on **Microsoft SQL Server through ODBC**.

---

## What It Does

Instead of creating separate backend code for every database query, the API accepts a structured JSON request and builds the required query through the backend query layer.

Basic flow:

```text
Client
  |
  | JSON Request
  v
Generic SQL API
  |
  +-- Request Validation
  |
  +-- Query Builder
  |
  +-- Query Execution
  |
  +-- Database Layer
  |
  v
SQL Server
```

Example request:

```json
{
  "controller": "Query",
  "action": "select",
  "table": "CustomerTable",
  "columns": [
    "Cust_Name",
    "Phone"
  ]
}
```

The frontend or client only needs to send the request. The backend handles the SQL generation and execution.

---

## Current Features

### API

- JSON-based requests
- Controller/action handling
- JSON responses
- Request validation
- CORS handling
- Error handling

### Query Engine

- SELECT queries
- WHERE conditions
- JOINs
- GROUP BY
- HAVING
- ORDER BY
- Pagination
- Column aliases
- Table aliases
- SQL expressions
- SQL functions
- Prepared query execution
- Multiple-result execution
- SQL file execution
- Query execution statistics

### Database

- Microsoft SQL Server
- ODBC connectivity
- SQL Server authentication
- Windows authentication
- Automatic ODBC driver selection
- Specific ODBC driver selection
- Database metadata access
- Database connection validation

### Runtime

Windows includes a prebuilt PHP runtime.

```text
runtime/
└── windows/
    └── php/
```

Therefore, a Windows deployment does not require a separate PHP installation when using the bundled runtime.

---

## Windows Quick Start

### 1. Configure the Database

Create:

```text
database/config/database.json
```

Example:

```json
{
  "provider": "sqlserver",
  "driver": "auto",
  "server": "localhost\\SQLEXPRESS",
  "database": "TestDB",
  "authentication": "windows",
  "port": 1433,
  "options": {
    "encrypt": false,
    "trustServerCertificate": true
  }
}
```

See:

[Database Configuration](docs/Database-Configuration.md)

---

### 2. Start the API

Run from the project root:

```bat
start-windows.bat
```

The startup script automatically checks:

```text
PHP Runtime
PHP Configuration
Runtime Directories
PHP ODBC
Database Configuration
Database Connection
API Directory
Available Port
```

Required runtime directories are also created automatically.

---

### 3. API Starts

The default port starts from:

```text
8000
```

If the port is already being used, the script automatically searches for another available port up to:

```text
8100
```

The selected API URL is printed in the console.

Example:

```text
========================================
              API Ready
========================================

API: http://localhost:8000
```

---

## Windows Runtime

The bundled runtime is intended to make Windows deployment simpler.

You do **not** need to install:

```text
PHP
XAMPP
WAMP
```

just to run the API with the bundled runtime.

The project can still be hosted using an existing PHP environment such as:

```text
Apache
IIS
Nginx
XAMPP
```

when required by the deployment.

The bundled runtime is currently provided for Windows.

Linux runtime packaging is planned for a future release.

See:

[Hosting](docs/Hosting.md)

---

## Database Connectivity

The current database connection flow is:

```text
Generic SQL API
      |
      v
PHP ODBC Extension
      |
      v
SQL Server ODBC Driver
      |
      v
Microsoft SQL Server
```

The API does not depend on only one fixed SQL Server ODBC driver version.

The configuration can use:

```json
"driver": "auto"
```

or specify an installed driver explicitly.

For example:

```json
"driver": "ODBC Driver 18 for SQL Server"
```

The required ODBC driver must be installed on the host system.

See:

[Database Configuration](docs/Database-Configuration.md)

---

## Database Startup Check

The Windows launcher verifies the database connection before starting the API.

Successful:

```text
Checking database connection...

[OK] Database Connected
```

Failed:

```text
[FAILED] Database connection failed.
```

If the connection fails, the API startup is aborted.

The database configuration file is:

```text
database/config/database.json
```

---

## API Example

A basic request:

```http
POST /api/index.php
Content-Type: application/json
```

```json
{
  "controller": "Query",
  "action": "select",
  "table": "CustomerTable",
  "columns": [
    "Cust_Name",
    "Phone"
  ]
}
```

The API returns JSON containing the result.

Example:

```json
{
  "success": true,
  "data": [
    {
      "Cust_Name": "ABC Traders",
      "Phone": "9876543210"
    }
  ]
}
```

See:

[API Reference](docs/API.md)

---

## Query Example

Filtering:

```json
{
  "controller": "Query",
  "action": "select",
  "table": "CustomerTable",
  "columns": [
    "Cust_Name",
    "City"
  ],
  "where": [
    {
      "left": {
        "column": "City"
      },
      "operator": "=",
      "right": "Bangalore"
    }
  ]
}
```

For more examples:

[Query Examples](docs/Query-Examples.md)

For the complete JSON request structure:

[JSON Request Reference](docs/JSON-Request-Reference.md)

---

## Project Structure

The main project areas are:

```text
Generic SQL API
│
├── api/
│   └── index.php
│
├── database/
│   └── config/
│       └── database.json
│
├── docs/
│
├── runtime/
│   └── windows/
│       └── php/
│
├── scripts/
│
├── logs/
│
├── start-windows.bat
│
├── CONTRIBUTING.md
├── CHANGELOG.md
└── README.md
```

Generated and local files such as database credentials, logs, and runtime cache should not be committed.

---

## Architecture

The backend is separated into layers:

```text
HTTP/API
   |
   v
Controller
   |
   v
Validation
   |
   v
Query Repository
   |
   v
Query Builder
   |
   v
Query Engine
   |
   v
Database Layer
   |
   v
ODBC
   |
   v
SQL Server
```

More details:

[Architecture](docs/Architecture.md)

---

## Documentation

| Document | Purpose |
|---|---|
| [Introduction](docs/Introduction.md) | Project overview and scope |
| [Architecture](docs/Architecture.md) | Backend structure and internal flow |
| [API](docs/API.md) | HTTP API usage |
| [JSON Request Reference](docs/JSON-Request-Reference.md) | JSON request fields and structure |
| [Query Examples](docs/Query-Examples.md) | Practical API requests |
| [Database Configuration](docs/Database-Configuration.md) | SQL Server and ODBC configuration |
| [Hosting](docs/Hosting.md) | Running and deploying the backend |
| [Roadmap](docs/Roadmap.md) | Planned backend features |
| [Contributing](CONTRIBUTING.md) | Development and contribution guidelines |
| [Changelog](CHANGELOG.md) | Version history |

---

## Security

Do not commit production database credentials.

Keep:

```text
database/config/database.json
```

out of Git when it contains real credentials.

For production deployments:

- Use HTTPS.
- Restrict CORS origins.
- Protect database credentials.
- Do not expose SQL Server directly to the Internet.
- Use appropriate authentication.
- Keep PHP and ODBC components updated.
- Monitor application logs.
- Maintain database backups.

---

## Project Scope

This repository is focused on the **backend database API**.

### Included

- Backend API
- JSON request processing
- SQL query generation
- Query execution
- Database connectivity
- Database validation
- Database metadata
- ODBC integration
- Logging
- Query statistics
- Runtime/deployment support

### Not Included

This repository does not provide:

- Frontend UI
- Dashboards
- Charts
- Reporting screens
- Frontend routing
- Frontend state management
- Website design

These are handled by applications that consume the API.

---

## Roadmap

Planned backend work includes:

```text
CRUD
  |
  v
Transactions
  |
  v
Advanced SQL
  |
  v
Database Metadata
  |
  v
API Security
  |
  v
Performance
  |
  v
Additional Database Providers
  |
  v
Linux / Deployment Improvements
```

See:

[Roadmap](docs/Roadmap.md)

---

## Contributing

Contributions are welcome.

Before making changes, read:

[Contributing Guide](CONTRIBUTING.md)

Keep backend layers separated and update the relevant documentation when changing the API contract.

---

## Changelog

See:

[CHANGELOG.md](CHANGELOG.md)

---

## License

This project is licensed under the MIT License.