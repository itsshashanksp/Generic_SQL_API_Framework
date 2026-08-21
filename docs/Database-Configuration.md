# Database Configuration

## Overview

Generic SQL API Framework uses a JSON configuration file to manage database connectivity.

Database configuration is separated from application code so connection details can be changed without modifying the framework.

---

# Configuration File

Location:

database/config/database.json

---

# Configuration Structure

{
    "provider": "sqlserver",
    "driver": "auto",
    "server": "localhost\\SQLEXPRESS",
    "database": "Database-Name",
    "authentication": "sql",
    "username": "sa",
    "password": "password",
    "port": 1433,
    "options": {
        "encrypt": false,
        "trustServerCertificate": true
    }
}

---

# Configuration Properties

| Property | Required | Description |
|---|---|---|
| provider | Yes | Database provider |
| driver | Yes | ODBC driver name or auto |
| server | Yes | SQL Server hostname, IP, or instance |
| database | Yes | Database name |
| authentication | Yes | Authentication mode |
| username | SQL Authentication | SQL username |
| password | SQL Authentication | SQL password |
| port | No | SQL Server TCP port |
| options | No | Connection options |

---

# Database Provider

Current provider:

sqlserver

The provider determines which database driver is loaded.

The provider-based architecture is designed to allow additional database providers in future releases.

---

# ODBC Driver

## Automatic Detection

Recommended:

{
    "driver": "auto"
}

When auto is selected, the SQL Server driver attempts to detect a compatible installed ODBC driver.

The driver checks supported Microsoft SQL Server ODBC driver generations from newer to older.

Supported driver names currently include:

ODBC Driver 19 for SQL Server
ODBC Driver 18 for SQL Server
ODBC Driver 17 for SQL Server
ODBC Driver 13.1 for SQL Server
ODBC Driver 13 for SQL Server
ODBC Driver 11 for SQL Server
ODBC Driver 10 for SQL Server
SQL Server Native Client 11.0
SQL Server Native Client 10.0
SQL Server Native Client 9.0
SQL Native Client
SQL Server

Not every system will have every driver installed.

The framework tries the available compatible drivers and reports the connection errors if none can establish a connection.

---

# Manual Driver Selection

A specific installed driver can be selected manually.

Example:

{
    "driver": "ODBC Driver 18 for SQL Server"
}

Use manual selection when a specific driver version is required.

---

# Server Configuration

## Local SQL Server

{
    "server": "localhost"
}

## SQL Express Named Instance

{
    "server": "localhost\\SQLEXPRESS"
}

## Remote SQL Server

{
    "server": "192.168.1.100"
}

## Named SQL Server

{
    "server": "SERVER01"
}

---

# Port

An explicit SQL Server TCP port can be configured.

Example:

{
    "port": 1433
}

The driver combines the configured server and port when building the connection target.

Port 1433 is commonly used for SQL Server TCP connections, but the framework does not require that specific port.

---

# Database

Example:

{
    "database": "LOYAL_IBMS"
}

The database must already exist and the configured login must have permission to access it.

---

# Authentication

The SQL Server driver supports:

sql

windows

---

## SQL Authentication

Example:

{
    "authentication": "sql",
    "username": "sa",
    "password": "password"
}

The connection uses SQL Server login credentials.

---

## Windows Authentication

Example:

{
    "authentication": "windows"
}

The connection uses the Windows account running the PHP process.

Username and password are not used for Windows Authentication.

---

# Connection Options

The options object contains connection-specific settings.

Example:

{
    "options": {
        "encrypt": false,
        "trustServerCertificate": true
    }
}

## encrypt

Controls whether the connection requests encryption.

## trustServerCertificate

Controls whether the SQL Server certificate is trusted without normal certificate-chain validation.

Production environments should use certificate settings appropriate to their security requirements.

---

# Configuration Loading

During startup:

database.json
      |
      v
Provider
      |
      v
Driver
      |
      v
Authentication
      |
      v
Connection

The database connection is then made available to the Database Engine.

---

# Database Connection Test

The Windows startup script performs a database connection test before starting the API.

If the connection fails:

API startup is aborted

The user is instructed to configure:

database/config/database.json

---

# Troubleshooting

## Database Not Found

Verify:

- Database name
- Database existence
- Database permissions

## Login Failed

Verify:

- Username
- Password
- Authentication mode
- Database permissions

## Server Not Found

Verify:

- SQL Server service
- Server name
- Instance name
- TCP/IP configuration
- Port

## ODBC Driver Not Found

Verify that a supported SQL Server ODBC driver is installed.

With:

{
    "driver": "auto"
}

the framework attempts compatible installed driver versions automatically.

## Cannot Open Database

If master connects but the selected database does not, verify that the login has access to the selected database.

---

# Security

Do not commit production credentials to source control.

Protect:

database/config/database.json

Use secure credentials and Windows Authentication where appropriate.

---

# Related Documentation

For deployment:

Hosting.md

For the framework's database architecture:

Architecture.md

For API request syntax:

JSON-Request-Reference.md