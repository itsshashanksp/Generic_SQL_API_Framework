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
  "password": "your-database-password",
  "options": {
    "encrypt": true,
    "trustServerCertificate": false
  }
}
```

SQL mode passes the username and resolved password to `odbc_connect`.

## Password encryption

Password encryption is optional. A normal string remains valid and is passed through unchanged, so existing installations do not have to migrate. An encrypted password protects the stored value in `database.json` with AES-256-GCM authenticated encryption. Every encryption uses a fresh random 12-byte nonce and produces a 16-byte authentication tag.

The encrypted form is a versioned JSON object:

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

Use that complete object as the value of `password`:

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

The nonce, ciphertext, and authentication tag are Base64-encoded because they contain binary data. The encryption key is deliberately absent from the object.

### Runtime credential resolution

The connection flow is:

```text
database.json password
  -> DatabaseCredentialResolver
  -> plain string: use unchanged
     OR encrypted object: validate and decrypt with AES-256-GCM
  -> SqlServerDriver
  -> odbc_connect
```

For an encrypted object, the resolver reads `GENERIC_SQL_API_ENCRYPTION_KEY` from the PHP process environment, strictly Base64-decodes it, and requires exactly 32 decoded bytes. Authentication failure prevents a connection from being attempted. The resolved plaintext exists in memory only for the normal connection path.

Plain strings do not require the environment variable. Normal application startup never generates a key, encrypts a password, or modifies `database.json`.

## One-time Windows setup

For an existing non-empty plaintext SQL password in `database.json`, run this from the backend root:

```bat
setup-database-encryption.bat
```

This setup uses `runtime\windows\php\php.exe` with `runtime\windows\php\php.ini` and performs the following sequence:

1. Checks the bundled PHP runtime, configuration, encryption scripts, database configuration, and OpenSSL extension.
2. Runs `scripts/generate-encryption-key.php` to generate a random 32-byte key.
3. Makes the key available to the current setup process and saves it as the Windows User environment variable `GENERIC_SQL_API_ENCRYPTION_KEY`.
4. Runs `scripts/setup-database-encryption.php`. This PHP script reads the existing password directly from `database.json`; it does not prompt for a password.
5. Replaces the password with the encrypted object and updates `database.json` through a temporary file without retaining a plaintext backup.
6. Runs `scripts/check-database.php` and reports success only when the connection returns `CONNECTED`.

Run this batch file only for the initial conversion of a plaintext password. The PHP setup rejects an already encrypted password, but the batch file generates and persists its new key before that check. Rerunning it against encrypted configuration can replace the matching Windows User key and prevent decryption.

After successful setup, open a new terminal or restart the hosting process before using `start-windows.bat`; existing processes do not automatically receive a newly persisted User environment variable. `start-windows.bat` checks OpenSSL and requires the key only when it detects an encrypted password. It does not generate encryption material or rewrite the configuration.

If the final database connection validation fails, the encrypted `database.json` and persisted Windows User key remain in place so the problem can be diagnosed. Setup cleans up its temporary key-output file, but it does not automatically roll back those completed changes. Keep a separately protected recovery copy only if your deployment policy requires one; the setup itself does not create or retain a plaintext backup.

## Manual and cross-platform setup

The PHP encryption format works on Windows, Linux, and macOS. Linux and macOS setup is manual; the repository does not provide platform-specific setup scripts for them.

Generate a key from the backend root:

```bash
php scripts/generate-encryption-key.php
```

The command prints an assignment in this form:

```text
GENERIC_SQL_API_ENCRYPTION_KEY=<generated Base64 key>
```

Configure the generated value for the PHP process. Examples for the current shell are:

```bat
set "GENERIC_SQL_API_ENCRYPTION_KEY=<generated Base64 key>"
```

```bash
export GENERIC_SQL_API_ENCRYPTION_KEY='<generated Base64 key>'
```

With an existing non-empty plaintext password in `database.json`, run:

```bash
php scripts/setup-database-encryption.php
php scripts/check-database.php
```

`setup-database-encryption.php` reads the existing password without prompting and updates the configuration without retaining a plaintext backup. It refuses an empty or already encrypted password.

For a non-mutating alternative, `scripts/encrypt-database-password.php` securely prompts for a password and prints only the encrypted object. It does not edit `database.json`; copy its output into the `password` field manually:

```bash
php scripts/encrypt-database-password.php
```

For hosted deployments, configure the key in the service manager, container secret facility, or hosting control panel so it is visible to the PHP worker. Interactive shell startup files are often not loaded by PHP-FPM, Apache, IIS, or scheduled services.

### Key rotation

Do not rerun `setup-database-encryption.bat` against an encrypted password. To rotate a key, retain access to the plaintext database password, generate and configure a new key, use `scripts/encrypt-database-password.php` to create a new encrypted object, then replace the password object and key together. Validate the connection before discarding the old matching pair.

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

Never commit `database.json` or `GENERIC_SQL_API_ENCRYPTION_KEY`. Keep the key separately from the encrypted configuration; storing both together defeats the at-rest protection. `database/config/database.example.json` contains placeholders only. Restrict filesystem and environment access to the deployment account, use least-privilege database users, choose encryption/trust settings appropriate for the environment, and avoid publishing connection error output. Query logs contain normalized SQL with literals redacted and parameter type/count metadata, but not parameter values or credentials; they should still be protected.

Encryption protects the stored password from disclosure when only `database.json` is exposed. It does not make the password unrecoverable to someone who controls the running application or can read both its process environment and configuration. The application must decrypt the password in memory to connect.

Missing or invalid keys, malformed objects, unsupported versions or algorithms, corrupted ciphertext or tags, and wrong keys all stop credential resolution safely. Error and log output does not include the plaintext password, key, encrypted payload details, or credential-bearing stack traces.

## Encryption troubleshooting

- `Database encryption key is not configured`: expose `GENERIC_SQL_API_ENCRYPTION_KEY` to the same process account that runs PHP. Setting it in an unrelated terminal or user account is not sufficient.
- `Database encryption key is invalid`: generate a key with the provided script. The decoded key must be exactly 32 bytes.
- `Database credential decryption failed`: the encrypted object may be damaged, or its key does not match. Restore the matching key/object pair or encrypt the password again; credential details are deliberately omitted from the error.
- Unsupported version or algorithm: use the documented version `1` and algorithm `AES-256-GCM`.
- OpenSSL extension required: enable PHP OpenSSL for the CLI, launcher runtime, and hosted PHP worker.
- `start-windows.bat` succeeds with a plain password but fails with an encrypted one: configure the environment key before launching it. The database check inherits the launcher's environment.
- Windows setup reports that the password is already encrypted: do not rerun the setup. Restore the environment key that matches the current encrypted object if the setup replaced it.
- Setup connection validation fails: the encrypted configuration and persisted key remain in place. Verify database reachability and ensure the environment key matches the encrypted object.

The query execution timeout is application configuration rather than a database credential. It defaults to 45 seconds and may be overridden for a deployment with the `DB_QUERY_TIMEOUT_SECONDS` environment variable. Keep it below PHP's request execution limit. This setting is applied to ODBC statements and does not control ODBC login, HTTP proxy, or browser timeouts.
