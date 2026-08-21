# Introduction

## Overview

Generic SQL API Framework is a lightweight, modular, and configurable PHP framework designed to simplify the development of SQL-based APIs for Microsoft SQL Server.

Instead of writing custom SQL queries and API endpoints for every project, the framework provides a reusable backend engine that dynamically builds SQL queries from JSON requests, validates user input, executes parameterized SQL statements, and returns standardized JSON responses.

The framework is intended to reduce repetitive backend development while promoting clean architecture, code reusability, security, and maintainability.

---

# Purpose

Modern business applications often require multiple APIs that perform similar database operations. Developing these APIs individually leads to duplicated code, inconsistent implementations, and increased maintenance overhead.

The Generic SQL API Framework addresses this by providing a generic query engine capable of generating SQL dynamically from structured JSON requests.

This allows developers to focus on application logic rather than repeatedly implementing backend query operations.

---

# Goals

The primary goals of the framework are:

- Simplify SQL API development.
- Reduce repetitive backend code.
- Provide a configurable database engine.
- Provide reusable SQL query generation.
- Improve maintainability through modular architecture.
- Standardize API request and response formats.
- Ensure secure database access using parameterized queries.
- Enable integration with any frontend technology.

---

# Design Philosophy

## Modularity

Each component has a single responsibility. Validation, query generation, database access, logging, and configuration are separated into independent modules.

## Reusability

The same framework can be used across multiple projects without modifying its core components. Configuration files determine how the framework connects to databases and executes queries.

## Security

Database interactions use parameterized statements to reduce SQL injection risk. Requests are validated before query generation, and database access is centralized through the Database Engine.

## Flexibility

The framework accepts structured JSON requests instead of hardcoded SQL statements. This allows APIs to adapt to different reporting and data retrieval requirements while keeping the backend generic.

## Scalability

The architecture uses provider-based database drivers, allowing additional database systems to be introduced without changing the core framework.

---

# Database Connectivity

The current database implementation is designed for Microsoft SQL Server through ODBC.

The SQL Server driver supports:

- Automatic ODBC driver detection
- Newer and older supported ODBC driver generations
- SQL Server Native Client / legacy SQL Server ODBC driver names where installed
- Named SQL Server instances
- Explicit TCP ports
- SQL Authentication
- Windows Authentication
- Encryption configuration
- Trust Server Certificate configuration

The database configuration is stored in:

database/config/database.json

---

# SQL Server Compatibility

Pagination is implemented with SQL Server compatibility in mind.

When the database supports modern pagination, the driver can use the modern implementation.

For older SQL Server compatibility levels where OFFSET/FETCH is unavailable, the framework can fall back to ROW_NUMBER() pagination.

This keeps the JSON API contract independent of the SQL Server pagination syntax used internally.

---

# Windows Runtime

The framework provides an optional prebuilt PHP runtime for Windows.

When using it, PHP does not need to be installed manually.

The runtime is located under:

runtime/windows/php/

Start the framework with:

start-windows.bat

The launcher checks the PHP runtime, PHP configuration, ODBC extension, database connection, API directory, and HTTP port before starting the API.

It also creates the required OPcache and log directories automatically.

The default port starts at 8000, with automatic fallback through 8100 if required.

Users who already have Apache, IIS, Nginx, XAMPP, WAMP, Docker, or another PHP environment can continue using that environment instead.

---

# Use Cases

The Generic SQL API Framework is suitable for:

- Business Management Systems
- Enterprise Resource Planning (ERP)
- Point of Sale (POS) Systems
- Inventory Management
- Reporting Systems
- Dashboard Applications
- Mobile Applications
- Internal Business Tools
- Data Analytics Platforms
- Administrative Portals

---

# Benefits

Using the Generic SQL API Framework provides:

- Faster API development
- Reduced duplicate code
- Centralized database access
- Consistent JSON responses
- Improved application security
- Easier maintenance
- Configurable database connections
- Frontend-independent architecture
- Clean and extensible project structure

---

# Project Vision

The long-term vision is to provide a reusable provider-based backend platform that simplifies database-driven application development.

The framework is intended to support multiple database systems and provide reusable components for reporting, dashboards, CRUD operations, and enterprise application development while maintaining a modular and extensible architecture.

---

# Getting Started

After configuring the database, the framework can accept JSON requests from any frontend application.

Before connecting a frontend, make sure its URL is included in the CORS allowlist in api/index.php.

For example:

$allowed_origins = [
    "http://127.0.0.1:5173",
    "http://localhost:3000",
    "https://your-frontend.com"
];

For deployment instructions, see Hosting.md.

For request syntax, see JSON-Request-Reference.md.