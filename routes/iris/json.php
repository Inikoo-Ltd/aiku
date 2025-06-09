<?php

/*
 * author Arya Permana - Kirin
 * created on 05-06-2025-14h-26m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

use App\Actions\Catalogue\Product\Json\GetIrisProductsInProductCategory;
use Illuminate\Support\Facades\Route;

Route::get('product-category/{productCategory}/products', GetIrisProductsInProductCategory::class)->name('product_category.products.index');
