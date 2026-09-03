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
    /** Wix access tokens are valid for 4 hours. */
    protected const int WIX_ACCESS_TOKEN_TTL = 14400;

    /** Renew a little early so a token cannot expire mid-request. */
    protected const int WIX_ACCESS_TOKEN_SKEW = 300;

    public function wixTokenUrl(): string
    {
        return config('services.wix.api_url').'/oauth2/token';
    }

    public function getInstallUrl(?string $postInstallationUrl = null): string
    {
        $base = config('services.wix.install_url') ?: 'https://www.wix.com/app-installer';

       [$path, $query] = array_pad(explode('?', $base, 2), 2, '');
        parse_str($query, $params);

        $params['appId'] ??= config('services.wix.app_id');

        if ($postInstallationUrl) {
            $params['postInstallationUrl'] = $postInstallationUrl;
        }

        return $path.'?'.http_build_query(array_filter($params));
    }

    /**
     * Create an access token for one app instance.
     *
     * @return array{access_token?: string, expires_in?: int, message?: string}
     */
    public function createAccessToken(string $instanceId): array
    {
        try {
            $response = Http::acceptJson()->post($this->wixTokenUrl(), [
                'grant_type'    => 'client_credentials',
                'client_id'     => config('services.wix.app_id'),
                'client_secret' => config('services.wix.app_secret'),
                'instance_id'   => $instanceId,
            ]);

            if ($response->failed()) {
                return [
                    'message' => Arr::get($response->json(), 'error_description')
                        ?? Arr::get($response->json(), 'message')
                        ?? Arr::get($response->json(), 'error')
                        ?? 'Wix access token request failed',
                ];
            }

            return $response->json() ?? [];
        } catch (\Exception $e) {
            return ['message' => $e->getMessage()];
        }
    }

    /**
     * Mint a token for this WixUser and cache it until it expires.
     *
     * @return array{access_token?: string, expires_in?: int, message?: string}
     */
    public function renewAccessToken(): array
    {
        $result = $this->createAccessToken($this->wix_instance_id);

        if (!Arr::get($result, 'access_token')) {
            return $result;
        }

        UpdateWixUser::make()->handle($this, [
            'access_token'           => Arr::get($result, 'access_token'),
            'access_token_expire_in' => $this->wixAccessTokenExpiry($result),
        ], false);

        $this->refresh();

        return $result;
    }

    public function hasFreshAccessToken(): bool
    {
        return $this->access_token
            && $this->access_token_expire_in
            && $this->access_token_expire_in > now()->timestamp;
    }

    public function wixAccessTokenExpiry(array $tokenData): int
    {
        $ttl = (int) Arr::get($tokenData, 'expires_in', self::WIX_ACCESS_TOKEN_TTL);

        return now()->addSeconds(max($ttl - self::WIX_ACCESS_TOKEN_SKEW, 60))->timestamp;
    }

    public function verifySignedInstance(?string $signedInstance): ?array
    {
        if (blank($signedInstance) || !str_contains($signedInstance, '.')) {
            return null;
        }

        $appSecret = config('services.wix.app_secret');

        if (blank($appSecret)) {
            return null;
        }

        [$signature, $payload] = explode('.', $signedInstance, 2);

        $expected = hash_hmac('sha256', $payload, $appSecret, true);

        if (!hash_equals($expected, $this->base64UrlDecode($signature))) {
            return null;
        }

        $decoded = json_decode($this->base64UrlDecode($payload), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'));
    }
}
