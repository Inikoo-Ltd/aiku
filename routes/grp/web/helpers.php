<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 07 Mar 2023 11:12:51 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

use App\Actions\Helpers\RedirectAssetLink;
use App\Actions\Helpers\Upload\DownloadUploads;
use App\Actions\Helpers\Upload\UI\ShowUpload;
use Illuminate\Support\Facades\Route;

Route::get('redirect-asset/{asset:id}', RedirectAssetLink::class)->name('redirect_asset');


Route::prefix('uploads/{upload}')->as('uploads.')->group(function () {
    Route::get('records', ShowUpload::class)->name('records.show');
    Route::get('download', DownloadUploads::class)->name('records.download');
});
