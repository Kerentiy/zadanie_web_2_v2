<?php

declare(strict_types=1);

use App\Config\Config;
use Illuminate\Support\Env;

/** @var Config $params статический конфиг (config/params.php) для текущего окружения */

return [
    'name' => Env::get('APP_NAME') ?? $params->get('app.name', 'PHP Eloquent Skeleton'),
    'env' => $params->env(),
    'debug' => filter_var(Env::get('APP_DEBUG') ?? $params->get('app.debug', false), FILTER_VALIDATE_BOOLEAN),
];
