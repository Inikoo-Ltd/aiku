<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\GoogleAds;

use App\Actions\CRM\Customer\SyncCustomersToGoogleAds;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class SyncShopCustomersToGoogleAds extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function handle(Shop $shop): RedirectResponse
    {
        $redirect = Redirect::back();

        if (blank(Arr::get($shop->settings, 'google_ads.refresh_token'))) {
            return $redirect->with('notification', [
                'status'      => 'error',
                'title'       => __('Google Ads not connected'),
                'description' => __('Connect this shop to Google Ads in the shop settings before syncing customers.'),
            ]);
        }

        SyncCustomersToGoogleAds::dispatch($shop);

        return $redirect->with('notification', [
            'status'      => 'success',
            'title'       => __('Syncing customers to Google Ads'),
            'description' => __('The shop customers are being uploaded to your Google Ads user list in the background.'),
        ]);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }
}
