<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Traits;

use App\Actions\Dropshipping\Wix\User\UpdateWixUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

trait WithWixOAuth
{
    /**
     * Wix access tokens are short lived, refresh tokens are not. The refresh token is the
     * only credential worth persisting long term.
     */
    protected const int WIX_ACCESS_TOKEN_TTL = 14400;

    public function wixInstallUrl(): string
    {
        return config('services.wix.install_url', 'https://www.wix.com/installer/install');
    }

    public function wixTokenUrl(): string
    {
        return config('services.wix.api_url').'/oauth/access';
    }

    /**
     * Step 1 — the URL that sends the seller to Wix to pick a site and grant permissions.
     */
    public function getAuthorizationUrl(string $redirectUri, ?string $state = null): string
    {
        $params = [
            'appId'       => config('services.wix.app_id'),
            'redirectUrl' => $redirectUri,
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return $this->wixInstallUrl().'?'.http_build_query($params);
    }

    /**
     * Step 2 — swap the authorization code for the instance's token pair.
     *
     * @return array{access_token?: string, refresh_token?: string, message?: string}
     */
    public function exchangeCodeForTokens(string $code): array
    {
        return $this->postToTokenEndpoint([
            'grant_type'    => 'authorization_code',
            'client_id'     => config('services.wix.app_id'),
            'client_secret' => config('services.wix.app_secret'),
            'code'          => $code,
        ]);
    }

    /**
     * @return array{access_token?: string, refresh_token?: string, message?: string}
     */
    public function refreshAccessToken(?string $refreshToken): array
    {
        if (blank($refreshToken)) {
            return ['message' => 'Missing Wix refresh token'];
        }

        $result = $this->postToTokenEndpoint([
            'grant_type'    => 'refresh_token',
            'client_id'     => config('services.wix.app_id'),
            'client_secret' => config('services.wix.app_secret'),
            'refresh_token' => $refreshToken,
        ]);

        if (blank($result) || Arr::get($result, 'message')) {
            return $result;
        }

        UpdateWixUser::make()->handle($this, [
            'access_token'           => Arr::get($result, 'access_token'),
            'refresh_token'          => Arr::get($result, 'refresh_token', $refreshToken),
            'access_token_expire_in' => $this->wixAccessTokenExpiry($result),
        ], false);

        $this->refresh();

        return $result;
    }

    public function wixAccessTokenExpiry(array $tokenData): int
    {
        return now()->addSeconds((int) Arr::get($tokenData, 'expires_in', self::WIX_ACCESS_TOKEN_TTL))->timestamp;
    }

    protected function postToTokenEndpoint(array $payload): array
    {
        try {
            $response = Http::acceptJson()->post($this->wixTokenUrl(), $payload);

            if ($response->failed()) {
                return [
                    'message' => Arr::get($response->json(), 'message')
                        ?? Arr::get($response->json(), 'errorDescription')
                        ?? 'Wix OAuth request failed',
                ];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            return ['message' => $e->getMessage()];
        }
    }
}
