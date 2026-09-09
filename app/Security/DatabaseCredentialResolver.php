<?php

require_once __DIR__ . '/DatabaseCredentialEncryption.php';

class DatabaseCredentialResolver
{
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
