<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Security/DatabaseConfigurationMigrator.php';

$configFile = __DIR__ . '/../database/config/database.json';

try {
    $storedConfiguration = DatabaseConfigurationResolver::readStored($configFile);
    if (DatabaseConfigurationResolver::usesEncryption($storedConfiguration)) {
        throw new RuntimeException('Database configuration is already encrypted.');
    }

    if (in_array('--check-plaintext', $argv ?? [], true)) {
        if (DatabaseCredentialResolver::usesEncryption($storedConfiguration['password'] ?? '')) {
            throw new RuntimeException(
                'Legacy password-only encryption requires its existing key and manual PHP migration.'
            );
        }
        echo "Database configuration is ready for encryption." . PHP_EOL;
        exit(0);
    }

    (new DatabaseConfigurationMigrator())->migrate($configFile);

    echo "Complete database configuration encrypted successfully." . PHP_EOL;
    echo "No plaintext backup was retained." . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    // Exceptions in this path use fixed messages and never include configuration,
    // key, ciphertext, or connection values.
    fwrite(STDERR, 'FAILED: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
