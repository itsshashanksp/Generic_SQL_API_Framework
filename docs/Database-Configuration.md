# Database Configuration

## Overview

The Generic SQL API Framework uses a JSON-based configuration file to manage database connections. This approach separates database settings from application code, allowing developers to change database providers, connection details, or authentication methods without modifying the framework.

The configuration is loaded automatically during framework initialization.

---

# Configuration File

Location

```
backend/database/config/database.json
```

---

# Configuration Structure

```json
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
```

---

# Configuration Properties

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| provider | String | Yes | Database provider |
| driver | String | Yes | Database driver name or `auto` |
| server | String | Yes | SQL Server hostname or IP |
| database | String | Yes | Database name |
| authentication | String | Yes | Authentication mode |
| username | String | SQL Auth | SQL login username |
| password | String | SQL Auth | SQL login password |
| port | Integer | No | Database port |
| options | Object | No | Additional connection options |

---

# Database Provider

The provider determines which database driver the framework loads.

Current Version

```
sqlserver
```

Planned Providers

```
mysql
postgresql
sqlite
oracle
```

The framework uses a provider-based architecture, making it easy to add support for new database systems without modifying application logic.

---

# Driver Configuration

## Automatic Driver Detection

Recommended

```json
{
    "driver": "auto"
}
```

When `auto` is specified, the framework automatically detects the highest compatible Microsoft ODBC Driver installed on the system.

Example

```
ODBC Driver 18 for SQL Server
```

If Driver 18 is unavailable, the framework automatically falls back to:

```
ODBC Driver 17 for SQL Server
```

---

## Manual Driver Selection

```json
{
    "driver": "ODBC Driver 18 for SQL Server"
}
```

Use manual selection when a specific ODBC version is required.

---

# Server Configuration

Local SQL Express

```json
{
    "server": "localhost\\SQLEXPRESS"
}
```

Named SQL Server

```json
{
    "server": "SERVER01"
}
```

Remote SQL Server

```json
{
    "server": "192.168.1.100"
}
```

---

# Database Configuration

Example

```json
{
    "database": "LOYAL_IBMS"
}
```

The database must already exist and be accessible to the configured user.

---

# Authentication Modes

## SQL Authentication

```json
{
    "authentication": "sql",
    "username": "sa",
    "password": "password"
}
```

Uses SQL Server login credentials.

---

## Windows Authentication

```json
{
    "authentication": "windows"
}
```

Uses the Windows account running the web server.

Username and password are ignored.

---

# Connection Options

The `options` object contains database-specific settings.

Example

```json
{
    "options": {
        "encrypt": false,
        "trustServerCertificate": true
    }
}
```

Supported Options

| Option | Description |
|---------|-------------|
| encrypt | Enables encrypted connections |
| trustServerCertificate | Trusts the SQL Server certificate |

---

# Configuration Loading

During startup, the framework performs the following steps:

1. Reads `database.json`
2. Loads the configured provider
3. Detects or loads the database driver
4. Creates the database connection
5. Makes the connection available to the Database Engine

This process is automatic and requires no application code changes.

---

# Best Practices

- Use `driver: "auto"` whenever possible.
- Store production credentials securely.
- Avoid committing production passwords to version control.
- Use Windows Authentication where appropriate.
- Keep ODBC drivers up to date.
- Back up configuration files before making changes.

---

# Troubleshooting

## Database Not Found

Verify the configured database name exists.

---

## Login Failed

Check the username, password, and authentication mode.

---

## Driver Not Found

Install Microsoft ODBC Driver 17 or 18 for SQL Server.

---

## Unable to Connect

Verify:

- SQL Server is running
- TCP/IP is enabled
- Firewall rules allow the connection
- Server name and port are correct

---

# Related Documentation

- API.md
- JSON-Request-Reference.md
- Hosting.md

---

# Summary

The Generic SQL API Framework uses a centralized JSON configuration file to manage database connectivity. By separating connection settings from application code, the framework simplifies deployment, improves maintainability, and provides a flexible foundation for supporting multiple database providers in future releases.