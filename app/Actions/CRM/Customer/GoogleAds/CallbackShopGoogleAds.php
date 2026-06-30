<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\GoogleAds;

use App\Actions\CRM\Customer\GoogleAds\Traits\WithGoogleAdsOAuth;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class CallbackShopGoogleAds extends OrgAction
{
    use WithGoogleAdsOAuth;

    public function handle(Shop $shop, string $authCode): RedirectResponse
    {
        $redirect = Redirect::route('grp.org.shops.show.settings.edit', [$shop->organisation->slug, $shop->slug]);

        try {
            $this->storeGoogleAdsRefreshToken($shop, $authCode);
        } catch (Exception $exception) {
            return $redirect->with('notification', [
                'status'      => 'error',
                'title'       => __('Google Ads connection failed'),
                'description' => $exception->getMessage(),
            ]);
        }

        return $redirect->with('notification', [
            'status'      => 'success',
            'title'       => __('Google Ads connected'),
            'description' => __('This shop is now authorized to sync customers to Google Ads.'),
        ]);
    }

    public function asController(ActionRequest $request): RedirectResponse
    {
        $shop = Shop::find($request->input('state'));
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, (string) $request->query('code'));
    }
}
