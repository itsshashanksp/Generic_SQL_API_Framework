<?php

require_once __DIR__ . '/../app/Security/DatabaseCredentialResolver.php';

$configPath = __DIR__ . '/../database/config/database.json';

if (!is_file($configPath)) {
    exit(0);
}

$contents = @file_get_contents($configPath);
$config = $contents === false ? null : json_decode($contents, true);

// The existing database connection check reports missing or invalid config.
// This preflight only prevents an encrypted connection attempt without its key.
if (!is_array($config)) {
    exit(0);
}

$password = $config['password'] ?? '';

if (
    DatabaseCredentialResolver::usesEncryption($password)
    && !DatabaseCredentialResolver::encryptionKeyIsAvailable()
) {
    exit(2);
}

exit(0);
