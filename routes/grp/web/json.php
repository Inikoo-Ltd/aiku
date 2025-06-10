<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jul 2024 13:50:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Accounting\Payment\Json\GetRefundPayments;
use App\Actions\Accounting\PaymentAccount\Json\GetShopPaymentAccounts;
use App\Actions\Catalogue\Collection\Json\GetCollections;
use App\Actions\Catalogue\Product\Json\GetOrderProducts;
use App\Actions\Catalogue\Product\Json\GetProducts;
use App\Actions\Catalogue\Product\Json\GetProductsInCollection;
use App\Actions\Catalogue\Product\Json\GetProductsInProductCategory;
use App\Actions\Catalogue\ProductCategory\Json\GetDepartments;
use App\Actions\Catalogue\ProductCategory\Json\GetFamilies;
use App\Actions\Catalogue\ProductCategory\Json\GetFamiliesInCollection;
use App\Actions\Catalogue\ProductCategory\Json\GetFamiliesInWorkshop;
use App\Actions\Catalogue\ProductCategory\Json\GetProductCategoryFamilies;
use App\Actions\Catalogue\ProductCategory\Json\GetSubDepartmentsInWorkshop;
use App\Actions\Comms\EmailTemplate\GetEmailTemplateCompiledLayout;
use App\Actions\Comms\EmailTemplate\GetOutboxEmailTemplates;
use App\Actions\Comms\EmailTemplate\GetSeededEmailTemplates;
use App\Actions\Comms\Mailshot\GetMailshotMergeTags;
use App\Actions\Comms\OutboxHasSubscribers\Json\GetOutboxUsers;
use App\Actions\Dispatching\Picking\Packer\Json\GetPackers;
use App\Actions\Dispatching\Picking\Picker\Json\GetPickers;
use App\Actions\Dispatching\Picking\Picker\Json\GetPickerUsers;
use App\Actions\Dispatching\Shipper\Json\GetShippers;
use App\Actions\Fulfilment\Pallet\Json\GetFulfilmentCustomerStoringPallets;
use App\Actions\Fulfilment\PalletDelivery\Json\GetFulfilmentPhysicalGoods;
use App\Actions\Fulfilment\PalletDelivery\Json\GetFulfilmentServices;
use App\Actions\Fulfilment\PalletDelivery\UI\IndexRecentPalletDeliveryUploads;
use App\Actions\Fulfilment\PalletReturn\Json\GetPalletsInReturnPalletWholePallets;
use App\Actions\Fulfilment\StoredItem\Json\GetPalletAuditStoredItems;
use App\Actions\Helpers\Brand\Json\GetBrands;
use App\Actions\Helpers\Tag\Json\GetTags;
use App\Actions\Ordering\Order\UI\IndexRecentOrderTransactionUploads;
use App\Actions\Procurement\OrgSupplierProducts\Json\GetOrgSupplierProducts;
use App\Actions\SysAdmin\User\GetSupervisorUsers;
use App\Actions\Web\WebBlockType\GetWebBlockTypes;
use App\Actions\Web\Webpage\Json\GetWebpagesForCollection;
use App\Actions\Web\Webpage\Json\GetWebpagesInCollection;
use App\Actions\Web\Webpage\Json\GetWebpagesWithCollection;
use App\Actions\Web\Website\GetWebsiteCloudflareUniqueVisitors;
use Illuminate\Support\Facades\Route;

Route::get('web-block-types', GetWebBlockTypes::class)->name('web-block-types.index');

Route::get('fulfilment/{fulfilment}/comms/outboxes/{outbox}/users', [GetOutboxUsers::class, 'inFulfilment'])->name('fulfilment.outbox.users.index');

Route::get('fulfilment/{fulfilment}/supervisors', [GetSupervisorUsers::class, 'inFulfilment'])->name('fulfilment.supervisors.index');

Route::get('fulfilment/{fulfilment}/delivery/{scope}/services', [GetFulfilmentServices::class, 'inPalletDelivery'])->name('fulfilment.delivery.services.index');
Route::get('fulfilment/{fulfilment}/return/{scope}/services', [GetFulfilmentServices::class, 'inPalletReturn'])->name('fulfilment.return.services.index');
Route::get('fulfilment/{fulfilment}/recurring-bill/{scope}/services', [GetFulfilmentServices::class, 'inRecurringBill'])->name('fulfilment.recurring-bill.services.index');
Route::get('fulfilment/{fulfilment}/invoice/{scope}/services', [GetFulfilmentServices::class, 'inInvoice'])->name('fulfilment.invoice.services.index');

Route::get('fulfilment/{fulfilment}/delivery/{scope}/physical-goods', [GetFulfilmentPhysicalGoods::class, 'inPalletDelivery'])->name('fulfilment.delivery.physical-goods.index');
Route::get('fulfilment/{fulfilment}/return/{scope}/physical-goods', [GetFulfilmentPhysicalGoods::class, 'inPalletReturn'])->name('fulfilment.return.physical-goods.index');
Route::get('fulfilment/{fulfilment}/recurring-bill/{scope}/physical-goods', [GetFulfilmentPhysicalGoods::class, 'inRecurringBill'])->name('fulfilment.recurring-bill.physical-goods.index');
Route::get('fulfilment/{fulfilment}/invoice/{scope}/physical-goods', [GetFulfilmentPhysicalGoods::class, 'inInvoice'])->name('fulfilment.invoice.physical-goods.index');

Route::get('refund/{invoice:id}/payments', GetRefundPayments::class)->name('refund.show.payments.index');

Route::get('pallet-return/{palletReturn}/pallets', GetPalletsInReturnPalletWholePallets::class)->name('pallet-return.pallets.index');

Route::get('fulfilment-customer/{fulfilmentCustomer}/storing-pallets', GetFulfilmentCustomerStoringPallets::class)->name('fulfilment-customer.storing-pallets.index');
Route::get('fulfilment-customer/{fulfilmentCustomer}/audit/{storedItemAudit}/stored-items', GetPalletAuditStoredItems::class)->name('fulfilment-customer.audit.stored-items.index');

Route::get('email/templates/seeded', GetSeededEmailTemplates::class)->name('email_templates.seeded');
Route::get('email/templates/outboxes/{outbox:id}', GetOutboxEmailTemplates::class)->name('email_templates.outbox');
Route::get('email/templates/{emailTemplate:id}/compiled_layout', GetEmailTemplateCompiledLayout::class)->name('email_templates.show.compiled_layout');
Route::get('/mailshot/{mailshot:id}/merge-tags', GetMailshotMergeTags::class)->name('mailshot.merge-tags');

Route::get('shop/{shop}/payment-accounts', GetShopPaymentAccounts::class)->name('shop.payment-accounts');

Route::get('shop/{shop}/collection/{collection}/webpages-for-collection', GetWebpagesForCollection::class)->name('shop.collection.webpages');

Route::get('shop/{shop}/catalogue/{productCategory}/families', GetProductCategoryFamilies::class)->name('shop.catalogue.departments.families');
Route::get('shop/{shop}/catalogue/collection/{scope}/products', GetProducts::class)->name('shop.catalogue.collection.products');
Route::get('shop/{shop}/catalogue/{scope}/departments', GetDepartments::class)->name('shop.catalogue.departments');
Route::get('shop/{shop}/catalogue/collection/{scope}/families', GetFamilies::class)->name('shop.catalogue.families');
Route::get('shop/{shop}/catalogue/{scope}/collections', GetCollections::class)->name('shop.catalogue.collections');
Route::get('shop/{shop}/catalogue/{scope}/collections/in-product-categories', [GetCollections::class, 'inProductCategory'])->name('shop.catalogue.collections.in-product-category');
Route::get('shop/{shop}/catalogue/{scope}/collections/in-collection', [GetCollections::class, 'inCollection'])->name('shop.catalogue.collections.in-collection');

Route::get('organisation/{organisation}/employees/packers', GetPackers::class)->name('employees.packers');
Route::get('organisation/{organisation}/employees/pickers', GetPickers::class)->name('employees.pickers');
Route::get('organisation/{organisation}/employees/picker-users', GetPickerUsers::class)->name('employees.picker_users');

Route::get('org-agent/{orgAgent}/purchase-order/{purchaseOrder}/org-supplier-products', [GetOrgSupplierProducts::class, 'inOrgAgent'])->name('org-agent.org-supplier-products');
Route::get('org-supplier/{orgSupplier}/purchase-order/{purchaseOrder}/org-supplier-products', [GetOrgSupplierProducts::class, 'inOrgSupplier'])->name('org-supplier.org-supplier-products');

Route::get('website/{website}/unique-visitors', GetWebsiteCloudflareUniqueVisitors::class)->name('website.unique-visitors');

Route::get('delivery-recent-uploads/{palletDelivery:id}', IndexRecentPalletDeliveryUploads::class)->name('pallet_delivery.recent_uploads');
Route::get('order-transaction-recent-uploads/{order:id}', IndexRecentOrderTransactionUploads::class)->name('order.transaction.recent_uploads');

Route::get('order/{order:id}/products', GetOrderProducts::class)->name('order.products');
Route::get('organisation/{organisation}/shippers', GetShippers::class)->name('shippers.index');

Route::get('trade-units/{tradeUnit}/tags', [GetTags::class, 'inTradeUnit'])->name('trade_units.tags.index');
Route::get('brands', GetBrands::class)->name('brands.index');

Route::get('workshop/department/{department}/sub-departments', GetSubDepartmentsInWorkshop::class)->name('workshop.sub_departments.index');
Route::get('workshop/sub-department/{subDepartment}/families', GetFamiliesInWorkshop::class)->name('workshop.families.index');

Route::get('workshop/product-category/{productCategory}/products', GetProductsInProductCategory::class)->name('product_category.products.index');
Route::get('workshop/collection/{collection}/products', GetProductsInCollection::class)->name('collection.products.index');
Route::get('workshop/collection/{collection}/families', GetFamiliesInCollection::class)->name('collection.families.index');
