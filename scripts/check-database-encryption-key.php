<?php

require_once __DIR__ . '/../app/Security/DatabaseConfigurationResolver.php';
require_once __DIR__ . '/../app/Security/DatabaseCredentialResolver.php';

$configPath = __DIR__ . '/../database/config/database.json';

if (!is_file($configPath)) {
    exit(0);
}

try {
    $config = DatabaseConfigurationResolver::readStored($configPath);
} catch (Throwable $exception) {
    exit(0);
}

if (
    (DatabaseConfigurationResolver::usesEncryption($config)
        || DatabaseCredentialResolver::usesEncryption($config['password'] ?? ''))
    && !DatabaseConfigurationResolver::encryptionKeyIsAvailable()
) {
    exit(2);
}

exit(0);
