# Hosting & Deployment Guide

## Overview

Generic SQL API Framework is a PHP-based backend framework designed to run between client applications and Microsoft SQL Server.

Windows users can use the bundled PHP runtime or an existing PHP/web-server environment.

---

# Windows Prebuilt PHP Runtime

The framework provides a prebuilt PHP runtime for Windows.

This allows Windows users to run the framework without manually installing PHP.

Runtime location:

runtime/
└── windows/
    └── php/

## Start the API

From the project root:

start-windows.bat

The launcher performs these checks:

1. PHP runtime
2. PHP configuration
3. OPcache directory
4. Log directory
5. PHP ODBC extension
6. Database connection
7. API directory
8. Available HTTP port

The default port is:

8000

If it is already occupied, the launcher automatically searches for an available port through:

8100

Example:

8000 -> occupied
8001 -> occupied
8002 -> available

The selected API URL is displayed before the server starts.

## Database Connection Failure

If the database connection fails, the launcher does not start the API.

The user is instructed to configure:

database/config/database.json

and refer to the documentation.

---

# SQL Server and ODBC

The PHP runtime provides PHP ODBC support.

The Microsoft SQL Server ODBC driver remains a system-level requirement.

The SQL Server driver supports automatic detection of compatible installed ODBC drivers when:

{
    "driver": "auto"
}

is configured.

The driver attempts supported newer and older SQL Server ODBC driver names, including SQL Server Native Client / legacy driver names where installed.

The connection layer supports:

- SQL Authentication
- Windows Authentication
- Named SQL Server instances
- Explicit ports
- Encryption options
- Trust Server Certificate options

---

# Database Configuration

Configure:

database/config/database.json

Example:

{
    "provider": "sqlserver",
    "driver": "auto",
    "server": "localhost\\SQLEXPRESS",
    "database": "IBMS",
    "authentication": "sql",
    "username": "sa",
    "password": "password",
    "port": 1433,
    "options": {
        "encrypt": false,
        "trustServerCertificate": true
    }
}

For complete settings, see Database-Configuration.md.

---

# Existing Hosting Environments

The bundled Windows runtime is optional.

The framework can also be deployed using:

- Apache
- Microsoft IIS
- Nginx + PHP-FPM
- XAMPP
- WAMP
- Docker
- PHP built-in development server

When using an existing environment, PHP, PHP ODBC, the Microsoft SQL Server ODBC driver, and the relevant web server must be installed/configured separately.

The framework does not require XAMPP.

---

# Production Deployment

A typical production deployment is:

Client Applications
        |
      HTTPS
        |
        v
Apache / IIS / Nginx
        |
        v
Generic SQL API Framework
        |
        v
Microsoft SQL Server

The SQL Server database should remain on a private network and should not be exposed directly to the Internet.

---

# CORS

Configure trusted frontend origins in:

api/index.php

Example:

$allowed_origins = [
    "http://127.0.0.1:5173",
    "http://localhost:3000",
    "https://your-frontend.com"
];

Only trusted origins should be added in production.

---

# Security Recommendations

- Enable HTTPS in production.
- Keep SQL Server on a private network.
- Do not expose SQL Server directly to the Internet.
- Protect database/config/database.json.
- Use secure database credentials.
- Use Windows Authentication where appropriate.
- Keep PHP and ODBC drivers updated.
- Review application logs.
- Perform regular database backups.

---

# Performance and Compatibility

The SQL Server driver detects compatible ODBC drivers instead of requiring one hard-coded driver version.

The framework also detects SQL Server pagination capability and can use a ROW_NUMBER() fallback for older compatibility levels where OFFSET/FETCH is not supported.

This allows the same JSON request format to work across different SQL Server compatibility environments.

---

# Troubleshooting

## API Does Not Start

Run:

start-windows.bat

and review the startup checks.

## Database Connection Failed

Check:

database/config/database.json

Verify:

- SQL Server is running.
- Server/instance name is correct.
- Port is correct.
- Database exists.
- Credentials are correct.
- A compatible ODBC driver is installed.

## ODBC Driver Not Found

Install a supported Microsoft SQL Server ODBC driver.

With driver: "auto", the framework attempts to detect supported installed driver generations.

## Port Already in Use

The Windows launcher automatically searches for another port between 8000 and 8100.

---

# Updating

1. Back up the existing project.
2. Review CHANGELOG.md.
3. Update the framework files.
4. Preserve database/config/database.json.
5. Test database connectivity.
6. Test the API.

---

# Linux

Linux runtime packaging is intentionally not documented here yet. Linux support can be documented separately when Linux packaging is introduced.