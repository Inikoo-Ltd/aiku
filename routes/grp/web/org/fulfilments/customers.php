<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Jan 2024 17:46:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */


use App\Actions\Accounting\Invoice\UI\EditInvoice;
use App\Actions\Accounting\Invoice\UI\IndexInvoices;
use App\Actions\Accounting\Invoice\UI\IndexRefunds;
use App\Actions\Accounting\Invoice\UI\ShowFulfilmentInvoice;
use App\Actions\Accounting\Invoice\UI\ShowInvoice;
use App\Actions\Accounting\Invoice\UI\ShowRefund;
use App\Actions\Accounting\StandaloneFulfilmentInvoice\UI\ShowStandaloneFulfilmentInvoiceInProcess;
use App\Actions\CRM\Customer\UI\EditCustomer;
use App\Actions\CRM\Customer\UI\EditCustomerClient;
use App\Actions\CRM\Customer\UI\IndexCustomerClients;
use App\Actions\CRM\Customer\UI\ShowCustomerClient;
use App\Actions\CRM\WebUser\CreateWebUser;
use App\Actions\CRM\WebUser\EditWebUser;
use App\Actions\CRM\WebUser\IndexWebUsers;
use App\Actions\CRM\WebUser\ShowWebUser;
use App\Actions\Dropshipping\CustomerSalesChannel\UI\IndexCustomerSalesChannelsInFulfilment;
use App\Actions\Dropshipping\CustomerSalesChannel\UI\ShowCustomerSalesChannelInFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\FetchNewWebhookFulfilmentCustomer;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\FulfilmentCustomer\UI\CreateFulfilmentCustomer;
use App\Actions\Fulfilment\FulfilmentCustomer\UI\EditFulfilmentCustomer;
use App\Actions\Fulfilment\FulfilmentCustomer\UI\IndexFulfilmentCustomersApproved;
use App\Actions\Fulfilment\FulfilmentCustomer\UI\IndexFulfilmentCustomersPendingApproval;
use App\Actions\Fulfilment\FulfilmentCustomer\UI\IndexFulfilmentCustomersRejected;
use App\Actions\Fulfilment\Pallet\DownloadPalletsTemplate;
use App\Actions\Fulfilment\Pallet\DownloadPalletStoredItemTemplate;
use App\Actions\Fulfilment\Pallet\PdfPallet;
use App\Actions\Fulfilment\Pallet\UI\EditPallet;
use App\Actions\Fulfilment\Pallet\UI\IndexPalletsInCustomer;
use App\Actions\Fulfilment\Pallet\UI\IndexPalletsInStoredItem;
use App\Actions\Fulfilment\Pallet\UI\ShowPallet;
use App\Actions\Fulfilment\Pallet\UI\ShowPalletDeleted;
use App\Actions\Fulfilment\PalletDelivery\UI\EditPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\UI\IndexPalletDeliveries;
use App\Actions\Fulfilment\PalletDelivery\UI\ShowPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\UI\ShowPalletDeliveryDeleted;
use App\Actions\Fulfilment\PalletReturn\ExportPalletReturnPallet;
use App\Actions\Fulfilment\PalletReturn\ExportPalletReturnStoredItem;
use App\Actions\Fulfilment\PalletReturn\UI\IndexPalletReturns;
use App\Actions\Fulfilment\PalletReturn\UI\IndexPalletReturnsInPlatform;
use App\Actions\Fulfilment\PalletReturn\UI\ShowPalletReturn;
use App\Actions\Fulfilment\PalletReturn\UI\ShowPalletReturnDeleted;
use App\Actions\Fulfilment\PalletReturn\UI\ShowStoredItemReturn;
use App\Actions\Fulfilment\RecurringBill\UI\IndexRecurringBills;
use App\Actions\Fulfilment\RecurringBill\UI\ShowRecurringBill;
use App\Actions\Fulfilment\RentalAgreement\UI\CreateRentalAgreement;
use App\Actions\Fulfilment\RentalAgreement\UI\EditRentalAgreement;
use App\Actions\Fulfilment\Space\UI\CreateSpace;
use App\Actions\Fulfilment\Space\UI\EditSpace;
use App\Actions\Fulfilment\Space\UI\IndexSpaces;
use App\Actions\Fulfilment\Space\UI\ShowSpace;
use App\Actions\Fulfilment\StoredItem\UI\CreateStoredItem;
use App\Actions\Fulfilment\StoredItem\UI\EditStoredItem;
use App\Actions\Fulfilment\StoredItem\UI\IndexStoredItems;
use App\Actions\Fulfilment\StoredItem\UI\IndexStoredItemsInFulfilmentCustomerPlatform;
use App\Actions\Fulfilment\StoredItem\UI\ShowStoredItem;
use App\Actions\Fulfilment\StoredItemAudit\UI\CreateStoredItemAudit;
use App\Actions\Fulfilment\StoredItemAudit\UI\CreateStoredItemAuditFromPallet;
use App\Actions\Fulfilment\StoredItemAudit\UI\IndexStoredItemAudits;
use App\Actions\Fulfilment\StoredItemAudit\UI\ShowStoredItemAudit;
use App\Actions\Fulfilment\StoredItemAudit\UI\ShowStoredItemAuditForPallet;
use App\Actions\Helpers\Upload\UI\IndexRecentUploads;
use App\Actions\Ordering\Order\UI\IndexOrders;
use App\Actions\Ordering\Order\UI\ShowOrder;

Route::get('', IndexFulfilmentCustomersApproved::class)->name('index');
Route::get('pending-approval', IndexFulfilmentCustomersPendingApproval::class)->name('pending_approval.index');
Route::get('rejected', IndexFulfilmentCustomersRejected::class)->name('rejected.index');
Route::get('create', CreateFulfilmentCustomer::class)->name('create');

Route::get('{fulfilmentCustomer}/edit', [EditCustomer::class, 'inShop'])->name('edit');

Route::prefix('{fulfilmentCustomer}')->as('show')->group(function () {
    Route::get('', ShowFulfilmentCustomer::class);
    Route::get('/edit', EditFulfilmentCustomer::class)->name('.edit');


    Route::get('/rental-agreement', CreateRentalAgreement::class)->name('.rental-agreement.create');
    Route::get('/rental-agreement/edit', EditRentalAgreement::class)->name('.rental-agreement.edit');

    Route::get('webhook', FetchNewWebhookFulfilmentCustomer::class)->name('.webhook.fetch');

    Route::prefix('web-users')->as('.web-users.')->group(function () {
        Route::get('', [IndexWebUsers::class, 'inFulfilmentCustomer'])->name('index');
        Route::get('create', [CreateWebUser::class, 'inFulfilmentCustomer'])->name('create');
        Route::prefix('{webUser}')->group(function () {
            Route::get('', [ShowWebUser::class, 'inFulfilmentCustomer'])->name('show');
            Route::get('edit', [EditWebUser::class, 'inFulfilmentCustomer'])->name('edit');
        });
    });


    Route::get('stored-items', [IndexStoredItems::class, 'inFulfilmentCustomer'])->name('.stored-items.index');
    Route::get('stored-items/create', CreateStoredItem::class)->name('.stored-items.create');
    Route::get('stored-items/{storedItem}', [ShowStoredItem::class, 'inFulfilmentCustomer'])->name('.stored-items.show');
    Route::get('stored-items/{storedItem}/edit', [EditStoredItem::class, 'inFulfilmentCustomer'])->name('.stored-items.edit');

    Route::prefix('pallets')->as('.pallets.')->group(function () {
        Route::get('', IndexPalletsInCustomer::class)->name('index');
        Route::get('stored-item/{storedItem}', IndexPalletsInStoredItem::class)->name('stored-item.index');
        Route::get('{pallet}', [ShowPallet::class, 'inFulfilmentCustomer'])->name('show');
        Route::get('{pallet}/edit', [EditPallet::class, 'inFulfilmentCustomer'])->name('edit');
        Route::get('{pallet}/export', [PdfPallet::class, 'inFulfilmentCustomer'])->name('export');
        Route::get('{pallet}/stored-item-audits', [IndexStoredItemAudits::class, 'inPalletInFulfilmentCustomer'])->name('stored-item-audits.index');
        Route::get('{pallet}/stored-item-audits/create', CreateStoredItemAuditFromPallet::class)->name('stored-item-audits.create');
        Route::get('{pallet}/stored-item-audits/{storedItemAudit}', [ShowStoredItemAuditForPallet::class, 'inPalletInFulfilmentCustomer'])->name('stored-item-audits.show');
    });

    Route::prefix('pallet-deliveries')->as('.pallet_deliveries.')->group(function () {
        Route::get('', [IndexPalletDeliveries::class, 'inFulfilmentCustomer'])->name('index');
        Route::get('{palletDelivery}', [ShowPalletDelivery::class, 'inFulfilmentCustomer'])->name('show');
        Route::get('{palletDelivery}/edit', [EditPalletDelivery::class, 'inFulfilmentCustomer'])->name('edit');
        Route::get('{palletDelivery}/pallets/{pallet}', [ShowPallet::class, 'inFulfilmentCustomer'])->name('pallets.show');

        Route::get('{palletDelivery}/pallets-histories', [IndexRecentUploads::class, 'inPalletDelivery'])->name('pallets.uploads.history');
        Route::get('{palletDelivery}/pallets-templates', DownloadPalletsTemplate::class)->name('pallets.uploads.templates');
        Route::get('{palletDelivery}/pallet-stored-item-templates', DownloadPalletStoredItemTemplate::class)->name('pallets-stored-item.uploads.templates');
    });

    Route::get('pallets-deleted/{pallet}', [ShowPalletDeleted::class, 'inFulfilmentCustomer'])->name('.deleted_pallets.show');
    Route::get('pallet-deliveries-deleted/{palletDelivery}', [ShowPalletDeliveryDeleted::class, 'inFulfilmentCustomer'])->name('.deleted_pallet_deliveries.show');
    Route::get('pallet-returns-deleted/{palletReturn}', [ShowPalletReturnDeleted::class, 'inFulfilmentCustomer'])->name('.deleted_pallet_returns.show');

    Route::prefix('pallet-returns')->as('.pallet_returns.')->group(function () {
        Route::get('', [IndexPalletReturns::class, 'inFulfilmentCustomer'])->name('index');
        Route::get('{palletReturn}', [ShowPalletReturn::class, 'inFulfilmentCustomer'])->name('show');
        Route::get('stored-item/{palletReturn}', [ShowStoredItemReturn::class, 'inFulfilmentCustomer'])->name('with_stored_items.show');
        Route::get('{palletReturn}/pallets/{pallet}', [ShowPallet::class, 'inFulfilmentCustomer'])->name('pallets.show');

        Route::get('{palletReturn}/pallets-histories', [IndexRecentUploads::class, 'inPalletReturn'])->name('pallets.uploads.history');
        Route::get('pallets-stored-items/export', ExportPalletReturnStoredItem::class)->name('pallets.stored-items.export');
        Route::get('pallets/export', ExportPalletReturnPallet::class)->name('pallets.export');
    });

    Route::prefix('recurring-bills')->as('.recurring_bills.')->group(function () {
        Route::get('', [IndexRecurringBills::class, 'inFulfilmentCustomer'])->name('index');
        Route::get('{recurringBill}', [ShowRecurringBill::class, 'inFulfilmentCustomer'])->name('show');
    });

    Route::prefix('spaces')->as('.spaces.')->group(function () {
        Route::get('', [IndexSpaces::class, 'inFulfilmentCustomer'])->name('index');
        Route::get('create', CreateSpace::class)->name('create');
        Route::get('{space}', ShowSpace::class)->name('show');
        Route::get('{space}/edit', EditSpace::class)->name('edit');
    });

    Route::prefix('invoices')->as('.invoices.')->group(function () {
        Route::get('', [IndexInvoices::class, 'inFulfilmentCustomer'])->name('index');
        Route::get('{invoice}', [ShowFulfilmentInvoice::class, 'inFulfilmentCustomer'])->name('show');
        Route::get('{invoice}/in-process', ShowStandaloneFulfilmentInvoiceInProcess::class)->name('in-process.show');
        Route::get('{invoice}/edit', [EditInvoice::class, 'inFulfilmentCustomer'])->name('edit');

        Route::prefix('{invoice}/refunds')->as('show.refunds.')->group(function () {
            Route::get('', [IndexRefunds::class, 'inInvoiceInFulfilmentCustomer'])->name('index');
            Route::get('{refund}', [ShowRefund::class, 'inInvoiceInFulfilmentCustomer'])->name('show');
        });
    });

    Route::prefix('customer-clients')->as('.customer_clients')->group(function () {
        Route::get('', [IndexCustomerClients::class, 'inFulfilmentCustomer'])->name('.index');
        Route::get('{customerClient}', [ShowCustomerClient::class, 'inFulfilmentCustomer'])->name('.show');
    });

    Route::prefix('channels')->as('.customer_sales_channels')->group(function () {
        Route::get('', IndexCustomerSalesChannelsInFulfilment::class)->name('.index');
        Route::prefix('/{customerSalesChannel}')->as('.show')->group(function () {
            Route::get('', ShowCustomerSalesChannelInFulfilment::class);

            Route::prefix('/portfolios')->as('.portfolios')->group(function () {
                Route::get('', IndexStoredItemsInFulfilmentCustomerPlatform::class)->name('.index');
                Route::get('/{storedItem}', [ShowStoredItem::class, 'inPlatformInFulfilmentCustomer'])->name('.show')->withoutScopedBindings();
            });
            Route::prefix('/customer-clients')->as('.customer_clients')->group(function () {
                Route::get('', [IndexCustomerClients::class, 'inCustomerSalesChannelInFulfilmentCustomer'])->name('.index');
                Route::get('/{customerClient}', [ShowCustomerClient::class, 'inPlatformInFulfilmentCustomer'])->name('.show');
                Route::get('/{customerClient}/edit', [EditCustomerClient::class, 'inFulfilmentPlatform'])->name('.edit');

                Route::prefix('{customerClient}/orders')->as('.show.orders')->group(function () {
                    Route::get('', [IndexOrders::class, 'inFulfilmentCustomerClient'])->name('.index');
                    Route::get('{order}', [ShowOrder::class, 'inFulfilmentCustomerClient'])->name('.show');
                });

                Route::prefix('{customerClient}/invoices')->as('.show.invoices')->group(function () {
                    Route::get('{invoice}', [ShowInvoice::class, 'inFulfilmentCustomerClient'])->name('.show');
                });
            });
            Route::prefix('/orders')->as('.orders')->group(function () {
                Route::get('', IndexPalletReturnsInPlatform::class)->name('.index');
                Route::get('/{palletReturn}', [ShowStoredItemReturn::class, 'inPlatformInFulfilmentCustomer'])->name('.show');
            });
        });
    });

    Route::get('/stored-item-audits', [IndexStoredItemAudits::class, 'inFulfilmentCustomer'])->name('.stored-item-audits.index');
    Route::get('/stored-item-audits/create', [CreateStoredItemAudit::class, 'inFulfilmentCustomer'])->name('.stored-item-audits.create');
    Route::get('/stored-item-audits/{storedItemAudit}', [ShowStoredItemAudit::class, 'inFulfilmentCustomer'])->name('.stored-item-audits.show');
});
