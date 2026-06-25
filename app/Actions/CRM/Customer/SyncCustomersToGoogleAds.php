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

    private const string DEFAULT_API_VERSION = 'v18';

    /**
     * Upload the shop's customers' hashed identifiers to a Google Ads Customer Match user list
     * through an OfflineUserDataJob, using the credentials stored in the shop settings.
     *
     * @return array{uploaded: int, job: string|null}
     * @throws Exception
     */
    public function handle(Shop $shop, int $chunkSize = 10000): array
    {
        $config = $this->resolveConfig($shop);

        $client = $this->client($config);

        $jobResourceName = $this->createOfflineUserDataJob($client, $config);

        $uploaded = 0;

        $shop->customers()
            ->where(function ($query) {
                $query->whereNotNull('email')->orWhereNotNull('phone');
            })
            ->select(['id', 'email', 'phone'])
            ->chunkById($chunkSize, function ($customers) use ($client, $jobResourceName, &$uploaded) {
                $operations = [];

                /** @var Customer $customer */
                foreach ($customers as $customer) {
                    $userIdentifiers = $this->buildUserIdentifiers($customer);

                    if (empty($userIdentifiers)) {
                        continue;
                    }

                    $operations[] = [
                        'create' => [
                            'userIdentifiers' => $userIdentifiers,
                        ],
                    ];
                }

                if (empty($operations)) {
                    return;
                }

                $this->addOperations($client, $jobResourceName, $operations);

                $uploaded += count($operations);
            });

        $this->runOfflineUserDataJob($client, $jobResourceName);

        return [
            'uploaded' => $uploaded,
            'job'      => $jobResourceName,
        ];
    }

    /**
     * @return array{customer_id: string, login_customer_id: string, developer_token: string, user_list: string, access_token: string, api_version: string}
     * @throws Exception
     */
    private function resolveConfig(Shop $shop): array
    {
        $settings = Arr::get($shop->settings, 'google_ads', []);

        $customerId      = $this->onlyDigits((string) Arr::get($settings, 'customer_id'));
        $loginCustomerId = $this->onlyDigits((string) Arr::get($settings, 'login_customer_id')) ?: $customerId;
        $developerToken  = (string) Arr::get($settings, 'developer_token');
        $userListId      = $this->onlyDigits((string) Arr::get($settings, 'user_list_id'));
        $apiVersion      = (string) Arr::get($settings, 'api_version') ?: self::DEFAULT_API_VERSION;

        if ($customerId === '' || $developerToken === '' || $userListId === '') {
            throw new Exception("Google Ads is not configured for shop $shop->slug: customer_id, developer_token and user_list_id are required.");
        }

        return [
            'customer_id'       => $customerId,
            'login_customer_id' => $loginCustomerId,
            'developer_token'   => $developerToken,
            'user_list'         => "customers/$customerId/userLists/$userListId",
            'access_token'      => $this->fetchAccessToken($settings),
            'api_version'       => $apiVersion,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @throws ConnectionException
     * @throws Exception
     */
    private function fetchAccessToken(array $settings): string
    {
        $response = Http::asForm()->post(self::OAUTH_TOKEN_URL, [
            'client_id'     => Arr::get($settings, 'client_id'),
            'client_secret' => Arr::get($settings, 'client_secret'),
            'refresh_token' => Arr::get($settings, 'refresh_token'),
            'grant_type'    => 'refresh_token',
        ]);

        if ($response->failed() || !$response->json('access_token')) {
            throw new Exception('Failed to obtain Google Ads access token: ' . $response->body());
        }

        return $response->json('access_token');
    }

    private function client(array $config): PendingRequest
    {
        return Http::withHeaders([
            'developer-token'   => $config['developer_token'],
            'login-customer-id' => $config['login_customer_id'],
        ])
            ->withToken($config['access_token'])
            ->baseUrl("https://googleads.googleapis.com/{$config['api_version']}");
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    private function createOfflineUserDataJob(PendingRequest $client, array $config): string
    {
        $response = $client->post("customers/{$config['customer_id']}/offlineUserDataJobs:create", [
            'job' => [
                'type'                          => 'CUSTOMER_MATCH_USER_LIST',
                'customerMatchUserListMetadata' => [
                    'userList' => $config['user_list'],
                ],
            ],
        ]);

        if ($response->failed() || !$response->json('resourceName')) {
            throw new Exception('Failed to create Google Ads offline user data job: ' . $response->body());
        }

        return $response->json('resourceName');
    }

    /**
     * @param array<int, array<string, mixed>> $operations
     * @throws ConnectionException
     * @throws Exception
     */
    private function addOperations(PendingRequest $client, string $jobResourceName, array $operations): void
    {
        $response = $client->post("$jobResourceName:addOperations", [
            'operations'           => $operations,
            'enablePartialFailure' => true,
        ]);

        if ($response->failed()) {
            throw new Exception('Failed to add operations to Google Ads job: ' . $response->body());
        }
    }

    /**
     * @throws ConnectionException
     * @throws Exception
     */
    private function runOfflineUserDataJob(PendingRequest $client, string $jobResourceName): void
    {
        $response = $client->post("$jobResourceName:run");

        if ($response->failed()) {
            throw new Exception('Failed to run Google Ads offline user data job: ' . $response->body());
        }
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
            $identifiers[] = ['hashedEmail' => $hashedEmail];
        }

        if ($hashedPhone = $this->hashPhone($customer->phone)) {
            $identifiers[] = ['hashedPhoneNumber' => $hashedPhone];
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

        $command->info("Uploaded {$result['uploaded']} customers to Google Ads job {$result['job']}.");

        return 0;
    }
}
