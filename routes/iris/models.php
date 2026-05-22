<?php

/*
 * author Arya Permana - Kirin
 * created on 05-06-2025-14h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/


use App\Actions\Iris\Basket\StoreEcomBasketTransaction;
use App\Actions\Iris\Basket\UpdateEcomBasketTransaction;
use App\Actions\Iris\CRM\DeleteIrisBackInStockReminder;
use App\Actions\Iris\CRM\DeleteIrisPortfolioFavourites;
use App\Actions\Iris\CRM\StoreIrisBackInStockReminder;
use App\Actions\Iris\CRM\StoreIrisFavourites;
use App\Actions\Iris\CRM\UpdateIrisCustomer;
use App\Actions\Iris\Portfolio\DeleteIrisPortfolioFromMultiChannels;
use App\Actions\Iris\Portfolio\StoreIrisPortfolioToAllChannels;
use App\Actions\Iris\Portfolio\StoreIrisPortfolioToMultiChannels;
use App\Actions\Catalogue\Review\StoreReview;
use App\Actions\Catalogue\Review\UpdateReview;
use App\Actions\Retina\Dropshipping\Bundle\CalculateRetinaBundleItemPriceDetails;
use App\Actions\Retina\Dropshipping\Bundle\DeleteRetinaBundle;
use App\Actions\Retina\Dropshipping\Bundle\GenerateRetinaProductBundleDescription;
use App\Actions\Retina\Dropshipping\Bundle\GenerateRetinaProductBundleTitle;
use App\Actions\Retina\Dropshipping\Bundle\GenerateRetinaProductImages;
use App\Actions\Retina\Dropshipping\Bundle\StoreOrUpdateRetinaBundle;
use App\Actions\Retina\Dropshipping\Bundle\StoreRetinaBundle;
use App\Actions\Retina\Dropshipping\Bundle\UpdateRetinaBundle;
use App\Actions\Retina\Dropshipping\Bundle\UploadRetinaBundleProductImages;
use App\Actions\Retina\Dropshipping\Orders\UpdateRetinaOrderExtraPacking;
use App\Actions\Retina\Dropshipping\Orders\UpdateRetinaOrderGrGift;
use App\Actions\Retina\Dropshipping\Orders\UpdateRetinaOrderInsurance;
use App\Actions\Retina\Dropshipping\Orders\UpdateRetinaOrderPremiumDispatch;
use App\Actions\Retina\UnsubscribeAurora;
use App\Actions\Web\Website\Analytics\RecordWebsiteHit;
use Illuminate\Support\Facades\Route;

Route::post('record-hit', RecordWebsiteHit::class)->name('hit');
Route::post('unsubscribe-aurora', UnsubscribeAurora::class)->name('unsubscribe_aurora');

Route::post('portfolio-all-channels', StoreIrisPortfolioToAllChannels::class)->name('all_channels.portfolio.store');
Route::post('portfolio-multi-channels', StoreIrisPortfolioToMultiChannels::class)->name('multi_channels.portfolio.store');
Route::post('product-category/{productCategory:id}/portfolio-multi-channels', [StoreIrisPortfolioToMultiChannels::class, 'inProductCategory'])->name('multi_channels.product_category.portfolio.store');

Route::post('delete-portfolio-multi-channels', DeleteIrisPortfolioFromMultiChannels::class)->name('multi_channels.portfolio.delete');

Route::post('favourite/{product:id}', StoreIrisFavourites::class)->name('favourites.store');
Route::delete('un-favourite/{product:id}', DeleteIrisPortfolioFavourites::class)->name('favourites.delete');

Route::patch('customer/update', UpdateIrisCustomer::class)->name('customer.update');

Route::post('{product:id}/store-transaction', StoreEcomBasketTransaction::class)->name('transaction.store')->withoutScopedBindings();
Route::post('{transaction:id}/update-transaction', UpdateEcomBasketTransaction::class)->name('transaction.update')->withoutScopedBindings();

Route::post('remind-back-in-stock/{product:id}', StoreIrisBackInStockReminder::class)->name('remind_back_in_stock.store')->withoutScopedBindings();
Route::delete('remind-back-in-stock/{product:id}', DeleteIrisBackInStockReminder::class)->name('remind_back_in_stock.delete')->withoutScopedBindings();

Route::middleware(['retina-auth:retina'])->group(function () {
    Route::post('review/store', StoreReview::class)->name('review.store');
    Route::patch('review/{review:id}/update', UpdateReview::class)->name('review.update');
});

Route::name('order.')->prefix('order/{order:id}')->group(function () {
    Route::patch('update-gr-gift', UpdateRetinaOrderGrGift::class)->name('update_gr_gift');
    Route::patch('update-premium-dispatch', UpdateRetinaOrderPremiumDispatch::class)->name('update_premium_dispatch');
    Route::patch('update-extra-packing', UpdateRetinaOrderExtraPacking::class)->name('update_extra_packing');
    Route::patch('update-insurance', UpdateRetinaOrderInsurance::class)->name('update_insurance');
});

Route::name('dropshipping.')->prefix('dropshipping')->group(function () {
    Route::prefix('bundles')->name('bundles.')->group(function () {
        Route::post('title-generator', GenerateRetinaProductBundleTitle::class)->name('title.generate');
        Route::post('description-generator', GenerateRetinaProductBundleDescription::class)->name('description.generate');
    });

    Route::prefix('{customerSalesChannel:id}/bundles')->name('bundles.')->group(function () {
        Route::post('/', StoreRetinaBundle::class)->name('store');
        Route::post('store-or-update', StoreOrUpdateRetinaBundle::class)->name('store_or_update');
        Route::patch('{bundle:id}', UpdateRetinaBundle::class)->name('update')->withoutScopedBindings();
        Route::post('products/{product:id}/images-generator', GenerateRetinaProductImages::class)->name('products.images.generate')->withoutScopedBindings();
        Route::post('products/{product:id}/images', UploadRetinaBundleProductImages::class)->name('products.images.store')->withoutScopedBindings();
        Route::post('calculate-bundle-product', CalculateRetinaBundleItemPriceDetails::class)->name('products.calculate');

        Route::delete('{bundle:id}', DeleteRetinaBundle::class)->name('delete')->withoutScopedBindings();
    });
});
