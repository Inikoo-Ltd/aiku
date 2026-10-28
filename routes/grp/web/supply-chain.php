<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:47:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\GoodsIn\StockDelivery\UI\IndexStockDeliveries;
use App\Actions\Procurement\SupplierAgreedTerm\PdfSupplierAgreement;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrders;
use App\Actions\SupplyChain\Agent\UI\CreateAgent;
use App\Actions\SupplyChain\AgentSupplierPurchaseOrder\UI\ShowAgentSupplierPurchaseOrder;
use App\Actions\SupplyChain\Agent\UI\EditAgent;
use App\Actions\SupplyChain\Agent\UI\IndexAgents;
use App\Actions\SupplyChain\Agent\UI\ShowAgent;
use App\Actions\SupplyChain\Supplier\UI\CreateSupplier;
use App\Actions\SupplyChain\Supplier\UI\EditSupplier;
use App\Actions\SupplyChain\Supplier\UI\IndexAgentSuppliers;
use App\Actions\SupplyChain\Supplier\UI\IndexAssignableSuppliers;
use App\Actions\SupplyChain\Supplier\UI\IndexSuppliers;
use App\Actions\SupplyChain\Supplier\UI\ShowSupplier;
use App\Actions\SupplyChain\SupplierProduct\DownloadSupplierProductsTemplate;
use App\Actions\SupplyChain\SupplierProduct\Upload\UI\ShowSupplierProductUpload;
use App\Actions\SupplyChain\SupplierProduct\UI\CreateSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\UI\EditSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\UI\IndexSupplierProducts;
use App\Actions\SupplyChain\SupplierProduct\UI\ShowSupplierProduct;
use App\Actions\SupplyChain\SupplierSubmission\UI\IndexSupplierSubmissions;
use App\Actions\SupplyChain\SupplierSubmission\UI\ShowSupplierSubmission;
use App\Actions\SupplyChain\UI\ShowSupplyChainControl;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Actions\SupplyChain\UI\ShowSupplyChainPurchaseOrderJourney;
use App\Actions\Procurement\ShoppingListItem\UI\ShowShoppingListBoard;
use App\Actions\SupplyChain\SupplierShareLink\UI\IndexSupplierPortalActivity;
use App\Actions\SupplyChain\SupplierShareLink\UI\ShowSupplierDirectLink;
use App\Actions\SupplyChain\SupplierShareLink\UI\ShowSupplierPortalAccess;
use App\Actions\SupplyChain\SupplierShareLink\UI\ShowSupplierPortalContact;
use App\Actions\SupplyChain\SupplierShareLink\UI\ShowSupplierPortalPreview;
use App\Actions\SupplyChain\UI\ShowSupplyChainSales;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowSupplyChainPurchaseOrderJourney::class)->name('dashboard');
Route::get('overview', ShowSupplyChainDashboard::class)->name('overview');
Route::get('control', ShowSupplyChainControl::class)->name('control.dashboard');
Route::redirect('po-journey', '/supply-chain')->name('po_journey.redirect');
Route::get('shopping-list', [ShowShoppingListBoard::class, 'asGroupController'])->name('shopping_list.board');
Route::get('agent-suppliers', IndexAgentSuppliers::class)->name('agent_suppliers.index');
Route::get('supplier-submissions', IndexSupplierSubmissions::class)->name('supplier_submissions.index');
Route::get('supplier-portal-activity', IndexSupplierPortalActivity::class)->name('supplier_portal_activity.index');
Route::get('supplier-submissions/{supplierSubmission}', ShowSupplierSubmission::class)->name('supplier_submissions.show');


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
                Route::get('assignable', IndexAssignableSuppliers::class)->name('.assignable');

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

            Route::get('stock-deliveries', [IndexStockDeliveries::class, 'inAgent'])->name('.stock_deliveries.index');
            Route::get('sales', [ShowSupplyChainSales::class, 'inAgent'])->name('.sales');
        });
    }
);

Route::prefix("suppliers")->name("suppliers")->group(
    function () {
        Route::get('', IndexSuppliers::class)->name('.index');
        Route::get('create', CreateSupplier::class)->name('.create');


        Route::prefix('{supplier}')->group(function () {
            Route::get('', ShowSupplier::class)->name('.show');
            Route::get('agreement/pdf', [PdfSupplierAgreement::class, 'inGroup'])->name('.agreement.pdf');
            Route::get('edit', EditSupplier::class)->name('.edit');

            Route::prefix('supplier-products')->as('.supplier_products')->group(function () {
                Route::get('', [IndexSupplierProducts::class, 'inSupplier'])->name('.index');
                Route::get('create', CreateSupplierProduct::class)->name('.create');
                Route::get('templates', DownloadSupplierProductsTemplate::class)->name('.uploads.templates');
                Route::get('uploads/{upload:id}', ShowSupplierProductUpload::class)->name('.uploads.show')->withoutScopedBindings();

                Route::prefix('{supplierProduct}')->group(function () {
                    Route::get('', [ShowSupplierProduct::class, 'inSupplier'])->name('.show');
                    Route::get('edit', [EditSupplierProduct::class, 'inSupplier'])->name('.edit');
                });
            });

            Route::get('purchase-orders', [IndexPurchaseOrders::class, 'inSupplier'])->name('.purchase_orders.index');
            Route::get('stock-deliveries', [IndexStockDeliveries::class, 'inSupplier'])->name('.stock_deliveries.index');
            Route::get('sales', [ShowSupplyChainSales::class, 'inSupplier'])->name('.sales');
            Route::get('share', ShowSupplierPortalAccess::class)->name('.share');
            Route::get('share/links/{supplierShareLink}', ShowSupplierDirectLink::class)->name('.share.links.show')->withoutScopedBindings();
            Route::get('share/emails/{supplierPortalContact}', ShowSupplierPortalContact::class)->name('.share.emails.show')->withoutScopedBindings();
            Route::get('portal-preview', ShowSupplierPortalPreview::class)->middleware('supplier-portal-locale')->name('.portal_preview');
            Route::get('portal-preview/search', [ShowSupplierPortalPreview::class, 'search'])->name('.portal_preview.search');
            Route::post('portal-preview/{path}', [ShowSupplierPortalPreview::class, 'blocked'])->where('path', '.*')->name('.portal_preview.blocked');
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
        Route::get('/{agentSupplierPurchaseOrder}', ShowAgentSupplierPurchaseOrder::class)->name('show');
    }
);
