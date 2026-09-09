<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Sep 2026 17:05:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\FulfilmentService;

use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Shopify only sells stock held at locations inside a shipping profile, and a fulfilment
 * service location is never added to one by itself: the store shows our catalogue as sold
 * out until the merchant adds it by hand. The new location joins every profile the previous
 * aiku location was in, or the store's default profile on a first install.
 */
class AddShopifyLocationToDeliveryProfiles
{
    use AsAction;
    use WithShopifyApi;

    public string $commandSignature = 'shopify:add-location-to-shipping {customerSalesChannel?} {--dry-run}';

    /**
     * @return array{0: bool, 1: string}
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, bool $dryRun = false): array
    {
        $shopifyUser = $customerSalesChannel->user;
        if (!$shopifyUser instanceof ShopifyUser || !$shopifyUser->shopify_location_id) {
            return [false, 'No Shopify location on this channel'];
        }

        $query = <<<'QUERY'
            query getDeliveryProfiles {
                deliveryProfiles(first: 20) {
                    nodes {
                        id
                        name
                        default
                        profileLocationGroups {
                            locationGroup {
                                id
                                locations(first: 50) {
                                    nodes {
                                        id
                                        name
                                    }
                                }
                            }
                        }
                    }
                }
            }
        QUERY;

        [$status, $res] = $this->doPost($shopifyUser, $query, []);
        if (!$status) {
            return [false, $res];
        }

        $profiles = $res['body']->toArray()['data']['deliveryProfiles']['nodes'] ?? [];
        $groups   = $this->groupsToJoin($profiles, $shopifyUser->shopify_location_id);

        if ($groups === []) {
            return [true, 'Already in shipping profiles'];
        }

        if ($dryRun) {
            return [true, 'Would join '.count($groups).' shipping profile group(s)'];
        }

        $mutation = <<<'MUTATION'
            mutation deliveryProfileUpdate($id: ID!, $profile: DeliveryProfileInput!) {
                deliveryProfileUpdate(id: $id, profile: $profile) {
                    userErrors {
                        field
                        message
                    }
                }
            }
        MUTATION;

        foreach ($groups as $group) {
            [$status, $res] = $this->doPost($shopifyUser, $mutation, [
                'id'      => $group['profileId'],
                'profile' => [
                    'locationGroupsToUpdate' => [[
                        'id'             => $group['groupId'],
                        'locationsToAdd' => [$shopifyUser->shopify_location_id],
                    ]],
                ],
            ]);
            if (!$status) {
                return [false, $res];
            }
            $userErrors = $res['body']->toArray()['data']['deliveryProfileUpdate']['userErrors'] ?? [];
            if ($userErrors !== []) {
                return [false, 'User errors: '.json_encode($userErrors)];
            }
        }

        return [true, 'Joined '.count($groups).' shipping profile group(s)'];
    }

    /**
     * @param  list<array<string, mixed>>  $profiles
     * @return list<array{profileId: string, groupId: string}>
     */
    public static function groupsToJoin(array $profiles, string $locationId): array
    {
        $withPreviousAikuLocation = [];
        $defaultGroup             = null;

        foreach ($profiles as $profile) {
            foreach ($profile['profileLocationGroups'] ?? [] as $profileLocationGroup) {
                $group     = $profileLocationGroup['locationGroup'];
                $locations = $group['locations']['nodes'] ?? [];
                $candidate = ['profileId' => $profile['id'], 'groupId' => $group['id']];

                if (in_array($locationId, array_column($locations, 'id'), true)) {
                    return [];
                }

                if (array_filter($locations, fn (array $location) => str_starts_with($location['name'], 'aiku-dse'))) {
                    $withPreviousAikuLocation[] = $candidate;
                }

                if (($profile['default'] ?? false) && $defaultGroup === null) {
                    $defaultGroup = $candidate;
                }
            }
        }

        if ($withPreviousAikuLocation !== []) {
            return $withPreviousAikuLocation;
        }

        return $defaultGroup ? [$defaultGroup] : [];
    }

    public function asCommand(Command $command): int
    {
        $query = CustomerSalesChannel::where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->where('platform_status', true)
            ->whereIn('platform_id', Platform::where('type', PlatformTypeEnum::SHOPIFY)->pluck('id'));

        if ($command->argument('customerSalesChannel')) {
            $query->where('slug', $command->argument('customerSalesChannel'));
        }

        $counts = ['joined' => 0, 'already' => 0, 'waiting approval' => 0, 'unreachable' => 0];

        foreach ($query->get() as $customerSalesChannel) {
            [$status, $message] = $this->handle($customerSalesChannel, (bool)$command->option('dry-run'));
            $command->line(($status ? 'ok   ' : 'FAIL ').$customerSalesChannel->slug.' '.$message);

            $counts[match (true) {
                $status && str_starts_with($message, 'Already') => 'already',
                $status                                         => 'joined',
                str_contains($message, 'ACCESS_DENIED')          => 'waiting approval',
                default                                         => 'unreachable',
            }]++;
        }

        $command->info(collect($counts)->map(fn (int $count, string $label) => "$label: $count")->implode(', '));

        return 0;
    }
}
