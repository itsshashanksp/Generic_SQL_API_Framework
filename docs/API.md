# API Reference

## Overview

The Generic SQL API Framework exposes an HTTP API that allows client applications to communicate with Microsoft SQL Server through a standardized JSON interface.

The API acts as the middleware layer between the frontend/application and the database.

Client applications never need to communicate directly with SQL Server.

---

# Base URL

The base URL depends on the hosting environment.

## Windows Bundled Runtime

When using:

start-windows.bat

the launcher displays the actual API URL and selected port.

Example:

http://localhost:8000/

The port may change automatically if the default port is already in use.

## Existing Web Server

Example:

http://localhost/generic-sql-api-framework/api/

## Production

Example:

https://api.example.com/

---

# Communication

| Property | Value |
|---|---|
| Protocol | HTTP / HTTPS |
| Data Format | JSON |
| Character Encoding | UTF-8 |
| API Style | REST-style |

---

# HTTP Method

Current API:

POST

Additional HTTP methods may be introduced in future releases.

---

# Request Headers

Required:

Content-Type: application/json

Recommended:

Accept: application/json

---

# Query Endpoint

Current query endpoint:

POST /query/select.php

The endpoint accepts a JSON request describing the required query.

The request is validated before SQL generation and execution.

For the complete request structure, see:

JSON-Request-Reference.md

---

# Request Flow

Client

↓

HTTP POST

↓

/query/select.php

↓

Request Validation

↓

SQL Builder

↓

Query Engine

↓

SQL Server

↓

JSON Response

---

# Response Format

Successful responses use a standardized structure.

Example:

{
    "success": true,
    "message": "Data Loaded Successfully",
    "data": []
}

Error responses contain:

{
    "success": false,
    "message": "Error message"
}

The exact response fields may depend on the operation.

---

# HTTP Status Codes

| Code | Meaning |
|---|---|
| 200 | Request completed successfully |
| 400 | Invalid request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Resource not found |
| 405 | Method not allowed |
| 500 | Internal server error |

Authentication-related status codes are reserved for future authentication features.

---

# Current API Scope

The current API focuses on dynamic SQL query operations.

Supported query capabilities include:

- SELECT
- WHERE
- GROUP BY
- HAVING
- ORDER BY
- JOIN
- Pagination
- SQL functions

Supported JOIN types include:

- INNER JOIN
- LEFT JOIN
- RIGHT JOIN

---

# Pagination

Pagination is requested through the JSON request.

Example:

{
    "page": 1,
    "pageSize": 25
}

The database layer handles SQL Server pagination compatibility.

Modern SQL Server environments can use modern pagination.

Older SQL Server compatibility levels can use the ROW_NUMBER() fallback where OFFSET/FETCH is unavailable.

The frontend does not need to change its request format.

---

# Authentication

The current version does not require API authentication.

Future versions may introduce:

- API Keys
- JWT
- OAuth
- Role-Based Access Control

---

# Metadata

Metadata endpoints are planned for future releases.

---

# Versioning

Current framework version:

v1.0

Future API versions may introduce versioned endpoints while attempting to maintain backward compatibility.

---

# Client Compatibility

The API can be consumed by any application capable of sending HTTP requests.

Examples:

- React
- Angular
- Vue
- Flutter
- React Native
- Android
- iOS
- PHP
- Python
- Java
- .NET
- Node.js
- Desktop applications
- REST clients

---

# Related Documentation

For request structure:

JSON-Request-Reference.md

For practical examples:

Query-Examples.md

For database configuration:

Database-Configuration.md

For deployment:

Hosting.md

For architecture:

Architecture.md