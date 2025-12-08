<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:47:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\SupplyChain\Agent\UI\CreateAgent;
use App\Actions\SupplyChain\Agent\UI\EditAgent;
use App\Actions\SupplyChain\Agent\UI\IndexAgents;
use App\Actions\SupplyChain\Agent\UI\ShowAgent;
use App\Actions\SupplyChain\Supplier\ExportSuppliers;
use App\Actions\SupplyChain\Supplier\UI\CreateSupplier;
use App\Actions\SupplyChain\Supplier\UI\EditSupplier;
use App\Actions\SupplyChain\Supplier\UI\IndexSuppliers;
use App\Actions\SupplyChain\Supplier\UI\ShowSupplier;
use App\Actions\SupplyChain\SupplierProduct\UI\CreateSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\UI\IndexSupplierProducts;
use App\Actions\SupplyChain\SupplierProduct\UI\ShowSupplierProduct;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Stubs\UIDummies\EditDummy;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowSupplyChainDashboard::class)->name('dashboard');

Route::prefix('agents')->name('agents.')->group(
    function () {
        Route::get('', IndexAgents::class)->name('index');
        Route::get('create', CreateAgent::class)->name('create');
        Route::get('{agent}/edit', EditAgent::class)->name('edit');
        Route::get('{agent}', ShowAgent::class)->name('show');

        Route::prefix('{agent}')->as('show')->group(function () {
            Route::get('', ShowAgent::class);

            Route::prefix('suppliers')->as('.suppliers')->group(function () {
                Route::get('', [IndexSuppliers::class, 'inAgent'])->name('.index');
                Route::get('create', [CreateSupplier::class, 'inAgent'])->name('.create');

                Route::prefix('{supplier}')->group(function () {
                    Route::get('', [ShowSupplier::class, 'inAgent'])->name('.show');
                    Route::get('edit', [EditSupplier::class, 'inAgent'])->name('.edit');

                    Route::prefix('supplier-products')->as('.supplier_products')->group(function () {
                        Route::get('', [IndexSupplierProducts::class, 'inSupplierInAgent'])->name('.index');

                        Route::prefix('{supplierProduct}')->group(function () {
                            Route::get('', [ShowSupplierProduct::class, 'inSupplierInAgent'])->name('.show');
                            Route::get('edit', [EditDummy::class, 'inSupplierInAgent'])->name('.edit');
                        });
                    });
                });
            });

            Route::prefix('supplier-products')->as('.supplier_products')->group(function () {
                Route::get('', [IndexSupplierProducts::class, 'inAgent'])->name('.index');

                Route::prefix('{supplierProduct}')->group(function () {
                    Route::get('', [ShowSupplierProduct::class, 'inAgent'])->name('.show');
                    Route::get('edit', [EditDummy::class, 'inAgent'])->name('.edit');
                });
            });
        });
    }
);

Route::prefix('suppliers')->name('suppliers')->group(
    function () {
        Route::get('', IndexSuppliers::class)->name('.index');
        Route::get('create', CreateSupplier::class)->name('.create');
        Route::get('export', ExportSuppliers::class)->name('.export');

        Route::prefix('{supplier}')->group(function () {
            Route::get('', ShowSupplier::class)->name('.show');

            Route::prefix('supplier-products')->as('.supplier_products')->group(function () {
                Route::get('', [IndexSupplierProducts::class, 'inSupplier'])->name('.index');
                Route::get('create', CreateSupplierProduct::class)->name('.create');

                Route::prefix('{supplierProduct}')->group(function () {
                    Route::get('', [ShowSupplierProduct::class, 'inSupplier'])->name('.show');
                    Route::get('edit', [EditDummy::class, 'inSupplier'])->name('.edit');
                });
            });
        });

    }
);

Route::prefix('supplier-products')->name('supplier_products.')->group(
    function () {
        Route::get('', IndexSupplierProducts::class)->name('index');

        Route::get('/{supplierProduct}', ShowSupplierProduct::class)->name('show');
    }
);
