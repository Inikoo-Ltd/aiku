<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026 18:10:00 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\Route;

test('actions reached without an organisation route segment do not ask for one', function () {
    $unbound = collect(Route::getRoutes()->getRoutes())
        ->reject(fn ($route) => str_contains($route->uri(), '{organisation}'))
        ->map(fn ($route) => $route->getAction('controller'))
        ->filter(fn ($controller) => is_string($controller) && str_starts_with($controller, 'App\Actions\\') && !str_contains($controller, '@'))
        ->unique()
        ->filter(fn ($action) => method_exists($action, 'asController'))
        ->filter(function ($action) {
            return collect((new ReflectionMethod($action, 'asController'))->getParameters())
                ->contains(fn ($parameter) => (string)$parameter->getType() === Organisation::class);
        });

    expect($unbound->values()->all())->toBe([]);
});
