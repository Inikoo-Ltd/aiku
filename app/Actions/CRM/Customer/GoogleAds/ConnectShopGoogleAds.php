<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\GoogleAds;

use App\Actions\CRM\Customer\GoogleAds\Traits\WithGoogleAdsOAuth;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class ConnectShopGoogleAds extends OrgAction
{
    use WithGoogleAdsOAuth;

    /**
     * @throws Exception
     */
    public function handle(Shop $shop): string
    {
        $clientId     = (string) config('services.google_ads.client_id');
        $clientSecret = (string) config('services.google_ads.client_secret');

        if ($clientId === '' || $clientSecret === '') {
            return '';
        }

        return $this->googleAdsAuthUrl($shop);
    }

    /**
     * @throws Exception
     */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): string
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }
}
