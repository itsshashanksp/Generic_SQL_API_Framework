<?php

require_once __DIR__ . '/DatabaseConfigurationResolver.php';
require_once __DIR__ . '/DatabaseCredentialResolver.php';

class DatabaseConfigurationMigrator
{
    public function migrate(string $configPath): void
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

        $temporaryPath = $configPath . '.tmp.' . bin2hex(random_bytes(6));
        try {
            if (@file_put_contents($temporaryPath, $output) === false) {
                throw new DatabaseCredentialException('Unable to write encrypted database configuration.');
            }
            $permissions = @fileperms($configPath);
            if ($permissions !== false) {
                @chmod($temporaryPath, $permissions & 0777);
            }
            if (DatabaseConfigurationResolver::load($temporaryPath) !== $storedConfiguration) {
                throw new DatabaseCredentialException('Unable to verify encrypted database configuration.');
            }
            if (!@rename($temporaryPath, $configPath)) {
                // Windows cannot atomically rename over an existing file. The
                // ciphertext has already been decrypted and verified above.
                if (@file_put_contents($configPath, $output, LOCK_EX) === false) {
                    throw new DatabaseCredentialException('Unable to replace database configuration.');
                }
                @unlink($temporaryPath);
            }
            if (DatabaseConfigurationResolver::load($configPath) !== $storedConfiguration) {
                throw new DatabaseCredentialException('Unable to verify encrypted database configuration.');
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }
}
