<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Jan 2024 17:46:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */


use App\Actions\Accounting\Invoice\UI\IndexInvoices;
use App\Actions\Accounting\Invoice\UI\ShowInvoice;
use App\Actions\Accounting\Invoice\UI\ShowRefund;
use App\Actions\Accounting\Payment\UI\ShowPayment;
use App\Actions\Accounting\Payment\UI\ShowRefundPayment;
use App\Actions\CRM\Customer\UI\CreateCustomer;
use App\Actions\CRM\Customer\UI\CreateCustomerClient;
use App\Actions\CRM\Customer\UI\EditCustomer;
use App\Actions\CRM\Customer\UI\EditCustomerClient;
use App\Actions\CRM\Customer\UI\ExportCustomers;
use App\Actions\CRM\Customer\UI\IndexCustomerClients;
use App\Actions\CRM\Customer\UI\IndexCustomers;
use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\CRM\Customer\UI\ShowCustomerClient;
use App\Actions\CRM\WebUser\UI\CreateWebUser;
use App\Actions\CRM\WebUser\UI\EditWebUser;
use App\Actions\CRM\WebUser\UI\IndexWebUsersInCRM;
use App\Actions\CRM\WebUser\UI\ShowWebUser;
use App\Actions\Dispatching\DeliveryNote\UI\IndexDeliveryNotesInCustomers;
use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Actions\Dropshipping\CustomerSalesChannel\UI\IndexCustomerSalesChannels;
use App\Actions\Dropshipping\CustomerSalesChannel\UI\ShowCustomerSalesChannel;
use App\Actions\Dropshipping\Portfolio\UI\IndexPortfoliosInCustomerSalesChannels;
use App\Actions\GoodsIn\ReturnDeliveryNote\UI\IndexReturnDeliveryNotes;
use App\Actions\GoodsIn\ReturnDeliveryNote\UI\ShowReturnDeliveryNote;
use App\Actions\Ordering\Order\UI\IndexOrders;
use App\Actions\Ordering\Order\UI\IndexOrdersInCustomerSalesChannel;
use App\Actions\Ordering\Order\UI\ShowOrder;
use Illuminate\Support\Facades\Route;

Route::get('', IndexCustomers::class)->name('index');
Route::get('create', CreateCustomer::class)->name('create');
Route::get('export', [ExportCustomers::class, 'inShop'])->name('export');
Route::get('{customer}/edit', EditCustomer::class)->name('edit');
Route::prefix('{customer}')->as('show')->group(function () {
    Route::get('', ShowCustomer::class);

    Route::get('/payments/{payment}', [ShowPayment::class, 'inCustomer'])->name('.payments.show');
    Route::get('/refunds/{payment}', [ShowRefundPayment::class, 'inCustomer'])->name('.refunds.show');

    Route::prefix('/returns')->as('.return_delivery_notes.')->group(function () {
        Route::get('/', [IndexReturnDeliveryNotes::class, 'inCustomer'])->name('index');
        Route::get('/{returnDeliveryNote}', [ShowReturnDeliveryNote::class, 'inCustomer'])->name('show');
    });

    Route::prefix('/replacements')->as('.replacements.')->group(function () {
        Route::get('/', [IndexDeliveryNotesInCustomers::class, 'inCustomerReplacements'])->name('index');
        Route::get('/{deliveryNote}', [ShowDeliveryNote::class, 'inCustomerReplacements'])->name('show');
    });

    Route::prefix('/delivery_notes')->as('.delivery_notes.')->group(function () {
        Route::get('/', IndexDeliveryNotesInCustomers::class)->name('index');
        Route::get('/{deliveryNote}', [ShowDeliveryNote::class, 'inCustomerInShop'])->name('show');
    });

    Route::prefix('/invoices')->as('.invoices.')->group(function () {
        Route::get('/', [IndexInvoices::class, 'inCustomer'])->name('index');
        Route::get('/{invoice}', [ShowInvoice::class, 'inCustomerInShop'])->name('show');
    });

    Route::prefix('/orders')->as('.orders')->group(function () {
        Route::get('', [IndexOrders::class, 'inCustomer'])->name('.index');

        Route::prefix('/{order}')->as('.show')->group(function () {
            Route::get('', [ShowOrder::class, 'inCustomerInShop'])->name('');

            Route::get('/invoices/{invoice}', [ShowInvoice::class, 'inOrderInCustomerInShop'])->name('.invoices.show');
            Route::get('/invoices/{invoice}/refunds/{refund}', [ShowRefund::class, 'inInvoiceInOrderInCustomerInShop'])->name('.invoices.show.refunds.show');

            Route::get('/refunds/{refund}', [ShowRefund::class, 'inOrderInCustomerInShop'])->name('.refunds.show');

            Route::get('/delivery-note/{deliveryNote}', [ShowDeliveryNote::class, 'inOrderInCustomerInShop'])->name('.delivery-note.show');
        });
    });

    Route::prefix('/channels')->as('.customer_sales_channels')->group(function () {
        Route::get('', IndexCustomerSalesChannels::class)->name('.index');

        Route::prefix('/{customerSalesChannel}')->as('.show')->group(function () {
            Route::get('', ShowCustomerSalesChannel::class);

            Route::prefix('/portfolios')->as('.portfolios')->group(function () {
                Route::get('', IndexPortfoliosInCustomerSalesChannels::class)->name('.index');
            });

            Route::prefix('/customer-clients')->as('.customer_clients')->group(function () {
                Route::get('', IndexCustomerClients::class)->name('.index');
                Route::get('create', CreateCustomerClient::class)->name('.create');
                Route::get('/{customerClient}', ShowCustomerClient::class)->name('.show');
                Route::get('/{customerClient}/edit', EditCustomerClient::class)->name('.edit');

                Route::prefix('{customerClient}/orders')->as('.show.orders')->group(function () {
                    Route::get('', [IndexOrders::class, 'inCustomerClient'])->name('.index');
                    Route::get('{order}', [ShowOrder::class, 'inCustomerClient'])->name('.show');
                    Route::get('{order}/invoices/{invoice}', [ShowInvoice::class, 'inOrderInCustomerClientInCustomerInShop'])->name('.show.invoices.show');
                    Route::get('{order}/invoices/{invoice}/refunds/{refund}', [ShowRefund::class, 'InInvoiceInOrderInCustomerClientInCustomerInShop'])->name('.show.invoices.show.refunds.show');
                    Route::get('{order}/delivery-note/{deliveryNote}', [ShowDeliveryNote::class, 'inOrderInCustomerClientInCustomerInShop'])->name('.show.delivery-note.show');
                });


                Route::prefix('{customerClient}/invoices')->as('.show.invoices')->group(function () {
                    Route::get('{invoice}', [ShowInvoice::class, 'inCustomerClient'])->name('.show');
                });
            });

            Route::prefix('/orders')->as('.orders')->group(function () {
                Route::get('', IndexOrdersInCustomerSalesChannel::class)->name('.index');
                Route::get('/{order}', [ShowOrder::class, 'inPlatformInCustomer'])->name('.show');
                Route::get('{order}/invoices/{invoice}', [ShowInvoice::class, 'inOrderInPlatformInCustomerInShop'])->name('.show.invoices.show');
                Route::get('{order}/invoices/{invoice}/refunds/{refund}', [ShowRefund::class, 'InInvoiceInOrderInPlatformInCustomerInShop'])->name('.show.invoices.show.refunds.show');
                Route::get('{order}/delivery-note/{deliveryNote}', [ShowDeliveryNote::class, 'inOrderInPlatformInCustomerInShop'])->name('.show.delivery-note.show');
            });
        });
    });

    Route::prefix('web-users')->as('.web_users')->group(function () {
        Route::get('', IndexWebUsersInCRM::class)->name('.index');
        Route::get('create', CreateWebUser::class)->name('.create');
        Route::prefix('{webUser}')->group(function () {
            Route::get('', ShowWebUser::class)->name('.show');
            Route::get('edit', EditWebUser::class)->name('.edit');
        });
    });
});
