# Generic SQL API Framework

Generic SQL API Framework is a lightweight, JSON-driven PHP framework for building dynamic Microsoft SQL Server APIs.

Instead of creating a separate API and SQL query for every report or module, the framework accepts structured JSON requests, validates them, dynamically generates SQL, executes parameterized queries, and returns standardized JSON responses.

It is designed for reporting systems, dashboards, ERP applications, POS software, inventory systems, CRM solutions, and other business applications.

---

# Core Features

- Dynamic SQL query generation
- JSON-driven request processing
- Parameterized SQL execution
- Request validation
- JOIN support
- Filtering
- Grouping
- Sorting
- Pagination
- SQL function support
- Query execution statistics
- Centralized logging
- Standardized JSON responses
- Provider-based database architecture

---

# Database Support

The current database provider is:

Microsoft SQL Server

SQL Server connectivity uses ODBC.

The SQL Server driver supports automatic detection of compatible installed Microsoft SQL Server ODBC drivers and multiple driver generations.

It also supports:

- SQL Authentication
- Windows Authentication
- SQL Server named instances
- Explicit SQL Server ports
- Encryption configuration
- Trust Server Certificate configuration

---

# Windows Runtime

The project provides a prebuilt PHP runtime for Windows.

When using the bundled runtime, PHP does not need to be installed manually.

Start the framework with:

start-windows.bat

The launcher performs the required environment and database checks and automatically selects an available HTTP port.

The default port starts at:

8000

The launcher can automatically search for an available port through:

8100

---

# Existing PHP Environments

The bundled Windows runtime is optional.

The framework can also be deployed using an existing:

- Apache
- Microsoft IIS
- Nginx + PHP-FPM
- XAMPP
- WAMP
- Docker
- PHP environment

The framework does not depend on XAMPP.

---

# Quick Start

## Windows Bundled Runtime

Run:

start-windows.bat

## Existing PHP Environment

Configure your PHP/web server and expose the project's API directory through the web server.

Then configure:

database/config/database.json

---

# Documentation

| Document | Purpose |
|---|---|
| docs/Introduction.md | What the framework is and why it exists |
| docs/Architecture.md | Internal framework architecture |
| docs/API.md | HTTP API endpoints and communication |
| docs/JSON-Request-Reference.md | JSON request structure |
| docs/Query-Examples.md | Practical request examples |
| docs/Database-Configuration.md | Database and ODBC configuration |
| docs/Hosting.md | Hosting and deployment |
| docs/Roadmap.md | Planned development |
| CHANGELOG.md | Project history and changes |
| CONTRIBUTING.md | Contribution guidelines |

Start with:

docs/Introduction.md

---

# Project Status

Current release:

v1.0

The framework currently focuses on dynamic SQL Server SELECT/reporting APIs.

Future development will expand the framework with additional database operations, security, reporting, export, and other enterprise features.

---

# License

This project is licensed under the MIT License.

See:

LICENSE