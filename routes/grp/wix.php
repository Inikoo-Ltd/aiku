<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Shop\External\Wix\AuthenticateWixExternalShop;
use App\Actions\Dropshipping\Wix\User\AuthenticateWixAccount;
use Illuminate\Support\Facades\Route;

/*
 * The post-installation callback of the Wix external install flow. It sits on the single fixed
 * aiku domain because the URL is handed to Wix per install, while retina is served per website
 * domain.
 */

Route::get('link/{customer:id}', AuthenticateWixAccount::class)->name('link')->whereNumber('customer');
Route::get('link-external-shop/{shop:id}', AuthenticateWixExternalShop::class)->name('link_external_shop')->whereNumber('shop');
