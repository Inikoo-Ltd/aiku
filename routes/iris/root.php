<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Feb 2024 16:50:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Accounting\Invoice\IrisPdfInvoice;
use App\Actions\Iris\Catalogue\DownloadIrisProduct;
use App\Actions\Iris\UpdateIrisLocale;
use App\Actions\Web\Webpage\Iris\IndexIrisBlogWebpages;
use App\Actions\Web\Webpage\Iris\ShowIrisBlogDashboard;
use App\Actions\Web\Webpage\Iris\ShowIrisBlogWebpage;
use App\Actions\Web\Webpage\Iris\ShowIrisSitemap;
use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use Illuminate\Support\Facades\Route;


Route::get('wi/{image}', function () {
    return redirect('/image_not_found.png');
})->where('image', '.*')->name('wi.not_found');
    

Route::get(".well-known/apple-developer-merchantid-domain-association", function () {
    return config('services.apple_pay.verification_string', '');
})->name("apple-pay-verification");

Route::prefix("disclosure")
    ->name("disclosure.")
    ->group(__DIR__."/disclosure.php");

Route::prefix("unsubscribe")
    ->name("unsubscribe.")
    ->group(__DIR__."/unsubscribe.php");

Route::prefix("json")
    ->name("json.")
    ->group(__DIR__."/json.php");

Route::patch('/locale/{locale}', UpdateIrisLocale::class)->name('locale.update');
Route::middleware(["iris-relax-auth:retina"])->group(function () {
    Route::prefix("models")
        ->name("models.")
        ->group(__DIR__."/models.php");


    Route::get('data-feed.csv', DownloadIrisProduct::class)->name('shop.data_feed');
    Route::get('{productCategory}/data-feed.csv', [DownloadIrisProduct::class, 'inProductCategory'])->name('product_category.data_feed');

    Route::prefix("catalogue")
        ->name("catalogue.")
        ->group(__DIR__."/catalogue.php");


    Route::prefix("")->group(function () {
        Route::group([], __DIR__.'/system.php');
        Route::get('/sitemap.xml', ShowIrisSitemap::class)->name('iris_sitemap');
        Route::get('/invoice/{invoice:ulid}', IrisPdfInvoice::class)->name('iris_invoice');
        Route::get('/blog', ShowIrisBlogDashboard::class)->name('iris_blog');
        Route::get('/blog/articles', IndexIrisBlogWebpages::class)->name('iris_blog.articles.index');
        Route::get('/blog/articles/{webpage}', ShowIrisBlogWebpage::class)->name('iris_blog.articles.show');

        Route::get('/{path?}', ShowIrisWebpage::class)->name('iris_webpage');
        Route::get('/{parentPath1}/{path}', [ShowIrisWebpage::class, 'deep1'])->name('iris_webpage.deep1');
        Route::get('/{parentPath1}/{parentPath2}/{path}', [ShowIrisWebpage::class, 'deep2'])->name('iris_webpage.deep2');
        Route::get('/{parentPath1}/{parentPath2}/{parentPath3}/{path}', [ShowIrisWebpage::class, 'deep3'])->name('iris_webpage.deep3');
        Route::get('/{parentPath1}/{parentPath2}/{parentPath3}/{parentPath4}/{path}', [ShowIrisWebpage::class, 'deep4'])->name('iris_webpage.deep4');
    });
    // do not put any route below here, put it above {parentPath1}/... routes

});
