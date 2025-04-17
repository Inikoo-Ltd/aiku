<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Jan 2024 17:46:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */


use App\Actions\Accounting\Invoice\UI\IndexInvoices;
use App\Actions\Accounting\Invoice\UI\ShowInvoice;
use App\Actions\CRM\Customer\UI\CreateCustomer;
use App\Actions\CRM\Customer\UI\CreateCustomerClient;
use App\Actions\CRM\Customer\UI\EditCustomer;
use App\Actions\CRM\Customer\UI\EditCustomerClient;
use App\Actions\CRM\Customer\UI\IndexCustomerClients;
use App\Actions\CRM\Customer\UI\IndexCustomerPlatformCustomerClients;
use App\Actions\CRM\Customer\UI\IndexCustomerPlatformOrders;
use App\Actions\CRM\Customer\UI\IndexCustomerPlatformPortfolios;
use App\Actions\CRM\Customer\UI\IndexCustomerPlatforms;
use App\Actions\CRM\Customer\UI\IndexCustomers;
use App\Actions\CRM\Customer\UI\IndexPortfolios;
use App\Actions\CRM\Customer\UI\IndexFilteredProducts;
use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\CRM\Customer\UI\ShowCustomerClient;
use App\Actions\CRM\Customer\UI\ShowCustomerPlatform;
use App\Actions\CRM\WebUser\CreateWebUser;
use App\Actions\CRM\WebUser\EditWebUser;
use App\Actions\CRM\WebUser\IndexWebUsers;
use App\Actions\CRM\WebUser\ShowWebUser;
use App\Actions\Dispatching\DeliveryNote\UI\IndexDeliveryNotesInCustomers;
use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Actions\Ordering\Order\UI\IndexOrders;
use App\Actions\Ordering\Order\UI\ShowOrder;

Route::get('', IndexCustomers::class)->name('index');
Route::get('create', CreateCustomer::class)->name('create');
Route::get('{customer}/edit', EditCustomer::class)->name('edit');
Route::prefix('{customer}')->as('show')->group(function () {
    Route::get('', ShowCustomer::class);
    Route::get('/orders', [IndexOrders::class, 'inCustomer'])->name('.orders.index');
    Route::get('/orders/{order}', [ShowOrder::class, 'inCustomerInShop'])->name('.orders.show');

    Route::get('/delivery_notes', IndexDeliveryNotesInCustomers::class)->name('.delivery_notes.index');
    Route::get('/delivery_notes/{deliveryNote}', [ShowDeliveryNote::class, 'inCustomerInShop'])->name('.delivery_notes.show');

    Route::get('/invoices', [IndexInvoices::class, 'inCustomer'])->name('.invoices.index');
    Route::get('/invoices/{invoice}', [ShowInvoice::class, 'inCustomerInShop'])->name('.invoices.show');

    Route::prefix('/platforms')->as('.platforms')->group(function () {
        Route::get('', IndexCustomerPlatforms::class)->name('.index');
        Route::prefix('/{customerHasPlatform}')->as('.show')->group(function () {
            Route::get('', ShowCustomerPlatform::class);
            Route::prefix('/portfolios')->as('.portfolios')->group(function () {
                Route::get('', IndexCustomerPlatformPortfolios::class)->name('.index');
            });
            Route::prefix('/customer-clients')->as('.customer-clients')->group(function () {
                Route::get('', IndexCustomerClients::class)->name('.aiku.index');
                Route::get('create', CreateCustomerClient::class)->name('.create');
                Route::get('other-platforms', IndexCustomerPlatformCustomerClients::class)->name('.other-platform.index');
                Route::get('/{customerClient}', ShowCustomerClient::class)->name('.show');
                Route::get('/{customerClient}/edit', [EditCustomerClient::class, 'inPlatform'])->name('.edit');
                
                Route::prefix('{customerClient}/orders')->as('.show.orders')->group(function () {
                    Route::get('', [IndexOrders::class, 'inCustomerClient'])->name('.index');
                    Route::get('{order}', [ShowOrder::class, 'inCustomerClient'])->name('.show');
                });

                Route::prefix('{customerClient}/delivery_notes')->as('.show.delivery_notes')->group(function () {
                    Route::get('', [IndexDeliveryNotesInCustomers::class,'inCustomerClient'])->name('.index');
                    Route::get('{deliveryNote}', [ShowDeliveryNote::class, 'inCustomerClientInShop'])->name('.show');
                });
            
                Route::prefix('{customerClient}/invoices')->as('.show.invoices')->group(function () {
                    Route::get('', [IndexInvoices::class, 'inCustomerClient'])->name('.index');
                    Route::get('{invoice}', [ShowInvoice::class, 'inCustomerClient'])->name('.show');
                });

            });
            Route::prefix('/orders')->as('.orders')->group(function () {
                Route::get('', IndexCustomerPlatformOrders::class)->name('.index');
                Route::get('/{order}', [ShowOrder::class, 'inPlatformInCustomer'])->name('.show');
            });
        });
    });
    Route::prefix('web-users')->as('.web-users')->group(function () {
        Route::get('', IndexWebUsers::class)->name('.index');
        Route::get('create', CreateWebUser::class)->name('.create');
        Route::prefix('{webUser}')->group(function () {
            Route::get('', ShowWebUser::class)->name('.show');
            Route::get('edit', EditWebUser::class)->name('.edit');
        });
    });

    Route::prefix('portfolios')->as('.portfolios')->group(function () {
        Route::get('', IndexPortfolios::class)->name('.index');
        Route::get('products', IndexFilteredProducts::class)->name('.filtered-products');
    });
});
