<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Security/DatabaseCredentialEncryption.php';

$configFile = __DIR__ . '/../database/config/database.json';
$tempFile = $configFile . '.tmp';

try {
    // ------------------------------------------------------------
    // Validate configuration file
    // ------------------------------------------------------------

    if (!is_file($configFile)) {
        throw new RuntimeException('database.json was not found.');
    }

    $configJson = file_get_contents($configFile);

    if ($configJson === false) {
        throw new RuntimeException('Unable to read database.json.');
    }

    // ------------------------------------------------------------
    // Parse configuration
    // ------------------------------------------------------------

    $config = json_decode(
        $configJson,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (!is_array($config)) {
        throw new RuntimeException('Invalid database.json structure.');
    }

    if (!array_key_exists('password', $config)) {
        throw new RuntimeException(
            'Password field not found in database.json.'
        );
    }

    // ------------------------------------------------------------
    // Prevent double encryption
    // ------------------------------------------------------------

    if (
        is_array($config['password']) &&
        ($config['password']['encrypted'] ?? false) === true
    ) {
        throw new RuntimeException(
            'Database password is already encrypted.'
        );
    }

    // ------------------------------------------------------------
    // Read plaintext password
    // ------------------------------------------------------------

    $password = (string) $config['password'];

    if ($password === '') {
        throw new RuntimeException(
            'Database password cannot be empty.'
        );
    }

    // ------------------------------------------------------------
    // Encrypt password
    // ------------------------------------------------------------

    $encryption = new DatabaseCredentialEncryption();

    $encryptedPassword = $encryption->encryptPassword($password);

    $config['password'] = $encryptedPassword;

    // ------------------------------------------------------------
    // Build updated JSON
    // ------------------------------------------------------------

    $output = json_encode(
        $config,
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE |
        JSON_THROW_ON_ERROR
    ) . PHP_EOL;

    // ------------------------------------------------------------
    // Write updated configuration to temporary file
    //
    // Do NOT use LOCK_EX here because the bundled Windows PHP
    // stream reports that exclusive locks are unsupported.
    // ------------------------------------------------------------

    if (file_put_contents($tempFile, $output) === false) {
        @unlink($tempFile);

        throw new RuntimeException(
            'Unable to write temporary database configuration.'
        );
    }

    // ------------------------------------------------------------
    // Replace original database.json
    // ------------------------------------------------------------

    if (!rename($tempFile, $configFile)) {
        @unlink($tempFile);

        throw new RuntimeException(
            'Unable to update database.json.'
        );
    }

    // ------------------------------------------------------------
    // Success
    // ------------------------------------------------------------

    echo "Database password encrypted successfully." . PHP_EOL;
    echo "database.json updated successfully." . PHP_EOL;

    exit(0);

} catch (Throwable $exception) {

    // Remove temporary file if anything failed.
    if (is_file($tempFile)) {
        @unlink($tempFile);
    }

    // Never expose password, encryption key, ciphertext,
    // or other credential information in the error output.
    fwrite(
        STDERR,
        'FAILED: ' . $exception->getMessage() . PHP_EOL
    );

    exit(1);
}
