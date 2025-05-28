<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Feb 2024 23:54:37 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Accounting\TopUpPaymentApiPoint\StoreTopUpPaymentApiPoint;
use App\Actions\Dropshipping\Aiku\StoreRetinaManualPlatform;
use App\Actions\Dropshipping\CustomerSalesChannel\ToggleCustomerSalesChannel;
use App\Actions\Dropshipping\Shopify\Product\GetApiProductsFromShopify;
use App\Actions\Dropshipping\Shopify\Product\SyncroniseDropshippingPortfoliosToShopify;
use App\Actions\Dropshipping\Shopify\Product\SyncroniseDropshippingPortfolioToShopify;
use App\Actions\Dropshipping\Tiktok\Product\GetProductsFromTiktokApi;
use App\Actions\Dropshipping\Tiktok\Product\StoreProductToTiktok;
use App\Actions\Dropshipping\Tiktok\User\DeleteTiktokUser;
use App\Actions\Dropshipping\WooCommerce\Product\StoreProductWooCommerce;
use App\Actions\Retina\Accounting\MitSavedCard\DeleteMitSavedCard;
use App\Actions\Retina\Accounting\Payment\PlaceOrderPayByBank;
use App\Actions\Retina\Accounting\TopUp\StoreRetinaTopUp;
use App\Actions\Retina\CRM\DeleteRetinaCustomerDeliveryAddress;
use App\Actions\Retina\CRM\StoreRetinaCustomerClient;
use App\Actions\Retina\CRM\UpdateRetinaCustomerAddress;
use App\Actions\Retina\CRM\UpdateRetinaCustomerDeliveryAddress;
use App\Actions\Retina\CRM\UpdateRetinaCustomerSettings;
use App\Actions\Retina\Dropshipping\Client\ImportRetinaClients;
use App\Actions\Retina\Dropshipping\Client\UpdateRetinaCustomerClient;
use App\Actions\Retina\Dropshipping\CustomerSalesChannel\UnlinkRetinaCustomerSalesChannel;
use App\Actions\Retina\Dropshipping\Orders\ImportRetinaOrderTransaction;
use App\Actions\Retina\Dropshipping\Orders\PayRetinaOrderWithBalance;
use App\Actions\Retina\Dropshipping\Orders\StoreRetinaOrder;
use App\Actions\Retina\Dropshipping\Orders\StoreRetinaPlatformOrder;
use App\Actions\Retina\Dropshipping\Orders\SubmitRetinaOrder;
use App\Actions\Retina\Dropshipping\Orders\Transaction\DeleteRetinaTransaction;
use App\Actions\Retina\Dropshipping\Orders\Transaction\StoreRetinaTransaction;
use App\Actions\Retina\Dropshipping\Orders\Transaction\UpdateRetinaTransaction;
use App\Actions\Retina\Dropshipping\Orders\UpdateRetinaOrder;
use App\Actions\Retina\Dropshipping\Portfolio\DeleteRetinaPortfolio;
use App\Actions\Retina\Dropshipping\Portfolio\UpdateRetinaPortfolio;
use App\Actions\Retina\Dropshipping\Product\StoreRetinaProductManual;
use App\Actions\Retina\Ecom\Basket\RetinaEcomDeleteTransaction;
use App\Actions\Retina\Ecom\Basket\RetinaEcomUpdateTransaction;
use App\Actions\Retina\Fulfilment\Dropshipping\Channel\Manual\StoreRetinaFulfilmentManualPlatform;
use App\Actions\Retina\Fulfilment\Dropshipping\Client\StoreRetinaFulfilmentCustomerClient;
use App\Actions\Retina\Fulfilment\Dropshipping\Client\StoreRetinaFulfilmentCustomerClientWithOrder;
use App\Actions\Retina\Fulfilment\Dropshipping\Portfolio\SyncAllRetinaStoredItemsToPortfolios;
use App\Actions\Retina\Fulfilment\Dropshipping\Portfolio\SyncRetinaStoredItemsFromApiProductsShopify;
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
use App\Actions\Retina\Fulfilment\PalletReturn\AttachRetinaPalletToReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\CancelRetinaPalletReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\DeleteRetinaPalletReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\DeleteRetinaPalletReturnAddress;
use App\Actions\Retina\Fulfilment\PalletReturn\DetachRetinaPalletFromReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\ImportRetinaPalletReturnItem;
use App\Actions\Retina\Fulfilment\PalletReturn\StoreRetinaPalletReturn;
use App\Actions\Retina\Fulfilment\PalletReturn\StoreRetinaPlatformPalletReturn;
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

Route::post('place-order-pay-by-bank', PlaceOrderPayByBank::class)->name('place-order-pay-by-bank');
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

Route::name('order.')->prefix('order/{order:id}')->group(function () {
    Route::patch('/', UpdateRetinaOrder::class)->name('update');
    Route::patch('submit', SubmitRetinaOrder::class)->name('submit');
    Route::patch('pay-with-balance', PayRetinaOrderWithBalance::class)->name('pay_with_balance');

    Route::name('transaction.')->prefix('transaction/{transaction:id}')->group(function () {
        Route::delete('', DeleteRetinaTransaction::class)->name('delete')->withoutScopedBindings();
        Route::patch('', UpdateRetinaTransaction::class)->name('update')->withoutScopedBindings();
    });

    Route::name('transaction.')->prefix('transaction')->group(function () {
        Route::post('upload', ImportRetinaOrderTransaction::class)->name('upload');
        Route::post('/', StoreRetinaTransaction::class)->name('store')->withoutScopedBindings();
    });
});

Route::name('fulfilment_customer.')->prefix('fulfilment-customer/{fulfilmentCustomer:id}')->group(function () {

    Route::post('delivery-address/store', AddRetinaDeliveryAddressToFulfilmentCustomer::class)->name('delivery_address.store');
});

Route::name('customer-client.')->prefix('customer-client')->group(function () {
    Route::patch('{customerClient:id}/update', UpdateRetinaCustomerClient::class)->name('update')->withoutScopedBindings();
    Route::post('{customerClient:id}/order', StoreRetinaOrder::class)->name('order.store')->withoutScopedBindings();
    Route::post('{customerClient:id}/dashboard/order', StoreRetinaOrder::class)->name('dashboard-order.store')->withoutScopedBindings();
    Route::post('{customerClient:id}/fulfilment/order', StoreRetinaPlatformPalletReturn::class)->name('fulfilment_order.store')->withoutScopedBindings();
});

Route::post('fulfilment-customer-sales-channel-manual', StoreRetinaFulfilmentManualPlatform::class)->name('fulfilment.customer_sales_channel.manual.store')->withoutScopedBindings();
Route::post('customer-sales-channel-manual', StoreRetinaManualPlatform::class)->name('customer_sales_channel.manual.store')->withoutScopedBindings();


Route::name('customer_sales_channel.')->prefix('customer-sales-channel/{customerSalesChannel:id}')->group(function () {
    Route::post('', StoreRetinaCustomerClient::class)->name('customer-client.store');
    Route::post('fulfilment', StoreRetinaFulfilmentCustomerClient::class)->name('fulfilment.customer-client.store');
    Route::post('fulfilment-client-with-order', StoreRetinaFulfilmentCustomerClientWithOrder::class)->name('fulfilment.customer-client-with-order.store');
    Route::post('sync-all-stored-items', SyncAllRetinaStoredItemsToPortfolios::class)->name('sync_all_stored_items');
    Route::post('shopify-sync-all-stored-items', SyncRetinaStoredItemsFromApiProductsShopify::class)->name('shopify_sync_all_stored_items');
    Route::post('upload', ImportRetinaClients::class)->name('clients.upload');
    Route::post('products', StoreRetinaProductManual::class)->name('customer.product.store')->withoutScopedBindings();
    Route::post('wc-products', StoreProductWooCommerce::class)->name('woo.product.store')->withoutScopedBindings();

    Route::delete('unlink', UnlinkRetinaCustomerSalesChannel::class)->name('unlink');
    Route::patch('toggle', ToggleCustomerSalesChannel::class)->name('toggle');

    Route::delete('products/{portfolio:id}', DeleteRetinaPortfolio::class)->name('product.delete')->withoutScopedBindings();
});

Route::name('dropshipping.')->prefix('dropshipping')->group(function () {
    Route::post('shopify-user/{shopifyUser:id}/products', StoreRetinaProductShopify::class)->name('shopify_user.product.store')->withoutScopedBindings();
    Route::delete('shopify-user/{shopifyUser:id}/products/{product}', HandleRetinaApiDeleteProductFromShopify::class)->name('shopify_user.product.delete')->withoutScopedBindings();
    Route::get('shopify-user/{shopifyUser:id}/sync-products', GetApiProductsFromShopify::class)->name('shopify_user.product.sync')->withoutScopedBindings();
    Route::post('{shopifyUser:id}/shopify-batch-upload', SyncroniseDropshippingPortfoliosToShopify::class)->name('shopify.batch_upload')->withoutScopedBindings();
    Route::post('{shopifyUser:id}/shopify-single-upload/{portfolio:id}', SyncroniseDropshippingPortfolioToShopify::class)->name('shopify.single_upload')->withoutScopedBindings();

    Route::delete('tiktok/{tiktokUser:id}', DeleteTiktokUser::class)->name('tiktok.delete')->withoutScopedBindings();
    Route::post('tiktok/{tiktokUser:id}/products', StoreProductToTiktok::class)->name('tiktok.product.store')->withoutScopedBindings();
    Route::get('tiktok/{tiktokUser:id}/sync-products', GetProductsFromTiktokApi::class)->name('tiktok.product.sync')->withoutScopedBindings();


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

Route::delete('portfolio/{portfolio:id}', DeleteRetinaPortfolio::class)->name('portfolio.delete');
Route::patch('portfolio/{portfolio:id}', UpdateRetinaPortfolio::class)->name('portfolio.update');

Route::name('mit_saved_card.')->prefix('mit-saved-card')->group(function () {
    Route::delete('{mitSavedCard:id}/delete', DeleteMitSavedCard::class)->name('delete');
});
