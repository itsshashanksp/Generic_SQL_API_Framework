# Database Configuration

## Overview

The Generic SQL API Framework uses a provider-based database configuration.

The current database provider is:

sqlserver

Configuration is stored in:

database/config/database.json

---

# Configuration Structure

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

---

# provider

Specifies the database provider.

Example:

"provider": "sqlserver"

Current implementation:

sqlserver

The provider-based architecture is designed to allow additional database providers in future releases.

---

# driver

Specifies the ODBC driver selection.

Automatic detection:

"driver": "auto"

When auto is used, the SQL Server driver attempts to detect compatible installed ODBC drivers.

The detection logic checks supported driver generations from newer to older, including Microsoft ODBC Driver releases and SQL Server Native Client / legacy SQL Server driver names where installed.

This means the framework is not tied to one specific ODBC driver version.

A specific installed driver can also be configured where required:

"driver": "ODBC Driver 18 for SQL Server"

---

# server

Specifies the SQL Server host or instance.

Default server:

"server": "localhost"

Named instance:

"server": "localhost\\SQLEXPRESS"

A named SQL Server instance can be used together with an explicit port.

---

# database

Specifies the database to connect to.

Example:

"database": "IBMS"

The configured login must have permission to access the selected database.

---

# authentication

Supported authentication modes:

sql
windows

## SQL Authentication

"authentication": "sql",
"username": "sa",
"password": "password"

## Windows Authentication

"authentication": "windows"

For Windows Authentication, the PHP process uses the Windows account under which the API is running.

---

# username

Used with SQL Authentication.

Example:

"username": "sa"

It is not required for Windows Authentication.

---

# password

Used with SQL Authentication.

Example:

"password": "password"

Do not commit production credentials to source control.

---

# port

Specifies the SQL Server TCP port.

Example:

"port": 1433

The framework supports explicit ports as well as named SQL Server instances.

The server and port are combined when constructing the ODBC connection.

---

# options

Connection options are configured under:

"options": {}

## encrypt

Controls connection encryption.

Example:

"encrypt": false

## trustServerCertificate

Controls whether the SQL Server certificate is trusted without certificate-chain validation.

Example:

"trustServerCertificate": true

Production deployments should use certificate settings appropriate to the organization's security requirements.

---

# SQL Server ODBC Driver Support

The framework uses ODBC for SQL Server connectivity.

The driver supports automatic detection across supported installed driver generations.

Examples include:

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

Not every system will have every driver installed. The framework attempts the available compatible drivers and reports the connection errors if none can establish a connection.

---

# Connection Compatibility

The SQL Server driver supports:

- Modern ODBC Driver releases
- Older SQL Server ODBC driver releases where installed
- SQL Server Native Client / legacy driver names
- SQL Server named instances
- Explicit ports
- SQL Authentication
- Windows Authentication
- Encryption configuration
- Trust Server Certificate configuration

---

# SQL Server Pagination Compatibility

The framework does not require the frontend to know which pagination syntax the SQL Server environment supports.

The database layer can detect SQL Server capability and choose a compatible pagination implementation.

Modern environments can use modern pagination.

Older compatibility levels that do not support OFFSET/FETCH can use:

ROW_NUMBER()

pagination instead.

The JSON request remains:

{
    "pagination": {
        "page": 1,
        "pageSize": 25
    }
}

This keeps pagination implementation inside the database layer.

---

# Windows Portable Runtime

Windows users can use the bundled PHP runtime:

runtime/windows/php/

Start it with:

start-windows.bat

The bundled runtime includes PHP and PHP ODBC support.

The Microsoft SQL Server ODBC driver remains an external system requirement.

---

# Troubleshooting

## Cannot Open Database

Verify:

- Database name
- SQL login permissions
- Windows account permissions when using Windows Authentication

## Server Not Found

Verify:

- SQL Server service
- Server/instance name
- SQL Server network configuration
- TCP port
- ODBC driver installation

## Login Failed

Verify:

- Username
- Password
- Authentication mode
- Database permissions

## ODBC Driver Not Found

Install a compatible Microsoft SQL Server ODBC driver or configure the correct installed driver explicitly.

## OFFSET Syntax Error

If an older SQL Server compatibility level does not support OFFSET/FETCH, the framework's capability-aware pagination can use ROW_NUMBER() pagination instead.