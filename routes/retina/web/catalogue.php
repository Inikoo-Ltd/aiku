<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 12:02:06 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\Retina\Dropshipping\Catalogue\ShowRetinaCatalogue;
use App\Actions\Retina\Dropshipping\Collection\UI\IndexRetinaCollections;
use App\Actions\Retina\Dropshipping\Collection\UI\ShowRetinaCollection;
use App\Actions\Retina\Dropshipping\Product\DownloadProduct;
use App\Actions\Retina\Dropshipping\Product\UI\IndexRetinaProductsInCatalogue;
use App\Actions\Retina\Dropshipping\Product\UI\ShowRetinaCatalogueProduct;
use App\Actions\Retina\Dropshipping\ProductCategory\UI\IndexRetinaDepartments;
use App\Actions\Retina\Dropshipping\ProductCategory\UI\IndexRetinaFamilies;
use App\Actions\Retina\Dropshipping\ProductCategory\UI\IndexRetinaSubDepartments;
use App\Actions\Retina\Dropshipping\ProductCategory\UI\ShowRetinaDepartment;
use App\Actions\Retina\Dropshipping\ProductCategory\UI\ShowRetinaFamily;
use App\Actions\Retina\Dropshipping\ProductCategory\UI\ShowRetinaSubDepartment;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', ShowRetinaCatalogue::class)->name('dashboard');

Route::prefix('departments')->as('departments.')->group(function () {
    Route::get('/', IndexRetinaDepartments::class)->name('index');
    Route::get('{department}', ShowRetinaDepartment::class)->name('show');
});

Route::prefix('feeds')->as('feeds.')->group(function () {
    Route::get('feeds/{productCategory}/download', [DownloadProduct::class, 'inProductCategory'])->name('product_category.download');
    Route::get('feeds/{shop}/download', DownloadProduct::class)->name('shop.download');
});


Route::prefix('sub-departments')->as('sub_departments.')->group(function () {
    Route::get('/', IndexRetinaSubDepartments::class)->name('index');
    Route::get('{subDepartment}', ShowRetinaSubDepartment::class)->name('show');
});

Route::prefix('families')->as('families.')->group(function () {
    Route::get('/', IndexRetinaFamilies::class)->name('index');
    Route::get('{family}', ShowRetinaFamily::class)->name('show');
});

Route::prefix('products')->as('products.')->group(function () {
    Route::get('/', IndexRetinaProductsInCatalogue::class)->name('index');
    Route::get('{product}', ShowRetinaCatalogueProduct::class)->name('show');
});

Route::prefix('collections')->as('collections.')->group(function () {
    Route::get('/', IndexRetinaCollections::class)->name('index');
    Route::get('{collection}', ShowRetinaCollection::class)->name('show');
});
