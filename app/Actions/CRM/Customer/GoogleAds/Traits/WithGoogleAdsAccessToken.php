<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\GoogleAds\Traits;

use App\Models\Catalogue\Shop;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

trait WithGoogleAdsAccessToken
{
    private const string OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /**
     * @throws Exception
     */
    protected function googleAdsAccessToken(Shop $shop): string
    {
        $refreshToken = (string) Arr::get($shop->settings, 'google_ads.refresh_token');

        if (blank($refreshToken)) {
            throw new Exception("Google Ads is not connected for shop $shop->slug: no refresh token stored.");
        }

        $response = Http::asForm()->post(self::OAUTH_TOKEN_URL, [
            'client_id'     => config('services.google_ads.client_id'),
            'client_secret' => config('services.google_ads.client_secret'),
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token',
        ]);

        if ($response->failed() || !$response->json('access_token')) {
            throw new Exception('Failed to obtain Google Ads access token: ' . $response->body());
        }

        return $response->json('access_token');
    }
}
