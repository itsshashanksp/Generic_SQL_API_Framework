# Hosting & Deployment Guide

## Overview

Generic SQL API Framework is a PHP-based backend framework that runs between client applications and Microsoft SQL Server.

This document explains how to run and deploy the framework.

For database connection settings, see:

Database-Configuration.md

---

# Windows - Bundled PHP Runtime

The project provides a prebuilt PHP runtime for Windows.

This allows Windows users to run the framework without manually installing PHP.

Runtime location:

runtime/
└── windows/
    └── php/

---

# Windows Startup

From the project root, run:

start-windows.bat

The startup script checks:

1. PHP runtime
2. PHP configuration
3. Runtime directories
4. PHP ODBC extension
5. Database connection
6. API directory
7. Available HTTP port

If all checks pass, the API starts automatically.

---

# Runtime Directories

The startup script automatically creates required directories when they do not exist.

OPcache:

runtime/windows/php/opcache/

Logs:

logs/

No manual directory creation is required.

---

# Automatic Port Selection

The Windows launcher starts checking from:

8000

If the port is already in use, it checks the next port.

The maximum automatic port is:

8100

Example:

8000 -> occupied
8001 -> occupied
8002 -> available

The selected API address is displayed in the terminal.

---

# Database Startup Check

Before starting the API, the Windows launcher runs the database connection check.

If the connection succeeds:

[OK] Database Connected

The API starts.

If the connection fails:

[FAILED] Database connection failed.

The API startup is aborted and the user is instructed to configure:

database/config/database.json

and refer to the documentation.

---

# Existing PHP Environments

The bundled Windows runtime is optional.

The framework can also be deployed using an existing PHP environment.

Supported environments include:

- Apache
- Microsoft IIS
- Nginx + PHP-FPM
- XAMPP
- WAMP
- Docker
- PHP built-in development server

When using an existing environment, PHP and the required PHP extensions must be installed manually.

The Microsoft SQL Server ODBC driver must also be available.

---

# Apache / XAMPP Example

Example project location:

htdocs/
└── generic-sql-api-framework/
    ├── api/
    ├── core/
    ├── database/
    ├── docs/
    ├── README.md
    └── LICENSE

The web server should expose the API directory through the appropriate application URL.

---

# IIS

For Microsoft IIS:

1. Install PHP for IIS.
2. Configure PHP through FastCGI.
3. Enable the required PHP extensions.
4. Configure the application/site.
5. Point the application to the framework.
6. Configure the database connection.
7. Test the API endpoint.

---

# Nginx + PHP-FPM

For Nginx:

Client
  |
  v
Nginx
  |
  v
PHP-FPM
  |
  v
Generic SQL API
  |
  v
SQL Server

Configure PHP-FPM and the Nginx site according to the server environment.

---

# Production Architecture

A typical production environment is:

Client Applications
        |
      HTTPS
        |
        v
Apache / IIS / Nginx
        |
        v
Generic SQL API
        |
        v
Private SQL Server

The SQL Server should not be exposed directly to the Internet.

---

# Frontend Integration

Any application capable of making HTTP requests can consume the framework.

Examples include:

- React
- Angular
- Vue
- Flutter
- React Native
- Android
- iOS
- Electron
- PHP
- Python
- Java
- .NET
- Node.js
- Desktop applications

No framework-specific frontend SDK is required.

For API endpoints, continue with:

API.md

For request JSON structure, continue with:

JSON-Request-Reference.md

---

# CORS

CORS configuration is handled in:

api/index.php

Example:

$allowed_origins = [
    "http://127.0.0.1:5173",
    "http://localhost:3000"
];

For production:

$allowed_origins = [
    "https://app.company.com",
    "https://portal.company.com"
];

Only trusted frontend origins should be added.

Avoid allowing every origin in production.

---

# Deployment Checklist

Before deployment verify:

- PHP is available, unless using the bundled Windows runtime.
- PHP ODBC is enabled.
- Microsoft SQL Server is available.
- A compatible Microsoft SQL Server ODBC driver is installed.
- database/config/database.json is configured.
- The API directory is accessible.
- CORS is configured where required.
- HTTPS is enabled in production.
- SQL Server is not publicly exposed.
- Database credentials are protected.

---

# Updating

To update the framework:

1. Back up the existing project.
2. Review CHANGELOG.md.
3. Replace/update framework files.
4. Preserve database/config/database.json.
5. Test database connectivity.
6. Test the API.

---

# Troubleshooting

## API Does Not Start

When using Windows:

start-windows.bat

Review the startup checks.

## Database Connection Failed

See:

Database-Configuration.md

## ODBC Driver Missing

Install a compatible Microsoft SQL Server ODBC driver.

## Port Already in Use

The Windows launcher automatically searches from port 8000 through 8100.

## HTTP 500

Check:

- PHP error logs
- Web server logs
- PHP configuration
- File permissions
- Database configuration

---

# Next Documentation

For the API interface:

API.md

For JSON requests:

JSON-Request-Reference.md

For practical examples:

Query-Examples.md

For planned features:

Roadmap.md