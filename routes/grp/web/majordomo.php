<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 07 Mar 2023 11:12:51 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

use App\Actions\Helpers\Redirects\RedirectAssetLink;
use App\Actions\Helpers\Redirects\RedirectCollectionLink;
use App\Actions\Helpers\Redirects\RedirectCollectionsInProductCategoryLink;
use App\Actions\Helpers\Redirects\RedirectCustomersInShopFromDashboard;
use App\Actions\Helpers\Redirects\RedirectDeletedInvoicesInShopLink;
use App\Actions\Helpers\Redirects\RedirectDeliveryNotesLink;
use App\Actions\Helpers\Redirects\RedirectEmployeeLink;
use App\Actions\Helpers\Redirects\RedirectInvoiceInAccounting;
use App\Actions\Helpers\Redirects\RedirectInvoicesInCustomerLink;
use App\Actions\Helpers\Redirects\RedirectInvoicesInShopFromDashboard;
use App\Actions\Helpers\Redirects\RedirectInvoicesInShopLink;
use App\Actions\Helpers\Redirects\RedirectMailshotWorkshopLink;
use App\Actions\Helpers\Redirects\RedirectMasterCollectionLink;
use App\Actions\Helpers\Redirects\RedirectMasterProductCategoryLink;
use App\Actions\Helpers\Redirects\RedirectMasterProductLink;
use App\Actions\Helpers\Redirects\RedirectOrder;
use App\Actions\Helpers\Redirects\RedirectOrgStockFamilyLink;
use App\Actions\Helpers\Redirects\RedirectOrgStockLink;
use App\Actions\Helpers\Redirects\RedirectOutboxLink;
use App\Actions\Helpers\Redirects\RedirectOutboxWorkshopLink;
use App\Actions\Helpers\Redirects\RedirectPalletDelivery;
use App\Actions\Helpers\Redirects\RedirectPalletReturn;
use App\Actions\Helpers\Redirects\RedirectPickingSessionLink;
use App\Actions\Helpers\Redirects\RedirectPortfolioItemLink;
use App\Actions\Helpers\Redirects\RedirectProductCategoryLink;
use App\Actions\Helpers\Redirects\RedirectProductLink;
use App\Actions\Helpers\Redirects\RedirectProspectLink;
use App\Actions\Helpers\Redirects\RedirectReturnDeliveryNotesLink;
use App\Actions\Helpers\Redirects\RedirectShopInShopFromDashboard;
use App\Actions\Helpers\Redirects\RedirectStockFamilyLink;
use App\Actions\Helpers\Redirects\RedirectStockLink;
use App\Actions\Helpers\Redirects\RedirectStoredItemAudit;
use App\Actions\Helpers\Redirects\RedirectBarcodeLink;
use App\Actions\Helpers\Redirects\RedirectBrandLink;
use App\Actions\Helpers\Redirects\RedirectChargeLink;
use App\Actions\Helpers\Redirects\RedirectCustomerLink;
use App\Actions\Helpers\Redirects\RedirectMailshotLink;
use App\Actions\Helpers\Redirects\RedirectOfferCampaignLink;
use App\Actions\Helpers\Redirects\RedirectOfferLink;
use App\Actions\Helpers\Redirects\RedirectServiceLink;
use App\Actions\Helpers\Redirects\RedirectShippingZoneSchemaLink;
use App\Actions\Helpers\Redirects\RedirectSupplierLink;
use App\Actions\Helpers\Redirects\RedirectTradeUnitFamilyLink;
use App\Actions\Helpers\Redirects\RedirectTradeUnitLink;
use App\Actions\Helpers\Redirects\RedirectWebpageLink;
use Illuminate\Support\Facades\Route;

Route::get('redirect-asset/{asset:id}', RedirectAssetLink::class)->name('redirect_asset');
Route::get('redirect-deleted-invoices-in-shop/{shop:id}', RedirectDeletedInvoicesInShopLink::class)->name('redirect_deleted_invoices_in_shop');
Route::get('redirect-refunds-in-shop/{invoice:id}', RedirectInvoicesInShopLink::class)->name('redirect_invoices_in_shop');
Route::get('redirect-invoice-in-customer/{invoice:id}', RedirectInvoicesInCustomerLink::class)->name('redirect_invoices_in_customer');

Route::get('redirect-delivery-note/{deliveryNote:id}', RedirectDeliveryNotesLink::class)->name('redirect_delivery_notes');
Route::get('redirect-return-note/{returnDeliveryNote:id}', RedirectReturnDeliveryNotesLink::class)->name('redirect_return_notes');

Route::get('redirect-invoice-in-accounting/{invoice:id}', RedirectInvoiceInAccounting::class)->name('redirect_invoice_in_accounting');


Route::get('redirect-org-stock/{orgStock:id}', RedirectOrgStockLink::class)->name('redirect_org_stock');
Route::get('redirect-org-stock-family/{orgStockFamily:id}', RedirectOrgStockFamilyLink::class)->name('redirect_org_stock_family');
Route::get('redirect-stock/{stock:id}', RedirectStockLink::class)->name('redirect_stock');
Route::get('redirect-stock-family/{stockFamily:id}', RedirectStockFamilyLink::class)->name('redirect_stock_family');
Route::get('redirect-trade-unit/{tradeUnit:id}', RedirectTradeUnitLink::class)->name('redirect_trade_unit');
Route::get('redirect-trade-unit-family/{tradeUnitFamily:id}', RedirectTradeUnitFamilyLink::class)->name('redirect_trade_unit_family');
Route::get('redirect-supplier/{supplier:id}', RedirectSupplierLink::class)->name('redirect_supplier');
Route::get('redirect-prospect/{prospect:id}', RedirectProspectLink::class)->name('redirect_prospect');
Route::get('redirect-charge/{charge:id}', RedirectChargeLink::class)->name('redirect_charge');
Route::get('redirect-service/{service:id}', RedirectServiceLink::class)->name('redirect_service');
Route::get('redirect-shipping-zone-schema/{shippingZoneSchema:id}', RedirectShippingZoneSchemaLink::class)->name('redirect_shipping_zone_schema');
Route::get('redirect-offer/{offer:id}', RedirectOfferLink::class)->name('redirect_offer');
Route::get('redirect-offer-campaign/{offerCampaign:id}', RedirectOfferCampaignLink::class)->name('redirect_offer_campaign');
Route::get('redirect-mailshot-page/{mailshot:id}', RedirectMailshotLink::class)->name('redirect_mailshot');
Route::get('redirect-webpage/{webpage:id}', RedirectWebpageLink::class)->name('redirect_webpage');
Route::get('redirect-brand/{brand:id}', RedirectBrandLink::class)->name('redirect_brand');
Route::get('redirect-barcode/{barcode:id}', RedirectBarcodeLink::class)->name('redirect_barcode');
Route::get('redirect-employee/{employee:id}', RedirectEmployeeLink::class)->name('redirect_employee');
Route::get('redirect-org-stock/{orgStock:id}/to-products-index', [RedirectOrgStockLink::class, 'toProductsIndex'])->name('redirect_org_stock.to_products_index');


Route::get('redirect-invoices-from-dashboard/{shop:id}', RedirectInvoicesInShopFromDashboard::class)->name('redirect_invoices_from_dashboard');
Route::get('redirect-customers-from-dashboard/{shop:id}', RedirectCustomersInShopFromDashboard::class)->name('redirect_customers_from_dashboard');
Route::get('redirect-shops-from-dashboard/{shop:id}', RedirectShopInShopFromDashboard::class)->name('redirect_shops_from_dashboard');

Route::get('redirect-portfolio-item/{portfolio:id}', RedirectPortfolioItemLink::class)->name('redirect_portfolio_item');

Route::get('redirect-product-category/{productCategory:id}', RedirectProductCategoryLink::class)->name('redirect_product_category');
Route::get('redirect-collections-in-product-category/{productCategory:slug}', RedirectCollectionsInProductCategoryLink::class)->name('redirect_collections_in_product_category');

Route::get('redirect-collection/{collection:id}', RedirectCollectionLink::class)->name('redirect_collection');
Route::get('redirect-product/{product:id}', RedirectProductLink::class)->name('redirect_product');


Route::get('redirect-picking-session/{pickingSession:id}', RedirectPickingSessionLink::class)->name('redirect_picking_session');

Route::get('redirect-master-product/{masterAsset:id}', RedirectMasterProductLink::class)->name('redirect_master_product');
Route::get('redirect-master-product-category/{masterProductCategory:id}', RedirectMasterProductCategoryLink::class)->name('redirect_master_product_category');
Route::get('redirect-master-collections/{masterCollection:id}', RedirectMasterCollectionLink::class)->name('redirect_master_collection');


Route::get('redirect-outbox/{outbox:id}', RedirectOutboxLink::class)->name('redirect_outbox');
Route::get('redirect-outbox-workshop/{outbox:id}', RedirectOutboxWorkShopLink::class)->name('redirect_outbox_workshop');

Route::get('redirect-order/{order:id}', RedirectOrder::class)->name('redirect_order');


Route::get('redirect-mailshot-workshop/{mailshot:id}', RedirectMailshotWorkshopLink::class)->name('redirect_mailshot_workshop');

Route::get('redirect-pallet-delivery/{palletDelivery:id}', RedirectPalletDelivery::class)->name('redirect_pallet_delivery');
Route::get('redirect-stored-item-audit/{storedItemAudit:id}', RedirectStoredItemAudit::class)->name('redirect_stored_item_audit');
Route::get('redirect-pallet-return/{palletReturn:id}', RedirectPalletReturn::class)->name('redirect_pallet_return');
Route::get('redirect-customer/{customer:id}', RedirectCustomerLink::class)->name('redirect_customer');
