<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;

/**
 * Шифрование/расшифровка строк через openssl_encrypt / openssl_decrypt (расширение openssl).
 *
 * Алгоритм: AES-256-GCM (шифрование + проверка целостности).
 * Формат зашифрованного значения (его можно спокойно хранить в git):
 *
 *     enc:v1:<base64( iv[12] . tag[16] . ciphertext )>
 *
 * Ключ (32 байта) в код и в git не попадает — он приходит из переменной окружения
 * APP_CONFIG_KEY в формате "base64:...". Сгенерировать: `php bin/config key:generate`.
 */
final class Encrypter
{
    public const PREFIX = 'enc:v1:';

    private const CIPHER = 'aes-256-gcm';
    private const KEY_BYTES = 32;
    private const IV_BYTES = 12;
    private const TAG_BYTES = 16;

    /** @param string $key сырой ключ ровно из 32 байт */
    public function __construct(private readonly string $key)
    {
        if (!extension_loaded('openssl')) {
            throw new RuntimeException('PHP extension "openssl" is required for encryption.');
        }
        if (strlen($key) !== self::KEY_BYTES) {
            throw new RuntimeException(sprintf('Encryption key must be exactly %d bytes.', self::KEY_BYTES));
        }
    }

    /** Создаёт объект из строки вида "base64:...." (как лежит в APP_CONFIG_KEY). */
    public static function fromString(string $value): self
    {
        $value = trim($value);
        if (!str_starts_with($value, 'base64:')) {
            throw new RuntimeException('APP_CONFIG_KEY must have the form "base64:<32 random bytes in base64>". Generate one: php bin/config key:generate');
        }

        $raw = base64_decode(substr($value, 7), true);
        if ($raw === false) {
            throw new RuntimeException('APP_CONFIG_KEY is not valid base64.');
        }

        return new self($raw);
    }

    /** Новый случайный ключ в формате для APP_CONFIG_KEY. */
    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(self::KEY_BYTES));
    }

    public static function isEncrypted(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    public function encrypt(string $plain): string
    {
        $iv = random_bytes(self::IV_BYTES);
        $tag = '';

        $cipherText = openssl_encrypt($plain, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_BYTES);
        if ($cipherText === false) {
            throw new RuntimeException('openssl_encrypt failed: ' . (openssl_error_string() ?: 'unknown error'));
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipherText);
    }

    public function decrypt(string $payload): string
    {
        if (!self::isEncrypted($payload)) {
            throw new RuntimeException('Value is not encrypted (expected prefix "' . self::PREFIX . '").');
        }

        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < self::IV_BYTES + self::TAG_BYTES) {
            throw new RuntimeException('Encrypted value is malformed.');
        }

        $iv = substr($raw, 0, self::IV_BYTES);
        $tag = substr($raw, self::IV_BYTES, self::TAG_BYTES);
        $cipherText = substr($raw, self::IV_BYTES + self::TAG_BYTES);

        $plain = openssl_decrypt($cipherText, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new RuntimeException('Cannot decrypt the value: wrong APP_CONFIG_KEY or the value was modified.');
        }

        return $plain;
    }
}
