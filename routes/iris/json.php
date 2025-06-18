<?php

/*
 * author Arya Permana - Kirin
 * created on 05-06-2025-14h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

use App\Actions\Catalogue\Product\Json\GetIrisOutOfStockProductsInProductCategory;
use App\Actions\Catalogue\Product\Json\GetIrisProductsInProductCategory;
use App\Actions\Retina\Dropshipping\CustomerSalesChannel\UI\IndexDropshippingCustomerSalesChannels;
use Illuminate\Support\Facades\Route;

Route::middleware(["iris-auth:retina"])->group(function () {
    Route::get('product-category/{productCategory}/products', GetIrisProductsInProductCategory::class)->name('product_category.products.index');
    Route::get('product-category/{productCategory}/out-of-stock-products', GetIrisOutOfStockProductsInProductCategory::class)->name('product_category.out_of_stock_products.index');

    Route::get('collection/{collection}/products', GetIrisProductsInProductCategory::class)->name('product_category.products.index');
    Route::get('collection/{collection}/out-of-stock-products', GetIrisOutOfStockProductsInProductCategory::class)->name('product_category.out_of_stock_products.index');


    Route::get('channels', IndexDropshippingCustomerSalesChannels::class)->name('channels.index');
});
