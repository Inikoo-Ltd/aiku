<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 Mar 2023 18:47:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\Procurement\OrgSupplierProducts\Json\GetOrgSupplierProductLabelsPlan;
use App\Actions\Procurement\OrgSupplierProducts\PdfOrgSupplierProductLabels;
use App\Actions\SupplyChain\SupplierProduct\UI\IndexRepairSupplierProducts;
use App\Actions\Procurement\ProcurementNote\UI\IndexSupplierIncidents;
use App\Actions\Procurement\AgentLabel\DownloadAgentArtefactLabelPdf;
use App\Actions\Procurement\SupplierAgreedTerm\PdfSupplierAgreement;
use App\Actions\Procurement\AgentLabel\FetchAgentOrgStockBarcodeLabelOptions;
use App\Actions\Procurement\AgentLabel\PdfAgentOrgStockBarcodeLabel;
use App\Actions\Procurement\AgentLabel\UI\IndexAgentLabels;
use App\Actions\GoodsIn\StockDelivery\ExportStockDeliveries;
use App\Actions\GoodsIn\StockDelivery\PdfStockDelivery;
use App\Actions\GoodsIn\StockDelivery\ExportStockDeliveryItems;
use App\Actions\GoodsIn\StockDelivery\UI\CreateStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UI\EditStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UI\IndexStockDeliveries;
use App\Actions\GoodsIn\StockDelivery\UI\ShowStockDelivery;
use App\Actions\GoodsIn\StockDeliveryServiceInvoice\UI\IndexStockDeliveryServiceInvoices;
use App\Actions\Inventory\OrgStock\UI\IndexOrgStocks;
use App\Actions\Procurement\OrgAgent\UI\EditOrgAgent;
use App\Actions\Procurement\OrgAgent\UI\IndexOrgAgents;
use App\Actions\Procurement\OrgAgent\UI\IndexSuspendedOrgAgents;
use App\Actions\Procurement\OrgAgent\PrepareAgentPurchaseOrders;
use App\Actions\Procurement\OrgAgent\SendAgentDraftsToAgent;
use App\Actions\Procurement\OrgAgent\SetAgentDraftLine;
use App\Actions\Procurement\OrgAgent\TellOrganisationsAboutSupplierDraft;
use App\Actions\Procurement\OrgAgent\UI\ShowAgentDraftsToSend;
use App\Actions\Procurement\OrgAgent\UI\ShowAgentContainerInPreparation;
use App\Actions\Procurement\OrgAgent\UI\ShowAgentOrderStage;
use App\Actions\Procurement\OrgAgent\RemoveMisplacedAgentShoppingListItems;
use App\Actions\Procurement\OrgAgent\StoreAgentShoppingListItems;
use App\Actions\Procurement\OrgAgent\SuggestAgentShoppingList;
use App\Actions\Procurement\OrgAgent\UI\IndexAgentCoverBucketItems;
use App\Actions\Procurement\OrgAgent\UI\ShowAgentOrderPipeline;
use App\Actions\Procurement\OrgAgent\UI\ShowAgentShoppingDashboard;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgPartner\UI\IndexOrgPartners;
use App\Actions\Procurement\OrgPartner\UI\IndexPartnerRescueItems;
use App\Actions\Procurement\OrgPartner\UI\IndexPartnerCoverBucketItems;
use App\Actions\Procurement\OrgPartner\UI\ShowPartnerBrowse;
use App\Actions\Procurement\OrgPartner\UI\ShowPartnerShoppingDashboard;
use App\Actions\Procurement\OrgPartner\RemoveMisplacedShoppingListItems;
use App\Actions\Procurement\OrgPartner\UI\IndexPartnerBlockedOrgStocks;
use App\Actions\Procurement\OrgPartner\UnblockPartnerOrgStock;
use App\Actions\Procurement\OrgPartner\UpdatePartnerLeadTimeEstimate;
use App\Actions\Procurement\PartnerShoppingListItem\ImportPartnerShoppingListItems;
use App\Actions\Procurement\PartnerShoppingListItem\DeleteOpenPartnerShoppingListItems;
use App\Actions\Procurement\PartnerShoppingListItem\SubmitPartnerShoppingList;
use App\Actions\Procurement\PartnerShoppingListItem\DeletePartnerShoppingListItem;
use App\Actions\Procurement\PartnerShoppingListItem\PokePartnerShoppingListItem;
use App\Actions\Procurement\PartnerShoppingListItem\UI\IndexPartnerShoppingListItems;
use App\Actions\Procurement\PartnerShoppingListItem\StorePartnerShoppingListItem;
use App\Actions\Procurement\PartnerShoppingListItem\StorePartnerShoppingListItems;
use App\Actions\Procurement\PartnerShoppingListItem\SuggestPartnerShoppingList;
use App\Actions\Procurement\PartnerShoppingListItem\UpdatePartnerShoppingListItem;
use App\Actions\Procurement\OrgPartner\UI\ShowOrgPartner;
use App\Actions\Procurement\OrgPartner\UI\EditOrgPartner;
use App\Actions\Procurement\OrgPartner\UpdateOrgPartnerCosmeticSettings;
use App\Actions\Procurement\OrgSupplier\UI\CreateOrgSupplier;
use App\Actions\Procurement\OrgSupplier\UI\EditOrgSupplier;
use App\Actions\Procurement\OrgSupplier\UI\IndexOrgAgentSuppliers;
use App\Actions\Procurement\OrgSupplier\UI\IndexOrgSuppliers;
use App\Actions\Procurement\OrgSupplier\UI\IndexSupplierCoverBucketItems;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\OrgSupplier\UI\ShowSupplierShoppingDashboard;
use App\Actions\Procurement\OrgSupplier\UI\ShowSupplierDraftsToSend;
use App\Actions\Procurement\OrgSupplier\RemoveMisplacedSupplierShoppingListItems;
use App\Actions\Procurement\ShoppingListItem\StoreShoppingListItems;
use App\Actions\Procurement\ShoppingListItem\SuggestSupplierShoppingList;
use App\Actions\SupplyChain\Supplier\UI\CreateSupplier;
use App\Actions\Procurement\OrgSupplierProducts\UI\EditOrgSupplierProduct;
use App\Actions\SupplyChain\AgentSupplierPurchaseOrder\UI\ShowAgentSupplierPurchaseOrder;
use App\Actions\Procurement\OrgSupplierProducts\UI\IndexOrgSupplierProducts;
use App\Actions\Procurement\OrgSupplierProducts\UI\ShowOrgSupplierProduct;
use App\Actions\Procurement\ShoppingListItem\CherryPickShoppingListItems;
use App\Actions\Procurement\ShoppingListItem\DeleteShoppingListItem;
use App\Actions\Procurement\ShoppingListItem\ProposeDismissShoppingListItem;
use App\Actions\Procurement\ShoppingListItem\ResolveDismissShoppingListItem;
use App\Actions\Procurement\ShoppingListItem\StoreShoppingListItem;
use App\Actions\Procurement\ShoppingListItem\UI\IndexShoppingListItems;
use App\Actions\Procurement\ShoppingListItem\UI\ShowShoppingListBoard;
use App\Actions\Procurement\ShoppingListItem\UpdateShoppingListItem;
use App\Actions\Procurement\PurchaseOrder\ExportPurchaseOrders;
use App\Actions\Procurement\PurchaseOrder\PdfPackingInstructions;
use App\Actions\Procurement\PurchaseOrder\OpenPurchaseOrderLabels;
use App\Actions\Procurement\OrgAgent\PdfAgentOrderedPurchaseOrders;
use App\Actions\Procurement\PurchaseOrder\PdfPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\ExportPurchaseOrderTransactions;
use App\Actions\Procurement\PurchaseOrder\UI\EditPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrders;
use App\Actions\Procurement\PurchaseOrder\UI\ShowPurchaseOrder;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\Settings\UI\EditProcurementSettings;
use App\Actions\Procurement\Settings\UpdateProcurementSettings;
use App\Actions\Chat\Whatsapp\GetWhatsappPhoneNumberStatus;
use App\Actions\Chat\Whatsapp\GetWhatsappSubscribedApps;
use App\Actions\Procurement\SupplierMessage\Whatsapp\SendSupplierWhatsappMessage;
use App\Actions\Comms\Mailbox\ConnectProcurementMailbox;
use App\Actions\Comms\Mailbox\DisconnectProcurementMailbox;
use App\Actions\Procurement\SupplierMessage\AssignSupplierMessage;
use App\Actions\Procurement\SupplierMessage\AttachSupplierMessageAttachment;
use App\Actions\Procurement\SupplierMessage\DownloadSupplierMessageAttachment;
use App\Actions\Procurement\SupplierMessage\SendSupplierEmail;
use App\Actions\Procurement\SupplierMessage\UI\IndexSupplierMessages;
use App\Actions\Procurement\SupplierMessage\UI\ShowSupplierMessage;
use App\Actions\Procurement\UI\IndexOrganisationStockCoverItems;
use App\Actions\Procurement\UI\IndexPreOrdersBySupplier;
use App\Actions\Procurement\UpdatePreOrdersForSupplier;
use App\Actions\Procurement\ExportOrganisationStockCoverItems;
use App\Http\Middleware\EnsurePartnerIsManufacturingHub;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowProcurementDashboard::class)->name('dashboard');
Route::get('/stock-cover', IndexOrganisationStockCoverItems::class)->name('stock_cover.index');
Route::get('/stock-cover/export', ExportOrganisationStockCoverItems::class)->name('stock_cover.export');
Route::get('/pre-orders', IndexPreOrdersBySupplier::class)->name('pre_orders.index');
Route::get('/service-invoices', IndexStockDeliveryServiceInvoices::class)->name('service_invoices.index');
Route::get('/supplier-incidents', IndexSupplierIncidents::class)->name('supplier_incidents.index');
Route::patch('/pre-orders', UpdatePreOrdersForSupplier::class)->name('pre_orders.update');

Route::prefix('settings')->as('settings.')->group(function () {
    Route::get('', EditProcurementSettings::class)->name('edit');
    Route::patch('', UpdateProcurementSettings::class)->name('update');
    Route::get('mailbox/connect', ConnectProcurementMailbox::class)->name('mailbox.connect');
    Route::post('mailbox/disconnect', DisconnectProcurementMailbox::class)->name('mailbox.disconnect');
    Route::post('whatsapp-phone/status', [GetWhatsappPhoneNumberStatus::class, 'inProcurement'])->name('whatsapp_phone.status');
    Route::post('whatsapp-app/subscribed', [GetWhatsappSubscribedApps::class, 'inProcurement'])->name('whatsapp_app.subscribed');
});

Route::prefix('emails')->as('supplier_messages.')->group(function () {
    Route::get('', IndexSupplierMessages::class)->name('index');
    Route::post('send', SendSupplierEmail::class)->name('send');
    Route::post('whatsapp', SendSupplierWhatsappMessage::class)->name('whatsapp');
    Route::post('{supplierMessage}/reply', [SendSupplierEmail::class, 'inReply'])->name('reply')->withoutScopedBindings();
    Route::get('{supplierMessage}', ShowSupplierMessage::class)->name('show')->withoutScopedBindings();
    Route::post('{supplierMessage}/assign', AssignSupplierMessage::class)->name('assign')->withoutScopedBindings();
    Route::get('{supplierMessage}/attachments/{index}', DownloadSupplierMessageAttachment::class)->name('attachment')->whereNumber('index')->withoutScopedBindings();
    Route::post('{supplierMessage}/attachments/{index}/attach', AttachSupplierMessageAttachment::class)->name('attachment.attach')->whereNumber('index')->withoutScopedBindings();
});

Route::prefix('agents')->as('org_agents.')->group(function () {
    Route::get('', IndexOrgAgents::class)->name('index');
    Route::get('suspended', IndexSuspendedOrgAgents::class)->name('suspended');

    Route::prefix('{orgAgent}')->as('show')->group(function () {
        Route::get('', ShowOrgAgent::class);
        Route::get('edit', EditOrgAgent::class)->name('.edit');
        Route::get('suppliers', [IndexOrgAgentSuppliers::class, 'inOrgAgent'])->name('.suppliers.index');
        Route::get('purchase-orders', [IndexPurchaseOrders::class, 'inOrgAgent'])->name('.purchase-orders.index');
        Route::get('purchase-order/{purchaseOrder}', [ShowPurchaseOrder::class, 'inOrgAgent'])->name('.purchase-orders.show');
        Route::get('order-pipeline', ShowAgentOrderPipeline::class)->name('.order_pipeline');
        Route::get('org-stocks', [IndexOrgStocks::class, 'inOrgAgent'])->name('.org-stocks.index');
        Route::get('stock-deliveries', [IndexStockDeliveries::class, 'inOrgAgent'])->name('.stock-deliveries.index');
        Route::get('suppliers/{orgSupplier}', [ShowOrgSupplier::class, 'inOrgAgent'])->name('.suppliers.show');
        Route::get('suppliers/{orgSupplier}/edit', [EditOrgSupplier::class, 'inOrgAgent'])->name('.suppliers.edit');
        Route::get('suppliers/{orgSupplier}/supplier-products', [IndexOrgSupplierProducts::class, 'inOrgSupplierInOrgAgent'])->name('.suppliers.show.supplier_products.index');
        Route::get('suppliers/{orgSupplier}/purchase-orders', [IndexPurchaseOrders::class, 'inOrgSupplierInOrgAgent'])->name('.suppliers.show.purchase_orders.index');
        Route::get('suppliers/{orgSupplier}/stock-deliveries', [IndexStockDeliveries::class, 'inOrgSupplierInOrgAgent'])->name('.suppliers.show.stock_deliveries.index');
        Route::get('supplier-products', [IndexOrgSupplierProducts::class, 'inOrgAgent'])->name('.supplier_products.index');
        Route::get('supplier-products/{orgSupplierProduct}', [ShowOrgSupplierProduct::class, 'inOrgAgent'])->name('.supplier_products.show');
        Route::prefix('shopping')->as('.shopping.')->group(function () {
            Route::get('', ShowAgentShoppingDashboard::class)->name('dashboard');
            Route::get('items', IndexAgentCoverBucketItems::class)->name('items.index');
            Route::get('browse', IndexAgentCoverBucketItems::class)->name('browse.index');
            Route::post('suggest', SuggestAgentShoppingList::class)->name('suggest');
            Route::post('bulk', StoreAgentShoppingListItems::class)->name('bulk_store');
            Route::post('prepare', PrepareAgentPurchaseOrders::class)->name('prepare');
            Route::get('drafts', ShowAgentDraftsToSend::class)->name('drafts.index');
            Route::post('drafts/send', SendAgentDraftsToAgent::class)->name('drafts.send');
            Route::post('drafts/tell', TellOrganisationsAboutSupplierDraft::class)->name('drafts.tell');
            Route::post('drafts/line/{orgSupplierProduct:id}', SetAgentDraftLine::class)->name('drafts.line')->withoutScopedBindings();
            Route::delete('misplaced', RemoveMisplacedAgentShoppingListItems::class)->name('misplaced.destroy');
            Route::get('preparing/{stockDelivery}', ShowAgentContainerInPreparation::class)->name('container')->withoutScopedBindings();
            Route::get('ordered/pdf/{filename?}', PdfAgentOrderedPurchaseOrders::class)->name('ordered.pdf');
            Route::get('{stage}', ShowAgentOrderStage::class)->whereIn('stage', ShowAgentOrderStage::STAGES)->name('stage');
        });
    });
});

Route::get('agent-suppliers', IndexOrgAgentSuppliers::class)->name('org_agent_suppliers.index');

Route::prefix('agent-labels')->as('agent_labels.')->group(function () {
    Route::get('', IndexAgentLabels::class)->name('index');
    Route::get('{orgStock:id}/{label:id}/pdf', DownloadAgentArtefactLabelPdf::class)->name('pdf')->withoutScopedBindings();
    Route::get('{orgStock:id}/barcode-label', PdfAgentOrgStockBarcodeLabel::class)->name('barcode_label')->withoutScopedBindings();
    Route::get('{orgStock:id}/barcode-label-options', FetchAgentOrgStockBarcodeLabelOptions::class)->name('barcode_label_options')->withoutScopedBindings();
});

Route::prefix('suppliers')->as('org_suppliers.')->group(function () {
    Route::get('', IndexOrgSuppliers::class)->name('index');
    Route::get('create', CreateOrgSupplier::class)->name('create');
    Route::get('create-new', [CreateSupplier::class, 'inOrganisation'])->name('create_new');
    Route::get('{orgSupplier}', ShowOrgSupplier::class)->name('show');
    Route::get('{orgSupplier}/agreement/pdf', PdfSupplierAgreement::class)->name('show.agreement.pdf');
    Route::get('{orgSupplier}/edit', EditOrgSupplier::class)->name('edit');
    Route::get('{orgSupplier}/purchase-order/{purchaseOrder}', [ShowPurchaseOrder::class, 'inOrgSupplier'])->name('show.purchase-orders.show');
    Route::get('{orgSupplier}/supplier-products', [IndexOrgSupplierProducts::class, 'inOrgSupplier'])->name('show.supplier_products.index');
    Route::get('{orgSupplier}/supplier-products/{orgSupplierProduct}', [ShowOrgSupplierProduct::class, 'inOrgSupplier'])->name('show.supplier_products.show');
    Route::get('{orgSupplier}/purchase-orders', [IndexPurchaseOrders::class, 'inOrgSupplier'])->name('show.purchase_orders.index');
    Route::get('{orgSupplier}/stock-deliveries', [IndexStockDeliveries::class, 'inOrgSupplier'])->name('show.stock_deliveries.index');
    Route::get('{orgSupplier}/repair', IndexRepairSupplierProducts::class)->name('show.repair_supplier_products.index');
    Route::get('{orgSupplier}/shopping-list', [IndexShoppingListItems::class, 'inOrgSupplier'])->name('show.shopping_list.index');

    Route::prefix('{orgSupplier}/shopping')->as('show.shopping.')->group(function () {
        Route::get('', ShowSupplierShoppingDashboard::class)->name('dashboard');
        Route::get('items', IndexSupplierCoverBucketItems::class)->name('items.index');
        Route::get('drafts', ShowSupplierDraftsToSend::class)->name('drafts.index');
        Route::post('suggest', SuggestSupplierShoppingList::class)->name('suggest');
        Route::delete('misplaced', RemoveMisplacedSupplierShoppingListItems::class)->name('misplaced.destroy');
    });
});

Route::prefix('partners')->as('org_partners.')->group(function () {
    Route::get('', IndexOrgPartners::class)->name('index');
    Route::prefix('{orgPartner}')->as('show')->group(function () {
        Route::get('', ShowOrgPartner::class);
        Route::get('edit', EditOrgPartner::class)->name('.edit');
        Route::patch('cosmetic-settings', UpdateOrgPartnerCosmeticSettings::class)->name('.cosmetic_settings.update');
        Route::prefix('purchase-orders')->as('.purchase-orders.')->group(function () {
            Route::get('index', [IndexPurchaseOrders::class, 'inOrgPartner'])->name('index');
            Route::get('{purchaseOrder}', [ShowPurchaseOrder::class, 'inOrgPartner'])->name('show');
        });
        Route::prefix('org-stocks')->as('.org-stocks.')->group(function () {
            Route::get('index', [IndexOrgStocks::class, 'inOrgPartner'])->name('index');
        });
        Route::prefix('stock-deliveries')->as('.stock-deliveries.')->group(function () {
            Route::get('index', [IndexStockDeliveries::class, 'inOrgPartner'])->name('index');
            Route::get('{stockDelivery}', [ShowStockDelivery::class, 'inOrgPartner'])->name('show');
        });
        Route::get('rescue', IndexPartnerRescueItems::class)->name('.rescue.index');
        Route::redirect('shopping-list', '/org/{organisation}/procurement/partners/{orgPartner}/ongoing-po')->name('.shopping_list.legacy');
        Route::middleware(EnsurePartnerIsManufacturingHub::class)->group(function () {
            Route::prefix('shopping')->as('.shopping.')->group(function () {
                Route::get('', ShowPartnerShoppingDashboard::class)->name('dashboard');
                Route::patch('lead-time', UpdatePartnerLeadTimeEstimate::class)->name('lead_time.update');
                Route::delete('misplaced', RemoveMisplacedShoppingListItems::class)->name('misplaced.destroy');
                Route::get('items', IndexPartnerCoverBucketItems::class)->name('items.index');
            });
            Route::prefix('browse')->as('.browse.')->group(function () {
                Route::get('', ShowPartnerBrowse::class)->name('index');
            });
            Route::get('sent', [IndexPartnerShoppingListItems::class, 'inSent'])->name('.shopping_list.sent');
            Route::get('blocked', IndexPartnerBlockedOrgStocks::class)->name('.shopping_list.blocked');
            Route::delete('blocked/{orgStock:id}', UnblockPartnerOrgStock::class)->name('.shopping_list.unblock')->withoutScopedBindings();
            Route::prefix('ongoing-po')->as('.shopping_list.')->group(function () {
                Route::get('', IndexPartnerShoppingListItems::class)->name('index');
                Route::post('suggest', SuggestPartnerShoppingList::class)->name('suggest');
                Route::post('bulk', StorePartnerShoppingListItems::class)->name('bulk_store');
                Route::delete('open', DeleteOpenPartnerShoppingListItems::class)->name('destroy_open');
                Route::delete('hub-suggestions', [DeleteOpenPartnerShoppingListItems::class, 'hubSuggestions'])->name('destroy_hub_suggestions');
                Route::post('submit', SubmitPartnerShoppingList::class)->name('submit');
                Route::post('{partnerShoppingListItem}/submit', [SubmitPartnerShoppingList::class, 'inItem'])->name('submit_item')->withoutScopedBindings();
                Route::post('upload', ImportPartnerShoppingListItems::class)->name('upload');
                Route::post('{orgStock:id}', StorePartnerShoppingListItem::class)->name('store')->withoutScopedBindings();
                Route::patch('{partnerShoppingListItem}', UpdatePartnerShoppingListItem::class)->name('update')->withoutScopedBindings();
                Route::delete('{partnerShoppingListItem}', DeletePartnerShoppingListItem::class)->name('destroy')->withoutScopedBindings();
                Route::post('{partnerShoppingListItem}/poke', PokePartnerShoppingListItem::class)->name('poke')->withoutScopedBindings();
            });
        });
    });

});

Route::prefix('supplier-products')->as('org_supplier_products.')->group(function () {
    Route::get('', IndexOrgSupplierProducts::class)->name('index');
    Route::get('labels', PdfOrgSupplierProductLabels::class)->name('labels');
    Route::get('labels/plan', GetOrgSupplierProductLabelsPlan::class)->name('labels.plan');
    Route::get('{orgSupplierProduct}', ShowOrgSupplierProduct::class)->name('show');
    Route::get('{orgSupplierProduct}/edit', EditOrgSupplierProduct::class)->name('edit');
});

Route::prefix('shopping-list')->as('shopping_list.')->group(function () {
    Route::get('', IndexShoppingListItems::class)->name('index');
    Route::post('bulk', StoreShoppingListItems::class)->name('bulk_store');
    Route::get('board', ShowShoppingListBoard::class)->name('board');
    Route::post('cherry-pick', [CherryPickShoppingListItems::class, 'inOrganisation'])->name('cherry_pick');
    Route::post('{orgSupplierProduct}', StoreShoppingListItem::class)->name('store');
    Route::patch('{shoppingListItem}', UpdateShoppingListItem::class)->name('update');
    Route::delete('{shoppingListItem}', DeleteShoppingListItem::class)->name('destroy');
    Route::post('{shoppingListItem}/propose-dismiss', ProposeDismissShoppingListItem::class)->name('propose_dismiss');
    Route::post('{shoppingListItem}/resolve-dismiss', ResolveDismissShoppingListItem::class)->name('resolve_dismiss');
});

Route::prefix('agent-supplier-purchase-orders')->as('agent_supplier_purchase_orders.')->group(function () {
    Route::get('{agentSupplierPurchaseOrder}', [ShowAgentSupplierPurchaseOrder::class, 'inOrganisation'])->name('show');
});

Route::prefix('purchase-orders')->as('purchase_orders.')->group(function () {
    Route::get('', IndexPurchaseOrders::class)->name('index');
    Route::get('export', ExportPurchaseOrders::class)->name('export');
    Route::get('{purchaseOrder}', ShowPurchaseOrder::class)->name('show');
    Route::get('{purchaseOrder}/edit', EditPurchaseOrder::class)->name('edit');
    Route::get('{purchaseOrder}/pdf/{filename?}', PdfPurchaseOrder::class)->name('pdf');
    Route::get('{purchaseOrder}/packing-instructions/{filename?}', PdfPackingInstructions::class)->name('packing_instructions');
    Route::get('{purchaseOrder}/labels', OpenPurchaseOrderLabels::class)->name('labels');
    Route::get('{purchaseOrder}/transactions-export', ExportPurchaseOrderTransactions::class)->name('transactions.export');
});
Route::prefix('stock-deliveries')->as('stock_deliveries.')->group(function () {
    Route::get('', IndexStockDeliveries::class)->name('index');
    Route::get('export', ExportStockDeliveries::class)->name('export');
    Route::get('create', CreateStockDelivery::class)->name('create');
    Route::get('{stockDelivery}', ShowStockDelivery::class)->name('show');
    Route::get('{stockDelivery}/edit', EditStockDelivery::class)->name('edit');
    Route::get('{stockDelivery}/pdf/{filename?}', PdfStockDelivery::class)->name('pdf');
    Route::get('{stockDelivery}/csv', ExportStockDeliveryItems::class)->name('csv');
});
