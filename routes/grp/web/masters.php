<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Apr 2025 13:04:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Masters\MasterAsset\UI\IndexMasterAssets;
use App\Actions\Masters\MasterCollection\UI\IndexMasterCollections;
use App\Actions\Masters\MasterProductCategory\UI\CreateMasterSubDepartment;
use App\Actions\Masters\MasterProductCategory\UI\IndexMasterDepartments;
use App\Actions\Masters\MasterProductCategory\UI\IndexMasterFamilies;
use App\Actions\Masters\MasterProductCategory\UI\IndexMasterSubDepartments;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterDepartment;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterDepartmentWorkshop;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterFamily;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterFamilyWorkshop;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterSubDepartment;
use App\Actions\Masters\MasterShop\UI\IndexMasterShops;
use App\Actions\Masters\MasterShop\UI\ShowMasterShop;
use App\Actions\Masters\UI\ShowMastersDashboard;
use Illuminate\Support\Facades\Route;
use App\Actions\Masters\MasterProductCategory\UI\ShowMasterDepartmentsWorkshop;

Route::get('/', ShowMastersDashboard::class)->name('dashboard');


Route::get('/shops', IndexMasterShops::class)->name('master_shops.index');

Route::get('/products', IndexMasterAssets::class)->name('master_products.index');
Route::get('/departments', [IndexMasterDepartments::class, 'inGroup'])->name('master_departments.index');


Route::prefix('/departments/{masterDepartment}')->as('master_departments.show')->group(function () {
    Route::get('', [ShowMasterDepartment::class, 'inGroup'])->name('');
    Route::get('blueprint', [ShowMasterDepartmentWorkshop::class, 'inGroup'])->name('.blueprint');

    Route::prefix('families')->as('.master_families.')->group(function () {
        Route::get('', [IndexMasterFamilies::class, 'inMasterDepartment'])->name('index');
        Route::get('{masterFamily}', [ShowMasterFamily::class, 'inMasterDepartment'])->name('show');
        Route::get('/families/{masterFamily}/blueprint', [ShowMasterFamilyWorkshop::class, 'inMasterDepartment'])->name('blueprint');
    });
    Route::get('/products', [IndexMasterAssets::class, 'inMasterDepartment'])->name('.master_products.index');


    Route::get('/sub-departments', [IndexMasterSubDepartments::class, 'inMasterDepartment'])->name('.master_sub_departments.index');
    Route::get('/sub-departments/create', CreateMasterSubDepartment::class)->name('.master_sub_departments.create');
    Route::get('/sub-departments/{masterSubDepartment}', [ShowMasterSubDepartment::class, 'inMasterDepartment'])->name('.master_sub_departments.show');
});


Route::get('/families', [IndexMasterFamilies::class, 'inGroup'])->name('master_families.index');
Route::get('/families/{masterFamily}', [ShowMasterFamily::class, 'inGroup'])->name('master_families.show');

Route::get('/collections', [IndexMasterCollections::class, 'inGroup'])->name('master_collections.index');
// Route::get('/collections/{masterCollection}', [ShowMasterCollection::class, 'inGroup'])->name('master_collections.show');

Route::prefix('/shops/{masterShop}')->as('master_shops.show')->group(function () {
    Route::get('', ShowMasterShop::class)->name('');

    Route::prefix('departments')->as('.master_departments.')->group(function () {
        Route::get('', IndexMasterDepartments::class)->name('index');
        Route::get('{masterDepartment}', ShowMasterDepartment::class)->name('show');
        Route::get('{masterDepartment}/blueprint', ShowMasterDepartmentWorkshop::class)->name('blueprint');
    });
    Route::prefix('families')->as('.master_families.')->group(function () {
        Route::get('', IndexMasterFamilies::class)->name('index');
        Route::get('{masterFamily}', ShowMasterFamily::class)->name('show');
        Route::get('/families/{masterFamily}/blueprint', ShowMasterFamilyWorkshop::class)->name('blueprint');
    });
    Route::prefix('sub-departments')->as('.master_sub_departments.')->group(function () {
        Route::get('', IndexMasterSubDepartments::class)->name('index');
        Route::get('{masterSubDepartment}', ShowMasterSubDepartment::class)->name('show');
    });
    Route::prefix('products')->as('.products.')->group(function () {
        Route::get('', [IndexMasterAssets::class, 'inMasterShop'])->name('index');
    });
    Route::prefix('collections')->as('.master_collections.')->group(function () {
        Route::get('', IndexMasterCollections::class)->name('index');
        // Route::get('{masterCollection}', ShowMasterCollection::class)->name('show');
        // Route::get('/families/{masterCollection}/blueprint', ShowMasterCollectionWorkshop::class)->name('blueprint');
    });
});
Route::get('/shops/{masterShop}/blueprint', ShowMasterDepartmentsWorkshop::class)->name('master_shops.blueprint');
