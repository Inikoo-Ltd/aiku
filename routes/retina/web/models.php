<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Feb 2024 23:54:37 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\CRM\Customer\DeleteCustomerDeliveryAddress;
use App\Actions\CRM\Customer\UpdateCustomerDeliveryAddress;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Actions\CRM\WebUser\UpdateWebUser;
use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\Shopify\Product\HandleApiDeleteProductFromShopify;
use App\Actions\Dropshipping\Shopify\Product\StoreProductShopify;
use App\Actions\Fulfilment\FulfilmentCustomer\AddDeliveryAddressToFulfilmentCustomer;
use App\Actions\Fulfilment\FulfilmentTransaction\StoreFulfilmentTransaction;
use App\Actions\Fulfilment\Pallet\DeletePallet;
use App\Actions\Fulfilment\Pallet\ImportPallet;
use App\Actions\Fulfilment\Pallet\StoreMultiplePalletsFromDelivery;
use App\Actions\Fulfilment\Pallet\StorePalletFromDelivery;
use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\Fulfilment\PalletDelivery\Pdf\PdfPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\StorePalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\SubmitPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\UpdatePalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\UpdatePalletDeliveryTimeline;
use App\Actions\Fulfilment\StoredItem\StoreStoredItem;
use App\Actions\Fulfilment\StoredItem\SyncStoredItemToPallet;
use App\Actions\Retina\CRM\RetinaUpdateCustomerSettings;
use App\Actions\Retina\Fulfilment\RetinaDeleteFulfilmentTransaction;
use App\Actions\Retina\Fulfilment\RetinaUpdateFulfilmentTransaction;
use App\Actions\UI\Retina\Profile\RetinaUpdateProfile;
use App\Actions\UI\Retina\SysAdmin\UpdateRetinaFulfilmentCustomer;
use Illuminate\Support\Facades\Route;

Route::patch('/profile', RetinaUpdateProfile::class)->name('profile.update');
Route::patch('/settings', RetinaUpdateCustomerSettings::class)->name('settings.update');

Route::name('fulfilment-transaction.')->prefix('fulfilment_transaction/{fulfilmentTransaction:id}')->group(function () {
    Route::patch('', RetinaUpdateFulfilmentTransaction::class)->name('update');
    Route::delete('', RetinaDeleteFulfilmentTransaction::class)->name('delete');
});

Route::post('pallet-return', RetinaStorePalletReturn::class)->name('pallet-return.store');
Route::post('pallet-return/stored-items', [RetinaStorePalletReturn::class, 'fromRetinaWithStoredItems'])->name('pallet-return-stored-items.store');
Route::name('pallet-return.')->prefix('pallet-return/{palletReturn:id}')->group(function () {
    Route::post('stored-item-upload', RetinaImportPalletReturnItem::class)->name('stored-item.upload');
    Route::post('stored-item', RetinaStoreStoredItemsToReturn::class)->name('stored_item.store');
    Route::post('pallet', RetinaAttachPalletsToReturn::class)->name('pallet.store');
    Route::patch('update', RetinaUpdatePalletReturn::class)->name('update');
    Route::post('submit', RetinaSubmitPalletReturn::class)->name('submit');
    Route::post('cancel', RetinaCancelPalletReturn::class)->name('cancel');
    Route::delete('pallet/{pallet:id}', RetinaDetachPalletFromReturn::class)->name('pallet.delete')->withoutScopedBindings();
    Route::post('transaction', [RetinaStoreFulfilmentTransaction::class, 'fromRetinaInPalletReturn'])->name('transaction.store');
});



Route::post('pallet-delivery', [StorePalletDelivery::class, 'fromRetina'])->name('pallet-delivery.store');
Route::name('pallet-delivery.')->prefix('pallet-delivery/{palletDelivery:id}')->group(function () {
    Route::post('pallet-upload', [ImportPallet::class,'fromRetina'])->name('pallet.upload');
    Route::post('pallet', [StorePalletFromDelivery::class, 'fromRetina'])->name('pallet.store');
    Route::post('multiple-pallet', [StoreMultiplePalletsFromDelivery::class, 'fromRetina'])->name('multiple-pallets.store');
    Route::patch('update', [UpdatePalletDelivery::class, 'fromRetina'])->name('update');
    Route::patch('update-timeline', [UpdatePalletDeliveryTimeline::class, 'fromRetina'])->name('timeline.update');
    Route::post('transaction', [StoreFulfilmentTransaction::class,'fromRetinaInPalletDelivery'])->name('transaction.store');
    Route::post('submit', SubmitPalletDelivery::class)->name('submit');
    Route::get('pdf', PdfPalletDelivery::class)->name('pdf');
});

Route::name('pallet.')->prefix('pallet/{pallet:id}')->group(function () {
    Route::post('stored-items', SyncStoredItemToPallet::class)->name('stored-items.update');
    Route::delete('', [DeletePallet::class, 'fromRetina'])->name('delete');
    Route::patch('', [UpdatePallet::class, 'fromRetina'])->name('update');
});

Route::post('stored-items', [StoreStoredItem::class, 'fromRetina'])->name('stored-items.store');

Route::name('customer.')->prefix('customer/{customer:id}')->group(function () {
    Route::patch('delivery-address/update', [UpdateCustomerDeliveryAddress::class, 'fromRetina'])->name('delivery-address.update');
    Route::delete('delivery-address/{address:id}/delete', [DeleteCustomerDeliveryAddress::class, 'fromRetina'])->name('delivery-address.delete');
});

Route::name('fulfilment-customer.')->prefix('fulfilment-customer/{fulfilmentCustomer:id}')->group(function () {
    Route::patch('update', UpdateRetinaFulfilmentCustomer::class)->name('update');
    Route::post('delivery-address/store', [AddDeliveryAddressToFulfilmentCustomer::class, 'fromRetina'])->name('delivery-address.store');
});

Route::post('customer-client', [StoreCustomerClient::class, 'fromRetina'])->name('customer-client.store');

Route::name('dropshipping.')->prefix('dropshipping')->group(function () {
    Route::post('shopify-user/{shopifyUser:id}/products', StoreProductShopify::class)->name('shopify_user.product.store')->withoutScopedBindings();
    Route::delete('shopify-user/{shopifyUser:id}/products/{product}', HandleApiDeleteProductFromShopify::class)->name('shopify_user.product.delete')->withoutScopedBindings();
});

Route::name('web-users.')->prefix('web-users')->group(function () {
    Route::post('', [StoreWebUser::class, 'inRetina'])->name('store');
    Route::patch('{webUser:id}/update', [UpdateWebUser::class, 'inRetina'])->name('update');
});
