<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop\GoogleMerchant;

use App\Services\GoogleMerchant\GoogleMerchantClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use Throwable;

class CallbackShopGoogleMerchant
{
    use AsAction;

    public function asController(Request $request): RedirectResponse
    {
        $shop = GoogleMerchantClient::shopFromState((string) $request->query('state'));

        if (!$shop || blank($request->query('code'))) {
            return Redirect::route('grp.dashboard.show')->with('notification', [
                'status'      => 'error',
                'title'       => __('Google Merchant Center not connected'),
                'description' => __('The connection was cancelled or has expired. Start again from the shop settings.'),
            ]);
        }

        $redirect = Redirect::route('grp.org.shops.show.settings.edit', [$shop->organisation->slug, $shop->slug]);

        try {
            GoogleMerchantClient::storeRefreshToken($shop, (string) $request->query('code'));
        } catch (Throwable $exception) {
            Sentry::captureException($exception);

            return $redirect->with('notification', [
                'status'      => 'error',
                'title'       => __('Google Merchant Center not connected'),
                'description' => __('Google did not accept the connection. Please try again.'),
            ]);
        }

        return $redirect->with('notification', [
            'status'      => 'success',
            'title'       => __('Google Merchant Center connected'),
            'description' => __('Product costs will be sent to Merchant Center every night.'),
        ]);
    }
}
