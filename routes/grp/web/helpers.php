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
use App\Actions\Helpers\Redirects\RedirectInvoiceInAccounting;
use App\Actions\Helpers\Redirects\RedirectInvoicesInCustomerLink;
use App\Actions\Helpers\Redirects\RedirectInvoicesInShopFromDashboard;
use App\Actions\Helpers\Redirects\RedirectInvoicesInShopLink;
use App\Actions\Helpers\Redirects\RedirectMailshotWorkshopLink;
use App\Actions\Helpers\Redirects\RedirectMasterCollectionLink;
use App\Actions\Helpers\Redirects\RedirectMasterProductCategoryLink;
use App\Actions\Helpers\Redirects\RedirectMasterProductLink;
use App\Actions\Helpers\Redirects\RedirectOrder;
use App\Actions\Helpers\Redirects\RedirectOrgStockLink;
use App\Actions\Helpers\Redirects\RedirectOutboxLink;
use App\Actions\Helpers\Redirects\RedirectOutboxWorkshopLink;
use App\Actions\Helpers\Redirects\RedirectPalletDelivery;
use App\Actions\Helpers\Redirects\RedirectPalletReturn;
use App\Actions\Helpers\Redirects\RedirectPickingSessionLink;
use App\Actions\Helpers\Redirects\RedirectPortfolioItemLink;
use App\Actions\Helpers\Redirects\RedirectProductCategoryLink;
use App\Actions\Helpers\Redirects\RedirectProductLink;
use App\Actions\Helpers\Redirects\RedirectReturnDeliveryNotesLink;
use App\Actions\Helpers\Redirects\RedirectShopInShopFromDashboard;
use App\Actions\Helpers\Redirects\RedirectStoredItemAudit;
use App\Actions\Helpers\Upload\DownloadUploads;
use App\Actions\Helpers\Upload\UI\ShowUpload;
use Illuminate\Support\Facades\Route;


Route::prefix('uploads/{upload}')->as('uploads.')->group(function () {
    Route::get('records', ShowUpload::class)->name('records.show');
    Route::get('download', DownloadUploads::class)->name('records.download');
});

