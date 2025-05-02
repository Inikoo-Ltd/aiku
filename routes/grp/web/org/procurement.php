<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:47:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\Inventory\OrgStock\UI\IndexOrgStocks;
use App\Actions\Procurement\OrgAgent\ExportOrgAgents;
use App\Actions\Procurement\OrgAgent\UI\IndexOrgAgents;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgPartner\UI\IndexOrgPartners;
use App\Actions\Procurement\OrgPartner\UI\ShowOrgPartner;
use App\Actions\Procurement\OrgSupplier\ExportOrgSuppliers;
use App\Actions\Procurement\OrgSupplier\UI\EditOrgSupplier;
use App\Actions\Procurement\OrgSupplier\UI\IndexOrgSuppliers;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\OrgSupplierProducts\UI\IndexOrgSupplierProducts;
use App\Actions\Procurement\OrgSupplierProducts\UI\ShowOrgSupplierProduct;
use App\Actions\Procurement\PurchaseOrder\ExportPurchaseOrders;
use App\Actions\Procurement\PurchaseOrder\UI\CreatePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UI\EditPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrders;
use App\Actions\Procurement\PurchaseOrder\UI\ShowPurchaseOrder;
use App\Actions\Procurement\StockDelivery\ExportStockDeliveries;
use App\Actions\Procurement\StockDelivery\UI\CreateStockDelivery;
use App\Actions\Procurement\StockDelivery\UI\EditStockDelivery;
use App\Actions\Procurement\StockDelivery\UI\IndexStockDeliveries;
use App\Actions\Procurement\StockDelivery\UI\ShowStockDelivery;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowProcurementDashboard::class)->name('dashboard');

Route::prefix('agents')->as('org_agents.')->group(function () {
    Route::get('', IndexOrgAgents::class)->name('index');
    Route::get('export', ExportOrgAgents::class)->name('export');

    Route::prefix('{orgAgent}')->as('show')->group(function () {
        Route::get('', ShowOrgAgent::class);
        Route::get('suppliers', [IndexOrgSuppliers::class, 'inOrgAgent'])->name('.suppliers.index');
        Route::get('purchase-orders', [IndexPurchaseOrders::class, 'inOrgAgent'])->name('.purchase-orders.index');
        Route::get('purchase-order/{purchaseOrder}', [ShowPurchaseOrder::class, 'inOrgAgent'])->name('.purchase-orders.show');
        Route::get('org-stocks', [IndexOrgStocks::class, 'inOrgAgent'])->name('.org-stocks.index');
        Route::get('stock-deliveries', [IndexStockDeliveries::class, 'inOrgAgent'])->name('.stock-deliveries.index');
        Route::get('suppliers/{orgSupplier}', [ShowOrgSupplier::class, 'inOrgAgent'])->name('.suppliers.show');
        Route::get('suppliers/{orgSupplier}/edit', [EditOrgSupplier::class, 'inOrgAgent'])->name('.suppliers.edit');
        Route::get('supplier-products', [IndexOrgSupplierProducts::class, 'inOrgAgent'])->name('.supplier_products.index');
        Route::get('supplier-products/{orgSupplierProduct}', [ShowOrgSupplierProduct::class, 'inOrgAgent'])->name('.supplier_products.show');
    });
});

Route::prefix('suppliers')->as('org_suppliers.')->group(function () {
    Route::get('', IndexOrgSuppliers::class)->name('index');
    Route::get('export', ExportOrgSuppliers::class)->name('export');
    Route::get('{orgSupplier}', ShowOrgSupplier::class)->name('show');
    Route::get('{orgSupplier}/edit', EditOrgSupplier::class)->name('edit');
    Route::get('{orgSupplier}/purchase-order/{purchaseOrder}', [ShowPurchaseOrder::class, 'inOrgSupplier'])->name('show.purchase-orders.show');
    Route::get('{orgSupplier}/supplier-products', [IndexOrgSupplierProducts::class, 'inOrgSupplier'])->name('show.supplier_products.index');
    Route::get('{orgSupplier}/supplier-products/{orgSupplierProduct}', [ShowOrgSupplierProduct::class, 'inOrgSupplier'])->name('show.supplier_products.show');
    Route::get('{orgSupplier}/purchase-orders', [IndexPurchaseOrders::class, 'inOrgSupplier'])->name('show.purchase_orders.index');
    Route::get('{orgSupplier}/purchase-orders/create', CreatePurchaseOrder::class)->name('show.purchase_orders.create');
    Route::get('{orgSupplier}/stock-deliveries', [IndexStockDeliveries::class, 'inOrgSupplier'])->name('show.stock_deliveries.index');

});

Route::prefix('partners')->as('org_partners.')->group(function () {
    Route::get('', IndexOrgPartners::class)->name('index');
    Route::prefix('{orgPartner}')->as('show')->group(function () {
        Route::get('', ShowOrgPartner::class);
        Route::prefix('purchase-orders')->as('.purchase-orders.')->group(function () {
            Route::get('index', [IndexPurchaseOrders::class, 'inOrgPartner'])->name('index');
            Route::get('{purchaseOrder}', [ShowPurchaseOrder::class, 'inOrgPartner'])->name('show');
        });
        Route::prefix('org-stocks')->as('.org-stocks.')->group(function () {
            Route::get('index', [IndexOrgStocks::class, 'inOrgPartner'])->name('index');
        });
        Route::prefix('stock-deliveries')->as('.stock-deliveries.')->group(function () {
            Route::get('index', [IndexStockDeliveries::class, 'inOrgPartner'])->name('index');
        });
    });

});

Route::prefix('supplier-products')->as('org_supplier_products.')->group(function () {
    Route::get('', IndexOrgSupplierProducts::class)->name('index');
    //todo  Route::get('export', ExportOrgSupplierProducts::class)->name('export');
    Route::get('{orgSupplierProduct}', ShowOrgSupplierProduct::class)->name('show');
});

Route::prefix('purchase-orders')->as('purchase_orders.')->group(function () {
    Route::get('', IndexPurchaseOrders::class)->name('index');
    Route::get('export', ExportPurchaseOrders::class)->name('export');
    Route::get('{purchaseOrder}', ShowPurchaseOrder::class)->name('show');
    Route::get('{purchaseOrder}/edit', EditPurchaseOrder::class)->name('edit');
});
Route::prefix('stock-deliveries')->as('stock_deliveries.')->group(function () {
    Route::get('', IndexStockDeliveries::class)->name('index');
    Route::get('export', ExportStockDeliveries::class)->name('export');
    Route::get('create', CreateStockDelivery::class)->name('create');
    Route::get('{stockDelivery}', ShowStockDelivery::class)->name('show');
    Route::get('{stockDelivery}/edit', EditStockDelivery::class)->name('edit');
});
