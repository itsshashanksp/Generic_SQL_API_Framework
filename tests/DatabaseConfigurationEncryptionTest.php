<?php

require_once __DIR__ . '/../app/Security/DatabaseConfigurationMigrator.php';
require_once __DIR__ . '/../database/drivers/SqlServerDriver.php';
require_once __DIR__ . '/../core/ExceptionHandler.php';
require_once __DIR__ . '/../core/Logger.php';

function configurationAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function configurationExpectFailure(callable $operation, string $message): Throwable
{
    try {
        $operation();
    } catch (Throwable $exception) {
        return $exception;
    }

    throw new RuntimeException($message);
}

function setConfigurationEnvironment(?string $value): void
{
    if ($value === null) {
        putenv(DatabaseCredentialEncryption::ENVIRONMENT_VARIABLE);
        return;
    }
    putenv(DatabaseCredentialEncryption::ENVIRONMENT_VARIABLE . '=' . $value);
}

function tamperConfigurationComponent(string $value): string
{
    $decoded = base64_decode($value, true);
    configurationAssert($decoded !== false && $decoded !== '', 'Encrypted test component is invalid.');
    $decoded[0] = chr(ord($decoded[0]) ^ 1);
    return base64_encode($decoded);
}

$originalEnvironment = getenv(DatabaseCredentialEncryption::ENVIRONMENT_VARIABLE);
$key = base64_encode(random_bytes(32));
$wrongKey = base64_encode(random_bytes(32));
$temporaryDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR
    . 'generic-sql-config-test-' . bin2hex(random_bytes(8));
$logDirectory = $temporaryDirectory . DIRECTORY_SEPARATOR . 'logs';
$configPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'database.json';
$configuration = [
    'provider' => 'sqlserver',
    'driver' => 'ODBC Driver 18 for SQL Server',
    'server' => 'private-db.internal\\INSTANCE',
    'database' => 'SensitiveApplicationDatabase',
    'authentication' => 'sql',
    'username' => 'sensitive_api_user',
    'password' => 'sensitive-password-🔐',
    'port' => '15433',
    'options' => [
        'encrypt' => true,
        'trustServerCertificate' => false,
        'applicationIntent' => 'ReadOnlySensitiveSetting',
    ],
];

try {
    mkdir($temporaryDirectory, 0700, true);
    setConfigurationEnvironment($key);
    $encryption = new DatabaseCredentialEncryption();

    $encrypted = $encryption->encryptConfiguration($configuration);
    configurationAssert(
        $encryption->decryptConfiguration($encrypted) === $configuration,
        'Complete database configuration round trip failed.'
    );

    $encoded = json_encode($encrypted, JSON_THROW_ON_ERROR);
    foreach (['private-db.internal', '15433', 'SensitiveApplicationDatabase', 'sensitive_api_user',
        'sensitive-password', 'ODBC Driver 18', 'ReadOnlySensitiveSetting'] as $secret) {
        configurationAssert(strpos($encoded, $secret) === false, "Encrypted payload exposed {$secret}.");
    }
    configurationAssert(
        array_keys($encrypted) === ['encrypted', 'version', 'algorithm', 'nonce', 'ciphertext', 'tag'],
        'Encrypted file contains fields outside the authenticated payload envelope.'
    );

    $second = $encryption->encryptConfiguration($configuration);
    configurationAssert($encrypted['nonce'] !== $second['nonce'], 'Configuration encryption reused a nonce.');
    configurationAssert(
        $encrypted['ciphertext'] !== $second['ciphertext'],
        'Repeated configuration encryption produced identical ciphertext.'
    );

    configurationExpectFailure(
        fn () => (new DatabaseCredentialEncryption($wrongKey))->decryptConfiguration($encrypted),
        'A wrong key decrypted the configuration.'
    );
    $tamperedCiphertext = $encrypted;
    $tamperedCiphertext['ciphertext'] = tamperConfigurationComponent($tamperedCiphertext['ciphertext']);
    configurationExpectFailure(
        fn () => $encryption->decryptConfiguration($tamperedCiphertext),
        'Tampered configuration ciphertext was accepted.'
    );
    $tamperedTag = $encrypted;
    $tamperedTag['tag'] = tamperConfigurationComponent($tamperedTag['tag']);
    configurationExpectFailure(
        fn () => $encryption->decryptConfiguration($tamperedTag),
        'Tampered configuration tag was accepted.'
    );

    setConfigurationEnvironment(null);
    configurationExpectFailure(
        fn () => DatabaseConfigurationResolver::resolve($encrypted),
        'Encrypted configuration was resolved without a key.'
    );
    setConfigurationEnvironment($key);

    foreach ([
        [],
        ['encrypted' => true],
        ['encrypted' => true, 'version' => 1, 'algorithm' => 'AES-256-GCM'],
    ] as $malformed) {
        configurationExpectFailure(
            fn () => $encryption->decryptConfiguration($malformed),
            'Malformed encrypted configuration was accepted.'
        );
    }
    $unsupportedVersion = $encrypted;
    $unsupportedVersion['version'] = 2;
    configurationExpectFailure(
        fn () => $encryption->decryptConfiguration($unsupportedVersion),
        'Unsupported configuration encryption version was accepted.'
    );
    $unsupportedAlgorithm = $encrypted;
    $unsupportedAlgorithm['algorithm'] = 'AES-128-GCM';
    configurationExpectFailure(
        fn () => $encryption->decryptConfiguration($unsupportedAlgorithm),
        'Unsupported configuration encryption algorithm was accepted.'
    );

    configurationAssert(
        DatabaseConfigurationResolver::resolve($configuration) === $configuration,
        'Plaintext database configuration compatibility failed.'
    );
    file_put_contents($configPath, json_encode($configuration, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    configurationAssert(
        DatabaseConfigurationResolver::load($configPath) === $configuration,
        'Plaintext database configuration loading failed.'
    );

    (new DatabaseConfigurationMigrator())->migrate($configPath);
    configurationAssert(
        !is_file($configPath . '.backup') && glob($configPath . '.backup.*') === [],
        'Migration retained an automatic plaintext backup.'
    );
    $migratedContents = (string)file_get_contents($configPath);
    foreach (['private-db.internal', '15433', 'SensitiveApplicationDatabase', 'sensitive_api_user',
        'sensitive-password', 'ODBC Driver 18', 'ReadOnlySensitiveSetting'] as $secret) {
        configurationAssert(strpos($migratedContents, $secret) === false, 'Migrated file exposed plaintext.');
    }
    configurationAssert(
        DatabaseConfigurationResolver::load($configPath) === $configuration,
        'Migrated database configuration did not resolve transparently.'
    );
    configurationExpectFailure(
        fn () => (new DatabaseConfigurationMigrator())->migrate($configPath),
        'Migration accepted an already encrypted configuration.'
    );

    $failure = configurationExpectFailure(
        fn () => (new DatabaseCredentialEncryption($wrongKey))->decryptConfiguration($encrypted),
        'Expected configuration decryption failure did not occur.'
    );
    $logFormatter = new ReflectionMethod(ExceptionHandler::class, 'formatExceptionForLog');
    $logger = new Logger($logDirectory);
    $logger->write($logFormatter->invoke(null, $failure));
    $logFiles = glob($logDirectory . DIRECTORY_SEPARATOR . '*.log');
    configurationAssert(is_array($logFiles) && count($logFiles) === 1, 'Configuration test log was not created.');
    $logContents = (string)file_get_contents($logFiles[0]);
    foreach (array_merge([$key, $encrypted['ciphertext']], array_values($configuration)) as $secret) {
        if (is_string($secret)) {
            configurationAssert(strpos($logContents, $secret) === false, 'Configuration secret was written to a log.');
        }
    }

    $invalidRuntimeConfiguration = $configuration;
    $invalidRuntimeConfiguration['authentication'] = 'secret-auth-mode';
    $runtimeFailure = configurationExpectFailure(
        fn () => (new SqlServerDriver($invalidRuntimeConfiguration))->connect(),
        'Invalid runtime configuration unexpectedly connected.'
    );
    foreach (['private-db.internal', 'SensitiveApplicationDatabase', 'sensitive_api_user', 'secret-auth-mode'] as $secret) {
        configurationAssert(
            strpos($runtimeFailure->getMessage(), $secret) === false,
            'Database connection error exposed configuration.'
        );
    }

    echo "Database configuration encryption tests passed.\n";
} finally {
    if ($originalEnvironment === false) {
        setConfigurationEnvironment(null);
    } else {
        setConfigurationEnvironment($originalEnvironment);
    }

    foreach (glob($logDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($logDirectory);
    foreach (glob($temporaryDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($temporaryDirectory);
}
