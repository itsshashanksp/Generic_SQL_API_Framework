# Introduction

## What is Generic SQL API Framework?

Generic SQL API Framework is a PHP backend framework for building database APIs without creating a separate backend implementation for every query.

The framework accepts structured JSON requests, validates the request, builds SQL dynamically, executes the query through the database layer, and returns a JSON response.

The current implementation is focused on Microsoft SQL Server through ODBC.

---

## Why This Project Exists

In a traditional application, adding a new query often means:

1. Write the SQL query.
2. Create a backend endpoint.
3. Add request validation.
4. Add database execution code.
5. Format the response.
6. Repeat the same process for the next query.

As the number of queries increases, this creates repeated backend code.

This framework moves the common query work into a reusable backend engine.

Instead of creating a new backend endpoint for every SELECT query, an application can send a structured JSON request describing the required data.

---

## Basic Idea

A request describes the required query using JSON.

Example:

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

The backend processes the request and generates the required SQL.

```text
Client
  |
  v
JSON Request
  |
  v
API Controller
  |
  v
Validation
  |
  v
Query Repository
  |
  v
Query Engine
  |
  v
Database
  |
  v
JSON Response
```

---

## Current Scope

This repository provides the backend API and database layer.

It is responsible for:

- API request handling
- Request validation
- SQL generation
- Database connectivity
- Query execution
- Database metadata access
- Query logging
- Query statistics
- Database-specific connectivity

The frontend is a separate project.

This repository does not provide:

- Dashboard UI
- Chart components
- Report screens
- Frontend routing
- Frontend state management
- Website design

Applications can consume this API from any frontend or client application capable of making HTTP requests.

---

## Database

The current database implementation targets:

**Microsoft SQL Server**

Database connectivity is handled through:

**ODBC**

The database layer is kept separate from the query-building layer so additional database providers can be introduced later.

---

## Main Goals

The project is designed to:

- Reduce repeated backend query code.
- Keep database access centralized.
- Provide a consistent JSON request structure.
- Validate requests before database execution.
- Use prepared SQL execution where applicable.
- Keep database-specific logic isolated.
- Make the backend reusable across different applications.

---

## Typical Use

The API can be used as a backend for:

- ERP systems
- POS systems
- Inventory applications
- Accounting applications
- Courier and logistics applications
- Internal business applications
- MIS applications
- Web applications
- Mobile applications

The consuming application remains responsible for its own user interface.

---

## Windows Runtime

The repository includes a prebuilt Windows PHP runtime.

When using the bundled runtime, PHP does not need to be installed separately on the system.

Start the API from the project root:

```bat
start-windows.bat
```

The startup script checks the runtime, ODBC extension, database connection, required directories, and available port before starting the API.

See [Hosting](Hosting.md).

---

## Documentation

| Document | Purpose |
|---|---|
| [Architecture](Architecture.md) | Internal framework structure and request flow |
| [API](API.md) | HTTP API and endpoint usage |
| [JSON Request Reference](JSON-Request-Reference.md) | JSON request fields and structure |
| [Query Examples](Query-Examples.md) | Practical query examples |
| [Database Configuration](Database-Configuration.md) | Database and ODBC configuration |
| [Hosting](Hosting.md) | Running and deploying the backend |
| [Roadmap](Roadmap.md) | Planned backend versions |
| [Contributing](../CONTRIBUTING.md) | Development and contribution guidelines |
| [Changelog](../CHANGELOG.md) | Release history |

---

## Next

To understand how the framework is structured internally, see [Architecture](Architecture.md).