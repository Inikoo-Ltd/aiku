<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Apr 2025 13:04:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

use App\Actions\Goods\MasterAsset\UI\IndexMasterAssets;
use App\Actions\Goods\MasterProductCategory\UI\IndexMasterDepartments;
use App\Actions\Goods\MasterProductCategory\UI\IndexMasterFamilies;
use App\Actions\Goods\MasterProductCategory\UI\IndexMasterSubDepartments;
use App\Actions\Goods\MasterProductCategory\UI\ShowMasterDepartment;
use App\Actions\Goods\MasterProductCategory\UI\ShowMasterDepartmentWorkshop;
use App\Actions\Goods\MasterProductCategory\UI\ShowMasterFamily;
use App\Actions\Goods\MasterProductCategory\UI\ShowMasterFamilyWorkshop;
use App\Actions\Goods\MasterProductCategory\UI\ShowMasterSubDepartment;
use App\Actions\Goods\MasterShop\UI\IndexMasterShops;
use App\Actions\Goods\MasterShop\UI\ShowMasterShop;
use App\Actions\Masters\UI\ShowMastersDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowMastersDashboard::class)->name('dashboard');


Route::get('/shops', IndexMasterShops::class)->name('shops.index');
Route::get('/products', IndexMasterAssets::class)->name('products.index');
Route::get('/families', [IndexMasterFamilies::class, 'inGroup'])->name('families.index');
Route::get('/families/{masterFamily}', [ShowMasterFamily::class, 'inGroup'])->name('families.show');

Route::prefix('{masterShop}')->as('shops.show')->group(function () {
    Route::get('', ShowMasterShop::class)->name('');
    Route::prefix('departments')->as('.departments.')->group(function () {
        Route::get('index', IndexMasterDepartments::class)->name('index');
        Route::get('{masterDepartment}', ShowMasterDepartment::class)->name('show');
        Route::get('{masterDepartment}/blueprint', ShowMasterDepartmentWorkshop::class)->name('blueprint');
    });
    Route::prefix('families')->as('.families.')->group(function () {
        Route::get('index', IndexMasterFamilies::class)->name('index');
        Route::get('{masterFamily}', ShowMasterFamily::class)->name('show');
        Route::get('/families/{masterFamily}/blueprint', ShowMasterFamilyWorkshop::class)->name('blueprint');
    });
    Route::prefix('sub-departments')->as('.sub-departments.')->group(function () {
        Route::get('index', IndexMasterSubDepartments::class)->name('index');
        Route::get('{masterSubDepartment}', ShowMasterSubDepartment::class)->name('show');
    });
    Route::prefix('products')->as('.products.')->group(function () {
        Route::get('index', [IndexMasterAssets::class, 'inMasterShop'])->name('index');
    });
});
