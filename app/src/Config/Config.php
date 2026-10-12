<?php

declare(strict_types=1);

namespace App\Config;

use App\Security\Encrypter;
use InvalidArgumentException;
use RuntimeException;

/**
 * Статический конфиг приложения по окружениям.
 *
 * Файл (config/params.php) просто возвращает массив:
 *
 *     return [
 *         'common'     => [...],   // общие параметры
 *         'local'      => [...],   // + параметры окружения "local"
 *         'production' => [...],
 *     ];
 *
 * Итоговый массив = common, поверх которого накладывается блок текущего окружения.
 * Любая строка вида "enc:v1:..." расшифровывается налету при обращении к параметру
 * (Encrypter + ключ из APP_CONFIG_KEY), поэтому в git лежит только шифртекст.
 */
final class Config
{
    public const COMMON = 'common';

    /**
     * @param array<string, mixed> $params итоговые параметры (common + окружение), значения ещё зашифрованы
     * @param list<string> $environments все окружения, описанные в файле
     */
    private function __construct(
        private readonly string $env,
        private readonly array $params,
        private readonly array $environments,
        private readonly ?Encrypter $encrypter,
    ) {
    }

    public static function fromFile(string $file, string $env, ?Encrypter $encrypter = null): self
    {
        if (!is_file($file)) {
            throw new RuntimeException("Config file not found: {$file}");
        }

        $all = (static fn () => require $file)();
        if (!is_array($all)) {
            throw new RuntimeException("Config file {$file} must return an array.");
        }

        $environments = array_values(array_filter(array_keys($all), static fn ($k) => $k !== self::COMMON));
        if (!in_array($env, $environments, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unknown environment "%s". Set APP_ENV to one of: %s.',
                $env,
                implode(', ', $environments),
            ));
        }

        $params = array_replace_recursive($all[self::COMMON] ?? [], $all[$env] ?? []);

        return new self($env, $params, array_map('strval', $environments), $encrypter);
    }

    public function env(): string
    {
        return $this->env;
    }

    /** @return list<string> */
    public function environments(): array
    {
        return $this->environments;
    }

    public function hasEncrypter(): bool
    {
        return $this->encrypter !== null;
    }

    /** Значение по ключу с точками ("db.host"). Зашифрованные строки расшифровываются. */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->find($key, $found);

        return $found ? $this->reveal($value, $key) : $default;
    }

    public function has(string $key): bool
    {
        $this->find($key, $found);

        return $found;
    }

    /** Значение как есть (без расшифровки): для зашифрованных параметров это "enc:v1:...". */
    public function raw(string $key, mixed $default = null): mixed
    {
        $value = $this->find($key, $found);

        return $found ? $value : $default;
    }

    /**
     * Все параметры в плоском виде "a.b.c" => значение (без расшифровки).
     *
     * @return array<string, mixed>
     */
    public function flatten(): array
    {
        $result = [];
        $walk = static function (array $items, string $prefix) use (&$walk, &$result): void {
            foreach ($items as $k => $v) {
                $path = $prefix === '' ? (string) $k : $prefix . '.' . $k;
                if (is_array($v) && $v !== [] && !array_is_list($v)) {
                    $walk($v, $path);
                } else {
                    $result[$path] = $v;
                }
            }
        };
        $walk($this->params, '');

        return $result;
    }

    /** Зашифрован ли параметр (или лежит ли внутри него зашифрованное значение). */
    public function isEncrypted(string $key): bool
    {
        return $this->containsEncrypted($this->raw($key));
    }

    /** Ключи всех зашифрованных параметров. @return list<string> */
    public function encryptedKeys(): array
    {
        $keys = [];
        foreach ($this->flatten() as $key => $value) {
            if ($this->containsEncrypted($value)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /** @param bool $found */
    private function find(string $key, ?bool &$found): mixed
    {
        $value = $this->params;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                $found = false;

                return null;
            }
            $value = $value[$segment];
        }
        $found = true;

        return $value;
    }

    private function reveal(mixed $value, string $key): mixed
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$k] = $this->reveal($v, $key . '.' . $k);
            }

            return $value;
        }

        if (!Encrypter::isEncrypted($value)) {
            return $value;
        }

        if ($this->encrypter === null) {
            throw new RuntimeException(sprintf('Parameter "%s" is encrypted, but APP_CONFIG_KEY is not set.', $key));
        }

        try {
            return $this->encrypter->decrypt($value);
        } catch (RuntimeException $e) {
            throw new RuntimeException(sprintf('Parameter "%s": %s', $key, $e->getMessage()), 0, $e);
        }
    }

    private function containsEncrypted(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $v) {
                if ($this->containsEncrypted($v)) {
                    return true;
                }
            }

            return false;
        }

        return Encrypter::isEncrypted($value);
    }
}
