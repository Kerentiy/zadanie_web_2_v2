<?php

declare(strict_types=1);

/**
 * Статический конфиг приложения по окружениям.
 *
 * Окружение выбирается переменной APP_ENV (local | staging | production),
 * ключ шифрования - переменной APP_CONFIG_KEY. Оба значения НЕ хранятся в git.
 *
 * Итог = 'common' + блок текущего окружения (array_replace_recursive).
 * Переменные окружения (DB_PASSWORD, LOG_LEVEL ...) имеют приоритет над этим файлом -
 * так их удобно переопределять в Docker/CI.
 *
 * Секреты кладём только в зашифрованном виде "enc:v1:...". Получить такое значение:
 *
 *     php bin/config encrypt "мой-секрет"        # (make encrypt value=...)
 *
 * и вставить результат сюда. Ключ при этом должен быть тем же, что и на сервере.
 * Читать в коде:  $app->params->get('db.password')  - расшифруется автоматически.
 */
return [
    'common' => [
        'app' => [
            'name' => 'Kanban Board',
        ],
        'db' => [
            'host' => 'postgres',
            'port' => 5432,
            'name' => 'app',
            'user' => 'app',
            // 'password' - в common не задаём: локально его даёт DB_PASSWORD из compose,
            // на staging/production - зашифрованное значение ниже (или DB_PASSWORD).
        ],
        'log' => [
            'level' => 'info',
            'queries' => false,
        ],
    ],

    'local' => [
        'app' => ['debug' => true],
        'log' => ['level' => 'debug', 'queries' => true],
    ],

    'staging' => [
        'app' => ['debug' => false],
        'log' => ['level' => 'info'],
        // 'db' => ['password' => 'enc:v1:...'],
    ],

    'production' => [
        'app' => ['debug' => false],
        'log' => ['level' => 'warning'],
        // 'db' => ['password' => 'enc:v1:...'],
    ],
];
