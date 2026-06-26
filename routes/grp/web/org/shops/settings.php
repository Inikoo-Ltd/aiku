<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 13 Jul 2024 00:38:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Shop\UI\EditShop;
use App\Actions\CRM\Customer\GoogleAds\CallbackShopGoogleAds;
use App\Actions\CRM\Customer\GoogleAds\ConnectShopGoogleAds;
use Illuminate\Support\Facades\Route;

Route::get('', EditShop::class)->name('edit');
Route::get('google-ads/connect', ConnectShopGoogleAds::class)->name('google_ads.connect');
