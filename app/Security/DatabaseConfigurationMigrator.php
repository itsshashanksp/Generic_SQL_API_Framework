<?php

require_once __DIR__ . '/DatabaseConfigurationResolver.php';
require_once __DIR__ . '/DatabaseCredentialResolver.php';

class DatabaseConfigurationMigrator
{
    public function migrate(string $configPath): string
    {
        $storedConfiguration = DatabaseConfigurationResolver::readStored($configPath);
        if (DatabaseConfigurationResolver::usesEncryption($storedConfiguration)) {
            throw new DatabaseCredentialException('Database configuration is already encrypted.');
        }

        // Resolve the former password-only format before sealing the complete
        // configuration. Plaintext configurations pass through unchanged.
        if (array_key_exists('password', $storedConfiguration)) {
            $storedConfiguration['password'] = DatabaseCredentialResolver::resolve(
                $storedConfiguration['password']
            );
        }

        $encryptedConfiguration = (new DatabaseCredentialEncryption())
            ->encryptConfiguration($storedConfiguration);

        try {
            $output = json_encode(
                $encryptedConfiguration,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . PHP_EOL;
        } catch (Throwable $exception) {
            throw new DatabaseCredentialException('Unable to encode encrypted database configuration.');
        }

        $original = @file_get_contents($configPath);
        if ($original === false) {
            throw new DatabaseCredentialException('Unable to read database configuration.');
        }

        $backupPath = $this->availableBackupPath($configPath);
        if (@file_put_contents($backupPath, $original) === false) {
            throw new DatabaseCredentialException('Unable to back up database configuration.');
        }
        @chmod($backupPath, 0600);

        $temporaryPath = $configPath . '.tmp.' . bin2hex(random_bytes(6));
        try {
            if (@file_put_contents($temporaryPath, $output) === false) {
                throw new DatabaseCredentialException('Unable to write encrypted database configuration.');
            }
            $permissions = @fileperms($configPath);
            if ($permissions !== false) {
                @chmod($temporaryPath, $permissions & 0777);
            }
            if (!@rename($temporaryPath, $configPath)) {
                // Windows may not allow rename() to replace an existing file.
                // The verified backup makes this overwrite recoverable.
                if (!@copy($temporaryPath, $configPath)) {
                    throw new DatabaseCredentialException('Unable to replace database configuration.');
                }
                @unlink($temporaryPath);
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }

        return $backupPath;
    }

    private function availableBackupPath(string $configPath): string
    {
        $backupPath = $configPath . '.backup';
        if (!file_exists($backupPath)) {
            return $backupPath;
        }

        do {
            $backupPath = $configPath . '.backup.' . gmdate('YmdHis') . '.' . bin2hex(random_bytes(4));
        } while (file_exists($backupPath));

        return $backupPath;
    }
}
