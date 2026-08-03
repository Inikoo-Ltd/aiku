<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Aug 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Support\Facades\Route;

test('public iris routes binding on id only accept numbers', function () {
    $unguarded = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with((string)$route->getName(), 'iris.'))
        ->flatMap(function ($route) {
            return collect($route->bindingFields())
                ->filter(fn ($field) => $field === 'id')
                ->keys()
                ->reject(fn ($parameter) => ($route->wheres[$parameter] ?? null) === '[0-9]+')
                ->map(fn ($parameter) => $route->getName().' -> {'.$parameter.'}');
        });

    expect($unguarded->all())->toBe([]);
});
