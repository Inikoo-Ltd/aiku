<?php

/*
 * author Arya Permana - Kirin
 * created on 05-06-2025-14h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

use Illuminate\Support\Facades\Route;
use App\Actions\Iris\IrisLogWebUserRequest;
use App\Actions\Helpers\Tag\Json\GetIrisTags;
use App\Actions\Iris\Json\GetIrisFirstHitData;
use App\Actions\Iris\Basket\FetchIrisEcomBasket;
use App\Actions\Helpers\Brand\Json\GetIrisBrands;
use App\Actions\Helpers\Tag\Json\GetIrisShopTags;
use App\Actions\Iris\Json\GetRetinaEcomCustomerData;
use App\Actions\Helpers\Brand\Json\GetIrisShopBrands;
use App\Actions\CRM\WebUser\Retina\Json\GetRedirectUrl;
use App\Actions\Catalogue\Product\Json\GetIrisLastOrderedProducts;
use App\Actions\Catalogue\Product\Json\GetIrisProductEcomOrdering;
use App\Actions\Catalogue\Product\Json\GetIrisProductsInCollection;
use App\Actions\Catalogue\Product\Json\GetIrisPortfoliosInCollection;
use App\Actions\Catalogue\Product\Json\GetIrisProductsInProductCategory;
use App\Actions\Catalogue\Product\Json\GetIrisBasketTransactionsInProduct;
use App\Actions\Catalogue\Product\Json\GetIrisInStockProductsInCollection;
use App\Actions\Catalogue\Product\Json\GetIrisPortfoliosInProductCategory;
use App\Actions\Catalogue\Product\Json\GetIrisBasketTransactionsInCollection;
use App\Actions\Catalogue\Product\Json\GetIrisOutOfStockProductsInCollection;
use App\Actions\Catalogue\Product\Json\GetIrisInStockProductsInProductCategory;
use App\Actions\Catalogue\Product\Json\GetIrisBasketTransactionsInProductCategory;
use App\Actions\Catalogue\Product\Json\GetIrisOutOfStockProductsInProductCategory;
use App\Actions\Dropshipping\CustomerSalesChannel\Json\GetCustomerProductSalesChannelIds;
use App\Actions\Dropshipping\CustomerSalesChannel\Json\GetCustomerCollectionSalesChannelIds;
use App\Actions\Dropshipping\CustomerSalesChannel\Json\GetCustomerProductCategorySalesChannelIds;
use App\Actions\Retina\Dropshipping\CustomerSalesChannel\UI\IndexRetinaDropshippingCustomerSalesChannels;

Route::middleware(["retina-auth:retina"])->group(function () {
    Route::get('product-category/{productCategory:id}/portfolio-data', GetIrisPortfoliosInProductCategory::class)->name('product_category.portfolio_data');
    Route::get('product-category/{productCategory:id}/transaction-data', GetIrisBasketTransactionsInProductCategory::class)->name('product_category.transaction_data');
    Route::get('product/{product:id}/transaction-data', GetIrisBasketTransactionsInProduct::class)->name('product.transaction_data')->withoutScopedBindings();

    Route::get('collection/{collection:id}/transaction-data', GetIrisBasketTransactionsInCollection::class)->name('collection.transaction_data');
});


Route::middleware(["iris-relax-auth:retina"])->group(function () {
    Route::get('canonical-redirect', GetRedirectUrl::class)->name('canonical_redirect');


    Route::get('first-hit', GetIrisFirstHitData::class)->name('first_hit');
    Route::get('ecom-customer-data', GetRetinaEcomCustomerData::class)->name('ecom_customer_data');
    Route::get('hit', IrisLogWebUserRequest::class)->name('hit');

    Route::get('collection/{collection:id}/portfolio-data', GetIrisPortfoliosInCollection::class)->name('collection.portfolio_data');
    Route::get('tags', GetIrisTags::class)->name('tags.index');
    Route::get('brands', GetIrisBrands::class)->name('brands.index');

    Route::get('/fetch-basket', FetchIrisEcomBasket::class)->name('fetch_basket');


    Route::get('shop-tags', GetIrisShopTags::class)->name('shops.tags.index');
    Route::get('shop-brands', GetIrisShopBrands::class)->name('shops.brands.index');

    Route::get('product-category/{productCategory:id}/last-ordered-products', GetIrisLastOrderedProducts::class)->name('product_category.last-ordered-products.index');
    Route::get('product-category/{productCategory:id}/products', GetIrisProductsInProductCategory::class)->name('product_category.products.index');
    Route::get('product-category/{productCategory:id}/in-stock-products', GetIrisInStockProductsInProductCategory::class)->name('product_category.in_stock_products.index');
    Route::get('product-category/{productCategory:id}/out-of-stock-products', GetIrisOutOfStockProductsInProductCategory::class)->name('product_category.out_of_stock_products.index');
    Route::get('collection/{collection:id}/products', GetIrisProductsInCollection::class)->name('collection.products.index');
    Route::get('collection/{collection:id}/in-stock-products', GetIrisInStockProductsInCollection::class)->name('collection.in_stock_products.index');
    Route::get('collection/{collection:id}/out-of-stock-products', GetIrisOutOfStockProductsInCollection::class)->name('collection.out_of_stock_products.index');
    Route::get('customer/{customer:id}/product/{product:id}/channels', GetCustomerProductSalesChannelIds::class)->name('customer.product.channel_ids.index')->withoutScopedBindings();
    Route::get('customer/{customer:id}/product-category/{productCategory:id}/channels', GetCustomerProductCategorySalesChannelIds::class)->name('customer.product_category.channel_ids.index')->withoutScopedBindings();
    Route::get('customer/{customer:id}/collection/{collection:id}/channels', GetCustomerCollectionSalesChannelIds::class)->name('customer.collection.channel_ids.index')->withoutScopedBindings();

    Route::get('channels', IndexRetinaDropshippingCustomerSalesChannels::class)->name('channels.index');
    Route::get('product/{product:id}', GetIrisProductEcomOrdering::class)->name('product.ecom_ordering_data');
});
