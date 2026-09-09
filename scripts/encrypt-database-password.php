<?php

require_once __DIR__ . '/../app/Security/DatabaseCredentialEncryption.php';

function readDatabasePassword(): string
{
    if (PHP_OS_FAMILY === 'Windows') {
        $command = 'powershell.exe -NoProfile -Command '
            . escapeshellarg(
                '$secure = Read-Host \'Enter database password\' -AsSecureString; '
                . '$pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure); '
                . 'try { [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) } '
                . 'finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }'
            );
        $password = shell_exec($command);

        if ($password === null) {
            throw new RuntimeException('Unable to read the database password securely.');
        }

        return rtrim($password, "\r\n");
    }

    fwrite(STDERR, 'Enter database password: ');
    $isInteractive = function_exists('stream_isatty') && stream_isatty(STDIN);

    if ($isInteractive) {
        exec('stty -echo', $output, $status);
        if ($status !== 0) {
            throw new RuntimeException('Unable to disable password echo.');
        }
    }

    try {
        $password = fgets(STDIN);
    } finally {
        if ($isInteractive) {
            exec('stty echo');
            fwrite(STDERR, PHP_EOL);
        }
    }

    if ($password === false) {
        throw new RuntimeException('Unable to read the database password securely.');
    }

    return rtrim($password, "\r\n");
}

try {
    $encryption = new DatabaseCredentialEncryption();
    $encryptedPassword = $encryption->encryptPassword(readDatabasePassword());
    $json = json_encode($encryptedPassword, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        throw new RuntimeException('Unable to encode the encrypted database credential.');
    }

    echo $json . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAILED: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
