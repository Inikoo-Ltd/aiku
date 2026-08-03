<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Feb 2024 11:51:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Dropshipping\Tiktok\User\UI\OnboardingTiktokUser;
use App\Actions\UI\AikuPublic\ShowHome;
use Illuminate\Support\Facades\Route;

Route::get('robots.txt', function () {
    return response(
        "User-agent: *\nTest: 1",
        200,
        [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]
    );
})->name('aiku_robots');

Route::get('/', ShowHome::class)->name('home');

Route::get('tiktok/onboarding', OnboardingTiktokUser::class)->name('tiktok.onboarding');
