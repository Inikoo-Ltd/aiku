<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Services\GoogleMerchant;

use App\Models\Catalogue\Shop;
use Google\Client;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GoogleMerchantClient
{
    private const string SCOPE = 'https://www.googleapis.com/auth/content';

    private const string BASE_URL = 'https://merchantapi.googleapis.com/';

    private function __construct(private readonly string $accessToken, private readonly string $merchantId)
    {
    }

    public static function forShop(Shop $shop): ?self
    {
        $encryptedToken = Arr::get($shop->settings, 'google_merchant.refresh_token');
        $merchantId     = self::merchantId($shop);

        if (blank($encryptedToken) || $merchantId === '' || blank(config('services.google.client_id'))) {
            return null;
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id'     => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => Crypt::decryptString($encryptedToken),
            'grant_type'    => 'refresh_token',
        ]);

        if ($response->failed() || blank($response->json('access_token'))) {
            throw new RuntimeException('Google refused the Merchant Center refresh token: '.$response->json('error_description', $response->body()));
        }

        return new self($response->json('access_token'), $merchantId);
    }

    public static function merchantId(Shop $shop): string
    {
        return preg_replace('/\D/', '', (string) Arr::get($shop->settings, 'google_merchant.merchant_id'));
    }

    public static function isConnected(Shop $shop): bool
    {
        return filled(Arr::get($shop->settings, 'google_merchant.refresh_token'));
    }

    public static function authUrl(Shop $shop): string
    {
        if (blank(config('services.google.client_id'))) {
            return '';
        }

        $client = self::baseClient();
        $client->setRedirectUri(route('google_merchant.callback'));
        $client->setAccessType('offline');
        $client->setPrompt('consent select_account');
        $client->setState(Crypt::encryptString($shop->id.'|'.now()->addMinutes(15)->timestamp));

        return $client->createAuthUrl();
    }

    public static function shopFromState(string $state): ?Shop
    {
        try {
            [$shopId, $expiresAt] = explode('|', Crypt::decryptString($state));
        } catch (Throwable) {
            return null;
        }

        return (int) $expiresAt >= now()->timestamp ? Shop::find((int) $shopId) : null;
    }

    public static function storeRefreshToken(Shop $shop, string $authCode): void
    {
        $client = self::baseClient();
        $client->setRedirectUri(route('google_merchant.callback'));

        $accessToken  = $client->fetchAccessTokenWithAuthCode($authCode);
        $refreshToken = Arr::get($accessToken, 'refresh_token');

        if (array_key_exists('error', $accessToken) || blank($refreshToken)) {
            throw new RuntimeException('Google did not return a Merchant Center refresh token: '.json_encode($accessToken));
        }

        $settings = $shop->settings;
        data_set($settings, 'google_merchant.refresh_token', Crypt::encryptString($refreshToken));
        data_set($settings, 'google_merchant.connected_at', now()->toIso8601String());
        $shop->update(['settings' => $settings]);
    }

    /**
     * @return array<int, array{name: string, offerId: string, contentLanguage: string, feedLabel: string, productAttributes?: array<string, mixed>}>
     */
    public function products(): array
    {
        $products  = [];
        $pageToken = null;

        do {
            $page      = $this->request('get', "products/v1/accounts/$this->merchantId/products", array_filter(['pageSize' => 1000, 'pageToken' => $pageToken]));
            $products  = array_merge($products, $page->json('products', []));
            $pageToken = $page->json('nextPageToken');
        } while ($pageToken);

        return $products;
    }

    public function createSupplementalDataSource(string $displayName): string
    {
        $this->request('post', "accounts/v1/accounts/$this->merchantId/developerRegistration:registerGcp", (object) []);

        return $this->request('post', "datasources/v1/accounts/$this->merchantId/dataSources", [
            'displayName'                   => $displayName,
            'supplementalProductDataSource' => (object) [],
        ])->json('name');
    }

    /**
     * @param array<string, mixed> $productInput
     */
    public function insertProductInput(string $dataSource, array $productInput): void
    {
        $this->request('post', "products/v1/accounts/$this->merchantId/productInputs:insert?dataSource=".urlencode($dataSource), $productInput);
    }

    /**
     * @param array<string, mixed>|object $data
     */
    private function request(string $method, string $path, array|object $data): Response
    {
        return Http::withToken($this->accessToken)
            ->timeout(60)
            ->retry(3, 5000, fn (Throwable $e) => $e instanceof RequestException && $e->response->status() === 429, throw: false)
            ->$method(self::BASE_URL.$path, $data)
            ->throw();
    }

    private static function baseClient(): Client
    {
        $client = new Client();
        $client->setApplicationName('Aiku Google Merchant Center');
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->addScope(self::SCOPE);

        return $client;
    }
}
