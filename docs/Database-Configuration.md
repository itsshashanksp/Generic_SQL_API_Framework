# Database Configuration

## Configuration file

Runtime database settings come from the ignored local file:

```text
database/config/database.json
```

`DriverFactory` reads this file and currently accepts only `provider: "sqlserver"`. Missing/invalid JSON, an unsupported provider, missing server/database, an unsupported authentication mode, or a failed ODBC connection stops construction with an exception. Normal CI tests do not create or read this file.

## Fields

| Field | Type | Required | Actual default/behavior |
|---|---|---:|---|
| `provider` | string | yes | Only `sqlserver` is selectable |
| `driver` | string | no | `auto`; otherwise exact installed ODBC driver name |
| `server` | string | yes | Host, instance (for example `localhost\\SQLEXPRESS`), or an address already containing a comma-port |
| `port` | number/string | no | No default is added; when non-empty it is appended as `server,port` unless `server` already contains a comma |
| `database` | string | yes | No default |
| `authentication` | string | no | `sql`; allowed `sql` or `windows` |
| `username` | string | SQL auth | Empty string if omitted |
| `password` | string | SQL auth | Empty string if omitted |
| `options.encrypt` | boolean-like | no | false (`Encrypt=no`) |
| `options.trustServerCertificate` | boolean-like | no | false (`TrustServerCertificate=no`) |

Unknown configuration properties are ignored by the current driver; they should not be relied upon.

## SQL authentication

```json
{
  "provider": "sqlserver",
  "driver": "ODBC Driver 18 for SQL Server",
  "server": "db.example.internal",
  "port": 1433,
  "database": "ApplicationDb",
  "authentication": "sql",
  "username": "api_user",
  "password": "replace-me",
  "options": {
    "encrypt": true,
    "trustServerCertificate": false
  }
}
```

SQL mode passes username/password to `odbc_connect`.

## Windows authentication

```json
{
  "provider": "sqlserver",
  "driver": "auto",
  "server": "localhost\\SQLEXPRESS",
  "database": "ApplicationDb",
  "authentication": "windows",
  "options": {
    "encrypt": false,
    "trustServerCertificate": true
  }
}
```

Windows mode adds `Trusted_Connection=yes` and supplies empty ODBC credentials. The account running PHP must already have SQL Server access.

## ODBC requirements and auto detection

The host needs PHP's `odbc` extension and a compatible installed SQL Server ODBC driver. With `driver: "auto"`, `SqlServerDriver` tries its fixed list from newest to oldest: Microsoft ODBC Driver 19, 18, 17, 13.1, 13, 11, 10; SQL Server Native Client 11.0, 10.0, 9.0; SQL Native Client; then legacy SQL Server. Auto detection tests real connections and retains the first successful one—it does not merely inspect installed driver names.

With an explicit `driver`, that exact string is placed in the ODBC DSN and only one connection is attempted.

## Validate configuration

Production startup calls:

```bash
php scripts/check-database.php
```

It prints `CONNECTED` and exits 0 after opening and closing a connection, or prints `FAILED: ...` and exits 1. `start-windows.bat` aborts if this check fails. This behavior is intentionally separate from `php tests/run.php`, which needs no database.

## Security

`database/config/database.json` is gitignored; do not commit credentials. Restrict filesystem access to the deployment account, use least-privilege database users, choose encryption/trust settings appropriate for the environment, and avoid publishing connection error output. Query logs can contain SQL and parameter values and should also be protected.
