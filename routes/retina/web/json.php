<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 12:02:06 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Product\Json\GetRetinaPortfoliosInProduct;
use App\Actions\Dropshipping\CustomerSalesChannel\Json\GetEbayProducts;
use App\Actions\Dropshipping\CustomerSalesChannel\Json\GetRetinaCustomerProductCategorySalesChannelIds;
use App\Actions\Dropshipping\CustomerSalesChannel\Json\GetShopifyProducts;
use App\Actions\Dropshipping\CustomerSalesChannel\Json\GetWooProducts;
use App\Actions\Fulfilment\PalletReturn\Json\GetPalletsInReturnPalletWholePallets;
use App\Actions\Iris\Json\GetIrisAuthData;
use App\Actions\Iris\Json\GetIrisEcomCustomerData;
use App\Actions\Retina\Dropshipping\Portfolio\DownloadPortfolioZipImages;
use App\Actions\Retina\Fulfilment\PalletDelivery\Json\GetRetinaFulfilmentPhysicalGoods;
use App\Actions\Retina\Fulfilment\PalletDelivery\Json\GetRetinaFulfilmentServices;
use App\Actions\Retina\GetCheckoutComTokenToPayOrder;
use Illuminate\Support\Facades\Route;

Route::get('fulfilment/{fulfilment}/delivery/{scope}/services', [GetRetinaFulfilmentServices::class, 'inPalletDelivery'])->name('fulfilment.delivery.services.index');
Route::get('fulfilment/{fulfilment}/return/{scope}/services', [GetRetinaFulfilmentServices::class, 'inPalletReturn'])->name('fulfilment.return.services.index');
Route::get('fulfilment/{fulfilment}/delivery/{scope}/physical-goods', [GetRetinaFulfilmentPhysicalGoods::class, 'inPalletDelivery'])->name('fulfilment.delivery.physical-goods.index');
Route::get('fulfilment/{fulfilment}/return/{scope}/physical-goods', [GetRetinaFulfilmentPhysicalGoods::class, 'inPalletReturn'])->name('fulfilment.return.physical-goods.index');
Route::get('pallet-return/{palletReturn}/pallets', GetPalletsInReturnPalletWholePallets::class)->name('pallet-return.pallets.index');
Route::get('/{order}/recent-uploads', \App\Actions\Ordering\Order\UI\IndexRecentOrderTransactionUploads::class)->name('recent_uploads');

Route::get('/{order:id}/get-checkout-com-token-to_pay-order', GetCheckoutComTokenToPayOrder::class)->name('get_checkout_com_token_to_pay_order');

Route::get('dropshipping/{customerSalesChannel:id}/portfolio-images-zip', DownloadPortfolioZipImages::class)->name('dropshipping.customer_sales_channel.portfolio_images_zip');

Route::get('dropshipping/{product:id}/channels_list', GetRetinaPortfoliosInProduct::class)->name('dropshipping.product.channels_list');

Route::get('product-category/{productCategory:id}/channels', GetRetinaCustomerProductCategorySalesChannelIds::class)->name('product_category.channel_ids.index');

Route::get('customer-sales-channel/{customerSalesChannel:id}/shopify-products', GetShopifyProducts::class)->name('dropshipping.customer_sales_channel.shopify_products');
Route::get('customer-sales-channel/{customerSalesChannel:id}/woo-products', GetWooProducts::class)->name('dropshipping.customer_sales_channel.woo_products');
Route::get('customer-sales-channel/{customerSalesChannel:id}/ebay-products', GetEbayProducts::class)->name('dropshipping.customer_sales_channel.ebay_products');
Route::get('auth-data', GetIrisAuthData::class)->name('auth_data');
Route::get('ecom-customer-data', GetIrisEcomCustomerData::class)->name('ecom_customer_data');
