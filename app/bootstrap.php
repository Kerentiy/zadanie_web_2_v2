<?php

declare(strict_types=1);

use App\Application;
use App\Http\HttpLogger;

require __DIR__ . '/vendor/autoload.php';

/**
 * Общий старт для всех HTTP-точек входа (public/*.php):
 * .env -> config -> Monolog -> Eloquent, затем включается логирование
 * КАЖДОГО входящего запроса вместе с телом ответа (канал "http").
 */
$app = Application::boot(__DIR__);

HttpLogger::register($app->httpLog, $app->config['logging']['http']);

header('X-Request-Id: ' . $app->loggers->requestId());

return $app;
