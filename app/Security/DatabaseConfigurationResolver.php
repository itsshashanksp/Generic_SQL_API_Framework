<?php

require_once __DIR__ . '/DatabaseCredentialEncryption.php';
require_once __DIR__ . '/DatabaseCredentialResolver.php';

class DatabaseConfigurationResolver
{
    public static function load(string $configPath): array
    {
        return self::resolve(self::readStored($configPath));
    }

    public static function readStored(string $configPath): array
    {
        if (!is_file($configPath)) {
            throw new DatabaseCredentialException('Database configuration file was not found.');
        }

        $contents = @file_get_contents($configPath);
        if ($contents === false) {
            throw new DatabaseCredentialException('Unable to read database configuration.');
        }

        try {
            $configuration = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new DatabaseCredentialException('Invalid database configuration.');
        }

        if (!is_array($configuration) || array_is_list($configuration)) {
            throw new DatabaseCredentialException('Invalid database configuration.');
        }

        return $configuration;
    }

    public static function resolve(array $configuration): array
    {
        if (self::usesEncryption($configuration)) {
            $configuration = (new DatabaseCredentialEncryption())->decryptConfiguration($configuration);
        }

        self::validate($configuration);
        return $configuration;
    }

    public static function usesEncryption(array $configuration): bool
    {
        return ($configuration['encrypted'] ?? null) === true;
    }

    public static function encryptionKeyIsAvailable(): bool
    {
        $key = getenv(DatabaseCredentialEncryption::ENVIRONMENT_VARIABLE);

        return $key !== false && trim($key) !== '';
    }

    private static function validate(array $configuration): void
    {
        if (!is_string($configuration['provider'] ?? null)
            || trim($configuration['provider']) === '') {
            throw new DatabaseCredentialException('Invalid database configuration.');
        }

        foreach (['server', 'database', 'authentication', 'username', 'driver'] as $field) {
            if (array_key_exists($field, $configuration) && !is_string($configuration[$field])) {
                throw new DatabaseCredentialException('Invalid database configuration.');
            }
        }
        if (array_key_exists('port', $configuration)
            && !is_string($configuration['port'])
            && !is_int($configuration['port'])
            && $configuration['port'] !== null) {
            throw new DatabaseCredentialException('Invalid database configuration.');
        }
        if (array_key_exists('password', $configuration)
            && !is_string($configuration['password'])
            && !DatabaseCredentialResolver::usesEncryption($configuration['password'])) {
            throw new DatabaseCredentialException('Invalid database configuration.');
        }
        if (array_key_exists('options', $configuration) && !is_array($configuration['options'])) {
            throw new DatabaseCredentialException('Invalid database configuration.');
        }
    }
}
