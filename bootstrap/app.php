<?php

if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null) === 'production') {
    error_reporting(E_ALL & ~E_DEPRECATED);
}

$app = new Illuminate\Foundation\Application(dirname(__DIR__));

$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);


return $app;
