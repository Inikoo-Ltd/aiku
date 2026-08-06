<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026 18:10:00 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Support\Facades\Route;

test('actions passing a route bound organisation to initialisation declare it in the uri', function () {
    $unbound = collect(Route::getRoutes()->getRoutes())
        ->reject(fn ($route) => str_contains($route->uri(), '{organisation}'))
        ->map(fn ($route) => $route->getAction('controller'))
        ->filter(fn ($controller) => is_string($controller) && str_starts_with($controller, 'App\Actions\\') && !str_contains($controller, '@'))
        ->unique()
        ->filter(fn ($action) => method_exists($action, 'asController'))
        ->filter(function ($action) {
            $method = new ReflectionMethod($action, 'asController');

            $parameter = collect($method->getParameters())
                ->first(fn ($parameter) => (string)$parameter->getType() === \App\Models\SysAdmin\Organisation::class);

            if (!$parameter) {
                return false;
            }

            $body = implode("\n", array_slice(
                file($method->getFileName()),
                $method->getStartLine(),
                $method->getEndLine() - $method->getStartLine()
            ));

            return preg_match('/initialisation\(\s*\$'.$parameter->getName().'\b/', $body) === 1;
        });

    expect($unbound->values()->all())->toBe([]);
});
