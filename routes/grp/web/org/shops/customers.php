<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Jan 2024 17:46:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */


use App\Actions\CRM\Customer\UI\CreateCustomer;
use App\Actions\CRM\Customer\UI\CreateCustomerClient;
use App\Actions\CRM\Customer\UI\EditCustomer;
use App\Actions\CRM\Customer\UI\IndexCustomerClients;
use App\Actions\CRM\Customer\UI\IndexCustomers;
use App\Actions\CRM\Customer\UI\IndexPortfolios;
use App\Actions\CRM\Customer\UI\IndexFilteredProducts;
use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\CRM\Customer\UI\ShowCustomerClient;
use App\Actions\CRM\Customer\UI\ShowCustomerOrder;
use App\Actions\CRM\WebUser\CreateWebUser;
use App\Actions\CRM\WebUser\EditWebUser;
use App\Actions\CRM\WebUser\IndexWebUsers;
use App\Actions\CRM\WebUser\ShowWebUser;
use App\Actions\Ordering\Order\UI\ShowOrder;

Route::get('', IndexCustomers::class)->name('index');
Route::get('create', CreateCustomer::class)->name('create');
Route::get('{customer}/edit', EditCustomer::class)->name('edit');
Route::prefix('{customer}')->as('show')->group(function () {
    Route::get('', ShowCustomer::class);
    Route::get('/orders/{order}', [ShowOrder::class, 'inCustomerInShop'])->name('.orders.show');
    Route::prefix('web-users')->as('.web-users')->group(function () {
        Route::get('', IndexWebUsers::class)->name('.index');
        Route::get('create', CreateWebUser::class)->name('.create');
        Route::prefix('{webUser}')->group(function () {
            Route::get('', ShowWebUser::class)->name('.show');
            Route::get('edit', EditWebUser::class)->name('.edit');
        });
    });
    Route::prefix('customer-clients')->as('.customer-clients')->group(function () {
        Route::get('', [IndexCustomerClients::class, 'inCustomer'])->name('.index');
        Route::get('create', [CreateCustomerClient::class, 'inCustomer'])->name('.create');
        Route::prefix('{customerClient}')->group(function () {
            Route::get('', ShowCustomerClient::class)->name('.show');
            // Route::get('edit', [EditWebUser::class, 'inCustomerInShop'])->name('.edit');
            Route::prefix('orders')->as('.orders')->group(function () {
                Route::get('{order}', ShowCustomerOrder::class)->name('.show');
            });
        });
    });
    Route::prefix('portfolios')->as('.portfolios')->group(function () {
        Route::get('', [IndexPortfolios::class, 'inCustomer'])->name('.index');
        Route::get('products', IndexFilteredProducts::class)->name('.filtered-products');
        // Route::get('create', [CreateCustomerClient::class, 'inCustomer'])->name('.create');
        // Route::prefix('{customerClient}')->group(function () {
        //     Route::get('', ShowCustomerClient::class)->name('.show');
        //     // Route::get('edit', [EditWebUser::class, 'inCustomerInShop'])->name('.edit');
        // });
    });
});
