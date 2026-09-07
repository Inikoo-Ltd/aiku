<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:47:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\GoodsIn\StockDelivery\UI\IndexStockDeliveries;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrders;
use App\Actions\SupplyChain\Agent\UI\CreateAgent;
use App\Actions\SupplyChain\AgentSupplierPurchaseOrder\UI\EditAgentSupplierPurchaseOrder;
use App\Actions\SupplyChain\AgentSupplierPurchaseOrder\UI\IndexAgentSupplierPurchaseOrders;
use App\Actions\SupplyChain\AgentSupplierPurchaseOrder\UI\ShowAgentSupplierPurchaseOrder;
use App\Actions\SupplyChain\Agent\UI\EditAgent;
use App\Actions\SupplyChain\Agent\UI\IndexAgents;
use App\Actions\SupplyChain\Agent\UI\ShowAgent;
use App\Actions\SupplyChain\Supplier\ExportSuppliers;
use App\Actions\SupplyChain\Supplier\UI\CreateSupplier;
use App\Actions\SupplyChain\Supplier\UI\EditSupplier;
use App\Actions\SupplyChain\Supplier\UI\IndexAgentSuppliers;
use App\Actions\SupplyChain\Supplier\UI\IndexSuppliers;
use App\Actions\SupplyChain\Supplier\UI\ShowSupplier;
use App\Actions\SupplyChain\SupplierProduct\DownloadSupplierProductsTemplate;
use App\Actions\SupplyChain\SupplierProduct\UI\CreateSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\UI\EditSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\UI\IndexSupplierProducts;
use App\Actions\SupplyChain\SupplierProduct\UI\ShowSupplierProduct;
use App\Actions\SupplyChain\UI\ShowSupplyChainControl;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Actions\Procurement\ShoppingListItem\UI\ShowShoppingListBoard;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowSupplyChainDashboard::class)->name('dashboard');
Route::get('control', ShowSupplyChainControl::class)->name('control.dashboard');
Route::get('shopping-list', [ShowShoppingListBoard::class, 'asGroupController'])->name('shopping_list.board');
Route::get('agent-suppliers', IndexAgentSuppliers::class)->name('agent_suppliers.index');


Route::prefix("agents")->name("agents.")->group(
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
                            Route::get('edit', [EditSupplierProduct::class, 'inSupplierInAgent'])->name('.edit');
                        });
                    });
                });
            });

            Route::prefix('supplier-products')->as('.supplier_products')->group(function () {
                Route::get('', [IndexSupplierProducts::class, 'inAgent'])->name('.index');

                Route::prefix('{supplierProduct}')->group(function () {
                    Route::get('', [ShowSupplierProduct::class, 'inAgent'])->name('.show');
                    Route::get('edit', [EditSupplierProduct::class, 'inAgent'])->name('.edit');
                });
            });

            Route::get('agent-supplier-purchase-orders', [IndexAgentSupplierPurchaseOrders::class, 'inAgent'])->name('.agent_supplier_purchase_orders.index');
            Route::get('stock-deliveries', [IndexStockDeliveries::class, 'inAgent'])->name('.stock_deliveries.index');
        });
    }
);

Route::prefix("suppliers")->name("suppliers")->group(
    function () {
        Route::get('', IndexSuppliers::class)->name('.index');
        Route::get('create', CreateSupplier::class)->name('.create');
        Route::get('export', ExportSuppliers::class)->name('.export');


        Route::prefix('{supplier}')->group(function () {
            Route::get('', ShowSupplier::class)->name('.show');

            Route::prefix('supplier-products')->as('.supplier_products')->group(function () {
                Route::get('', [IndexSupplierProducts::class, 'inSupplier'])->name('.index');
                Route::get('create', CreateSupplierProduct::class)->name('.create');
                Route::get('templates', DownloadSupplierProductsTemplate::class)->name('.uploads.templates');

                Route::prefix('{supplierProduct}')->group(function () {
                    Route::get('', [ShowSupplierProduct::class, 'inSupplier'])->name('.show');
                    Route::get('edit', [EditSupplierProduct::class, 'inSupplier'])->name('.edit');
                });
            });

            Route::get('agent-supplier-purchase-orders', [IndexAgentSupplierPurchaseOrders::class, 'inSupplier'])->name('.agent_supplier_purchase_orders.index');
            Route::get('purchase-orders', [IndexPurchaseOrders::class, 'inSupplier'])->name('.purchase_orders.index');
            Route::get('stock-deliveries', [IndexStockDeliveries::class, 'inSupplier'])->name('.stock_deliveries.index');
        });


    }
);

Route::prefix("supplier-products")->name("supplier_products.")->group(
    function () {
        Route::get('', IndexSupplierProducts::class)->name('index');
        Route::get('free', [IndexSupplierProducts::class, 'free'])->name('free');
        Route::get('in-agents', [IndexSupplierProducts::class, 'inAgents'])->name('in_agents');

        Route::get('/{supplierProduct}', ShowSupplierProduct::class)->name('show');
        Route::get('/{supplierProduct}/edit', EditSupplierProduct::class)->name('edit');
    }
);

Route::prefix("agent-supplier-purchase-orders")->name("agent_supplier_purchase_orders.")->group(
    function () {
        Route::get('', IndexAgentSupplierPurchaseOrders::class)->name('index');
        Route::get('/{agentSupplierPurchaseOrder}', ShowAgentSupplierPurchaseOrder::class)->name('show');
        Route::get('/{agentSupplierPurchaseOrder}/edit', EditAgentSupplierPurchaseOrder::class)->name('edit');
    }
);
