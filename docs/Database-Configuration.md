# Database Configuration

## Configuration file

Runtime database settings come from the ignored local file:

```text
database/config/database.json
```

The runtime accepts either the historical plaintext JSON object or the recommended encrypted envelope. Normal API requests only read this file; they never encrypt, migrate, or rewrite it.

## Plaintext fields

The plaintext form is retained for backward compatibility and as the input to the one-time migration:

| Field | Type | Required | Behavior |
|---|---|---:|---|
| `provider` | string | yes | Currently `sqlserver` |
| `driver` | string | no | `auto`, or an exact installed ODBC driver name |
| `server` | string | yes | Host, instance, or server address |
| `port` | number/string | no | Appended as `server,port` when supplied |
| `database` | string | yes | Database name |
| `authentication` | string | no | `sql` (default) or `windows` |
| `username` | string | SQL auth | Empty string if omitted |
| `password` | string | SQL auth | Empty string if omitted |
| `options.encrypt` | boolean-like | no | false (`Encrypt=no`) |
| `options.trustServerCertificate` | boolean-like | no | false (`TrustServerCertificate=no`) |

See `database/config/database.example.json` for a placeholder-only example. The earlier password-only encrypted object remains readable so existing deployments can migrate, but new deployments should encrypt the complete configuration.

## Complete configuration encryption

The recommended deployed `database.json` contains only this versioned envelope:

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

The ciphertext is the serialized complete configuration. Provider, driver, server/IP, port, database, authentication mode, username, password, connection options, and any other properties do not appear outside the authenticated ciphertext. Every encryption uses a fresh random 12-byte nonce and a 16-byte authentication tag.

The key is a random 32-byte value represented in Base64 and supplied separately through `GENERIC_SQL_API_ENCRYPTION_KEY`. It must never be stored in `database.json`, source control, logs, or command output retained as an ordinary file.

Runtime resolution is centralized:

```text
database.json
  -> parse and detect encrypted envelope
  -> read external key
  -> authenticate and decrypt AES-256-GCM payload
  -> decode the complete configuration
  -> existing driver validation and connection path
```

Authentication, malformed-payload, wrong-key, version, and algorithm failures stop before a connection is attempted. Fixed error messages omit decrypted configuration, connection strings, encrypted internals, and key material.

## One-time migration

Before migration, configure the plaintext `database.json` and verify its values. Then generate and install the external key for the same account that runs PHP.

On Windows, run from the backend root:

```bat
setup-database-encryption.bat
```

The launcher verifies the configuration is still plaintext before generating a key, checks the bundled PHP/OpenSSL runtime, persists the key as a Windows User environment variable, runs the PHP migration, and checks the database connection. Open a new terminal or restart the hosting process afterward so it receives the persisted variable.

On Linux, macOS, or for manual Windows setup:

```bash
php scripts/generate-encryption-key.php
export GENERIC_SQL_API_ENCRYPTION_KEY='<generated Base64 key>'
php scripts/setup-database-encryption.php
php scripts/check-database.php
```

Use the platform/service secret mechanism rather than a shell export for production workers. `scripts/setup-database-encryption.php` is the single migration utility. It:

1. Reads and validates the existing plaintext JSON object.
2. Encrypts the complete object as one AES-256-GCM payload.
3. Writes and decrypts a temporary encrypted file to verify it.
4. Replaces `database.json` and verifies it again.
5. Refuses an already fully encrypted configuration.

The utility does not create or retain a plaintext backup and never prints
configuration values, ciphertext, or the key. Create any operational backup
explicitly in protected storage before migration if deployment policy requires it.

Do not rerun setup against an encrypted file. For key rotation, decrypt with the existing key in a controlled environment, create a plaintext migration input, install a new key, rerun the migration, validate the connection, and then retire the old key/configuration pair according to deployment policy.

## Windows authentication

The decrypted configuration may use `authentication: "windows"`. In that mode the driver adds `Trusted_Connection=yes` and supplies empty ODBC credentials; the PHP process account must have SQL Server access.

## ODBC and validation

The host needs PHP's `odbc` extension and a compatible SQL Server ODBC driver. With `driver: "auto"`, the framework tests its supported driver list newest-first and keeps the first successful connection. With an explicit driver, only that driver is attempted.

Validate a deployment with:

```bash
php scripts/check-database.php
```

It prints `CONNECTED` on success or a sanitized failure on error. Database-independent tests neither connect nor require the local configuration file.

## Security and troubleshooting

- Keep `GENERIC_SQL_API_ENCRYPTION_KEY` separate from the encrypted file and restrict access to both.
- Never commit `database.json`, plaintext configuration copies, or encryption keys.
- A missing-key error means the PHP worker does not see `GENERIC_SQL_API_ENCRYPTION_KEY`.
- An invalid-key error means the environment value is not strict Base64 for exactly 32 bytes.
- A decryption failure means authentication failed, the payload is malformed, or the key/payload pair does not match.
- Unsupported version/algorithm errors require migration to version `1` and `AES-256-GCM`.
- OpenSSL must be enabled in CLI and hosted PHP runtimes.

Encryption protects all stored connection settings when the configuration file alone is disclosed. A process or operator able to read both the key and ciphertext can decrypt it, because the application must recover the configuration in memory to connect.

The query timeout is separate application configuration. `DB_QUERY_TIMEOUT_SECONDS` defaults to 45 seconds and applies to ODBC statements, not login, HTTP proxy, or browser timeouts.
