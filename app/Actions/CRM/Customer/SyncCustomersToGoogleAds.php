<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Actions\CRM\Customer\GoogleAds\Traits\WithGoogleAdsAccessToken;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncCustomersToGoogleAds
{
    use AsAction;
    use WithGoogleAdsAccessToken;

    public string $jobQueue = 'analytics';
    public int $jobTimeout = 600;
    public int $jobTries = 1;

    public string $commandSignature = 'sync:customers-to-google-ads {shop? : The shop slug} {--all : Sync every shop with a Google Ads refresh token} {--chunk=10000 : Customers per addOperations request}';

    private const string DATA_MANAGER_BASE_URL = 'https://datamanager.googleapis.com/v1';

    private const int MAX_MEMBERS_PER_REQUEST = 10000;

    /**
     * Upload the shop's marketing-eligible customers' hashed identifiers to a Google Ads
     * Customer Match user list, and remove customers who are no longer eligible.
     *
     * @return array{uploaded: int, removed: int, request_ids: array<int, string>}
     * @throws Exception
     */
    public function handle(Shop $shop, int $chunkSize = self::MAX_MEMBERS_PER_REQUEST): array
    {
        $config = $this->resolveConfig($shop);

        $client = $this->client($config);

        $chunkSize = min($chunkSize, self::MAX_MEMBERS_PER_REQUEST);

        $audienceScope = Arr::get($shop->settings, 'google_ads.audience_scope', 'subscribed');

        $eligibleComms = function ($query) use ($audienceScope) {
            $query->where('is_suspended', false);

            if ($audienceScope !== 'all') {
                $query->where('is_subscribed_to_marketing', true);
            }
        };

        $uploadResult = $this->syncAudienceMembers(
            $shop->customers()->whereHas('comms', $eligibleComms),
            true,
            $client,
            $config,
            $chunkSize
        );

        $removeResult = $this->syncAudienceMembers(
            $shop->customers()->whereDoesntHave('comms', $eligibleComms),
            false,
            $client,
            $config,
            $chunkSize
        );

        $result = [
            'uploaded'    => $uploadResult['count'],
            'removed'     => $removeResult['count'],
            'request_ids' => array_merge($uploadResult['request_ids'], $removeResult['request_ids']),
        ];

        $settings = $shop->settings;
        Arr::set($settings, 'google_ads.last_sync', [
            'at'       => now()->toIso8601String(),
            'uploaded' => $result['uploaded'],
            'removed'  => $result['removed'],
        ]);
        $shop->update(['settings' => $settings]);

        return $result;
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
            'access_token'      => $this->googleAdsAccessToken($shop),
        ];
    }

    private function client(array $config): PendingRequest
    {
        return Http::withToken($config['access_token'])
            ->baseUrl(self::DATA_MANAGER_BASE_URL);
    }

    /**
     * @return array{count: int, request_ids: array<int, string>}
     * @throws ConnectionException
     * @throws Exception
     */
    private function syncAudienceMembers(HasMany $query, bool $eligible, PendingRequest $client, array $config, int $chunkSize): array
    {
        $count      = 0;
        $requestIds = [];

        $query->where(function ($query) {
            $query->whereNotNull('email')->orWhereNotNull('phone');
        })
            ->select(['id', 'email', 'phone'])
            ->chunkById($chunkSize, function ($customers) use ($eligible, $client, $config, &$count, &$requestIds) {
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

                $requestIds[] = $eligible
                    ? $this->ingestAudienceMembers($client, $config, $audienceMembers)
                    : $this->removeAudienceMembers($client, $config, $audienceMembers);

                $count += count($audienceMembers);
            });

        return [
            'count'       => $count,
            'request_ids' => $requestIds,
        ];
    }

    /**
     * @return array{operatingAccount: array<string, string>, productDestinationId: string, loginAccount?: array<string, string>}
     */
    private function buildDestination(array $config): array
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

        return $destination;
    }

    /**
     * @param array<int, array<string, mixed>> $audienceMembers
     * @throws ConnectionException
     * @throws Exception
     */
    private function ingestAudienceMembers(PendingRequest $client, array $config, array $audienceMembers): string
    {
        $response = $client->post('audienceMembers:ingest', [
            'destinations'    => [$this->buildDestination($config)],
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
     * @param array<int, array<string, mixed>> $audienceMembers
     * @throws ConnectionException
     * @throws Exception
     */
    private function removeAudienceMembers(PendingRequest $client, array $config, array $audienceMembers): string
    {
        $response = $client->post('audienceMembers:remove', [
            'destinations'    => [$this->buildDestination($config)],
            'audienceMembers' => $audienceMembers,
            'encoding'        => 'HEX',
        ]);

        if ($response->failed()) {
            throw new Exception('Failed to remove Google Ads audience members: ' . $response->body());
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
        if ($command->option('all')) {
            Shop::query()
                ->whereRaw("settings->'google_ads'->>'refresh_token' is not null")
                ->each(function (Shop $shop) use ($command) {
                    try {
                        $result = $this->handle($shop, (int) $command->option('chunk'));
                        $command->info("{$shop->slug}: uploaded {$result['uploaded']}, removed {$result['removed']} customers across " . count($result['request_ids']) . ' request(s).');
                    } catch (Exception $exception) {
                        report($exception);
                        $command->error("{$shop->slug}: {$exception->getMessage()}");
                    }
                });

            return 0;
        }

        $shopSlug = $command->argument('shop');

        if (blank($shopSlug)) {
            $command->error('Provide a shop slug or use --all.');

            return 1;
        }

        $shop = Shop::where('slug', $shopSlug)->firstOrFail();

        $result = $this->handle($shop, (int) $command->option('chunk'));

        $command->info("Uploaded {$result['uploaded']} and removed {$result['removed']} customers from the Google Ads Customer Match list across " . count($result['request_ids']) . ' request(s).');

        return 0;
    }
}
