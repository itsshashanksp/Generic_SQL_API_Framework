# Database Configuration

## Overview

Generic SQL API Framework currently connects to Microsoft SQL Server through ODBC.

Database connection details are kept outside the query request and are loaded from:

```text
database/config/database.json
```

The application uses this configuration when establishing the database connection.

---

## Configuration File

Create the following file:

```text
database/
└── config/
    └── database.json
```

A configuration file is required before the API can connect to the database.

Do not commit the real configuration file when it contains production credentials.

The repository already ignores:

```text
database/config/database.json
```

---

## Basic Configuration

A SQL Server configuration can look like:

```json
{
  "provider": "sqlserver",
  "driver": "auto",
  "server": "localhost\\SQLEXPRESS",
  "database": "LOYAL_IBMS",
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

Replace the values with the details of the SQL Server being used.

---

## Configuration Fields

| Field | Description |
|---|---|
| `provider` | Database provider |
| `driver` | ODBC driver selection |
| `server` | SQL Server hostname, instance, or IP |
| `database` | Database name |
| `authentication` | Authentication method |
| `username` | SQL authentication username |
| `password` | SQL authentication password |
| `port` | SQL Server TCP port |
| `options` | Additional connection options |

---

## provider

The current provider is:

```json
"provider": "sqlserver"
```

This tells the database layer to use the SQL Server implementation.

If the provider is unsupported, the database connection check will fail.

---

## driver

The recommended configuration is:

```json
"driver": "auto"
```

With automatic selection, the framework can select a supported SQL Server ODBC driver available on the system.

A specific driver can also be configured:

```json
"driver": "ODBC Driver 18 for SQL Server"
```

The name must match an ODBC driver installed on the machine.

Examples of commonly installed Microsoft SQL Server ODBC drivers include:

```text
ODBC Driver 18 for SQL Server
ODBC Driver 17 for SQL Server
ODBC Driver 13 for SQL Server
```

The exact drivers available depend on the machine.

---

## ODBC Requirement

PHP must have the ODBC extension enabled.

Check the bundled Windows runtime with:

```bat
runtime\windows\php\php.exe -m | findstr /i odbc
```

Expected:

```text
odbc
```

You can also check the PHP configuration:

```bat
runtime\windows\php\php.exe --ini
```

The loaded configuration should point to:

```text
runtime/windows/php/php.ini
```

---

## SQL Server Driver

The PHP ODBC extension is the PHP-side interface.

The Microsoft SQL Server ODBC driver is the system-side driver used to communicate with SQL Server.

The connection path is:

```text
Generic SQL API
       |
       v
PHP ODBC Extension
       |
       v
SQL Server ODBC Driver
       |
       v
Microsoft SQL Server
```

The framework therefore does not depend on one fixed ODBC driver version.

The selected driver must, however, be installed and compatible with the environment.

---

## server

### Local SQL Server

```json
"server": "localhost"
```

### SQL Server Express

```json
"server": "localhost\\SQLEXPRESS"
```

### Named Server

```json
"server": "SERVER01\\SQLEXPRESS"
```

### IP Address

```json
"server": "192.168.1.100"
```

### SQL Server With Port

```json
"server": "192.168.1.100",
"port": 1433
```

Use the server and port that are configured for the SQL Server instance.

---

## database

The `database` field specifies the database that the API should connect to.

Example:

```json
"database": "LOYAL_IBMS"
```

The configured account must have permission to access this database.

---

## Authentication

The framework supports SQL Server authentication modes provided by the database connection implementation.

### SQL Authentication

Use:

```json
"authentication": "sql"
```

and provide:

```json
"username": "sa",
"password": "password"
```

Example:

```json
{
  "provider": "sqlserver",
  "driver": "auto",
  "server": "localhost\\SQLEXPRESS",
  "database": "LOYAL_IBMS",
  "authentication": "sql",
  "username": "sa",
  "password": "password"
}
```

---

### Windows Authentication

Use:

```json
"authentication": "windows"
```

Example:

```json
{
  "provider": "sqlserver",
  "driver": "auto",
  "server": "SERVER01\\SQLEXPRESS",
  "database": "LOYAL_IBMS",
  "authentication": "windows",
  "port": 1433
}
```

The connection uses the Windows account running the PHP process.

That account must have the required SQL Server permissions.

---

## Port

The standard SQL Server TCP port is:

```json
"port": 1433
```

If the SQL Server instance uses another port, configure that port instead.

Example:

```json
"port": 1500
```

For named instances, make sure the server and instance configuration matches the SQL Server environment.

---

## Encryption

Encryption can be configured through the `options` section.

Example:

```json
"options": {
  "encrypt": true
}
```

For production environments, use the encryption settings appropriate for the SQL Server configuration.

---

## Trust Server Certificate

The configuration can specify whether the SQL Server certificate should be trusted.

Example:

```json
"options": {
  "trustServerCertificate": true
}
```

This can be useful in environments using a certificate that is not issued by a trusted certificate authority.

For production, configure certificate validation according to the organization's security requirements.

---

## Complete SQL Authentication Example

```json
{
  "provider": "sqlserver",
  "driver": "auto",
  "server": "SERVER01\\SQLEXPRESS",
  "database": "ApplicationDB",
  "authentication": "sql",
  "username": "api_user",
  "password": "YOUR_PASSWORD",
  "port": 1433,
  "options": {
    "encrypt": true,
    "trustServerCertificate": false
  }
}
```

---

## Complete Windows Authentication Example

```json
{
  "provider": "sqlserver",
  "driver": "auto",
  "server": "SERVER01\\SQLEXPRESS",
  "database": "ApplicationDB",
  "authentication": "windows",
  "port": 1433,
  "options": {
    "encrypt": true,
    "trustServerCertificate": false
  }
}
```

---

## Startup Database Check

The Windows launcher checks the database before starting the API.

Run:

```bat
start-windows.bat
```

The startup flow is:

```text
PHP Runtime
     |
     v
PHP Configuration
     |
     v
ODBC Extension
     |
     v
database.json
     |
     v
Database Connection
     |
     +---- Failed ----> Stop API
     |
     +---- Connected -> Start API
```

If the connection fails, the API does not start.

The console displays a database connection failure and directs the user to configure:

```text
database/config/database.json
```

---

## Testing the Configuration

The easiest way to test the configuration is:

```bat
start-windows.bat
```

A successful startup should show:

```text
[OK] PHP Runtime
[OK] PHP Configuration
[OK] Runtime Directories
[OK] PHP ODBC
Checking database connection...
[OK] Database Connected
[OK] API Directory
[OK] Port 8000 Available
```

The API then starts on the selected port.

---

## Common Errors

### Unsupported Database Provider

Example:

```text
FAILED: Unsupported database provider.
```

Check:

```json
"provider": "sqlserver"
```

---

### ODBC Extension Not Available

Check:

```bat
runtime\windows\php\php.exe -m | findstr /i odbc
```

If nothing is returned, PHP ODBC is not available.

---

### ODBC Driver Not Found

If the PHP ODBC extension is available but the configured driver cannot be found, check the installed ODBC drivers on the system.

If possible, use:

```json
"driver": "auto"
```

or specify the exact installed driver name.

---

### Database Login Failed

Check:

- Username
- Password
- Authentication mode
- SQL Server authentication configuration
- Database permissions

---

### SQL Server Instance Not Found

Verify the configured server:

```json
"server": "SERVER01\\SQLEXPRESS"
```

Also verify that SQL Server is running and accepting connections.

---

### Connection Timeout

Check:

- SQL Server service
- TCP/IP configuration
- Firewall
- Server address
- Port
- Remote connection settings

---

## Security

Do not commit production database credentials.

The following file should remain local:

```text
database/config/database.json
```

Never place production passwords directly into source code.

For production deployments, protect the configuration file and restrict filesystem permissions where possible.

---

## Configuration Example for Development

A simple local SQL Server Express setup:

```json
{
  "provider": "sqlserver",
  "driver": "auto",
  "server": "localhost\\SQLEXPRESS",
  "database": "TestDB",
  "authentication": "windows",
  "port": 1433,
  "options": {
    "encrypt": false,
    "trustServerCertificate": true
  }
}
```

Adjust the values to match the local SQL Server installation.

---

## Configuration Example for Production

A production environment should use the organization's actual SQL Server and security configuration.

Example structure:

```json
{
  "provider": "sqlserver",
  "driver": "auto",
  "server": "DB-SERVER01",
  "database": "ProductionDB",
  "authentication": "sql",
  "username": "api_user",
  "password": "YOUR_SECURE_PASSWORD",
  "port": 1433,
  "options": {
    "encrypt": true,
    "trustServerCertificate": false
  }
}
```

Do not copy these example credentials into a real environment.

---

## Related Documentation

- [Architecture](Architecture.md) — database and query layer design
- [API](API.md) — HTTP API usage
- [JSON Request Reference](JSON-Request-Reference.md) — request structure
- [Hosting](Hosting.md) — running the backend
- [Roadmap](Roadmap.md) — planned database provider support