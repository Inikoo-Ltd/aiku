<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Jun 2024 12:54:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Helpers\Gallery\UI\StockImages\IndexBannersStockImages;
use App\Actions\Helpers\Gallery\UI\StockImages\IndexStockImages;
use App\Actions\Helpers\Gallery\UI\UploadedImages\IndexUploadedBannerImages;
use App\Actions\Helpers\Gallery\UI\UploadedImages\IndexUploadedImages;
use Illuminate\Support\Facades\Route;

Route::prefix('stock-images')->name('stock-images')->group(function () {
    Route::get('', IndexStockImages::class)->name('.index');
    Route::get('banner/landscape', [IndexBannersStockImages::class, 'landscape'])->name('.banner.landscape.index');
    Route::get('banner/square', [IndexBannersStockImages::class, 'square'])->name('.banner.square.index');
});

Route::prefix('uploaded-images')->name('uploaded-images')->group(function () {
    Route::get('', IndexUploadedImages::class)->name('.index');
    Route::get('banner', IndexUploadedBannerImages::class)->name('.banner.index');
    Route::get('email', [IndexUploadedImages::class, 'email'])->name('.email.index');
});
