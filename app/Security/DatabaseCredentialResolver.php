<?php

require_once __DIR__ . '/DatabaseCredentialEncryption.php';

class DatabaseCredentialResolver
{
    public static function usesEncryption($password): bool
    {
        return is_array($password)
            && ($password['encrypted'] ?? null) === true;
    }

    public static function encryptionKeyIsAvailable(): bool
    {
        $key = getenv(DatabaseCredentialEncryption::ENVIRONMENT_VARIABLE);

        return $key !== false && trim($key) !== '';
    }

    public static function resolve($password): string
    {
        if (is_string($password)) {
            return $password;
        }

        if (is_array($password)) {
            return (new DatabaseCredentialEncryption())->decryptPassword($password);
        }

        throw new DatabaseCredentialException('Invalid database password configuration.');
    }
}
