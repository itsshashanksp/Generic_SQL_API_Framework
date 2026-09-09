<?php

require_once __DIR__ . '/../app/Security/DatabaseCredentialEncryption.php';
require_once __DIR__ . '/../app/Security/DatabaseCredentialResolver.php';
require_once __DIR__ . '/../core/ExceptionHandler.php';
require_once __DIR__ . '/../core/Logger.php';

function credentialAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function credentialExpectFailure(callable $operation, string $message): Throwable
{
    try {
        $operation();
    } catch (Throwable $exception) {
        return $exception;
    }

    throw new RuntimeException($message);
}

function setCredentialEnvironment(?string $value): void
{
    if ($value === null) {
        putenv(DatabaseCredentialEncryption::ENVIRONMENT_VARIABLE);
        return;
    }

    putenv(DatabaseCredentialEncryption::ENVIRONMENT_VARIABLE . '=' . $value);
}

function alterEncodedComponent(string $value): string
{
    $decoded = base64_decode($value, true);
    credentialAssert($decoded !== false && $decoded !== '', 'Test fixture component must contain binary data.');
    $decoded[0] = chr(ord($decoded[0]) ^ 1);
    return base64_encode($decoded);
}

$originalEnvironment = getenv(DatabaseCredentialEncryption::ENVIRONMENT_VARIABLE);
$key = base64_encode(random_bytes(32));
$wrongKey = base64_encode(random_bytes(32));
$password = "p@ss ' \" \\ / : ; $ % & !\t\n終わり🔐";
$logDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'generic-sql-credential-test-' . bin2hex(random_bytes(8));

try {
    $encryption = new DatabaseCredentialEncryption($key);

    $encrypted = $encryption->encryptPassword($password);
    credentialAssert($encryption->decryptPassword($encrypted) === $password, 'Password round trip failed.');

    $empty = $encryption->encryptPassword('');
    credentialAssert($encryption->decryptPassword($empty) === '', 'Empty password round trip failed.');

    $unicode = '密碼-पासवर्ड-🔑';
    credentialAssert(
        $encryption->decryptPassword($encryption->encryptPassword($unicode)) === $unicode,
        'Unicode password round trip failed.'
    );

    $first = $encryption->encryptPassword($password);
    $second = $encryption->encryptPassword($password);
    credentialAssert($first['nonce'] !== $second['nonce'], 'Encryption reused a nonce.');
    credentialAssert($first['ciphertext'] !== $second['ciphertext'], 'Repeated encryption produced identical ciphertext.');

    credentialExpectFailure(
        fn () => (new DatabaseCredentialEncryption($wrongKey))->decryptPassword($encrypted),
        'A wrong key decrypted the credential.'
    );

    $modifiedCiphertext = $encrypted;
    $modifiedCiphertext['ciphertext'] = alterEncodedComponent($modifiedCiphertext['ciphertext']);
    credentialExpectFailure(
        fn () => $encryption->decryptPassword($modifiedCiphertext),
        'Modified ciphertext was accepted.'
    );

    $modifiedTag = $encrypted;
    $modifiedTag['tag'] = alterEncodedComponent($modifiedTag['tag']);
    credentialExpectFailure(
        fn () => $encryption->decryptPassword($modifiedTag),
        'Modified authentication tag was accepted.'
    );

    setCredentialEnvironment(null);
    credentialExpectFailure(
        fn () => new DatabaseCredentialEncryption(),
        'A missing environment key was accepted.'
    );

    setCredentialEnvironment(base64_encode(random_bytes(31)));
    credentialExpectFailure(
        fn () => new DatabaseCredentialEncryption(),
        'A key with the wrong length was accepted.'
    );

    setCredentialEnvironment('not-valid-base64***');
    credentialExpectFailure(
        fn () => new DatabaseCredentialEncryption(),
        'Invalid key base64 was accepted.'
    );

    foreach ([
        [],
        ['encrypted' => true, 'version' => 1, 'algorithm' => 'AES-256-GCM'],
        ['encrypted' => false, 'version' => 1, 'algorithm' => 'AES-256-GCM', 'nonce' => '', 'ciphertext' => '', 'tag' => ''],
    ] as $malformed) {
        credentialExpectFailure(
            fn () => $encryption->decryptPassword($malformed),
            'Malformed encrypted configuration was accepted.'
        );
    }

    $invalidBase64 = $encrypted;
    $invalidBase64['nonce'] = '***';
    credentialExpectFailure(
        fn () => $encryption->decryptPassword($invalidBase64),
        'Invalid component base64 was accepted.'
    );

    $invalidNonce = $encrypted;
    $invalidNonce['nonce'] = base64_encode(random_bytes(11));
    credentialExpectFailure(
        fn () => $encryption->decryptPassword($invalidNonce),
        'An invalid nonce length was accepted.'
    );

    $invalidTag = $encrypted;
    $invalidTag['tag'] = base64_encode(random_bytes(15));
    credentialExpectFailure(
        fn () => $encryption->decryptPassword($invalidTag),
        'An invalid authentication tag length was accepted.'
    );

    $unsupportedVersion = $encrypted;
    $unsupportedVersion['version'] = 2;
    credentialExpectFailure(
        fn () => $encryption->decryptPassword($unsupportedVersion),
        'An unsupported version was accepted.'
    );

    $unsupportedAlgorithm = $encrypted;
    $unsupportedAlgorithm['algorithm'] = 'AES-128-GCM';
    credentialExpectFailure(
        fn () => $encryption->decryptPassword($unsupportedAlgorithm),
        'An unsupported algorithm was accepted.'
    );

    credentialAssert(
        DatabaseCredentialResolver::resolve($password) === $password,
        'Plain-password backward compatibility failed.'
    );
    credentialAssert(
        !DatabaseCredentialResolver::usesEncryption($password),
        'A plain password was identified as encrypted.'
    );
    credentialAssert(
        DatabaseCredentialResolver::usesEncryption($encrypted),
        'An encrypted password object was not detected.'
    );

    setCredentialEnvironment(null);
    credentialAssert(
        !DatabaseCredentialResolver::encryptionKeyIsAvailable(),
        'A missing encryption key was reported as available.'
    );

    setCredentialEnvironment($key);
    credentialAssert(
        DatabaseCredentialResolver::encryptionKeyIsAvailable(),
        'A configured encryption key was reported as unavailable.'
    );
    credentialAssert(
        DatabaseCredentialResolver::resolve($encrypted) === $password,
        'Encrypted-password resolution failed.'
    );

    $failure = credentialExpectFailure(
        fn () => (new DatabaseCredentialEncryption($wrongKey))->decryptPassword($encrypted),
        'Expected credential decryption failure did not occur.'
    );
    credentialAssert(
        $failure instanceof DatabaseCredentialException,
        'Credential failures must be identifiable for safe exception logging.'
    );
    $logFormatter = new ReflectionMethod(ExceptionHandler::class, 'formatExceptionForLog');
    $logger = new Logger($logDirectory);
    $logger->write($logFormatter->invoke(null, $failure));
    $logFiles = glob($logDirectory . DIRECTORY_SEPARATOR . '*.log');
    credentialAssert(is_array($logFiles) && count($logFiles) === 1, 'Credential test log was not created.');
    $logContents = (string)file_get_contents($logFiles[0]);
    credentialAssert(strpos($logContents, $password) === false, 'Plaintext password was written to a log.');
    credentialAssert(strpos($logContents, $key) === false, 'Encryption key was written to a log.');
    credentialAssert(strpos($logContents, $encrypted['ciphertext']) === false, 'Ciphertext internals were written to a log.');

    echo "Database credential encryption tests passed.\n";
} finally {
    if ($originalEnvironment === false) {
        setCredentialEnvironment(null);
    } else {
        setCredentialEnvironment($originalEnvironment);
    }

    foreach (glob($logDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $logFile) {
        unlink($logFile);
    }
    if (is_dir($logDirectory)) {
        rmdir($logDirectory);
    }
}
