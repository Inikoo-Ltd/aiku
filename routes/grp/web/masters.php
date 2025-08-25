<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Apr 2025 13:04:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


use App\Actions\Masters\MasterAsset\UI\IndexMasterProducts;
use App\Actions\Masters\MasterAsset\UI\ShowMasterProducts;
use App\Actions\Masters\MasterCollection\UI\CreateMasterCollection;
use App\Actions\Masters\MasterCollection\UI\IndexMasterCollections;
use App\Actions\Masters\MasterCollection\UI\IndexMasterCollectionsInMasterProductCategory;
use App\Actions\Masters\MasterCollection\UI\ShowMasterCollection;
use App\Actions\Masters\MasterProductCategory\UI\CreateMasterDepartment;
use App\Actions\Masters\MasterProductCategory\UI\CreateMasterFamily;
use App\Actions\Masters\MasterProductCategory\UI\CreateMasterSubDepartment;
use App\Actions\Masters\MasterProductCategory\UI\EditMasterDepartment;
use App\Actions\Masters\MasterProductCategory\UI\EditMasterFamily;
use App\Actions\Masters\MasterProductCategory\UI\EditMasterSubDepartment;
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

require_once __DIR__ . '/common/trade_units.php';

Route::get('/', ShowMastersDashboard::class)->name('dashboard');



Route::get('/master-products', IndexMasterProducts::class)->name('master_products.index');
Route::get('/master-departments', [IndexMasterDepartments::class, 'inGroup'])->name('master_departments.index');


Route::prefix('/master-departments/{masterDepartment}')->as('master_departments.show')->group(function () {
    Route::get('', [ShowMasterDepartment::class, 'inGroup'])->name('');
    Route::get('blueprint', [ShowMasterDepartmentWorkshop::class, 'inGroup'])->name('.blueprint');

    Route::prefix('master-families')->as('.master_families.')->group(function () {
        Route::get('', [IndexMasterFamilies::class, 'inMasterDepartment'])->name('index');
        Route::get('create', [CreateMasterFamily::class, 'inMasterDepartment'])->name('create');

        Route::prefix('{masterFamily}')->group(function () {
            Route::get('edit', [EditMasterFamily::class, 'inMaserDepartment'])->name('edit');
            Route::get('', [ShowMasterFamily::class, 'inMasterDepartment'])->name('show');
            Route::get('blueprint', [ShowMasterFamilyWorkshop::class, 'inMasterDepartment'])->name('blueprint');

            Route::name("show.master_products.")->prefix('master-products')
                ->group(function () {
                    Route::get('', [IndexMasterProducts::class, 'inMasterFamilyInMasterDepartment'])->name('index');
                });
        });
    });
    Route::get('/master-products', [IndexMasterProducts::class, 'inMasterDepartment'])->name('.master_products.index');
    Route::get('/master-sub-departments', [IndexMasterSubDepartments::class, 'inMasterDepartment'])->name('.master_sub_departments.index');
    Route::get('/master-sub-departments/create', [CreateMasterSubDepartment::class, 'inMasterDepartment'])->name('.master_sub_departments.create');
    Route::get('/master-sub-departments/{masterSubDepartment}', [ShowMasterSubDepartment::class, 'inMasterDepartment'])->name('.master_sub_departments.show');
    Route::get('/master-sub-departments/{masterSubDepartment}/edit', [EditMasterSubDepartment::class, 'inMasterDepartment'])->name('.master_sub_departments.edit');
    Route::get('/master-sub-departments/{masterSubDepartment}/master-families', [IndexMasterFamilies::class, 'inMasterSubDepartmentInMasterDepartment'])->name('.master_sub_departments.show.master_families.index');
    Route::get('/master-sub-departments/{masterSubDepartment}/master-families/create', [CreateMasterFamily::class, 'inMasterSubDepartmentInMasterDepartment'])->name('.master_sub_departments.show.master_families.create');
    Route::get('/master-sub-departments/{masterSubDepartment}/master-families/{masterFamily}', [ShowMasterFamily::class, 'inMasterSubDepartmentInMasterDepartment'])->name('.master_sub_departments.show.master_families.show');
});


Route::get('/master-families', [IndexMasterFamilies::class, 'inGroup'])->name('master_families.index');
Route::get('/master-families/{masterFamily}', [ShowMasterFamily::class, 'inGroup'])->name('master_families.show');

Route::get('/master-collections', [IndexMasterCollections::class, 'inGroup'])->name('master_collections.index');



Route::name("master_shops")->prefix('master-shops')
    ->group(function () {

        Route::get('', IndexMasterShops::class)->name('.index');
        Route::get('{masterShop}/blueprint', ShowMasterDepartmentsWorkshop::class)->name('.blueprint');

        Route::prefix('/{masterShop}')->as('.show')->group(function () {
            Route::get('', ShowMasterShop::class)->name('');

            Route::prefix('master-departments')->as('.master_departments.')->group(function () {
                Route::get('', IndexMasterDepartments::class)->name('index');
                Route::get('create', CreateMasterDepartment::class)->name('create');

                Route::get('{masterDepartment}/blueprint', ShowMasterDepartmentWorkshop::class)->name('blueprint');
                Route::get('{masterDepartment}/edit', EditMasterDepartment::class)->name('edit');

                Route::prefix('{masterDepartment}')->name('show')->group(function () {
                    Route::get('', ShowMasterDepartment::class);


                    Route::prefix('master-families')->as('.master_families.')->group(function () {
                        Route::get('', [IndexMasterFamilies::class, 'inMasterDepartmentInMasterShop'])->name('index');
                        Route::get('create', [CreateMasterFamily::class, 'inMasterDepartmentInMasterShop'])->name('create');
                        Route::prefix('{masterFamily}')->group(function () {
                            Route::get('//blueprint', ShowMasterFamilyWorkshop::class)->name('blueprint');
                            Route::get('', [ShowMasterFamily::class, 'inMasterDepartmentInMasterShop'])->name('show');
                            Route::get('edit', [EditMasterFamily::class, 'InMasterDepartment'])->name('edit');
                            Route::get('master-products', [IndexMasterProducts::class, 'inMasterFamilyInMasterDepartmentInMasterShop'])->name('show.master_products.index');
                            Route::get('master-products/{masterProduct}', [ShowMasterProducts::class, 'inMasterFamilyInMasterDepartmentInMasterShop'])->name('show.master_products.show');
                        });
                    });

                    Route::prefix('sub-departments')->as('.master_sub_departments.')->group(function () {
                        Route::get('', [IndexMasterSubDepartments::class, 'inMasterDepartment'])->name('index');
                        Route::get('/sub-departments/create', [CreateMasterSubDepartment::class, 'inMasterDepartment'])->name('create');
                        Route::get('{masterSubDepartment}', [ShowMasterSubDepartment::class, 'inMasterDepartment'])->name('show');
                        Route::get('{masterSubDepartment}/edit', [EditMasterSubDepartment::class, 'inMasterDepartment'])->name('edit');

                        Route::prefix('/{masterSubDepartment}/families')->as('master_families.')->group(function () {
                            Route::get('', [IndexMasterFamilies::class, 'inMasterSubDepartmentInMasterDepartment'])->name('index');
                            Route::get('create', [CreateMasterFamily::class, 'inMasterSubDepartment'])->name('create');
                            Route::get('{masterFamily}', [ShowMasterFamily::class, 'inMasterSubDepartmentInMasterDepartment'])->name('show');
                            Route::get('{masterFamily}/edit', [EditMasterFamily::class, 'inMasterSubDepartmentInMasterDepartment'])->name('edit');
                            Route::prefix('{masterFamily}/master-products')->as('master_products.')->group(function () {
                                Route::get('', [IndexMasterProducts::class, 'inMasterFamilyInMasterSubDepartmentInMasterDepartment'])->name('index');
                                Route::get('{masterProduct}', [ShowMasterProducts::class, 'inMasterFamilyInMasterDepartment'])->name('show');
                            });
                        });
                    });

                    Route::prefix('master-products')->as('.master_products.')->group(function () {
                        Route::get('', [IndexMasterProducts::class, 'inMasterDepartmentInMasterShop'])->name('index');
                        Route::get('{masterProduct}', [ShowMasterProducts::class, 'inMasterDepartmentInMasterShop'])->name('show');
                    });

                    Route::prefix('master-collections')->as('.master_collections.')->group(function () {
                        Route::get('', [IndexMasterCollectionsInMasterProductCategory::class, 'inMasterDepartmentInMasterShop'])->name('index');
                        Route::get('create', [CreateMasterCollection::class, 'inMasterDepartmentInMasterShop'])->name('create');
                        Route::get('{masterCollection}', [ShowMasterCollection::class, 'inMasterDepartmentInMasterShop'])->name('show');
                    });

                });





            });

            Route::prefix('master-families')->as('.master_families.')->group(function () {
                Route::get('', IndexMasterFamilies::class)->name('index');
                Route::get('create', CreateMasterFamily::class)->name('create');
                Route::get('{masterFamily}', ShowMasterFamily::class)->name('show');
                Route::get('{masterFamily}/edit', EditMasterFamily::class)->name('edit');
                Route::get('/master-families/{masterFamily}/blueprint', ShowMasterFamilyWorkshop::class)->name('blueprint');

                Route::prefix('{masterFamily}/master-products')->as('master_products.')->group(function () {
                    Route::get('', [IndexMasterProducts::class, 'inMasterFamilyInMasterShop'])->name('index');
                    Route::get('{masterProduct}', [ShowMasterProducts::class, 'inMasterFamilyInMasterShop'])->name('show');
                });
            });

            Route::prefix('master-sub-departments')->as('.master_sub_departments.')->group(function () {
                Route::get('', IndexMasterSubDepartments::class)->name('index');
                Route::get('/master-sub-departments/create', CreateMasterSubDepartment::class)->name('create');
                Route::get('{masterSubDepartment}', ShowMasterSubDepartment::class)->name('show');
                Route::get('{masterSubDepartment}/edit', EditMasterSubDepartment::class)->name('edit');

                Route::prefix('/{masterSubDepartment}/families')->as('master_families.')->group(function () {
                    Route::get('', [IndexMasterFamilies::class, 'inMasterSubDepartment'])->name('index');
                    Route::get('create', [CreateMasterFamily::class, 'inMasterSubDepartment'])->name('create');
                    Route::get('{masterFamily}', [ShowMasterFamily::class, 'inMasterSubDepartment'])->name('show');
                    Route::get('{masterFamily}/edit', [EditMasterFamily::class, 'inMasterSubDepartment'])->name('edit');
                    Route::get('{masterFamily}/master-products', [IndexMasterProducts::class, 'inMasterFamilyInMasterSubDepartment'])->name('master_products.index');
                });

                Route::prefix('{masterSubDepartment}/master-collections')->as('master_collections.')->group(function () {
                    Route::get('', [IndexMasterCollectionsInMasterProductCategory::class, 'inMasterSubDepartmentInMasterShop'])->name('index');
                    Route::get('create', [CreateMasterCollection::class, 'inMasterSubDepartmentInMasterShop'])->name('create');
                    Route::get('{masterCollection}', [ShowMasterCollection::class, 'inMasterSubDepartmentInMasterShop'])->name('show');
                });
            });

            Route::prefix('master-products')->as('.master_products.')->group(function () {
                Route::get('', [IndexMasterProducts::class, 'inMasterShop'])->name('index');
                Route::get('{masterProduct}', ShowMasterProducts::class)->name('show');
            });
            Route::prefix('master-collections')->as('.master_collections.')->group(function () {
                Route::get('', IndexMasterCollections::class)->name('index');
                Route::get('create', CreateMasterCollection::class)->name('create');
                Route::get('{masterCollection}', ShowMasterCollection::class)->name('show');
            });
        });



    });



// Use the common trade units routes
tradeUnitsRoutes();
