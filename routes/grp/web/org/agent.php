<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Chat\Whatsapp\GetWhatsappPhoneNumberStatus;
use App\Actions\Chat\Whatsapp\GetWhatsappSubscribedApps;
use App\Actions\Comms\Mailbox\ConnectProcurementMailbox;
use App\Actions\Comms\Mailbox\DisconnectProcurementMailbox;
use App\Actions\GoodsIn\StockDelivery\ExportStockDeliveries;
use App\Actions\GoodsIn\StockDelivery\PdfStockDelivery;
use App\Actions\GoodsIn\StockDelivery\UI\IndexStockDeliveries;
use App\Actions\GoodsIn\StockDelivery\UI\ShowAgentContainersBoard;
use App\Actions\GoodsIn\StockDelivery\UI\ShowStockDelivery;
use App\Actions\Procurement\AgentLabel\DownloadAgentArtefactLabelPdf;
use App\Actions\Procurement\AgentLabel\FetchAgentOrgStockBarcodeLabelOptions;
use App\Actions\Procurement\AgentLabel\PdfAgentOrgStockBarcodeLabel;
use App\Actions\Procurement\AgentLabel\UI\IndexAgentBarcodes;
use App\Actions\Procurement\AgentLabel\UI\IndexAgentLabels;
use App\Actions\Procurement\OrgSupplier\ExportOrgSuppliers;
use App\Actions\Procurement\OrgSupplier\UI\EditOrgSupplier;
use App\Actions\Procurement\OrgSupplier\UI\IndexOrgSuppliers;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\OrgSupplierProducts\UI\EditOrgSupplierProduct;
use App\Actions\Procurement\OrgSupplierProducts\UI\IndexOrgSupplierProducts;
use App\Actions\Procurement\OrgSupplierProducts\UI\ShowOrgSupplierProduct;
use App\Actions\Procurement\PurchaseOrder\ExportPurchaseOrders;
use App\Actions\Procurement\PurchaseOrder\ExportPurchaseOrderTransactions;
use App\Actions\Procurement\PurchaseOrder\PdfPurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrders;
use App\Actions\Procurement\PurchaseOrder\UI\ShowAgentPurchaseOrdersBoard;
use App\Actions\Procurement\PurchaseOrder\UI\ShowAgentPurchaseOrdersDashboard;
use App\Actions\Procurement\PurchaseOrder\UI\ShowAgentPurchaseOrdersReports;
use App\Actions\Procurement\PurchaseOrder\UI\ShowPurchaseOrder;
use App\Actions\Procurement\ShoppingListItem\CherryPickShoppingListItems;
use App\Actions\Procurement\ShoppingListItem\DeleteShoppingListItem;
use App\Actions\Procurement\ShoppingListItem\ProposeDismissShoppingListItem;
use App\Actions\Procurement\ShoppingListItem\ResolveDismissShoppingListItem;
use App\Actions\Procurement\ShoppingListItem\StoreShoppingListItem;
use App\Actions\Procurement\ShoppingListItem\StoreShoppingListItems;
use App\Actions\Procurement\ShoppingListItem\UI\IndexShoppingListItems;
use App\Actions\Procurement\ShoppingListItem\UI\ShowShoppingListBoard;
use App\Actions\Procurement\ShoppingListItem\UpdateShoppingListItem;
use App\Actions\Procurement\Settings\UI\EditProcurementSettings;
use App\Actions\Procurement\Settings\UpdateProcurementSettings;
use App\Actions\Procurement\SupplierMessage\AssignSupplierMessage;
use App\Actions\Procurement\SupplierMessage\AttachSupplierMessageAttachment;
use App\Actions\Procurement\SupplierMessage\DownloadSupplierMessageAttachment;
use App\Actions\Procurement\SupplierMessage\SendSupplierEmail;
use App\Actions\Procurement\SupplierMessage\UI\IndexSupplierMessages;
use App\Actions\Procurement\SupplierMessage\UI\ShowSupplierMessage;
use App\Actions\Procurement\SupplierMessage\Whatsapp\SendSupplierWhatsappMessage;
use App\Actions\SupplyChain\AgentSupplierPurchaseOrder\UI\ShowAgentSupplierPurchaseOrder;
use App\Actions\SupplyChain\AspoDeposit\UI\IndexAgentDepositRequests;
use App\Actions\SupplyChain\AspoDeposit\UI\IndexAgentDeposits;
use App\Actions\SupplyChain\AspoDeposit\UI\ShowAgentAccountingDashboard;
use App\Actions\SupplyChain\Supplier\UI\CreateSupplier;
use App\Actions\Inventory\OrgStock\UI\IndexOrgStocks;
use App\Actions\Procurement\AgentOrder\UI\IndexAgentOrders;
use App\Actions\Procurement\AgentOrder\UI\ShowAgentOrder;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgAgent\UI\ShowAgentOrderPipeline;
use App\Actions\Procurement\OrgSupplier\UI\IndexOrgAgentSuppliers;
use App\Actions\SupplyChain\AgentInvoice\PdfAgentInvoice;
use App\Actions\SupplyChain\AgentInvoice\StoreAgentInvoice;
use App\Actions\SupplyChain\AgentInvoice\UI\IndexAgentInvoices;
use App\Actions\SupplyChain\AgentInvoice\UpdateAgentInvoiceCharges;
use App\Actions\SupplyChain\AgentPayment\DeleteAgentPayment;
use App\Actions\SupplyChain\AgentPayment\StoreAgentPayment;
use Illuminate\Support\Facades\Route;

Route::prefix('suppliers')->as('org_suppliers.')->group(function () {
    Route::get('', IndexOrgSuppliers::class)->name('index');
    Route::get('export', ExportOrgSuppliers::class)->name('export');
    Route::get('create-new', [CreateSupplier::class, 'inOrganisation'])->name('create_new');
    Route::get('{orgSupplier}', ShowOrgSupplier::class)->name('show');
    Route::get('{orgSupplier}/edit', EditOrgSupplier::class)->name('edit');
    Route::get('{orgSupplier}/purchase-order/{purchaseOrder}', [ShowPurchaseOrder::class, 'inOrgSupplier'])->name('show.purchase-orders.show');
    Route::get('{orgSupplier}/supplier-products', [IndexOrgSupplierProducts::class, 'inOrgSupplier'])->name('show.supplier_products.index');
    Route::get('{orgSupplier}/supplier-products/{orgSupplierProduct}', [ShowOrgSupplierProduct::class, 'inOrgSupplier'])->name('show.supplier_products.show');
    Route::get('{orgSupplier}/purchase-orders', [IndexPurchaseOrders::class, 'inOrgSupplier'])->name('show.purchase_orders.index');
    Route::get('{orgSupplier}/containers', [IndexStockDeliveries::class, 'inOrgSupplier'])->name('show.stock_deliveries.index');
});

Route::prefix('supplier-products')->as('org_supplier_products.')->group(function () {
    Route::get('', IndexOrgSupplierProducts::class)->name('index');
    Route::get('{orgSupplierProduct}', ShowOrgSupplierProduct::class)->name('show');
    Route::get('{orgSupplierProduct}/edit', EditOrgSupplierProduct::class)->name('edit');
});

Route::prefix('purchase-orders')->as('purchase_orders.')->group(function () {
    Route::get('', ShowAgentPurchaseOrdersDashboard::class)->name('dashboard');
    Route::get('list', IndexPurchaseOrders::class)->name('index');
    Route::get('board', ShowAgentPurchaseOrdersBoard::class)->name('board');
    Route::get('reports', ShowAgentPurchaseOrdersReports::class)->name('reports');
    Route::get('export', ExportPurchaseOrders::class)->name('export');
    Route::get('{purchaseOrder}', ShowPurchaseOrder::class)->name('show');
    Route::get('{purchaseOrder}/pdf', PdfPurchaseOrder::class)->name('pdf');
    Route::get('{purchaseOrder}/transactions-export', ExportPurchaseOrderTransactions::class)->name('transactions.export');
});

Route::prefix('client-orders')->as('agent_supplier_purchase_orders.')->group(function () {
    Route::get('{agentSupplierPurchaseOrder}', [ShowAgentSupplierPurchaseOrder::class, 'inOrganisation'])->name('show');
});

Route::prefix('containers')->as('stock_deliveries.')->group(function () {
    Route::get('', IndexStockDeliveries::class)->name('index');
    Route::get('current', [IndexStockDeliveries::class, 'inAgentCurrent'])->name('current');
    Route::get('past', [IndexStockDeliveries::class, 'inAgentPast'])->name('past');
    Route::get('board', ShowAgentContainersBoard::class)->name('board');
    Route::get('export', ExportStockDeliveries::class)->name('export');
    Route::get('{stockDelivery}', ShowStockDelivery::class)->name('show');
    Route::get('{stockDelivery}/pdf', PdfStockDelivery::class)->name('pdf');
});

Route::get('barcodes', IndexAgentBarcodes::class)->name('agent_barcodes.index');

Route::prefix('agent-accounting')->as('accounting.')->group(function () {
    Route::get('', ShowAgentAccountingDashboard::class)->name('dashboard');
    Route::get('deposits', IndexAgentDeposits::class)->name('deposits.index');
    Route::get('deposit-requests', IndexAgentDepositRequests::class)->name('deposit_requests.index');
    Route::get('invoices', IndexAgentInvoices::class)->name('invoices.index');
});

Route::prefix('labels')->as('agent_labels.')->group(function () {
    Route::get('', IndexAgentLabels::class)->name('index');
    Route::get('{orgStock:id}/{label:id}/pdf', DownloadAgentArtefactLabelPdf::class)->name('pdf')->withoutScopedBindings();
    Route::get('{orgStock:id}/barcode-label', PdfAgentOrgStockBarcodeLabel::class)->name('barcode_label')->withoutScopedBindings();
    Route::get('{orgStock:id}/barcode-label-options', FetchAgentOrgStockBarcodeLabelOptions::class)->name('barcode_label_options')->withoutScopedBindings();
});

Route::prefix('inbox')->as('supplier_messages.')->group(function () {
    Route::get('', IndexSupplierMessages::class)->name('index');
    Route::post('send', SendSupplierEmail::class)->name('send');
    Route::post('whatsapp', SendSupplierWhatsappMessage::class)->name('whatsapp');
    Route::post('{supplierMessage}/reply', [SendSupplierEmail::class, 'inReply'])->name('reply')->withoutScopedBindings();
    Route::get('{supplierMessage}', ShowSupplierMessage::class)->name('show')->withoutScopedBindings();
    Route::post('{supplierMessage}/assign', AssignSupplierMessage::class)->name('assign')->withoutScopedBindings();
    Route::get('{supplierMessage}/attachments/{index}', DownloadSupplierMessageAttachment::class)->name('attachment')->whereNumber('index')->withoutScopedBindings();
    Route::post('{supplierMessage}/attachments/{index}/attach', AttachSupplierMessageAttachment::class)->name('attachment.attach')->whereNumber('index')->withoutScopedBindings();
});

Route::prefix('agent-settings')->as('settings.')->group(function () {
    Route::get('', EditProcurementSettings::class)->name('edit');
    Route::patch('', UpdateProcurementSettings::class)->name('update');
    Route::get('mailbox/connect', ConnectProcurementMailbox::class)->name('mailbox.connect');
    Route::post('mailbox/disconnect', DisconnectProcurementMailbox::class)->name('mailbox.disconnect');
    Route::post('whatsapp-phone/status', [GetWhatsappPhoneNumberStatus::class, 'inProcurement'])->name('whatsapp_phone.status');
    Route::post('whatsapp-app/subscribed', [GetWhatsappSubscribedApps::class, 'inProcurement'])->name('whatsapp_app.subscribed');
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

Route::prefix('agents/{orgAgent}')->as('org_agents.show')->group(function () {
    Route::get('', ShowOrgAgent::class);
    Route::get('suppliers', [IndexOrgAgentSuppliers::class, 'inOrgAgent'])->name('.suppliers.index');
    Route::get('purchase-orders', [IndexPurchaseOrders::class, 'inOrgAgent'])->name('.purchase-orders.index');
    Route::get('purchase-order/{purchaseOrder}', [ShowPurchaseOrder::class, 'inOrgAgent'])->name('.purchase-orders.show');
    Route::get('order-pipeline', ShowAgentOrderPipeline::class)->name('.order_pipeline');
    Route::get('agent-orders', IndexAgentOrders::class)->name('.agent_orders.index');
    Route::get('agent-orders/{agentOrderReference}/pdf', [PdfPurchaseOrder::class, 'inAgentOrder'])->name('.agent_orders.pdf')->where('agentOrderReference', '.*');
    Route::get('agent-orders/{agentOrderReference}', ShowAgentOrder::class)->name('.agent_orders.show')->where('agentOrderReference', '.*');
    Route::get('org-stocks', [IndexOrgStocks::class, 'inOrgAgent'])->name('.org-stocks.index');
    Route::get('containers', [IndexStockDeliveries::class, 'inOrgAgent'])->name('.stock-deliveries.index');
    Route::get('suppliers/{orgSupplier}', [ShowOrgSupplier::class, 'inOrgAgent'])->name('.suppliers.show');
    Route::get('suppliers/{orgSupplier}/edit', [EditOrgSupplier::class, 'inOrgAgent'])->name('.suppliers.edit');
    Route::get('supplier-products', [IndexOrgSupplierProducts::class, 'inOrgAgent'])->name('.supplier_products.index');
    Route::get('supplier-products/{orgSupplierProduct}', [ShowOrgSupplierProduct::class, 'inOrgAgent'])->name('.supplier_products.show');
});

Route::post('containers/{stockDelivery}/invoice', StoreAgentInvoice::class)->name('agent_invoices.store');
Route::post('containers/{stockDelivery}/payments', StoreAgentPayment::class)->name('agent_payments.store');
Route::prefix('agent-invoices/{agentInvoice}')->as('agent_invoices.')->group(function () {
    Route::patch('charges', UpdateAgentInvoiceCharges::class)->name('charges.update');
    Route::get('pdf', PdfAgentInvoice::class)->name('pdf');
});
Route::delete('agent-payments/{agentPayment}', DeleteAgentPayment::class)->name('agent_payments.destroy');
