<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Feb 2024 23:54:37 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Accounting\TopUpPaymentApiPoint\StoreTopUpPaymentApiPoint;
use App\Actions\Dropshipping\Aiku\CloneMultipleManualPortfolios;
use App\Actions\Dropshipping\Aiku\StoreRetinaManualPlatform;
use App\Actions\Dropshipping\Amazon\Orders\GetRetinaOrdersFromAmazon;
use App\Actions\Dropshipping\Amazon\Product\SyncronisePortfoliosToAmazon;
use App\Actions\Dropshipping\Amazon\Product\SyncronisePortfolioToAmazon;
use App\Actions\Dropshipping\CustomerSalesChannel\RetinaDeleteCustomerSalesChannel;
use App\Actions\Dropshipping\Ebay\Orders\FetchEbayUserOrders;
use App\Actions\Dropshipping\Ebay\Product\SyncronisePortfoliosToEbay;
use App\Actions\Dropshipping\Ebay\Product\SyncronisePortfolioToEbay;
use App\Actions\Dropshipping\Magento\Orders\GetRetinaOrdersFromMagento;
use App\Actions\Dropshipping\Magento\Product\SyncronisePortfoliosToMagento;
use App\Actions\Dropshipping\Magento\Product\SyncronisePortfolioToMagento;
use App\Actions\Dropshipping\Shopify\ResetShopifyChannel;
use App\Actions\Dropshipping\Tiktok\Product\GetProductsFromTiktokApi;
use App\Actions\Dropshipping\Tiktok\Product\StoreProductToTiktok;
use App\Actions\Dropshipping\Tiktok\User\DeleteTiktokUser;
use App\Actions\Dropshipping\WooCommerce\Orders\FetchWooUserOrders;
use App\Actions\Dropshipping\WooCommerce\Product\CreateNewBulkPortfolioToWooCommerce;
use App\Actions\Dropshipping\WooCommerce\Product\StoreNewProductToCurrentWooCommerce;
use App\Actions\Iris\UpdateIrisLocale;
use App\Actions\Retina\Accounting\MitSavedCard\DeleteMitSavedCard;
use App\Actions\Retina\Accounting\MitSavedCard\SetAsDefaultRetinaMitSavedCard;
use App\Actions\Retina\Accounting\Payment\PlaceOrderPayByBank;
use App\Actions\Retina\Accounting\TopUp\StoreRetinaTopUp;
use App\Actions\Retina\CRM\DeleteRetinaCustomerDeliveryAddress;
use App\Actions\Retina\CRM\DeleteRetinaFavourite;
use App\Actions\Retina\CRM\StoreRetinaCustomerClient;
use App\Actions\Retina\CRM\StoreRetinaFavourite;
use App\Actions\Retina\CRM\UpdateRetinaCustomerAddress;
use App\Actions\Retina\CRM\UpdateRetinaCustomerDeliveryAddress;
use App\Actions\Retina\CRM\UpdateRetinaCustomerSettings;
use App\Actions\Retina\Dropshipping\ApiToken\DeleteCustomerAccessToken;
use App\Actions\Retina\Dropshipping\ApiToken\StoreCustomerToken;
use App\Actions\Retina\Dropshipping\Basket\DeleteRetinaBasket;
use App\Actions\Retina\Dropshipping\Client\ImportRetinaClients;
use App\Actions\Retina\Dropshipping\Client\UpdateRetinaCustomerClient;
use App\Actions\Retina\Dropshipping\CustomerSalesChannel\UpdateRetinaCustomerSalesChannel;
use App\Actions\Retina\Dropshipping\Orders\DeleteOrderAddressCollection;
use App\Actions\Retina\Dropshipping\Orders\ImportRetinaOrderTransaction;
use App\Actions\Retina\Dropshipping\Orders\PayRetinaOrderWithBalance;
use App\Actions\Retina\Dropshipping\Orders\StoreOrderAddressCollection;
use App\Actions\Retina\Dropshipping\Orders\StoreRetinaOrder;
use App\Actions\Retina\Dropshipping\Orders\StoreRetinaPlatformOrder;
use App\Actions\Retina\Dropshipping\Orders\SubmitRetinaOrder;
use App\Actions\Retina\Dropshipping\Orders\Transaction\DeleteRetinaTransaction;
use App\Actions\Retina\Dropshipping\Orders\Transaction\StoreRetinaEcomBasketTransaction;
use App\Actions\Retina\Dropshipping\Orders\UpdateRetinaOrder;
use App\Actions\Retina\Dropshipping\Orders\UpdateRetinaOrderExtraPacking;
use App\Actions\Retina\Dropshipping\Orders\UpdateRetinaOrderPremiumDispatch;
use App\Actions\Retina\Dropshipping\Portfolio\BatchDeleteRetinaPortfolio;
use App\Actions\Retina\Dropshipping\Portfolio\DeleteRetinaPortfolio;
use App\Actions\Retina\Dropshipping\Portfolio\StoreRetinaPortfoliosFromProductCategory;
use App\Actions\Retina\Dropshipping\Portfolio\StoreRetinaPortfoliosFromProductCategoryToAllChannels;
use App\Actions\Retina\Dropshipping\Portfolio\StoreRetinaPortfolioToAllChannels;
use App\Actions\Retina\Dropshipping\Portfolio\StoreRetinaPortfolioToMultiChannels;
use App\Actions\Retina\Dropshipping\Portfolio\UpdateRetinaPortfolio;
use App\Actions\Retina\Dropshipping\Product\StoreRetinaProductManual;
use App\Actions\Retina\Ecom\Basket\RetinaDeleteBasketTransaction;
use App\Actions\Retina\Ecom\Basket\RetinaEcomUpdateTransaction;
use App\Actions\Retina\Fulfilment\Dropshipping\Channel\Manual\StoreRetinaFulfilmentManualPlatform;
use App\Actions\Retina\Fulfilment\Dropshipping\Client\StoreRetinaFulfilmentCustomerClient;
use App\Actions\Retina\Fulfilment\Dropshipping\Client\StoreRetinaFulfilmentCustomerClientWithOrder;
use App\Actions\Retina\Fulfilment\Dropshipping\Portfolio\SyncAllRetinaStoredItemsToPortfolios;
use App\Actions\Retina\Fulfilment\Dropshipping\Portfolio\SyncRetinaStoredItemsFromApiProductsShopify;
use App\Actions\Retina\Fulfilment\Dropshipping\Portfolio\SyncRetinaStoredItemsFromApiProductsWooCommerce;
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
use App\Actions\Retina\Ordering\AddRetinaProductToBasket;
use App\Actions\Retina\Ordering\StoreRetinaTransaction;
use App\Actions\Retina\Ordering\UpdateRetinaOrderDeliveryAddress;
use App\Actions\Retina\Ordering\UpdateRetinaTransaction;
use App\Actions\Retina\Shopify\CreateRetinaNewAllPortfoliosToShopify;
use App\Actions\Retina\Shopify\CreateRetinaNewBulkPortfoliosToShopify;
use App\Actions\Retina\Shopify\MatchRetinaBulkPortfoliosToCurrentShopifyProduct;
use App\Actions\Retina\Shopify\MatchRetinaPortfolioToCurrentShopifyProduct;
use App\Actions\Retina\Shopify\StoreRetinaNewProductToCurrentShopify;
use App\Actions\Retina\Shopify\StoreRetinaProductShopify;
use App\Actions\Retina\SysAdmin\AddRetinaDeliveryAddressToCustomer;
use App\Actions\Retina\SysAdmin\AddRetinaDeliveryAddressToFulfilmentCustomer;
use App\Actions\Retina\SysAdmin\DeleteRetinaWebUser;
use App\Actions\Retina\SysAdmin\StoreRetinaWebUser;
use App\Actions\Retina\SysAdmin\UpdateRetinaCustomer;
use App\Actions\Retina\SysAdmin\UpdateRetinaWebUser;
use App\Actions\Retina\UI\Profile\UpdateRetinaProfile;
use App\Actions\Retina\Woo\CreateRetinaNewAllPortfoliosToWoo;
use App\Actions\Retina\Woo\CreateRetinaNewBulkPortfoliosToWoo;
use App\Actions\Retina\Woo\MatchRetinaBulkNewProductToCurrentWooCommerce;
use App\Actions\Retina\Woo\MatchRetinaPortfolioToCurrentWooProduct;
use App\Actions\Retina\Woo\StoreRetinaNewProductToCurrentWoo;
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
    Route::post('transaction', [StoreRetinaFulfilmentTransaction::class, 'fromRetinaInPalletDelivery'])->name('transaction.store');
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
    Route::patch('update-premium-dispatch', UpdateRetinaOrderPremiumDispatch::class)->name('update_premium_dispatch');
    Route::patch('update-extra-packing', UpdateRetinaOrderExtraPacking::class)->name('update_extra_packing');
    Route::delete('delete-basket', DeleteRetinaBasket::class)->name('delete_basket');
    Route::patch('submit', SubmitRetinaOrder::class)->name('submit');
    Route::patch('pay-with-balance', PayRetinaOrderWithBalance::class)->name('pay_with_balance');

    Route::patch('delivery-address-update', UpdateRetinaOrderDeliveryAddress::class)->name('delivery_address_update');

    Route::post('add-collection', StoreOrderAddressCollection::class)->name('basket.collection.store');
    Route::delete('delete-collection', DeleteOrderAddressCollection::class)->name('basket.collection.delete');

    Route::name('transaction.')->prefix('transaction')->group(function () {
        Route::post('upload', ImportRetinaOrderTransaction::class)->name('upload');
        Route::post('add', AddRetinaProductToBasket::class)->name('add');
        Route::post('', StoreRetinaTransaction::class)->name('store');
    });
});

Route::name('transaction.')->prefix('transaction/{transaction:id}')->group(function () {
    Route::delete('', DeleteRetinaTransaction::class)->name('delete')->withoutScopedBindings();
    Route::patch('', UpdateRetinaTransaction::class)->name('update')->withoutScopedBindings();
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
    Route::patch('reset-shopify', ResetShopifyChannel::class)->name('shopify_reset');

    Route::patch('update', UpdateRetinaCustomerSalesChannel::class)->name('update');
    Route::post('client', StoreRetinaCustomerClient::class)->name('customer-client.store');
    Route::post('fulfilment', StoreRetinaFulfilmentCustomerClient::class)->name('fulfilment.customer-client.store');
    Route::post('fulfilment-client-with-order', StoreRetinaFulfilmentCustomerClientWithOrder::class)->name('fulfilment.customer-client-with-order.store');
    Route::post('sync-all-stored-items', SyncAllRetinaStoredItemsToPortfolios::class)->name('sync_all_stored_items');
    Route::post('woo-sync-all-stored-items', SyncRetinaStoredItemsFromApiProductsWooCommerce::class)->name('woo_sync_all_stored_items');
    Route::post('shopify-sync-all-stored-items', SyncRetinaStoredItemsFromApiProductsShopify::class)->name('shopify_sync_all_stored_items');
    Route::post('upload', ImportRetinaClients::class)->name('clients.upload');
    Route::post('products', StoreRetinaProductManual::class)->name('customer.product.store')->withoutScopedBindings();
    Route::post('portfolio-clone-manual/{targetCustomerSalesChannel:id}', CloneMultipleManualPortfolios::class)->name('portfolio.clone_manual')->withoutScopedBindings();
    Route::post('product-category/{productCategory:id}/store', StoreRetinaPortfoliosFromProductCategory::class)->name('portfolio.store_from_product_category')->withoutScopedBindings();
    Route::delete('delete', RetinaDeleteCustomerSalesChannel::class)->name('delete');
    Route::delete('products/{portfolio:id}', DeleteRetinaPortfolio::class)->name('product.delete')->withoutScopedBindings();
    Route::post('portfolio-batch-delete', BatchDeleteRetinaPortfolio::class)->name('portfolio.batch.delete');
    Route::post('access-token', StoreCustomerToken::class)->name('access_token.create');
});


Route::delete('{token}/access-token', DeleteCustomerAccessToken::class)->name('access_token.delete');


Route::name('dropshipping.')->prefix('dropshipping')->group(function () {
    Route::post('shopify-user/{shopifyUser:id}/products', StoreRetinaProductShopify::class)->name('shopify_user.product.store')->withoutScopedBindings();

    Route::post('{customerSalesChannel:id}/shopify-batch-upload', CreateRetinaNewBulkPortfoliosToShopify::class)->name('shopify.batch_upload')->withoutScopedBindings();
    Route::post('{customerSalesChannel:id}/shopify-batch-match', MatchRetinaBulkPortfoliosToCurrentShopifyProduct::class)->name('shopify.batch_match')->withoutScopedBindings();
    Route::post('{customerSalesChannel:id}/shopify-batch-all', CreateRetinaNewAllPortfoliosToShopify::class)->name('shopify.batch_all')->withoutScopedBindings();

    Route::post('{customerSalesChannel:id}/woo-batch-upload', CreateRetinaNewBulkPortfoliosToWoo::class)->name('woo.batch_upload')->withoutScopedBindings();
    Route::post('{customerSalesChannel:id}/woo-batch-match', MatchRetinaBulkNewProductToCurrentWooCommerce::class)->name('woo.batch_match')->withoutScopedBindings();
    Route::post('{customerSalesChannel:id}/woo-batch-all', CreateRetinaNewAllPortfoliosToWoo::class)->name('woo.batch_all')->withoutScopedBindings();

    Route::post('{wooCommerceUser:id}/woo-batch-upload', CreateNewBulkPortfolioToWooCommerce::class)->name('woo.batch_upload_legacy')->withoutScopedBindings();
    Route::post('{wooCommerceUser:id}/woo-batch-sync', [CreateNewBulkPortfolioToWooCommerce::class, 'asBatchSync'])->name('woo.batch_sync')->withoutScopedBindings();
    Route::post('{wooCommerceUser:id}/woo-batch-brave', [CreateNewBulkPortfolioToWooCommerce::class, 'asBraveMode'])->name('woo.batch_brave')->withoutScopedBindings();
    Route::post('{wooCommerceUser:id}/woo-single-upload/{portfolio:id}', StoreNewProductToCurrentWooCommerce::class)->name('woo.single_upload')->withoutScopedBindings();

    Route::post('{ebayUser:id}/ebay-batch-upload', SyncronisePortfoliosToEbay::class)->name('ebay.batch_upload')->withoutScopedBindings();
    Route::post('{ebayUser:id}/ebay-single-upload/{portfolio:id}', SyncronisePortfolioToEbay::class)->name('ebay.single_upload')->withoutScopedBindings();

    Route::post('{amazonUser:id}/amazon-batch-upload', SyncronisePortfoliosToAmazon::class)->name('amazon.batch_upload')->withoutScopedBindings();
    Route::post('{amazonUser:id}/amazon-single-upload/{portfolio:id}', SyncronisePortfolioToAmazon::class)->name('amazon.single_upload')->withoutScopedBindings();

    Route::post('{magentoUser:id}/magento-batch-upload', SyncronisePortfoliosToMagento::class)->name('magento.batch_upload')->withoutScopedBindings();
    Route::post('{magentoUser:id}/magento-single-upload/{portfolio:id}', SyncronisePortfolioToMagento::class)->name('magento.single_upload')->withoutScopedBindings();

    Route::delete('tiktok/{tiktokUser:id}', DeleteTiktokUser::class)->name('tiktok.delete')->withoutScopedBindings();
    Route::post('tiktok/{tiktokUser:id}/products', StoreProductToTiktok::class)->name('tiktok.product.store')->withoutScopedBindings();
    Route::get('tiktok/{tiktokUser:id}/sync-products', GetProductsFromTiktokApi::class)->name('tiktok.product.sync')->withoutScopedBindings();

    Route::get('woocommerce/{wooCommerceUser:id}/catch-orders', FetchWooUserOrders::class)->name('woocommerce.orders.catch')->withoutScopedBindings();
    Route::get('ebay/{ebayUser:id}/catch-orders', FetchEbayUserOrders::class)->name('ebay.orders.catch')->withoutScopedBindings();
    Route::get('amazon/{amazonUser:id}/catch-orders', GetRetinaOrdersFromAmazon::class)->name('amazon.orders.catch')->withoutScopedBindings();
    Route::get('magento/{magentoUser:id}/catch-orders', GetRetinaOrdersFromMagento::class)->name('magento.orders.catch')->withoutScopedBindings();
});

Route::name('web-users.')->prefix('web-users')->group(function () {
    Route::post('', StoreRetinaWebUser::class)->name('store');
    Route::patch('{webUser:id}/update', UpdateRetinaWebUser::class)->name('update');
    Route::delete('{webUser:id}/delete', DeleteRetinaWebUser::class)->name('delete');
});

Route::get('attachment/{media:ulid}', DownloadRetinaAttachment::class)->name('attachment.download');


Route::name('transaction.')->prefix('transaction')->group(function () {
    Route::delete('{transaction:id}', RetinaDeleteBasketTransaction::class)->name('delete');
    Route::patch('{transaction:id}', RetinaEcomUpdateTransaction::class)->name('update');
});

Route::name('top-up.')->prefix('top-up')->group(function () {
    Route::post('{paymentAccount:id}', StoreRetinaTopUp::class)->name('store')->withoutScopedBindings();
});

Route::delete('portfolio/{portfolio:id}', DeleteRetinaPortfolio::class)->name('portfolio.delete');
Route::patch('portfolio/{portfolio:id}', UpdateRetinaPortfolio::class)->name('portfolio.update');

Route::post('portfolio/{portfolio:id}/match-to-existing-shopify-product', MatchRetinaPortfolioToCurrentShopifyProduct::class)->name('portfolio.match_to_existing_shopify_product');
Route::post('portfolio/{portfolio:id}/store-new-shopify-product', StoreRetinaNewProductToCurrentShopify::class)->name('portfolio.store_new_shopify_product');

Route::post('portfolio/{portfolio:id}/match-to-existing-woo-product', MatchRetinaPortfolioToCurrentWooProduct::class)->name('portfolio.match_to_existing_woo_product');
Route::post('portfolio/{portfolio:id}/store-new-woo-product', StoreRetinaNewProductToCurrentWoo::class)->name('portfolio.store_new_woo_product');

Route::post('portfolio/product-category/{productCategory:id}/store', StoreRetinaPortfoliosFromProductCategoryToAllChannels::class)->name('portfolio.store_from_product_category')->withoutScopedBindings();
Route::post('portfolio/all-channels/store', StoreRetinaPortfolioToAllChannels::class)->name('portfolio.store_to_all_channels');
Route::post('portfolio/product-category/{productCategory:id}/multi-channels/store', [StoreRetinaPortfolioToMultiChannels::class, 'inProductCategory'])->name('portfolio.store_to_multi_channels');

Route::name('mit_saved_card.')->prefix('mit-saved-card')->group(function () {
    Route::delete('{mitSavedCard:id}/delete', DeleteMitSavedCard::class)->name('delete');
    Route::patch('{mitSavedCard:id}/set-to-default', SetAsDefaultRetinaMitSavedCard::class)->name('set_to_default');
});

Route::name('product.')->prefix('product')->group(function () {
    Route::post('{product:id}/favourite', StoreRetinaFavourite::class)->name('favourite');
    Route::delete('{favourite:id}/unfavourite', DeleteRetinaFavourite::class)->name('unfavourite');
    Route::post('{product:id}/add-to-basket', StoreRetinaEcomBasketTransaction::class)->name('add-to-basket');
});

Route::patch('/locale/{locale}', UpdateIrisLocale::class)->name('locale.update');
