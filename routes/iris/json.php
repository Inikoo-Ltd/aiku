<?php

/*
 * author Arya Permana - Kirin
 * created on 05-06-2025-14h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

use App\Actions\Catalogue\Product\Json\GetIrisInStockProductsInCollection;
use App\Actions\Catalogue\Product\Json\GetIrisInStockProductsInProductCategory;
use App\Actions\Catalogue\Product\Json\GetIrisOutOfStockProductsInCollection;
use App\Actions\Catalogue\Product\Json\GetIrisOutOfStockProductsInProductCategory;
use App\Actions\Catalogue\Product\Json\GetIrisPortfoliosInCollection;
use App\Actions\Catalogue\Product\Json\GetIrisPortfoliosInProductCategory;
use App\Actions\Catalogue\Product\Json\GetIrisProductsInCollection;
use App\Actions\Catalogue\Product\Json\GetIrisProductsInProductCategory;
use App\Actions\Helpers\Brand\Json\GetIrisBrands;
use App\Actions\Helpers\Tag\Json\GetIrisTags;
use App\Actions\Retina\Dropshipping\CustomerSalesChannel\UI\IndexDropshippingCustomerSalesChannels;
use Illuminate\Support\Facades\Route;

Route::middleware(["retina-auth:retina"])->group(function () {
    Route::get('product-category/{productCategory:id}/portfolio-data', GetIrisPortfoliosInProductCategory::class)->name('product_category.portfolio_data');
    Route::get('collection/{collection:id}/portfolio-data', GetIrisPortfoliosInCollection::class)->name('collection.portfolio_data');
});


Route::middleware(["iris-relax-auth:retina"])->group(function () {
    Route::get('tags', GetIrisTags::class)->name('tags.index');
    Route::get('brands', GetIrisBrands::class)->name('brands.index');
    Route::get('product-category/{productCategory:id}/products', GetIrisProductsInProductCategory::class)->name('product_category.products.index');
    Route::get('product-category/{productCategory:id}/in-stock-products', GetIrisInStockProductsInProductCategory::class)->name('product_category.in_stock_products.index');
    Route::get('product-category/{productCategory:id}/out-of-stock-products', GetIrisOutOfStockProductsInProductCategory::class)->name('product_category.out_of_stock_products.index');
    Route::get('collection/{collection:id}/products', GetIrisProductsInCollection::class)->name('collection.products.index');
    Route::get('collection/{collection:id}/in-stock-products', GetIrisInStockProductsInCollection::class)->name('collection.in_stock_products.index');
    Route::get('collection/{collection:id}/out-of-stock-products', GetIrisOutOfStockProductsInCollection::class)->name('collection.out_of_stock_products.index');


    Route::get('channels', IndexDropshippingCustomerSalesChannels::class)->name('channels.index');
});
