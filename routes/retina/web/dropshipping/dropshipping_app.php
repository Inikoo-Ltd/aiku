<?php

/*
 * Author: Vika Aqordi <aqordivika@yahoo.co.id>
 * Created on: 19-08-2024, Bali, Indonesia
 * Github: https://github.com/aqordeon
 * Copyright: 2024
 *
*/

use App\Actions\Dropshipping\Amazon\AuthorizeRetinaAmazonUser;
use App\Actions\Dropshipping\Amazon\CallbackRetinaAmazonUser;
use App\Actions\Dropshipping\Ebay\AuthorizeRetinaEbayUser;
use App\Actions\Dropshipping\Ebay\CallbackRetinaEbayUser;
use App\Actions\Dropshipping\Magento\StoreMagentoUser;
use App\Actions\Dropshipping\ShopifyUser\DeleteRetinaShopifyUser;
use App\Actions\Dropshipping\ShopifyUser\StoreShopifyUser;
use App\Actions\Dropshipping\Tiktok\User\AuthenticateTiktokAccount;
use App\Actions\Dropshipping\WooCommerce\AuthorizeRetinaWooCommerceUser;
use App\Actions\Dropshipping\WooCommerce\Clients\GetRetinaCustomerClientFromWooCommerce;
use App\Actions\Fulfilment\Pallet\DownloadDropshippingClientTemplate;
use App\Actions\Retina\Accounting\MitSavedCard\UI\CreateMitSavedCard;
use App\Actions\Retina\Accounting\MitSavedCard\UI\ShowRetinaMitSavedCardsDashboard;
use App\Actions\Retina\Dropshipping\ApiToken\UI\ShowRetinaApiDropshippingDashboard;
use App\Actions\Retina\Dropshipping\Basket\UI\IndexRetinaBaskets;
use App\Actions\Retina\Dropshipping\Basket\UI\IndexRetinaDropshippingProductsForBasket;
use App\Actions\Retina\Dropshipping\Checkout\UI\ShowRetinaDropshippingCheckout;
use App\Actions\Retina\Dropshipping\Client\FetchRetinaCustomerClientFromShopify;
use App\Actions\Retina\Dropshipping\Client\UI\CreateRetinaCustomerClient;
use App\Actions\Retina\Dropshipping\Client\UI\EditRetinaCustomerClient;
use App\Actions\Retina\Dropshipping\Client\UI\IndexRetinaCustomerClients;
use App\Actions\Retina\Dropshipping\Client\UI\ShowRetinaCustomerClient;
use App\Actions\Retina\Dropshipping\CreateRetinaDropshippingCustomerSalesChannel;
use App\Actions\Retina\Dropshipping\CustomerSalesChannel\UI\IndexRetinaDropshippingCustomerSalesChannels;
use App\Actions\Retina\Dropshipping\Orders\IndexRetinaDropshippingOrders;
use App\Actions\Retina\Dropshipping\Orders\ShowRetinaDropshippingBasket;
use App\Actions\Retina\Dropshipping\Orders\ShowRetinaDropshippingOrder;
use App\Actions\Retina\Dropshipping\Portfolio\DownloadPortfolios;
use App\Actions\Retina\Dropshipping\Portfolio\IndexRetinaPortfolios;
use App\Actions\Retina\Dropshipping\Portfolio\ShowRetinaDropshippingPortfolio;
use App\Actions\Retina\Dropshipping\Product\UI\IndexRetinaFilteredProducts;
use App\Actions\Retina\Dropshipping\Product\UI\IndexRetinaProductsInDropshipping;
use App\Actions\Retina\Platform\EditRetinaCustomerSalesChannel;
use App\Actions\Retina\Platform\ShowRetinaCustomerSalesChannelDashboard;
use Illuminate\Support\Facades\Route;

Route::get('select-products-for-basket/{order:id}', IndexRetinaDropshippingProductsForBasket::class)->name('select_products_for_basket');


Route::prefix('sale-channels')->as('customer_sales_channels.')->group(function () {
    Route::get('/', IndexRetinaDropshippingCustomerSalesChannels::class)->name('index');
    Route::get('/create', CreateRetinaDropshippingCustomerSalesChannel::class)->name('create');
});

Route::prefix('platform')->as('platform.')->group(function () {
    Route::post('shopify-user', StoreShopifyUser::class)->name('shopify_user.store');
    Route::delete('shopify-user', DeleteRetinaShopifyUser::class)->name('shopify_user.delete');

    Route::post('wc-user/authorize', AuthorizeRetinaWooCommerceUser::class)->name('wc.authorize');
    Route::delete('wc-user', DeleteRetinaShopifyUser::class)->name('wc.delete');

    Route::post('ebay-user/authorize', AuthorizeRetinaEbayUser::class)->name('ebay.authorize');
    Route::get('ebay-user-callback', CallbackRetinaEbayUser::class)->name('ebay.callback');

    Route::post('amazon-user/authorize', AuthorizeRetinaAmazonUser::class)->name('amazon.authorize');
    Route::get('amazon-user-callback', CallbackRetinaAmazonUser::class)->name('amazon.callback');

    Route::post('magento/authorize', StoreMagentoUser::class)->name('magento.store');
});



Route::prefix('channels/{customerSalesChannel}')->as('customer_sales_channels.')->group(function () {
    Route::get('/', ShowRetinaCustomerSalesChannelDashboard::class)->name('show');
    Route::get('/edit', EditRetinaCustomerSalesChannel::class)->name('edit');
    Route::get('filtered-products', IndexRetinaFilteredProducts::class)->name('filtered_products.index');


    Route::prefix('basket')->as('basket.')->group(function () {
        Route::get('/', IndexRetinaBaskets::class)->name('index');
        Route::get('{order}', ShowRetinaDropshippingBasket::class)->name('show');
    });

    Route::prefix('client')->as('client.')->group(function () {
        Route::get('create', CreateRetinaCustomerClient::class)->name('create');
        Route::get('fetch', FetchRetinaCustomerClientFromShopify::class)->name('fetch');
        Route::get('wc-fetch', GetRetinaCustomerClientFromWooCommerce::class)->name('wc-fetch');
        Route::get('/', IndexRetinaCustomerClients::class)->name('index');
        Route::get('/client-upload-templates', DownloadDropshippingClientTemplate::class)->name('upload_templates');
        Route::get('/{customerClient}/edit', EditRetinaCustomerClient::class)->name('edit');
        Route::get('{customerClient}', ShowRetinaCustomerClient::class)->name('show');
    });

    Route::prefix('my-products')->as('portfolios.')->group(function () {
        Route::get('', IndexRetinaPortfolios::class)->name('index');
        Route::get('download', DownloadPortfolios::class)->name('download');
        Route::get('{portfolio}', ShowRetinaDropshippingPortfolio::class)->name('show');
        Route::get('products', IndexRetinaProductsInDropshipping::class)->name('products.index');
    });

    Route::prefix('orders')->as('orders.')->group(function () {
        Route::get('/', IndexRetinaDropshippingOrders::class)->name('index');
        Route::get('/{order}', ShowRetinaDropshippingOrder::class)->name('show');
    });

    Route::prefix('api')->as('api.')->group(function () {
        Route::get('/', ShowRetinaApiDropshippingDashboard::class)->name('dashboard');
    });
});


Route::prefix('tiktok')->name('tiktok.')->group(function () {
    Route::get('callback', AuthenticateTiktokAccount::class)->name('callback');
});

Route::prefix('saved-credit-cards')->name('mit_saved_cards.')->group(function () {
    Route::get('', ShowRetinaMitSavedCardsDashboard::class)->name('dashboard');
    Route::get('create', CreateMitSavedCard::class)->name('create');
});

Route::prefix('checkout')->name('checkout.')->group(function () {
    Route::get('{order}', ShowRetinaDropshippingCheckout::class)->name('show');
});
