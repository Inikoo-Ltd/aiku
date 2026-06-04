<?php

/*
 * author Arya Permana - Kirin
 * created on 05-06-2025-14h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/


use App\Actions\Iris\Basket\GetIrisBasketTransactionProductData;
use App\Actions\Iris\Json\GetIrisFooterData;
use Illuminate\Support\Facades\Route;
use App\Actions\Helpers\Tag\Json\GetIrisTags;
use App\Actions\Iris\Json\GetIrisSidebarData;
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
use App\Actions\Catalogue\Product\Json\GetIrisBasketTransactions;
use App\Actions\Catalogue\Product\Json\GetIrisBasketTransactionsInProductCategory;
use App\Actions\Catalogue\Product\Json\GetIrisOutOfStockProductsInProductCategory;
use App\Actions\Catalogue\Product\Json\GetProductsOfVariant;
use App\Actions\Catalogue\Product\Json\GetVariantAndProducts;
use App\Actions\Catalogue\Shop\Json\FetchProductReviewThirdParty;
use App\Actions\Dropshipping\CustomerSalesChannel\Json\GetCustomerProductSalesChannelIds;
use App\Actions\Dropshipping\CustomerSalesChannel\Json\GetCustomerCollectionSalesChannelIds;
use App\Actions\Dropshipping\CustomerSalesChannel\Json\GetCustomerProductCategorySalesChannelIds;
use App\Actions\Iris\Catalogue\FetchFamilyListCustomSorted;
use App\Actions\Retina\Dropshipping\CustomerSalesChannel\UI\IndexRetinaDropshippingCustomerSalesChannels;
use App\Actions\Web\Luigi\LuigiBoxGetProductDetail;
use App\Actions\Web\Luigi\LuigiBoxRecommendation;
use App\Actions\Iris\Json\GetBanner;

Route::middleware(["retina-auth:retina"])->group(function () {
    Route::get('basket/transaction-data', GetIrisBasketTransactions::class)->name('basket.transaction_data');

    Route::get('product-category/{productCategory:id}/portfolio-data', GetIrisPortfoliosInProductCategory::class)->name('product_category.portfolio_data')->whereNumber('productCategory');
    Route::get('product-category/{productCategory:id}/transaction-data', GetIrisBasketTransactionsInProductCategory::class)->name('product_category.transaction_data')->whereNumber('productCategory');
    Route::get('product/{product:id}/transaction-data', GetIrisBasketTransactionsInProduct::class)->name('product.transaction_data')->withoutScopedBindings()->whereNumber('product');

    Route::get('collection/{collection:id}/transaction-data', GetIrisBasketTransactionsInCollection::class)->name('collection.transaction_data')->whereNumber('collection');
});


Route::middleware(["iris-relax-auth:retina"])->group(function () {
    Route::get('basket-transaction-product-data/{transaction:id}', GetIrisBasketTransactionProductData::class)->name('basket_transaction_product_data')->whereNumber('transaction');

    Route::get('canonical-redirect', GetRedirectUrl::class)->name('canonical_redirect');

    Route::get('/sidebar', GetIrisSidebarData::class)->name('sidebar');
    Route::get('/footer', GetIrisFooterData::class)->name('footer');

    Route::get('first-hit', GetIrisFirstHitData::class)->name('first_hit');
    Route::get('ecom-customer-data', GetRetinaEcomCustomerData::class)->name('ecom_customer_data');

    Route::get('collection/{collection:id}/portfolio-data', GetIrisPortfoliosInCollection::class)->name('collection.portfolio_data')->whereNumber('collection');
    Route::get('tags', GetIrisTags::class)->name('tags.index');
    Route::get('brands', GetIrisBrands::class)->name('brands.index');

    Route::get('/fetch-basket', FetchIrisEcomBasket::class)->name('fetch_basket');


    Route::get('shop-tags', GetIrisShopTags::class)->name('shops.tags.index');
    Route::get('shop-brands', GetIrisShopBrands::class)->name('shops.brands.index');

    Route::get('product-category/{productCategory:id}/last-ordered-products', GetIrisLastOrderedProducts::class)->name('product_category.last-ordered-products.index')->whereNumber('productCategory');
    Route::get('product-category/{productCategory:id}/products', GetIrisProductsInProductCategory::class)->name('product_category.products.index')->whereNumber('productCategory');
    Route::get('product-category/{productCategory:id}/in-stock-products', GetIrisInStockProductsInProductCategory::class)->name('product_category.in_stock_products.index')->whereNumber('productCategory');
    Route::get('product-category/{productCategory:id}/out-of-stock-products', GetIrisOutOfStockProductsInProductCategory::class)->name('product_category.out_of_stock_products.index')->whereNumber('productCategory');
    Route::get('collection/{collection:id}/products', GetIrisProductsInCollection::class)->name('collection.products.index')->whereNumber('collection');
    Route::get('collection/{collection:id}/in-stock-products', GetIrisInStockProductsInCollection::class)->name('collection.in_stock_products.index')->whereNumber('collection');
    Route::get('collection/{collection:id}/out-of-stock-products', GetIrisOutOfStockProductsInCollection::class)->name('collection.out_of_stock_products.index')->whereNumber('collection');
    Route::get('customer/{customer:id}/product/{product:id}/channels', GetCustomerProductSalesChannelIds::class)->name('customer.product.channel_ids.index')->withoutScopedBindings()->whereNumber(['customer', 'product']);
    Route::get('customer/{customer:id}/product-category/{productCategory:id}/channels', GetCustomerProductCategorySalesChannelIds::class)->name('customer.product_category.channel_ids.index')->withoutScopedBindings()->whereNumber(['customer', 'productCategory']);
    Route::get('customer/{customer:id}/collection/{collection:id}/channels', GetCustomerCollectionSalesChannelIds::class)->name('customer.collection.channel_ids.index')->withoutScopedBindings()->whereNumber(['customer', 'collection']);

    Route::get('channels', IndexRetinaDropshippingCustomerSalesChannels::class)->name('channels.index');
    Route::get('product/{product:id}', GetIrisProductEcomOrdering::class)->name('product.ecom_ordering_data')->whereNumber('product');

    Route::get('variant/{variant:id}/products', GetProductsOfVariant::class)->name('products.variant')->whereNumber('variant');
    Route::get('variant/{variant:id}', GetVariantAndProducts::class)->name('variant')->whereNumber('variant');
    Route::post('luigi-product-recommendation', LuigiBoxRecommendation::class)->name('luigi.product_recommendation');
    Route::get('luigi-product-details', LuigiBoxGetProductDetail::class)->name('luigi.product_details');

    Route::get('banner/{banner:id}', GetBanner::class)->name('get_banner')->whereNumber('banner');

    // Reviews
    Route::get('product/{product:id}/reviews-third-party', FetchProductReviewThirdParty::class)->name('reviews.third_party.product_review')->whereNumber('product');

    // Families Custom Sort
    Route::get('{webpage:slug}/{productCategory}/families', FetchFamilyListCustomSorted::class)->name('website.category.family_list_sorted');
});
