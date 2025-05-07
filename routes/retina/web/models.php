<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Feb 2024 23:54:37 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Accounting\TopUpPaymentApiPoint\StoreTopUpPaymentApiPoint;
use App\Actions\Dropshipping\Aiku\StoreRetinaManualPlatform;
use App\Actions\Dropshipping\Shopify\Product\GetApiProductsFromShopify;
use App\Actions\Dropshipping\Tiktok\Product\GetProductsFromTiktokApi;
use App\Actions\Dropshipping\Tiktok\Product\StoreProductToTiktok;
use App\Actions\Dropshipping\Tiktok\User\DeleteTiktokUser;
use App\Actions\Retina\Accounting\TopUp\StoreRetinaTopUp;
use App\Actions\Retina\CRM\DeleteRetinaCustomerDeliveryAddress;
use App\Actions\Retina\CRM\StoreRetinaCustomerClient;
use App\Actions\Retina\CRM\UpdateRetinaCustomerAddress;
use App\Actions\Retina\CRM\UpdateRetinaCustomerDeliveryAddress;
use App\Actions\Retina\CRM\UpdateRetinaCustomerSettings;
use App\Actions\Retina\Dropshipping\Orders\StoreRetinaPlatformOrder;
use App\Actions\Retina\Dropshipping\Orders\SubmitRetinaOrder;
use App\Actions\Retina\Dropshipping\Product\StoreRetinaProductManual;
use App\Actions\Retina\Ecom\Basket\RetinaEcomDeleteTransaction;
use App\Actions\Retina\Ecom\Basket\RetinaEcomUpdateTransaction;
use App\Actions\Retina\Fulfilment\FulfilmentTransaction\DeleteRetinaFulfilmentTransaction;
use App\Actions\Retina\Fulfilment\FulfilmentTransaction\StoreRetinaFulfilmentTransaction;
use App\Actions\Retina\Fulfilment\FulfilmentTransaction\UpdateRetinaFulfilmentTransaction;
use App\Actions\Retina\Fulfilment\Pallet\DeleteRetinaPallet;
use App\Actions\Retina\Fulfilment\Pallet\ImportRetinaPallet;
use App\Actions\Retina\Fulfilment\Pallet\ImportRetinaPalletsInPalletDeliveryWithStoredItems;
use App\Actions\Retina\Fulfilment\Pallet\StoreRetinaMultiplePalletsFromDelivery;
use App\Actions\Retina\Fulfilment\Pallet\StoreRetinaPalletFromDelivery;
use App\Actions\Retina\Fulfilment\Pallet\UpdateRetinaPallet;
use App\Actions\Retina\Fulfilment\PalletDelivery\DeleteRetinaPalletDelivery;
use App\Actions\Retina\Fulfilment\PalletDelivery\Pdf\PdfRetinaPalletDelivery;
use App\Actions\Retina\Fulfilment\PalletDelivery\StoreRetinaPalletDelivery;
use App\Actions\Retina\Fulfilment\PalletDelivery\SubmitRetinaPalletDelivery;
use App\Actions\Retina\Fulfilment\PalletDelivery\UpdateRetinaPalletDelivery;
use App\Actions\Retina\Fulfilment\PalletReturn\AddRetinaAddressToPalletReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\AttachRetinaPalletsToReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\AttachRetinaPalletToReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\CancelRetinaPalletReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\DeleteRetinaPalletReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\DeleteRetinaPalletReturnAddress;
use App\Actions\Retina\Fulfilment\PalletReturn\DetachRetinaPalletFromReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\ImportRetinaPalletReturnItem;
use App\Actions\Retina\Fulfilment\PalletReturn\StoreRetinaPalletReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\StoreRetinaStoredItemsToReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\SubmitRetinaPalletReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\SwitchRetinaPalletReturnDeliveryAddress;
use App\Actions\Retina\Fulfilment\PalletReturn\UpdateRetinaPalletReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\UpdateRetinaPalletReturnDeliveryAddress;
use App\Actions\Retina\Fulfilment\StoredItem\AttachRetinaStoredItemToReturn;
use App\Actions\Retina\Fulfilment\StoredItem\StoreRetinaStoredItem;
use App\Actions\Retina\Fulfilment\StoredItem\SyncRetinaStoredItemToPallet;
use App\Actions\Retina\Fulfilment\StoredItem\UpdateRetinaStoredItem;
use App\Actions\Retina\Media\AttachRetinaAttachmentToModel;
use App\Actions\Retina\Media\DetachRetinaAttachmentFromModel;
use App\Actions\Retina\Media\DownloadRetinaAttachment;
use App\Actions\Retina\Shopify\HandleRetinaApiDeleteProductFromShopify;
use App\Actions\Retina\Shopify\StoreRetinaProductShopify;
use App\Actions\Retina\SysAdmin\AddRetinaDeliveryAddressToCustomer;
use App\Actions\Retina\SysAdmin\AddRetinaDeliveryAddressToFulfilmentCustomer;
use App\Actions\Retina\SysAdmin\DeleteRetinaWebUser;
use App\Actions\Retina\SysAdmin\StoreRetinaWebUser;
use App\Actions\Retina\SysAdmin\UpdateRetinaCustomer;
use App\Actions\Retina\SysAdmin\UpdateRetinaWebUser;
use App\Actions\Retina\UI\Profile\UpdateRetinaProfile;
use Illuminate\Support\Facades\Route;


Route::post('top-up-payment-api-point', StoreTopUpPaymentApiPoint::class)->name('top_up_payment_api_point.store');

Route::patch('/profile', UpdateRetinaProfile::class)->name('profile.update');
Route::patch('/settings', UpdateRetinaCustomerSettings::class)->name('settings.update');

Route::name('fulfilment-transaction.')->prefix('fulfilment_transaction/{fulfilmentTransaction:id}')->group(function () {
    Route::patch('', UpdateRetinaFulfilmentTransaction::class)->name('update');
    Route::delete('', DeleteRetinaFulfilmentTransaction::class)->name('delete');
});

Route::post('pallet-return', StoreRetinaPalletReturn::class)->name('pallet-return.store');
Route::post('pallet-return/stored-items', [StoreRetinaPalletReturn::class, 'withStoredItems'])->name('pallet-return-stored-items.store');
Route::name('pallet-return.')->prefix('pallet-return/{palletReturn:id}')->group(function () {
    Route::post('attachment/attach', [AttachRetinaAttachmentToModel::class, 'inPalletReturn'])->name('attachment.attach');
    Route::delete('attachment/{attachment:id}/detach', [DetachRetinaAttachmentFromModel::class, 'inPalletReturn'])->name('attachment.detach')->withoutScopedBindings();

    Route::post('address', AddRetinaAddressToPalletReturn::class)->name('address.store');
    Route::patch('address/switch', SwitchRetinaPalletReturnDeliveryAddress::class)->name('address.switch');
    Route::patch('address/update', UpdateRetinaPalletReturnDeliveryAddress::class)->name('address.update');
    Route::delete('address/delete', DeleteRetinaPalletReturnAddress::class)->name('address.delete');

    Route::post('pallet-return-item-upload', ImportRetinaPalletReturnItem::class)->name('pallet-return-item.upload');
    Route::post('stored-item', StoreRetinaStoredItemsToReturn::class)->name('stored_item.store');

    Route::post('pallet', AttachRetinaPalletsToReturn::class)->name('pallet.store'); //No longer used (free to delete) but idk
    Route::patch('update', UpdateRetinaPalletReturn::class)->name('update');
    Route::post('submit', SubmitRetinaPalletReturn::class)->name('submit');
    Route::post('cancel', CancelRetinaPalletReturn::class)->name('cancel');
    Route::post('pallet-stored-item/{palletStoredItem:id}/attach', AttachRetinaStoredItemToReturn::class)->name('stored_item.attach')->withoutScopedBindings();
    Route::post('pallet/{pallet:id}/attach', AttachRetinaPalletToReturn::class)->name('pallet.attach')->withoutScopedBindings();
    Route::delete('pallet/{pallet:id}/detach', DetachRetinaPalletFromReturn::class)->name('pallet.delete')->withoutScopedBindings();
    Route::post('transaction', [StoreRetinaFulfilmentTransaction::class, 'fromRetinaInPalletReturn'])->name('transaction.store');
    Route::patch('/', DeleteRetinaPalletReturn::class)->name('delete');
});



Route::post('pallet-delivery', StoreRetinaPalletDelivery::class)->name('pallet-delivery.store');
Route::name('pallet-delivery.')->prefix('pallet-delivery/{palletDelivery:id}')->group(function () {
    Route::post('attachment/attach', [AttachRetinaAttachmentToModel::class, 'inPalletDelivery'])->name('attachment.attach');
    Route::delete('attachment/{attachment:id}/detach', [DetachRetinaAttachmentFromModel::class, 'inPalletDelivery'])->name('attachment.detach')->withoutScopedBindings();

    Route::post('pallet-upload', ImportRetinaPallet::class)->name('pallet.upload');
    Route::post('pallet-upload-with-stored-items', ImportRetinaPalletsInPalletDeliveryWithStoredItems::class)->name('pallet.upload.with-stored-items');
    Route::post('pallet', StoreRetinaPalletFromDelivery::class)->name('pallet.store');
    Route::post('multiple-pallet', StoreRetinaMultiplePalletsFromDelivery::class)->name('multiple-pallets.store');
    Route::patch('update', UpdateRetinaPalletDelivery::class)->name('update');
    Route::post('transaction', [StoreRetinaFulfilmentTransaction::class,'fromRetinaInPalletDelivery'])->name('transaction.store');
    Route::post('submit', SubmitRetinaPalletDelivery::class)->name('submit');
    Route::get('pdf', PdfRetinaPalletDelivery::class)->name('pdf');
    Route::patch('/', DeleteRetinaPalletDelivery::class)->name('delete');
});

Route::name('pallet.')->prefix('pallet/{pallet:id}')->group(function () {
    Route::post('stored-items', SyncRetinaStoredItemToPallet::class)->name('stored-items.update');
    Route::delete('', DeleteRetinaPallet::class)->name('delete');
    Route::patch('', UpdateRetinaPallet::class)->name('update');
});

Route::post('stored-items', StoreRetinaStoredItem::class)->name('stored-items.store');
Route::patch('stored-items/{storedItem:id}', UpdateRetinaStoredItem::class)->name('stored-items.update');

Route::name('customer.')->prefix('customer/{customer:id}')->group(function () {

    Route::patch('update', UpdateRetinaCustomer::class)->name('update');

    Route::patch('address/update', UpdateRetinaCustomerAddress::class)->name('address.update');
    Route::post('delivery-address/store', AddRetinaDeliveryAddressToCustomer::class)->name('delivery-address.store');
    Route::patch('delivery-address/update', UpdateRetinaCustomerDeliveryAddress::class)->name('delivery-address.update');
    Route::delete('delivery-address/{address:id}/delete', DeleteRetinaCustomerDeliveryAddress::class)->name('delivery-address.delete');

    Route::name('order.')->prefix('order')->group(function () {
        Route::post('{platform:id}', StoreRetinaPlatformOrder::class)->name('platform.store');
    });
});

Route::name('order.')->prefix('order')->group(function () {
    Route::patch('{order:id}/submit', SubmitRetinaOrder::class)->name('submit');
});

Route::name('fulfilment_customer.')->prefix('fulfilment-customer/{fulfilmentCustomer:id}')->group(function () {

    Route::post('delivery-address/store', AddRetinaDeliveryAddressToFulfilmentCustomer::class)->name('delivery_address.store');
});

Route::post('customer-client', StoreRetinaCustomerClient::class)->name('customer-client.store');
Route::post('platform-customer-client/{platform:id}', [StoreRetinaCustomerClient::class, 'inPlatform'])->name('platform-customer-client.store');

Route::name('dropshipping.')->prefix('dropshipping')->group(function () {
    Route::post('customer/{customer:id}/products', StoreRetinaProductManual::class)->name('customer.product.store')->withoutScopedBindings();
    Route::post('shopify-user/{shopifyUser:id}/products', StoreRetinaProductShopify::class)->name('shopify_user.product.store')->withoutScopedBindings();
    Route::delete('shopify-user/{shopifyUser:id}/products/{product}', HandleRetinaApiDeleteProductFromShopify::class)->name('shopify_user.product.delete')->withoutScopedBindings();
    Route::get('shopify-user/{shopifyUser:id}/sync-products', GetApiProductsFromShopify::class)->name('shopify_user.product.sync')->withoutScopedBindings();

    Route::delete('tiktok/{tiktokUser:id}', DeleteTiktokUser::class)->name('tiktok.delete')->withoutScopedBindings();
    Route::post('tiktok/{tiktokUser:id}/products', StoreProductToTiktok::class)->name('tiktok.product.store')->withoutScopedBindings();
    Route::get('tiktok/{tiktokUser:id}/sync-products', GetProductsFromTiktokApi::class)->name('tiktok.product.sync')->withoutScopedBindings();

    Route::post('aiku', StoreRetinaManualPlatform::class)->name('aiku.store')->withoutScopedBindings();
});

Route::name('web-users.')->prefix('web-users')->group(function () {
    Route::post('', StoreRetinaWebUser::class)->name('store');
    Route::patch('{webUser:id}/update', UpdateRetinaWebUser::class)->name('update');
    Route::delete('{webUser:id}/delete', DeleteRetinaWebUser::class)->name('delete');
});

Route::get('attachment/{media:ulid}', DownloadRetinaAttachment::class)->name('attachment.download');


Route::name('transaction.')->prefix('transaction')->group(function () {
    Route::delete('{transaction:id}', RetinaEcomDeleteTransaction::class)->name('delete')->withoutScopedBindings();
    Route::patch('{transaction:id}', RetinaEcomUpdateTransaction::class)->name('update')->withoutScopedBindings();
});

Route::name('top-up.')->prefix('top-up')->group(function () {
    Route::post('{paymentAccount:id}', StoreRetinaTopUp::class)->name('store')->withoutScopedBindings();
});
