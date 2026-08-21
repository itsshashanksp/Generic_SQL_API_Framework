# Hosting

## Overview

Generic SQL API Framework is a PHP backend and can be hosted using a normal PHP environment.

For Windows, the repository includes a **prebuilt PHP runtime**, so users do not need to install PHP manually when using the bundled runtime.

The current bundled runtime is Windows-specific.

Linux runtime packaging is planned separately.

---

## Windows

### Bundled PHP Runtime

The repository includes:

```text
runtime/
└── windows/
    └── php/
        ├── php.exe
        ├── php.ini
        └── ext/
```

The runtime contains the PHP installation required by the API, including the PHP ODBC extension.

This means a Windows user does not need:

- A separate PHP installation
- XAMPP
- WAMP
- A manually configured PHP environment

when using the bundled runtime.

---

## Start the API

From the project root:

```bat
start-windows.bat
```

The startup script performs the required checks and starts the API automatically.

---

## Windows Startup Process

The startup script checks the environment in this order:

```text
Start
  |
  v
PHP Runtime
  |
  v
php.ini
  |
  v
Runtime Directories
  |
  v
PHP ODBC
  |
  v
Database Configuration
  |
  v
Database Connection
  |
  v
API Directory
  |
  v
Available Port
  |
  v
Start PHP Server
```

If one of the required checks fails, the API startup is stopped.

---

## Runtime Directories

The Windows launcher creates required directories automatically.

### OPcache

```text
runtime/windows/php/opcache/
```

### Logs

```text
logs/
```

You do not need to create these directories manually.

The startup script creates them when they do not exist.

---

## PHP Configuration

The bundled PHP configuration is:

```text
runtime/windows/php/php.ini
```

The launcher explicitly loads this configuration.

You can verify it with:

```bat
runtime\windows\php\php.exe --ini
```

Expected:

```text
Loaded Configuration File:
D:\...\runtime\windows\php\php.ini
```

The exact path depends on where the project is installed.

---

## ODBC

The Windows runtime includes the PHP ODBC extension.

Check it with:

```bat
runtime\windows\php\php.exe -m | findstr /i odbc
```

Expected:

```text
odbc
```

PHP ODBC provides the connection between PHP and the installed SQL Server ODBC driver.

```text
PHP
 |
 v
PHP ODBC Extension
 |
 v
SQL Server ODBC Driver
 |
 v
SQL Server
```

See [Database Configuration](Database-Configuration.md).

---

## Database Configuration

Before starting the API, configure:

```text
database/config/database.json
```

The Windows launcher checks the database connection before starting the API.

If the connection fails, the API does not start.

See [Database Configuration](Database-Configuration.md).

---

## Database Startup Check

The startup script runs the database check before starting the PHP server.

Successful flow:

```text
Checking database connection...
[OK] Database Connected
```

Failed flow:

```text
Checking database connection...
[FAILED] Database connection failed.

Please configure:
database/config/database.json
```

This prevents the API from starting when the backend cannot reach its configured database.

---

## Port Selection

The default starting port is:

```text
8000
```

If port `8000` is already being used, the launcher automatically checks the next port.

The current range is:

```text
8000 - 8100
```

Example:

```text
Port 8000 -> In use
Port 8001 -> In use
Port 8002 -> Available
```

The API is then started using:

```text
http://localhost:8002
```

The selected URL is printed in the console.

---

## PHP Built-in Server

The Windows launcher uses the PHP built-in development server.

The command is conceptually:

```bat
php.exe -S localhost:PORT -t api
```

The API directory is:

```text
api/
```

The entry point is:

```text
api/index.php
```

---

## Example Startup Output

A successful startup looks similar to:

```text
========================================
         Generic SQL API
========================================

[OK] PHP Runtime
[OK] PHP Configuration
[OK] Runtime Directories
[OK] PHP ODBC

Checking database connection...

[OK] Database Connected
[OK] API Directory

Checking available port...

[OK] Port 8000 Available

========================================
              API Ready
========================================

API: http://localhost:8000

Starting API...
```

If `8000` is unavailable, another port is selected automatically.

---

## Apache

The API can also be hosted using Apache with PHP.

The general architecture is:

```text
Client
  |
  v
Apache
  |
  v
PHP
  |
  v
Generic SQL API
  |
  v
ODBC
  |
  v
SQL Server
```

The bundled PHP runtime is primarily intended to simplify Windows deployment and does not require Apache.

---

## XAMPP

XAMPP can also be used if it is already part of the deployment environment.

However, XAMPP is **not required** when using the bundled Windows runtime.

The project can run directly with:

```bat
start-windows.bat
```

This avoids making XAMPP a dependency of the application.

---

## IIS

The API can be hosted through IIS using PHP/FastCGI.

The architecture becomes:

```text
Client
  |
  v
IIS
  |
  v
FastCGI
  |
  v
PHP
  |
  v
Generic SQL API
  |
  v
SQL Server
```

The IIS environment must have a working PHP installation and PHP ODBC extension.

---

## Nginx

Nginx can be used with PHP-FPM.

```text
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
ODBC
  |
  v
SQL Server
```

The PHP environment must provide the required ODBC functionality.

---

## Docker

The backend can also be packaged into a Docker container.

A container would require:

```text
PHP
PHP ODBC Extension
SQL Server ODBC Driver
Generic SQL API
```

Conceptually:

```text
Docker Container
       |
       +-- PHP
       |
       +-- ODBC
       |
       +-- Generic SQL API
                |
                v
          SQL Server
```

Database credentials should be supplied through the deployment environment rather than committed into the container image.

Docker support is part of future deployment work.

---

## Production Deployment

The PHP built-in server is convenient for local development and simple internal deployments.

For larger production environments, the API can be placed behind a proper web server such as:

- Apache
- IIS
- Nginx

A production deployment should also provide:

- HTTPS
- Restricted CORS
- Protected database credentials
- Appropriate authentication
- Firewall rules
- Database backups
- Application logging
- PHP updates
- ODBC driver updates

---

## Security

Do not expose SQL Server directly to the public Internet.

The recommended flow is:

```text
Internet
   |
   v
HTTPS
   |
   v
Web Server
   |
   v
Generic SQL API
   |
   v
Private Network
   |
   v
SQL Server
```

Protect:

```text
database/config/database.json
```

and never commit production credentials.

---

## Updating PHP

The bundled Windows runtime is part of the repository.

When updating PHP:

1. Obtain a compatible PHP build.
2. Replace the runtime files.
3. Keep the required extensions enabled.
4. Verify `php.ini`.
5. Verify the ODBC extension.
6. Run the startup script.
7. Confirm the database connection.
8. Test the API.

Check the PHP version with:

```bat
runtime\windows\php\php.exe -v
```

Check ODBC with:

```bat
runtime\windows\php\php.exe -m | findstr /i odbc
```

---

## Updating ODBC Drivers

The PHP runtime and the SQL Server ODBC driver are separate components.

```text
Application
    |
    v
Bundled PHP
    |
    v
PHP ODBC Extension
    |
    v
Installed ODBC Driver
    |
    v
SQL Server
```

The ODBC driver can therefore be updated independently from the PHP runtime, provided the installed driver remains compatible with the application.

---

## Linux

Linux hosting can use a normal PHP installation with the required ODBC components.

The project does **not currently provide a bundled Linux runtime**.

Linux-specific runtime packaging and startup scripts are planned for a future release.

---

## Troubleshooting

### PHP Runtime Not Found

Check:

```text
runtime/windows/php/php.exe
```

Then run:

```bat
runtime\windows\php\php.exe -v
```

---

### php.ini Not Found

Check:

```text
runtime/windows/php/php.ini
```

Then run:

```bat
runtime\windows\php\php.exe --ini
```

---

### ODBC Extension Not Available

Run:

```bat
runtime\windows\php\php.exe -m | findstr /i odbc
```

If `odbc` is not displayed, check the PHP runtime and `php.ini`.

---

### Database Connection Failed

Check:

```text
database/config/database.json
```

Then verify:

- SQL Server is running
- Server name is correct
- Database name is correct
- Authentication details are correct
- ODBC driver is installed
- SQL Server accepts the configured connection
- Firewall rules allow the connection

---

### Port Already in Use

The Windows startup script automatically checks another port between:

```text
8000
```

and:

```text
8100
```

No manual port selection is normally required.

---

## Related Documentation

- [Introduction](Introduction.md)
- [Architecture](Architecture.md)
- [API](API.md)
- [Database Configuration](Database-Configuration.md)
- [Roadmap](Roadmap.md)
- [Changelog](../CHANGELOG.md)