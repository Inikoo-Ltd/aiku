<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Sept 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Http\Middleware\HandleInertiaGrpRequests;
use App\Http\Middleware\HandleIrisInertiaRequests;
use App\Http\Middleware\HandleRetinaInertiaRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;

it('gives iris, retina and grp distinct inertia asset versions so cross app responses force a full page load', function () {
    Vite::shouldReceive('manifestHash')->andReturnUsing(fn (string $buildDirectory) => "hash-$buildDirectory");

    $request = Request::create('/');

    $versions = [
        (new HandleIrisInertiaRequests())->version($request),
        (new HandleRetinaInertiaRequests())->version($request),
        (new HandleInertiaGrpRequests())->version($request),
    ];

    expect($versions)->toBe(['hash-iris', 'hash-retina', 'hash-grp']);
});
