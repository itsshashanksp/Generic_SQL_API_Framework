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
| `password` | string or encrypted object | SQL auth | Empty string if omitted; plain strings remain supported |
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

## Encrypt a stored password

Password encryption is optional and protects the password at rest in `database.json`. Existing plain string values continue to work and are never converted automatically. To use encryption, first generate a key from the backend root:

```bash
php scripts/generate-encryption-key.php
```

The command prints an environment assignment containing a newly generated, base64-encoded 32-byte key. Configure that value as `GENERIC_SQL_API_ENCRYPTION_KEY` in the environment of the PHP process. Do not save it in `database.json`, source control, documentation, or `start-windows.bat`.

Then encrypt the password interactively:

```bash
php scripts/encrypt-database-password.php
```

The prompt does not echo interactive input. The command prints a JSON object like this, with different base64 values each time:

```json
{
  "encrypted": true,
  "version": 1,
  "algorithm": "AES-256-GCM",
  "nonce": "<base64 nonce>",
  "ciphertext": "<base64 ciphertext>",
  "tag": "<base64 authentication tag>"
}
```

Replace only the string value of `password` with the complete object:

```json
{
  "provider": "sqlserver",
  "driver": "ODBC Driver 18 for SQL Server",
  "server": "db.example.internal",
  "database": "ApplicationDb",
  "authentication": "sql",
  "username": "api_user",
  "password": {
    "encrypted": true,
    "version": 1,
    "algorithm": "AES-256-GCM",
    "nonce": "<base64 nonce>",
    "ciphertext": "<base64 ciphertext>",
    "tag": "<base64 authentication tag>"
  }
}
```

At connection time, the credential resolver base64-decodes and validates the 32-byte key, authenticates and decrypts the password, and supplies the resulting string through the same existing ODBC connection path. AES-256-GCM uses a fresh random 12-byte nonce and a 16-byte authentication tag for every encryption.

### Configure the environment key

Use the full assignment printed by the key-generation script; the placeholders below are intentionally not usable keys.

Windows Command Prompt, for the current process and children:

```bat
set "GENERIC_SQL_API_ENCRYPTION_KEY=<generated base64 key>"
start-windows.bat
```

For a persistent Windows user variable, use the operating-system Environment Variables UI (then open a new terminal), or `setx GENERIC_SQL_API_ENCRYPTION_KEY "<generated base64 key>"`. Never edit the key into `start-windows.bat`.

Linux or macOS shell, for the current process and children:

```bash
export GENERIC_SQL_API_ENCRYPTION_KEY='<generated base64 key>'
php scripts/check-database.php
```

For hosted deployments, configure the variable in the service manager, container secret configuration, or hosting control panel so it is visible to the PHP worker. Shell startup files are often not loaded by PHP-FPM, Apache, IIS, or scheduled services.

### Migrate or rotate

To migrate, keep a recoverable copy outside the repository, configure the generated key, run the encryption command, and manually replace the `password` string with its output. Validate with `php scripts/check-database.php`. Normal startup never rewrites the file.

To rotate a key, decrypt/re-encrypt the password while the old key is still available, deploy the new encrypted object and new environment key together, and validate the connection. An encrypted object cannot be recovered with a different key.

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

`database/config/database.json` is gitignored; `database.example.json` contains placeholders only. Do not commit credentials or keys. Restrict filesystem access to the deployment account, restrict access to the environment key, use least-privilege database users, choose encryption/trust settings appropriate for the environment, and avoid publishing connection error output. Query logs contain normalized SQL with literals redacted and parameter type/count metadata, but not parameter values or credentials; they should still be protected.

Encryption protects the stored password from disclosure when only `database.json` is exposed. It does not make the password unrecoverable to someone who controls the running application or can read both its process environment and configuration. The application must decrypt the password in memory to connect.

## Encryption troubleshooting

- `Database encryption key is not configured`: expose `GENERIC_SQL_API_ENCRYPTION_KEY` to the same process account that runs PHP. Setting it in an unrelated terminal or user account is not sufficient.
- `Database encryption key is invalid`: generate a key with the provided script. The decoded key must be exactly 32 bytes.
- `Database credential decryption failed`: the encrypted object may be damaged, or its key does not match. Restore the matching key/object pair or encrypt the password again; credential details are deliberately omitted from the error.
- Unsupported version or algorithm: use the documented version `1` and algorithm `AES-256-GCM`.
- OpenSSL extension required: enable PHP OpenSSL for the CLI, launcher runtime, and hosted PHP worker.
- `start-windows.bat` succeeds with a plain password but fails with an encrypted one: configure the environment key before launching it. The database check inherits the launcher's environment.

The query execution timeout is application configuration rather than a database credential. It defaults to 45 seconds and may be overridden for a deployment with the `DB_QUERY_TIMEOUT_SECONDS` environment variable. Keep it below PHP's request execution limit. This setting is applied to ODBC statements and does not control ODBC login, HTTP proxy, or browser timeouts.
