<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 07 Mar 2023 11:12:51 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

use App\Actions\Helpers\Redirects\RedirectAssetLink;
use App\Actions\Helpers\Redirects\RedirectCollectionsInProductCategoryLink;
use App\Actions\Helpers\Redirects\RedirectCustomersInShopFromDashboard;
use App\Actions\Helpers\Redirects\RedirectDeletedInvoicesInShopLink;
use App\Actions\Helpers\Redirects\RedirectDeliveryNotesLink;
use App\Actions\Helpers\Redirects\RedirectInvoicesInCustomerLink;
use App\Actions\Helpers\Redirects\RedirectInvoicesInShopFromDashboard;
use App\Actions\Helpers\Redirects\RedirectInvoicesInShopLink;
use App\Actions\Helpers\Redirects\RedirectMasterProductCategoryLink;
use App\Actions\Helpers\Redirects\RedirectMasterProductLink;
use App\Actions\Helpers\Redirects\RedirectOrgStockLink;
use App\Actions\Helpers\Redirects\RedirectPickingSessionLink;
use App\Actions\Helpers\Redirects\RedirectPortfolioItemLink;
use App\Actions\Helpers\Redirects\RedirectProductCategoryLink;
use App\Actions\Helpers\Redirects\RedirectShopInShopFromDashboard;
use App\Actions\Helpers\Upload\DownloadUploads;
use App\Actions\Helpers\Upload\UI\ShowUpload;
use Illuminate\Support\Facades\Route;

Route::get('redirect-asset/{asset:id}', RedirectAssetLink::class)->name('redirect_asset');
Route::get('redirect-deleted-invoices-in-shop/{shop:id}', RedirectDeletedInvoicesInShopLink::class)->name('redirect_deleted_invoices_in_shop');
Route::get('redirect-refunds-in-shop/{invoice:id}', RedirectInvoicesInShopLink::class)->name('redirect_invoices_in_shop');
Route::get('redirect-invoice-in-customer/{invoice:id}', RedirectInvoicesInCustomerLink::class)->name('redirect_invoices_in_customer');

Route::get('redirect-delivery-note/{deliveryNote:id}', RedirectDeliveryNotesLink::class)->name('redirect_delivery_notes');
Route::get('redirect-org-stock/{orgStock:id}', RedirectOrgStockLink::class)->name('redirect_org_stock');


Route::get('redirect-invoices-from-dashboard/{shop:id}', RedirectInvoicesInShopFromDashboard::class)->name('redirect_invoices_from_dashboard');
Route::get('redirect-customers-from-dashboard/{shop:id}', RedirectCustomersInShopFromDashboard::class)->name('redirect_customers_from_dashboard');
Route::get('redirect-shops-from-dashboard/{shop:id}', RedirectShopInShopFromDashboard::class)->name('redirect_shops_from_dashboard');

Route::get('redirect-portfolio-item/{portfolio:id}', RedirectPortfolioItemLink::class)->name('redirect_portfolio_item');

Route::get('redirect-product-category/{productCategory:slug}', RedirectProductCategoryLink::class)->name('redirect_product_category');
Route::get('redirect-collections-in-product-category/{productCategory:slug}', RedirectCollectionsInProductCategoryLink::class)->name('redirect_collections_in_product_category');

Route::get('redirect-picking-session/{pickingSession:id}', RedirectPickingSessionLink::class)->name('redirect_picking_session');

Route::get('redirect-master-product/{masterAsset:id}', RedirectMasterProductLink::class)->name('redirect_master_product');
Route::get('redirect-master-product-category/{masterProductCategory:id}', RedirectMasterProductCategoryLink::class)->name('redirect_master_product_category');



Route::prefix('uploads/{upload}')->as('uploads.')->group(function () {
    Route::get('records', ShowUpload::class)->name('records.show');
    Route::get('download', DownloadUploads::class)->name('records.download');
});
