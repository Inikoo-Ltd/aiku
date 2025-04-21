<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Apr 2024 14:12:41 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Accounting\Invoice\UI\IndexDeletedInvoices;
use App\Actions\Accounting\Invoice\UI\IndexInvoicesInGroup;
use App\Actions\Accounting\Invoice\UI\IndexRefundsInGroup;
use App\Actions\Accounting\InvoiceTransaction\UI\IndexInvoiceTransactionsInGroup;
use App\Actions\Accounting\Payment\UI\IndexPayments;
use App\Actions\Accounting\PaymentAccount\UI\IndexPaymentAccounts;
use App\Actions\Accounting\UI\IndexCustomerBalances;
use App\Actions\Billables\Charge\UI\IndexCharges;
use App\Actions\Catalogue\Collection\UI\IndexCollection;
use App\Actions\Catalogue\Product\UI\IndexProducts;
use App\Actions\Catalogue\ProductCategory\UI\IndexDepartments;
use App\Actions\Catalogue\ProductCategory\UI\IndexFamilies;
use App\Actions\Comms\DispatchedEmail\UI\IndexDispatchedEmails;
use App\Actions\Comms\EmailAddress\UI\IndexEmailAddress;
use App\Actions\Comms\EmailAddress\UI\ShowEmailAddress;
use App\Actions\Comms\EmailBulkRun\UI\IndexEmailBulkRuns;
use App\Actions\Comms\Mailshot\UI\IndexAbandonedCartMailshots;
use App\Actions\Comms\Mailshot\UI\IndexInviteMailshots;
use App\Actions\Comms\Mailshot\UI\IndexMarketingMailshots;
use App\Actions\Comms\Mailshot\UI\IndexNewsletterMailshots;
use App\Actions\Comms\Outbox\UI\IndexOutboxes;
use App\Actions\Comms\PostRoom\UI\IndexPostRooms;
use App\Actions\Comms\PostRoom\UI\ShowPostRoom;
use App\Actions\CRM\Customer\UI\IndexCustomers;
use App\Actions\CRM\Prospect\UI\IndexProspects;
use App\Actions\CRM\WebUser\IndexWebUsers;
use App\Actions\Discounts\Offer\UI\IndexOffers;
use App\Actions\Discounts\OfferCampaign\UI\IndexOfferCampaigns;
use App\Actions\Dispatching\DeliveryNote\UI\IndexDeliveryNotesInGroup;
use App\Actions\Fulfilment\Pallet\UI\IndexPallets;
use App\Actions\Fulfilment\PalletDelivery\UI\IndexPalletDeliveries;
use App\Actions\Fulfilment\StoredItem\UI\IndexStoredItems;
use App\Actions\Fulfilment\UI\Catalogue\Rentals\IndexFulfilmentRentals;
use App\Actions\Fulfilment\UI\Catalogue\Services\IndexFulfilmentServices;
use App\Actions\HumanResources\ClockingMachine\UI\IndexClockingMachines;
use App\Actions\HumanResources\Employee\UI\IndexEmployees;
use App\Actions\HumanResources\JobPosition\UI\IndexJobPositions;
use App\Actions\HumanResources\Timesheet\UI\IndexTimesheets;
use App\Actions\HumanResources\Workplace\UI\IndexWorkplaces;
use App\Actions\Inventory\Location\UI\IndexLocations;
use App\Actions\Inventory\OrgStockMovement\UI\IndexOrgStockMovements;
use App\Actions\Inventory\Warehouse\UI\IndexWarehouses;
use App\Actions\Inventory\WarehouseArea\UI\IndexWarehouseAreas;
use App\Actions\Ordering\Order\UI\IndexOrdersInGroup;
use App\Actions\Ordering\Purge\UI\IndexPurges;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrders;
use App\Actions\SupplyChain\Agent\UI\IndexAgents;
use App\Actions\SupplyChain\Supplier\UI\IndexSuppliers;
use App\Actions\SupplyChain\SupplierProduct\UI\IndexSupplierProducts;
use App\Actions\SysAdmin\Group\UI\IndexHistoryInGroup;
use App\Actions\Web\Banner\UI\IndexBanners;
use App\Actions\Web\Webpage\UI\IndexWebpages;
use App\Actions\Web\Website\UI\IndexWebsites;
use Illuminate\Support\Facades\Route;

Route::get('/', ShowGroupOverviewHub::class)->name('hub');

Route::name('sysadmin.')->prefix('sysadmin')->group(function () {
    Route::get('/changelog', IndexHistoryInGroup::class)->name('changelog.index');
});

Route::name('comms-marketing.')->prefix('comms-marketing')->group(function () {
    Route::get('/post-rooms', IndexPostRooms::class)->name('post-rooms.index');
    Route::get('/post-rooms/{postRoom}', ShowPostRoom::class)->name('post-rooms.show');
    Route::get('/outboxes', [IndexOutboxes::class, 'inGroup'])->name('outboxes.index');
    Route::get('/newsletters', [IndexNewsletterMailshots::class, 'inGroup'])->name('newsletters.index');
    Route::get('/marketing-mailshots', [IndexMarketingMailshots::class, 'inGroup'])->name('marketing-mailshots.index');
    Route::get('/invite-mailshots', [IndexInviteMailshots::class, 'inGroup'])->name('invite-mailshots.index');
    Route::get('/abandoned-cart-mailshots', [IndexAbandonedCartMailshots::class, 'inGroup'])->name('abandoned-cart-mailshots.index');
    Route::get('/email-bulk-runs', [IndexEmailBulkRuns::class, 'inGroup'])->name('email-bulk-runs.index');
    Route::get('/email-addresses', IndexEmailAddress::class)->name('email-addresses.index');
    Route::get('/email-address/{emailAddress}', ShowEmailAddress::class)->name('email-addresses.show');
    Route::get('/dispatched-emails', [IndexDispatchedEmails::class, 'inGroup'])->name('dispatched-emails.index');
});

Route::name('catalogue.')->prefix('catalogue')->group(function () {
    Route::get('/products', [IndexProducts::class, 'inGroup'])->name('products.index');
    Route::get('/departments', [IndexDepartments::class, 'inGroup'])->name('departments.index');
    Route::get('/families', [IndexFamilies::class, 'inGroup'])->name('families.index');
    Route::get('/collections', [IndexCollection::class, 'inGroup'])->name('collections.index');
});

Route::name('billables.')->prefix('billables')->group(function () {
    Route::get('/rentals', [IndexFulfilmentRentals::class, 'inGroup'])->name('rentals.index');
    Route::get('/charges', [IndexCharges::class, 'inGroup'])->name('charges.index');
    Route::get('/services', [IndexFulfilmentServices::class, 'inGroup'])->name('services.index');
});

Route::name('offer.')->prefix('offer')->group(function () {
    Route::get('/campaigns', [IndexOfferCampaigns::class, 'inGroup'])->name('campaigns.index');
    Route::get('/offers', [IndexOffers::class, 'inGroup'])->name('offers.index');
});

Route::name('web.')->prefix('web')->group(function () {
    Route::get('/websites', [IndexWebsites::class, 'inGroup'])->name('websites.index');
    Route::get('/webpages', [IndexWebpages::class, 'inGroup'])->name('webpages.index');
    Route::get('/banners', [IndexBanners::class, 'inGroup'])->name('banners.index');
});

Route::name('crm.')->prefix('crm')->group(function () {
    Route::get('/customers', [IndexCustomers::class, 'inGroup'])->name('customers.index');
    Route::get('/web-users', [IndexWebUsers::class, 'inGroup'])->name('web-users.index');
    Route::get('/prospects', [IndexProspects::class, 'inGroup'])->name('prospects.index');
});

Route::name('ordering.')->prefix('ordering')->group(function () {
    Route::get('/orders', IndexOrdersInGroup::class)->name('orders.index');
    Route::get('/purges', [IndexPurges::class, 'inGroup'])->name('purges.index');
    Route::get('/delivery-notes', IndexDeliveryNotesInGroup::class)->name('delivery_notes.index');
    Route::get('/transactions', IndexInvoiceTransactionsInGroup::class)->name('transactions.index');
});

Route::name('inventory.')->prefix('inventory')->group(function () {
    Route::get('/org-stock-movements', [IndexOrgStockMovements::class, 'inGroup'])->name('org-stock-movements.index');
    Route::get('/warehouses', [IndexWarehouses::class, 'inGroup'])->name('warehouses.index');
    Route::get('/warehouses-areas', [IndexWarehouseAreas::class, 'inGroup'])->name('warehouses-areas.index');
    Route::get('/locations', [IndexLocations::class, 'inGroup'])->name('locations.index');
});

Route::name('fulfilment.')->prefix('fulfilment')->group(function () {
    Route::get('/pallets', [IndexPallets::class, 'inGroup'])->name('pallets.index');
    Route::get('/stored-items', [IndexStoredItems::class, 'inGroup'])->name('stored-items.index');
    Route::get('/pallet-deliveries', [IndexPalletDeliveries::class, 'inGroup'])->name('pallet-deliveries.index');
});

Route::name('procurement.')->prefix('procurement')->group(function () {
    Route::get('/agents', IndexAgents::class)->name('agents.index');
    Route::get('/suppliers', IndexSuppliers::class)->name('suppliers.index');
    Route::get('/supplier-products', IndexSupplierProducts::class)->name('supplier-products.index');
    Route::get('/purchase-orders', [IndexPurchaseOrders::class, 'inGroup'])->name('purchase-orders.index');
});

Route::name('accounting.')->prefix('accounting')->group(function () {
    Route::get('/payment-accounts', [IndexPaymentAccounts::class, 'inGroup'])->name('payment-accounts.index');
    Route::get('/payments', [IndexPayments::class, 'inGroup'])->name('payments.index');
    Route::get('/customer-balances', [IndexCustomerBalances::class, 'inGroup'])->name('customer-balances.index');
    Route::get('/invoices', IndexInvoicesInGroup::class)->name('invoices.index');
    Route::get('/deleted-invoices', [IndexDeletedInvoices::class, 'inGroup'])->name('deleted_invoices.index');
    Route::get('/refunds', IndexRefundsInGroup::class)->name('refunds.index');
});


Route::name('hr.')->prefix('hr')->group(function () {
    Route::get('/workplaces', [IndexWorkplaces::class, 'inGroup'])->name('workplaces.index');
    Route::get('/responsibilities', [IndexJobPositions::class, 'inGroup'])->name('responsibilities.index');
    Route::get('/employees', [IndexEmployees::class, 'inGroup'])->name('employees.index');
    Route::get('/clocking-machines', [IndexClockingMachines::class, 'inGroup'])->name('clocking-machines.index');
    Route::get('/timesheets', [IndexTimesheets::class, 'inGroup'])->name('timesheets.index');
});
