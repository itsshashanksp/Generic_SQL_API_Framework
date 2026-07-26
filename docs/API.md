# API Reference

## Overview

The Generic SQL API Framework exposes a REST-style HTTP API that allows client applications to communicate with Microsoft SQL Server through a standardized interface.

Instead of allowing applications to connect directly to the database, the framework receives HTTP requests, validates incoming data, executes SQL operations, and returns standardized JSON responses.

The API acts as a secure middleware layer between client applications and the database.

---

# API Architecture

```text
Client Application
        │
        ▼
HTTP Request (JSON)
        │
        ▼
Generic SQL API Framework
        │
        ▼
Microsoft SQL Server
        │
        ▼
HTTP Response (JSON)
```

---

# Base URL

Development

```
http://localhost/generic-sql-api-framework/backend/api/
```

Production

```
https://your-domain.com/api/
```

---

# Communication Protocol

| Property | Value |
|----------|-------|
| Protocol | HTTP / HTTPS |
| Data Format | JSON |
| Character Encoding | UTF-8 |
| API Style | REST-style |

---

# HTTP Method

Current Version

| Method | Status |
|---------|--------|
| POST | Supported |

Future Versions

| Method | Status |
|---------|--------|
| GET | Planned |
| PUT | Planned |
| DELETE | Planned |

---

# Request Headers

Required

```http
Content-Type: application/json
```

Recommended

```http
Accept: application/json
```

---

# Authentication

Version 1 does not require authentication.

Future versions will support:

- API Keys
- JWT Authentication
- OAuth 2.0
- Role-Based Access Control (RBAC)

---

# Available Endpoints

## Query Endpoint

Executes SQL queries.

```
POST /query/select.php
```

Description

Processes JSON requests and executes SELECT queries.

Reference

See **JSON-Request-Reference.md** for the complete request format.

---

## Metadata Endpoint

Returns database metadata.

```
GET /metadata/
```

Status

Planned

---

## Database Endpoint

Database management and provider information.

```
POST /database/
```

Status

Planned

---

## Authentication Endpoint

Authentication services.

```
POST /auth/
```

Status

Planned

---

# HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Request completed successfully |
| 400 | Invalid request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Resource not found |
| 405 | Method not allowed |
| 500 | Internal server error |

---

# API Versioning

Current Version

```
v1.0
```

Future versions may introduce additional endpoints while maintaining backward compatibility whenever possible.

---

# Supported Client Applications

The API can be consumed by any application capable of making HTTP requests, including:

- React
- Angular
- Vue.js
- Flutter
- React Native
- Android
- iOS
- PHP
- Python
- Java
- .NET
- Node.js
- Desktop Applications
- Third-Party Systems
- REST Clients

---

# Related Documentation

- Introduction.md
- Architecture.md
- JSON-Request-Reference.md
- Database-Configuration.md
- Hosting.md
- Roadmap.md
- CHANGELOG.md

---

# Summary

The Generic SQL API Framework exposes a standardized HTTP API for executing database operations. Applications communicate with the framework using HTTP requests containing JSON payloads, while the framework manages validation, query execution, and response generation. The detailed request structure and supported JSON properties are documented separately in **JSON-Request-Reference.md**.