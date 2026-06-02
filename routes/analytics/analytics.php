<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 30 May 2026 17:14:04 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Web\Website\Analytics\RecordWebsiteHit;
use Illuminate\Support\Facades\Route;
use Laravel\Nightwatch\Http\Middleware\Sample;

Route::post('analytics/hit', RecordWebsiteHit::class)->name('hit')->middleware(
    [
        'iris-relax-auth:retina',
        Sample::always()
    ]
);
