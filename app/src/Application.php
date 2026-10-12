<?php

declare(strict_types=1);

namespace App;

use App\Config\Config;
use App\Database\Database;
use App\Logging\LoggerFactory;
use App\Security\Encrypter;
use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Support\Env;
use Monolog\Logger;

/**
 * Application bootstrap: .env -> params (APP_ENV + APP_CONFIG_KEY) -> config -> Monolog -> Eloquent.
 * Used by public/*.php, bin/migrate and bin/config.
 */
final class Application
{
    /**
     * @param array{app:array, database:array, logging:array} $config
     */
    private function __construct(
        public readonly string $basePath,
        public readonly array $config,
        public readonly LoggerFactory $loggers,
        public readonly Logger $log,
        public readonly Logger $httpLog,
        public readonly Capsule $db,
        public readonly Config $params,
    ) {
    }

    /**
     * Читает .env (если есть; реальные переменные окружения имеют приоритет).
     * Нужно вызывать до любого обращения к Env::get().
     */
    public static function loadEnv(string $basePath): void
    {
        Dotenv::create(Env::getRepository(), $basePath)->safeLoad();
    }

    /** Объект шифрования по ключу из APP_CONFIG_KEY; null, если ключ не задан. */
    public static function encrypter(): ?Encrypter
    {
        $key = Env::get('APP_CONFIG_KEY');

        return is_string($key) && trim($key) !== '' ? Encrypter::fromString($key) : null;
    }

    /** Текущее окружение из APP_ENV (обязательная переменная). */
    public static function environment(): string
    {
        $env = Env::get('APP_ENV');
        if (!is_string($env) || trim($env) === '') {
            throw new \RuntimeException('APP_ENV is not set. Set it to one of the environments from config/params.php (e.g. local).');
        }

        return trim($env);
    }

    /** Статический конфиг config/params.php для текущего окружения. */
    public static function loadParams(string $basePath): Config
    {
        self::loadEnv($basePath);

        return Config::fromFile($basePath . '/config/params.php', self::environment(), self::encrypter());
    }

    public static function boot(string $basePath): self
    {
        $params = self::loadParams($basePath);

        // config/*.php получают $params и собирают итоговые настройки:
        // переменная окружения > config/params.php > значение по умолчанию
        $load = static fn (string $file) => (static fn (Config $params) => require $file)($params);

        $config = [
            'app' => $load($basePath . '/config/app.php'),
            'database' => $load($basePath . '/config/database.php'),
            'logging' => $load($basePath . '/config/logging.php'),
        ];

        $loggers = new LoggerFactory($config['logging']);
        $log = $loggers->make('app');
        $httpLog = $log->withName('http'); // same handler and processors, different channel

        $db = Database::boot(
            $config['database'],
            $config['logging']['log_queries'] ? $log : null,
        );

        return new self($basePath, $config, $loggers, $log, $httpLog, $db, $params);
    }
}
