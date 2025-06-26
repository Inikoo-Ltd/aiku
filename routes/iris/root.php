<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Feb 2024 16:50:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Web\Webpage\Iris\ShowIrisSitemap;
use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use Illuminate\Support\Facades\Route;

Route::get(".well-known/apple-developer-merchantid-domain-association", function () {
    return config('services.apple_pay.verification_string', '');
})->name("apple-pay-verification");


Route::middleware(["iris-relax-auth:retina"])->group(function () {
    Route::prefix("")->group(function () {
        Route::group([], __DIR__.'/system.php');
        Route::get('/sitemap.xml', ShowIrisSitemap::class)->name('iris_sitemap');
        Route::get('/{path?}', ShowIrisWebpage::class)->name('iris_webpage');
        Route::get('/{parentPath1}/{path}', [ShowIrisWebpage::class, 'deep1'])->name('iris_webpage.deep1');
        Route::get('/{parentPath1}/{parentPath2}/{path}', [ShowIrisWebpage::class, 'deep2'])->name('iris_webpage.deep2');
        Route::get('/{parentPath1}/{parentPath2}/{parentPath3}/{path}', [ShowIrisWebpage::class, 'deep3'])->name('iris_webpage.deep3');
        Route::get('/{parentPath1}/{parentPath2}/{parentPath3}/{parentPath4}/{path}', [ShowIrisWebpage::class, 'deep4'])->name('iris_webpage.deep4');
    });

    Route::prefix("models")
        ->name("models.")
        ->group(__DIR__."/models.php");
});

Route::prefix("disclosure")
    ->name("disclosure.")
    ->group(__DIR__."/disclosure.php");

Route::prefix("unsubscribe")
    ->name("unsubscribe.")
    ->group(__DIR__."/unsubscribe.php");

Route::prefix("json")
    ->name("json.")
    ->group(__DIR__."/json.php");
