# Hosting

## Windows bundled runtime

The repository includes a PHP runtime at `runtime/windows/php/` and a launcher at `start-windows.bat`. A user of this path does not need to install PHP, XAMPP, or WAMP. The machine still needs a SQL Server ODBC driver because the bundled PHP ODBC extension is only the PHP side of the connection.

From the backend root:

```bat
start-windows.bat
```

The implemented startup sequence is:

```text
locate php.exe and php.ini
  -> create runtime/windows/php/opcache and logs
  -> verify an ODBC module appears in php -m
  -> execute scripts/check-database.php
  -> verify api directory
  -> find a free port from 8000 through 8100
  -> php -S localhost:<port> -t api
```

The script explicitly loads `runtime/windows/php/php.ini`, configures OPcache's file cache, and writes PHP errors to `logs/php_errors.log`. If PHP/ODBC/database validation fails, startup aborts. It does not install an ODBC driver or create database configuration. The Windows built-in server is single-process/single-threaded: while one request is waiting on SQL Server, later requests queue. This is a development-server limitation, not application-level connection sharing.

The displayed URL is `http://localhost:<port>/index.php`. The document root is `api/`, so this maps to `api/index.php` in the repository.

## Required deployment configuration

Create `database/config/database.json` as described in [Database Configuration](Database-Configuration.md). SQL Server must be reachable and the PHP process identity or SQL credentials must have the needed permissions. The `logs/` directory must be writable; `Logger` creates it if absent and writes dated `YYYY-MM-DD.log` files containing successful and failed SQL execution details.

## Other PHP environments

The backend can run under another PHP installation with the ODBC extension. For local development:

```bash
php -S 127.0.0.1:8000 -t api
```

Use IIS/FastCGI, Apache with multiple PHP workers, or Nginx with PHP-FPM for concurrent production requests. Size the worker pool and SQL Server connection capacity together. This repository does not include production web-server configuration. PHP's built-in server and `start-windows.bat` are development/convenience launchers, not production process managers.

Before production deployment, configure HTTPS at the web server or reverse proxy, restrict the two hard-coded development CORS origins as needed, protect the ignored database JSON and logs, use a least-privilege SQL identity, and manage PHP/ODBC updates.

## CI versus runtime

`.github/workflows/backend-tests.yml` uses a hosted PHP runtime to lint code and execute faked database-independent tests. CI deliberately does not start the API, load ODBC, create fake credentials, or run `scripts/check-database.php`. No separate live SQL Server integration workflow currently exists.

## Troubleshooting

- `PHP runtime not found` or `php.ini not found`: restore the corresponding bundled files or use another PHP installation.
- `PHP ODBC extension not available`: check `runtime/windows/php/php.ini` and required runtime DLL dependencies.
- `database.json not found`: create the ignored file at the exact documented path.
- `No compatible SQL Server ODBC driver`: install a supported driver or configure the exact available driver and verify server/authentication settings.
- no port between 8000–8100: stop a conflicting service or host the backend manually on another port; the launcher has no flag to change its range.
- query failures: inspect `logs/YYYY-MM-DD.log` by request ID and `queryPhase` to distinguish count, data, prepare, execute, and fetch time. Parameter values are intentionally omitted.
