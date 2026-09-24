<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\GoogleAds\Traits;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Shop;
use Exception;
use Google_Client;
use Illuminate\Support\Arr;

trait WithGoogleAdsOAuth
{
    use WithActionUpdate;
    use WithGoogleAdsAccessToken;

    private const string GOOGLE_ADS_SCOPE = 'https://www.googleapis.com/auth/adwords';

    private const string DATA_MANAGER_SCOPE = 'https://www.googleapis.com/auth/datamanager';

    /**
     * @throws Exception
     */
    private function buildGoogleClient(Shop $shop): Google_Client
    {
        $clientId     = (string) config('services.google_ads.client_id');
        $clientSecret = (string) config('services.google_ads.client_secret');

        $client = new Google_Client();
        $client->setApplicationName('Aiku Google Ads');
        $client->setAuthConfig([
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
        ]);

        /* The webhook routes already carry SANDBOX_LOCAL_WEBHOOKS_URL as their domain off production,
           so the route resolves to whichever tunnel is in front of this machine. Whatever it resolves
           to has to be listed verbatim under Authorized redirect URIs on the Google Cloud OAuth client
           or Google answers redirect_uri_mismatch before the consent screen is ever shown. */
        $client->setRedirectUri(route('google_ads.callback'));
        $client->setScopes([self::GOOGLE_ADS_SCOPE, self::DATA_MANAGER_SCOPE]);
        $client->setState($shop->id);
        $client->setAccessType('offline');
        $client->setPrompt('select_account');

        return $client;
    }

    /**
     * @throws Exception
     */
    private function googleAdsAuthUrl(Shop $shop): string
    {
        return $this->buildGoogleClient($shop)->createAuthUrl();
    }

    /**
     * @throws Exception
     */
    private function storeGoogleAdsRefreshToken(Shop $shop, string $authCode): void
    {
        $client = $this->buildGoogleClient($shop);

        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

        if (array_key_exists('error', $accessToken)) {
            throw new Exception('Failed to obtain Google Ads refresh token: ' . join(', ', $accessToken));
        }

        $refreshToken = Arr::get($accessToken, 'refresh_token') ?: $client->getRefreshToken();

        if (blank($refreshToken)) {
            throw new Exception('Google did not return a refresh token. Make sure to grant offline access when connecting.');
        }

        $settings = $shop->settings ?? [];
        data_set($settings, 'google_ads.refresh_token', $refreshToken);

        $this->update($shop, ['settings' => $settings]);
    }
}
