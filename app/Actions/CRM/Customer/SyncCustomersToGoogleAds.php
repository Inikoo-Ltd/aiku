<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncCustomersToGoogleAds
{
    use AsAction;

    public string $jobQueue = 'analytics';
    public int $jobTimeout = 600;
    public int $jobTries = 1;

    public string $commandSignature = 'sync:customers-to-google-ads {shop : The shop slug} {--chunk=10000 : Customers per addOperations request}';

    private const string OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const string DATA_MANAGER_BASE_URL = 'https://datamanager.googleapis.com/v1';

    private const int MAX_MEMBERS_PER_REQUEST = 10000;

    /**
     * Upload the shop's customers' hashed identifiers to a Google Ads Customer Match user list
     * through the Data Manager API, using the credentials stored in the shop settings.
     *
     * @return array{uploaded: int, request_ids: array<int, string>}
     * @throws Exception
     */
    public function handle(Shop $shop, int $chunkSize = self::MAX_MEMBERS_PER_REQUEST): array
    {
        $config = $this->resolveConfig($shop);

        $client = $this->client($config);

        $chunkSize = min($chunkSize, self::MAX_MEMBERS_PER_REQUEST);

        $uploaded    = 0;
        $requestIds  = [];

        $shop->customers()
            ->where(function ($query) {
                $query->whereNotNull('email')->orWhereNotNull('phone');
            })
            ->limit(1)
            ->select(['id', 'email', 'phone'])
            ->chunkById($chunkSize, function ($customers) use ($client, $config, &$uploaded, &$requestIds) {
                $audienceMembers = [];

                /** @var Customer $customer */
                foreach ($customers as $customer) {
                    $userIdentifiers = $this->buildUserIdentifiers($customer);

                    if (empty($userIdentifiers)) {
                        continue;
                    }

                    $audienceMembers[] = [
                        'compositeData' => [
                            'userData' => [
                                'userIdentifiers' => $userIdentifiers,
                            ],
                        ],
                    ];
                }

                if (empty($audienceMembers)) {
                    return;
                }

                $requestIds[] = $this->ingestAudienceMembers($client, $config, $audienceMembers);

                $uploaded += count($audienceMembers);
            });

        return [
            'uploaded'    => $uploaded,
            'request_ids' => $requestIds,
        ];
    }

    /**
     * @return array{customer_id: string, login_customer_id: string, user_list_id: string, access_token: string}
     * @throws Exception
     */
    private function resolveConfig(Shop $shop): array
    {
        $settings = Arr::get($shop->settings, 'google_ads', []);

        $customerId      = $this->onlyDigits((string) Arr::get($settings, 'customer_id'));
        $loginCustomerId = $this->onlyDigits((string) Arr::get($settings, 'login_customer_id')) ?: $customerId;
        $userListId      = $this->onlyDigits((string) Arr::get($settings, 'user_list_id'));

        if ($customerId === '' || $userListId === '' || blank(Arr::get($settings, 'refresh_token'))) {
            throw new Exception("Google Ads is not configured for shop $shop->slug: connect the shop's Google account and set customer_id and user_list_id.");
        }

        return [
            'customer_id'       => $customerId,
            'login_customer_id' => $loginCustomerId,
            'user_list_id'      => $userListId,
            'access_token'      => $this->fetchAccessToken((string) Arr::get($settings, 'refresh_token')),
        ];
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    private function fetchAccessToken(string $refreshToken): string
    {
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

    private function client(array $config): PendingRequest
    {
        return Http::withToken($config['access_token'])
            ->baseUrl(self::DATA_MANAGER_BASE_URL);
    }

    /**
     * @param array<int, array<string, mixed>> $audienceMembers
     * @throws ConnectionException
     * @throws Exception
     */
    private function ingestAudienceMembers(PendingRequest $client, array $config, array $audienceMembers): string
    {
        $destination = [
            'operatingAccount' => [
                'accountType' => 'GOOGLE_ADS',
                'accountId'   => $config['customer_id'],
            ],
            'productDestinationId' => $config['user_list_id'],
        ];

        if ($config['login_customer_id'] !== '' && $config['login_customer_id'] !== $config['customer_id']) {
            $destination['loginAccount'] = [
                'accountType' => 'GOOGLE_ADS',
                'accountId'   => $config['login_customer_id'],
            ];
        }

        $response = $client->post('audienceMembers:ingest', [
            'destinations'    => [$destination],
            'audienceMembers' => $audienceMembers,
            'encoding'        => 'HEX',
            'termsOfService'  => [
                'customerMatchTermsOfServiceStatus' => 'ACCEPTED',
            ],
        ]);

        if ($response->failed()) {
            throw new Exception('Failed to ingest Google Ads audience members: ' . $response->body());
        }

        return (string) $response->json('requestId', '');
    }

    /**
     * Build hashed user identifiers for a customer, following Google Customer Match
     * normalization rules (lowercase and trim email, E.164 phone, SHA-256 hex).
     *
     * @return array<int, array<string, string>>
     */
    private function buildUserIdentifiers(Customer $customer): array
    {
        $identifiers = [];

        if ($hashedEmail = $this->hashEmail($customer->email)) {
            $identifiers[] = ['emailAddress' => $hashedEmail];
        }

        if ($hashedPhone = $this->hashPhone($customer->phone)) {
            $identifiers[] = ['phoneNumber' => $hashedPhone];
        }

        return $identifiers;
    }

    private function hashEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $this->sha256($email);
    }

    private function hashPhone(?string $phone): ?string
    {
        $phone = preg_replace('/[^0-9+]/', '', (string) $phone);

        if ($phone === '' || !str_starts_with($phone, '+')) {
            return null;
        }

        return $this->sha256($phone);
    }

    private function sha256(string $value): string
    {
        return hash('sha256', $value);
    }

    private function onlyDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }

    /**
     * @throws Exception
     */
    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();

        $result = $this->handle($shop, (int) $command->option('chunk'));

        $command->info("Uploaded {$result['uploaded']} customers to the Google Ads Customer Match list across " . count($result['request_ids']) . ' request(s).');

        return 0;
    }
}
