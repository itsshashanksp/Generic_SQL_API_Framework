<?php

require_once __DIR__ . '/DatabaseCredentialException.php';

class DatabaseCredentialEncryption
{
    public const ENVIRONMENT_VARIABLE = 'GENERIC_SQL_API_ENCRYPTION_KEY';
    public const VERSION = 1;
    public const ALGORITHM = 'AES-256-GCM';

    private const KEY_LENGTH = 32;
    private const NONCE_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private string $key;

    public function __construct(?string $encodedKey = null)
    {
        if ($encodedKey === null) {
            $environmentValue = getenv(self::ENVIRONMENT_VARIABLE);

            if ($environmentValue === false || trim($environmentValue) === '') {
                throw new DatabaseCredentialException(
                    'Database encryption key is not configured in '
                    . self::ENVIRONMENT_VARIABLE . '.'
                );
            }

            $encodedKey = $environmentValue;
        }

        $key = base64_decode(trim($encodedKey), true);

        if ($key === false || strlen($key) !== self::KEY_LENGTH) {
            throw new DatabaseCredentialException(
                'Database encryption key is invalid; '
                . self::ENVIRONMENT_VARIABLE
                . ' must contain a base64-encoded 32-byte key.'
            );
        }

        if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            throw new DatabaseCredentialException('The PHP OpenSSL extension is required for encrypted database credentials.');
        }

        $this->key = $key;
    }

    public function encryptPassword(string $password): array
    {
        try {
            $nonce = random_bytes(self::NONCE_LENGTH);
            $tag = '';
            $ciphertext = openssl_encrypt(
                $password,
                self::ALGORITHM,
                $this->key,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
                '',
                self::TAG_LENGTH
            );
        } catch (Throwable $exception) {
            throw new DatabaseCredentialException('Database credential encryption failed.');
        }

        if ($ciphertext === false || strlen($tag) !== self::TAG_LENGTH) {
            throw new DatabaseCredentialException('Database credential encryption failed.');
        }

        return [
            'encrypted' => true,
            'version' => self::VERSION,
            'algorithm' => self::ALGORITHM,
            'nonce' => base64_encode($nonce),
            'ciphertext' => base64_encode($ciphertext),
            'tag' => base64_encode($tag),
        ];
    }

    public function decryptPassword(array $encryptedPassword): string
    {
        $this->validateFormat($encryptedPassword);

        $nonce = $this->decodeComponent($encryptedPassword['nonce']);
        $ciphertext = $this->decodeComponent($encryptedPassword['ciphertext']);
        $tag = $this->decodeComponent($encryptedPassword['tag']);

        if (strlen($nonce) !== self::NONCE_LENGTH || strlen($tag) !== self::TAG_LENGTH) {
            throw new DatabaseCredentialException('Database credential decryption failed.');
        }

        try {
            $password = openssl_decrypt(
                $ciphertext,
                self::ALGORITHM,
                $this->key,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag
            );
        } catch (Throwable $exception) {
            throw new DatabaseCredentialException('Database credential decryption failed.');
        }

        if ($password === false) {
            throw new DatabaseCredentialException('Database credential decryption failed.');
        }

        return $password;
    }

    private function validateFormat(array $encryptedPassword): void
    {
        if (($encryptedPassword['encrypted'] ?? null) !== true) {
            throw new DatabaseCredentialException('Invalid encrypted database credential configuration.');
        }

        if (($encryptedPassword['version'] ?? null) !== self::VERSION) {
            throw new DatabaseCredentialException('Unsupported database credential encryption version.');
        }

        if (($encryptedPassword['algorithm'] ?? null) !== self::ALGORITHM) {
            throw new DatabaseCredentialException('Unsupported database credential encryption algorithm.');
        }

        foreach (['nonce', 'ciphertext', 'tag'] as $field) {
            if (!array_key_exists($field, $encryptedPassword) || !is_string($encryptedPassword[$field])) {
                throw new DatabaseCredentialException('Invalid encrypted database credential configuration.');
            }
        }
    }

    private function decodeComponent(string $value): string
    {
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            throw new DatabaseCredentialException('Database credential decryption failed.');
        }

        return $decoded;
    }
}
