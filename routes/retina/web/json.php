<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 12:02:06 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Product\Json\GetProductsInProductCategory;
use App\Actions\Fulfilment\PalletReturn\Json\GetPalletsInReturnPalletWholePallets;
use App\Actions\Retina\Fulfilment\PalletDelivery\Json\GetRetinaFulfilmentPhysicalGoods;
use App\Actions\Retina\Fulfilment\PalletDelivery\Json\GetRetinaFulfilmentServices;
use Illuminate\Support\Facades\Route;

Route::get('fulfilment/{fulfilment}/delivery/{scope}/services', [GetRetinaFulfilmentServices::class, 'inPalletDelivery'])->name('fulfilment.delivery.services.index');
Route::get('fulfilment/{fulfilment}/return/{scope}/services', [GetRetinaFulfilmentServices::class, 'inPalletReturn'])->name('fulfilment.return.services.index');
Route::get('fulfilment/{fulfilment}/delivery/{scope}/physical-goods', [GetRetinaFulfilmentPhysicalGoods::class, 'inPalletDelivery'])->name('fulfilment.delivery.physical-goods.index');
Route::get('fulfilment/{fulfilment}/return/{scope}/physical-goods', [GetRetinaFulfilmentPhysicalGoods::class, 'inPalletReturn'])->name('fulfilment.return.physical-goods.index');
Route::get('pallet-return/{palletReturn}/pallets', GetPalletsInReturnPalletWholePallets::class)->name('pallet-return.pallets.index');
Route::get('/{order}/recent-uploads', \App\Actions\Ordering\Order\UI\IndexRecentOrderTransactionUploads::class)->name('recent_uploads');

Route::get('product-category/{productCategory}/products', GetProductsInProductCategory::class)->name('product_category.products.index');
