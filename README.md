# Generic SQL API Framework

## Overview

Generic SQL API Framework is a lightweight, modular, and configurable PHP framework designed to simplify the development of SQL-based APIs for Microsoft SQL Server.

Instead of writing custom SQL queries and API endpoints for every project, the framework provides a reusable backend engine that dynamically builds SQL queries from JSON requests, validates user input, executes parameterized SQL statements, and returns standardized JSON responses.

The framework is intended to reduce repetitive backend development while promoting clean architecture, code reusability, security, and maintainability.

---

# Features

- Dynamic SQL Query Engine
- Validation Engine
- SQL Builder
- Metadata Engine
- Repository Pattern
- Centralized Logging
- Query Execution Statistics
- Standardized JSON Responses
- Global Exception Handling
- Configurable database connections
- Automatic SQL Server ODBC driver detection
- Support for newer and older SQL Server ODBC driver generations
- SQL Server named-instance support
- Explicit SQL Server port support
- SQL Authentication
- Windows Authentication
- SQL Server capability-aware pagination
- Windows portable PHP runtime
- Automatic Windows startup checks
- Automatic port selection
- Frontend-independent API

---

# Windows Portable PHP Runtime

The framework provides a prebuilt PHP runtime for Windows.

When using the bundled runtime, Windows users do not need to install PHP manually.

The runtime is located at:

runtime/
└── windows/
    └── php/

Start the API with:

start-windows.bat

The startup script automatically:

1. Checks the PHP runtime.
2. Checks the PHP configuration.
3. Creates the required OPcache directory.
4. Creates the log directory.
5. Verifies the PHP ODBC extension.
6. Tests the database connection.
7. Verifies the API directory.
8. Finds an available HTTP port.
9. Starts the API.

The launcher starts at port 8000. If that port is already in use, it automatically searches for another available port up to 8100.

If the database connection fails, startup is stopped and the user is instructed to configure:

database/config/database.json

and refer to the documentation.

## Existing Hosting Environments

The bundled Windows runtime is optional.

The framework can also be deployed using an existing PHP/web-server environment such as:

- Apache
- Microsoft IIS
- Nginx + PHP-FPM
- XAMPP
- WAMP
- Docker
- PHP built-in development server

When using an existing environment, PHP, the required PHP extensions, and the Microsoft SQL Server ODBC driver must be installed and configured separately.

> Linux runtime packaging is not covered by the current bundled runtime setup.

---

# SQL Server ODBC Support

The SQL Server driver supports automatic ODBC driver detection.

Configure:

{
    "driver": "auto"
}

The driver checks compatible SQL Server ODBC driver names from newer to older generations, including Microsoft ODBC Driver releases and SQL Server Native Client / legacy SQL Server ODBC driver names where installed.

This allows the framework to work with environments that have different supported SQL Server ODBC driver versions installed.

The driver also supports:

- SQL Server named instances
- Explicit TCP ports
- SQL Authentication
- Windows Authentication
- Encryption configuration
- Trust Server Certificate configuration

---

# Pagination Compatibility

The framework supports SQL Server pagination across different SQL Server compatibility levels.

Where modern pagination is available, the framework can use the modern pagination mechanism.

For older SQL Server compatibility levels where OFFSET/FETCH is not supported, the framework can use a ROW_NUMBER() pagination fallback.

This keeps the API request format consistent while allowing the database layer to select a compatible implementation.

---

# Getting Started

1. Clone or download the framework.
2. Configure database/config/database.json.
3. Make sure SQL Server is running.
4. Make sure a compatible SQL Server ODBC driver is available.
5. On Windows, run:

start-windows.bat

6. Use the API URL printed by the launcher.

For request formats, database configuration, architecture, and hosting information, see the documentation in docs/.

---

# Documentation

- docs/Introduction.md
- docs/Architecture.md
- docs/API.md
- docs/JSON-Request-Reference.md
- docs/Database-Configuration.md
- docs/Hosting.md
- docs/Roadmap.md
- CHANGELOG.md

---

# Project Vision

The long-term vision of Generic SQL API Framework is to become a flexible, provider-based SQL API framework capable of supporting multiple relational database systems while maintaining a consistent development experience.

Future releases will introduce additional database providers, CRUD operations, authentication, export capabilities, and advanced query features without requiring significant changes to existing applications.