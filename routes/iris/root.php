<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Feb 2024 16:50:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use Illuminate\Support\Facades\Route;
use App\Actions\Iris\UpdateIrisLocale;
use App\Actions\Web\Webpage\Iris\ShowIrisSitemap;
use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Actions\Accounting\Invoice\IrisPdfInvoice;
use App\Actions\Iris\Catalogue\DownloadIrisProduct;
use App\Actions\Helpers\Media\UI\DownloadAttachment;
use App\Actions\Web\Webpage\Iris\ShowIrisSubSitemap;
use App\Actions\Web\Webpage\Iris\ShowIrisWebpagesList;
use App\Actions\Web\Website\LlmsTxt\ServeLlmsTxt;
use App\Actions\Web\Webpage\Iris\ShowIrisBlogDashboard;
use App\Actions\Comms\Unsubscribe\ShowUnsubscribeFromAurora;
use App\Actions\Accounting\Payment\CheckoutCom\ReceiveCheckoutComPaymentWebhook;

Route::name('webhooks.')->group(function () {
    Route::any('webhooks/checkout-com-payment', ReceiveCheckoutComPaymentWebhook::class)->name('checkout_com_payment');
});

Route::get('/health-ping', function () {
    return response('OK', 200);
});

Route::get('wi/{image}', function () {
    return redirect('/image_not_found.png');
})->where('image', '.*')->name('wi.not_found');


Route::get(".well-known/apple-developer-merchantid-domain-association", function () {
    return config('services.apple_pay.verification_string', '');
})->name("apple-pay-verification");

Route::prefix("disclosure")
    ->name("disclosure.")
    ->group(__DIR__ . "/disclosure.php");

Route::prefix("unsubscribe")
    ->name("unsubscribe.")
    ->group(__DIR__ . "/unsubscribe.php");


Route::get('/unsubscribe.php', ShowUnsubscribeFromAurora::class)->name('unsubscribe.aurora');


Route::prefix("json")
    ->name("json.")
    ->group(__DIR__ . "/json.php");

Route::patch('/locale/{locale}', UpdateIrisLocale::class)->name('locale.update');
Route::middleware(["iris-relax-auth:retina"])->group(function () {
    Route::prefix("models")
        ->name("models.")
        ->group(__DIR__ . "/models.php");


    Route::get('data-feed.csv', DownloadIrisProduct::class)->name('shop.data_feed');
    Route::get('{productCategory}/data-feed.csv', [DownloadIrisProduct::class, 'inProductCategory'])->name('product_category.data_feed');

    Route::prefix("catalogue")
        ->name("catalogue.")
        ->group(__DIR__ . "/catalogue.php");


    Route::prefix("")->group(function () {
        Route::group([], __DIR__ . '/system.php');
        Route::get('/sitemap.xml', ShowIrisSitemap::class)->name('iris_sitemap');
        Route::get('/sitemaps/{sitemapType}.xml', ShowIrisSubSitemap::class)
            ->where('sitemapType', 'products|departments|sub_departments|families|contents|blogs|pages|collections')
            ->name('iris_sitemap_sub');
        Route::get('/warming_base.txt', [ShowIrisWebpagesList::class, 'base'])->name('warming.base');
        Route::get('/warming_families.txt', [ShowIrisWebpagesList::class, 'families'])->name('warming.families');
        Route::get('/warming_products.txt', [ShowIrisWebpagesList::class, 'products'])->name('warming.products');

        Route::get('/invoice/{invoice:ulid}', IrisPdfInvoice::class)->name('iris_invoice');
        Route::get('/attachment/{media:ulid}', DownloadAttachment::class)->name('iris_attachment');
        Route::get('/blog', ShowIrisBlogDashboard::class)->name('iris_blog');

        // LLMs.txt for AI crawlers
        Route::get('/llms.txt', ServeLlmsTxt::class)->name('iris_llms_txt');



        Route::get('/{path?}', ShowIrisWebpage::class)->name('iris_webpage');
        Route::get('/{parentPath1}/{path}', [ShowIrisWebpage::class, 'deep1'])->name('iris_webpage.deep1');
        Route::get('/{parentPath1}/{parentPath2}/{path}', [ShowIrisWebpage::class, 'deep2'])->name('iris_webpage.deep2');
        Route::get('/{parentPath1}/{parentPath2}/{parentPath3}/{path}', [ShowIrisWebpage::class, 'deep3'])->name('iris_webpage.deep3');
        Route::get('/{parentPath1}/{parentPath2}/{parentPath3}/{parentPath4}/{path}', [ShowIrisWebpage::class, 'deep4'])->name('iris_webpage.deep4');
    });
    // do not put any route below here, put it above {parentPath1}/... routes

});
