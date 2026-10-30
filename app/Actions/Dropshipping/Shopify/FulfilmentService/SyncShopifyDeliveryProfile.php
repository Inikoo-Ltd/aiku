<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 30 Oct 2026 12:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify\FulfilmentService;

use App\Actions\Dropshipping\Shopify\Product\WithShopifyStoreCurrency;
use App\Actions\Dropshipping\Shopify\WithShopifyApi;
use App\Actions\Ordering\Order\CalculateOrderShipping;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Billables\ShippingZone;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Shopify deprecates app writes to the merchant's own delivery profiles (market-driven shipping), so the
 * products we fulfil ship under a profile the app owns: the aiku- location only, our products, and the
 * rates of the shop's current shipping zone schema, in the store's currency.
 *
 * Shopify zones stop at country level. A zone that covers part of a country by postcode is left out and
 * that country gets the price of the zone covering it whole, as CalculateOrderShipping would charge any
 * other postcode there. Shopify has no "to be confirmed" rate and a missing one blocks checkout, so a band
 * priced TBC keeps the zone's last price and a rest of the world priced TBC takes the dearest zone's
 * prices. What we bill the merchant is unchanged: the order is still priced by our own schema.
 * Each variant belongs to one profile, so the merchant's own locations do not ship these products.
 */
class SyncShopifyDeliveryProfile
{
    use AsAction;
    use WithShopifyApi;
    use WithShopifyStoreCurrency;

    public const string PROFILE_NAME = 'AW Connect shipping';

    public const int VARIANTS_PER_CALL = 250;

    public string $commandSignature = 'shopify:sync-delivery-profile {customerSalesChannel?} {--dry-run}';

    /**
     * @return array{0: bool, 1: string}
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, bool $dryRun = false): array
    {
        $shopifyUser = $customerSalesChannel->user;
        if (!$shopifyUser instanceof ShopifyUser || !$shopifyUser->shopify_location_id) {
            return [false, 'No Shopify location on this channel'];
        }

        $shop               = $customerSalesChannel->shop;
        $shippingZoneSchema = $shop->currentShippingZoneSchema;
        if (!$shippingZoneSchema) {
            return [false, 'The shop has no current shipping zone schema'];
        }

        $storeCurrencyCode = Arr::get($shopifyUser->data, 'shop.currencyCode') ?: $shop->currency->code;
        $priceError        = null;
        $zones             = self::zonesToCreate(
            $shippingZoneSchema->shippingZones()->where('status', true)->orderBy('position', 'desc')->get()->all(),
            $storeCurrencyCode,
            function ($amount) use ($shopifyUser, $shop, &$priceError) {
                [$price, $error] = $this->priceInShopifyStoreCurrency($shopifyUser, $shop->currency, $amount);
                $priceError ??= $error;

                return $price;
            }
        );

        if ($priceError) {
            return [false, $priceError];
        }
        if ($zones === []) {
            return [false, 'The current shipping zone schema has no zone with a price'];
        }

        $ratesFingerprint = md5(json_encode($zones));
        $variantIds       = $customerSalesChannel->portfolios()
            ->where('status', true)
            ->whereNotNull('platform_product_variant_id')
            ->pluck('platform_product_variant_id')
            ->unique()
            ->values()
            ->all();

        $profileId = Arr::get($shopifyUser->data, 'delivery_profile.id');
        $profile   = null;
        if ($profileId) {
            [$status, $profile] = $this->readProfile($shopifyUser, $profileId);
            if (!$status) {
                return [false, $profile];
            }
        }

        if (!$profile) {
            if ($dryRun) {
                return [true, 'Would create the delivery profile with '.count($zones).' zone(s) and '.count($variantIds).' variant(s)'];
            }

            return $this->createProfile($shopifyUser, $zones, $variantIds, $ratesFingerprint);
        }

        $toAssociate    = array_values(array_diff($variantIds, $profile['variantIds']));
        $toDisassociate = array_values(array_diff($profile['variantIds'], $variantIds));
        $ratesChanged   = Arr::get($shopifyUser->data, 'delivery_profile.rates_fingerprint') !== $ratesFingerprint;

        if ($dryRun) {
            return [true, 'Would '.($ratesChanged ? 'replace the rates, ' : '').'add '.count($toAssociate).' and remove '.count($toDisassociate).' variant(s)'];
        }

        if ($ratesChanged) {
            [$status, $message] = $this->updateProfile($shopifyUser, $profile['id'], [
                'zonesToDelete'          => $profile['zoneIds'],
                'locationGroupsToUpdate' => [[
                    'id'            => $profile['locationGroupId'],
                    'zonesToCreate' => $zones,
                ]],
            ]);
            if (!$status) {
                return [false, $message];
            }
        }

        [$status, $message] = $this->moveVariants($shopifyUser, $profile['id'], $toAssociate, $toDisassociate);
        if (!$status) {
            return [false, $message];
        }

        $this->remember($shopifyUser, $profile['id'], $ratesFingerprint);

        return [true, ($ratesChanged ? 'Rates replaced, ' : '').'added '.count($toAssociate).' and removed '.count($toDisassociate).' variant(s)'];
    }

    /**
     * @param  list<ShippingZone>  $shippingZones  live zones, highest position first
     * @param  callable(mixed): mixed  $price  turns an amount in the shop's currency into the store's
     * @return list<array<string, mixed>>
     */
    public static function zonesToCreate(array $shippingZones, string $currencyCode, callable $price): array
    {
        usort($shippingZones, fn (ShippingZone $a, ShippingZone $b) => (empty($a->territories) ? 1 : 0) <=> (empty($b->territories) ? 1 : 0));

        $priceDataByZone = array_map(fn (ShippingZone $shippingZone) => CalculateOrderShipping::make()->getZonePriceDataForShipper($shippingZone, null), $shippingZones);
        $dearestPriceData = collect($priceDataByZone)
            ->filter(fn (array $priceData) => self::highestPrice($priceData) !== null)
            ->sortByDesc(fn (array $priceData) => self::highestPrice($priceData))
            ->first();

        $takenCountries = [];
        $zones          = [];

        foreach ($shippingZones as $index => $shippingZone) {
            $priceData = $priceDataByZone[$index];
            if (empty($shippingZone->territories) && self::highestPrice($priceData) === null && $dearestPriceData) {
                $priceData = $dearestPriceData;
            }

            $methods = self::methodDefinitions($priceData, $currencyCode, $price);
            if ($methods === []) {
                continue;
            }

            if (empty($shippingZone->territories)) {
                $countries = [['restOfWorld' => true]];
            } else {
                $countries = [];
                foreach ($shippingZone->territories as $territory) {
                    $countryCode = Arr::get($territory, 'country_code');
                    if (!$countryCode || Arr::get($territory, 'included_postal_codes') || isset($takenCountries[$countryCode])) {
                        continue;
                    }
                    $takenCountries[$countryCode] = true;
                    $countries[]                  = ['code' => $countryCode, 'includeAllProvinces' => true];
                }
            }

            if ($countries === []) {
                continue;
            }

            $zones[] = [
                'name'                      => $shippingZone->name,
                'countries'                 => $countries,
                'methodDefinitionsToCreate' => $methods,
            ];
        }

        return $zones;
    }

    /**
     * Our bands include both ends and the lower band wins a shared boundary, so a band that starts where
     * the previous one ends starts just above it.
     *
     * @return list<array<string, mixed>>
     */
    private static function methodDefinitions(array $priceData, string $currencyCode, callable $price): array
    {
        $conditionKey = match (Arr::get($priceData, 'type')) {
            'Step Order Estimated Weight' => 'weightConditionsToCreate',
            'Step Order Items Net Amount' => 'priceConditionsToCreate',
            default                       => null,
        };
        if (!$conditionKey) {
            return [];
        }

        $criteria = fn (float $value) => $conditionKey === 'weightConditionsToCreate'
            ? ['value' => $value, 'unit' => 'KILOGRAMS']
            : ['amount' => $price($value), 'currencyCode' => $currencyCode];
        $nudge = $conditionKey === 'weightConditionsToCreate' ? 0.001 : 0.01;

        $methods     = [];
        $previousTo  = null;
        $lastPrice   = null;
        foreach (Arr::get($priceData, 'steps', []) as $step) {
            $from      = (float) $step['from'];
            $to        = $step['to'];
            $stepPrice = is_numeric($step['price'] ?? null) ? $step['price'] : $lastPrice;

            if ($stepPrice !== null) {
                $conditions = [];
                if ($from > 0) {
                    $conditions[] = ['criteria' => $criteria($previousTo !== null && (float) $previousTo === $from ? $from + $nudge : $from), 'operator' => 'GREATER_THAN_OR_EQUAL_TO'];
                }
                if ($to !== 'INF') {
                    $conditions[] = ['criteria' => $criteria((float) $to), 'operator' => 'LESS_THAN_OR_EQUAL_TO'];
                }

                $methods[] = array_filter([
                    'name'           => 'Standard',
                    'active'         => true,
                    'rateDefinition' => ['price' => ['amount' => $price($stepPrice), 'currencyCode' => $currencyCode]],
                    $conditionKey    => $conditions,
                ], fn ($value) => $value !== []);
                $lastPrice = $stepPrice;
            }

            $previousTo = $to;
        }

        return $methods;
    }

    private static function highestPrice(array $priceData): ?float
    {
        $prices = array_filter(Arr::pluck(Arr::get($priceData, 'steps', []), 'price'), 'is_numeric');

        return $prices === [] ? null : (float) max($prices);
    }

    /**
     * @return array{0: bool, 1: array{id: string, locationGroupId: string, zoneIds: list<string>, variantIds: list<string>}|null|string}
     *         null when the merchant deleted the profile
     */
    private function readProfile(ShopifyUser $shopifyUser, string $profileId): array
    {
        $query = <<<'QUERY'
            query getAppDeliveryProfile($id: ID!, $after: String) {
                deliveryProfile(id: $id) {
                    id
                    profileLocationGroups {
                        locationGroup {
                            id
                        }
                        locationGroupZones(first: 250) {
                            nodes {
                                zone {
                                    id
                                }
                            }
                        }
                    }
                    profileItems(first: 250, after: $after) {
                        pageInfo {
                            hasNextPage
                            endCursor
                        }
                        nodes {
                            variants(first: 100) {
                                nodes {
                                    id
                                }
                            }
                        }
                    }
                }
            }
        QUERY;

        $profile = null;
        $after   = null;
        do {
            [$status, $res] = $this->doPost($shopifyUser, $query, ['id' => $profileId, 'after' => $after]);
            if (!$status) {
                return [false, $res];
            }

            $node = $res['body']->toArray()['data']['deliveryProfile'] ?? null;
            if (!$node) {
                return [true, null];
            }

            $profile ??= [
                'id'              => $node['id'],
                'locationGroupId' => Arr::get($node, 'profileLocationGroups.0.locationGroup.id'),
                'zoneIds'         => Arr::pluck(Arr::get($node, 'profileLocationGroups.0.locationGroupZones.nodes', []), 'zone.id'),
                'variantIds'      => [],
            ];

            foreach (Arr::get($node, 'profileItems.nodes', []) as $item) {
                array_push($profile['variantIds'], ...Arr::pluck(Arr::get($item, 'variants.nodes', []), 'id'));
            }

            $after = Arr::get($node, 'profileItems.pageInfo.hasNextPage') ? Arr::get($node, 'profileItems.pageInfo.endCursor') : null;
        } while ($after);

        return [true, $profile];
    }

    /**
     * @param  list<array<string, mixed>>  $zones
     * @param  list<string>  $variantIds
     * @return array{0: bool, 1: string}
     */
    private function createProfile(ShopifyUser $shopifyUser, array $zones, array $variantIds, string $ratesFingerprint): array
    {
        $mutation = <<<'MUTATION'
            mutation deliveryProfileCreate($profile: DeliveryProfileInput!) {
                deliveryProfileCreate(profile: $profile) {
                    profile {
                        id
                    }
                    userErrors {
                        field
                        message
                    }
                }
            }
        MUTATION;

        $firstVariants = array_slice($variantIds, 0, self::VARIANTS_PER_CALL);

        [$status, $res] = $this->doPost($shopifyUser, $mutation, [
            'profile' => [
                'name'                   => self::PROFILE_NAME,
                'locationGroupsToCreate' => [[
                    'locations'     => [$shopifyUser->shopify_location_id],
                    'zonesToCreate' => $zones,
                ]],
                'variantsToAssociate'    => $firstVariants,
            ],
        ]);
        if (!$status) {
            return [false, $res];
        }

        $result     = $res['body']->toArray()['data']['deliveryProfileCreate'] ?? [];
        $userErrors = $result['userErrors'] ?? [];
        $profileId  = Arr::get($result, 'profile.id');
        if ($userErrors !== [] || !$profileId) {
            return [false, 'User errors: '.json_encode($userErrors)];
        }

        $this->remember($shopifyUser, $profileId, $ratesFingerprint);

        [$status, $message] = $this->moveVariants($shopifyUser, $profileId, array_slice($variantIds, self::VARIANTS_PER_CALL), []);
        if (!$status) {
            return [false, $message];
        }

        return [true, 'Created the delivery profile with '.count($zones).' zone(s) and '.count($variantIds).' variant(s)'];
    }

    /**
     * @param  list<string>  $toAssociate
     * @param  list<string>  $toDisassociate
     * @return array{0: bool, 1: string|null}
     */
    private function moveVariants(ShopifyUser $shopifyUser, string $profileId, array $toAssociate, array $toDisassociate): array
    {
        foreach (array_chunk($toAssociate, self::VARIANTS_PER_CALL) as $chunk) {
            [$status, $message] = $this->updateProfile($shopifyUser, $profileId, ['variantsToAssociate' => $chunk]);
            if (!$status) {
                return [false, $message];
            }
        }

        foreach (array_chunk($toDisassociate, self::VARIANTS_PER_CALL) as $chunk) {
            [$status, $message] = $this->updateProfile($shopifyUser, $profileId, ['variantsToDisassociate' => $chunk]);
            if (!$status) {
                return [false, $message];
            }
        }

        return [true, null];
    }

    /**
     * @return array{0: bool, 1: string|null}
     */
    private function updateProfile(ShopifyUser $shopifyUser, string $profileId, array $profile): array
    {
        $mutation = <<<'MUTATION'
            mutation appDeliveryProfileUpdate($id: ID!, $profile: DeliveryProfileInput!) {
                deliveryProfileUpdate(id: $id, profile: $profile) {
                    userErrors {
                        field
                        message
                    }
                }
            }
        MUTATION;

        [$status, $res] = $this->doPost($shopifyUser, $mutation, ['id' => $profileId, 'profile' => $profile]);
        if (!$status) {
            return [false, $res];
        }

        $userErrors = $res['body']->toArray()['data']['deliveryProfileUpdate']['userErrors'] ?? [];
        if ($userErrors !== []) {
            return [false, 'User errors: '.json_encode($userErrors)];
        }

        return [true, null];
    }

    private function remember(ShopifyUser $shopifyUser, string $profileId, string $ratesFingerprint): void
    {
        $shopifyUser->update([
            'data' => [
                ...($shopifyUser->data ?? []),
                'delivery_profile' => [
                    'id'                => $profileId,
                    'rates_fingerprint' => $ratesFingerprint,
                    'synced_at'         => now()->toIso8601String(),
                ],
            ],
        ]);
    }

    public function asCommand(Command $command): int
    {
        $query = CustomerSalesChannel::where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->where('platform_status', true)
            ->whereIn('platform_id', Platform::where('type', PlatformTypeEnum::SHOPIFY)->pluck('id'));

        if ($command->argument('customerSalesChannel')) {
            $query->where('slug', $command->argument('customerSalesChannel'));
        }

        $failed = 0;
        foreach ($query->get() as $customerSalesChannel) {
            [$status, $message] = $this->handle($customerSalesChannel, (bool) $command->option('dry-run'));
            $command->line(($status ? 'ok   ' : 'FAIL ').$customerSalesChannel->slug.' '.$message);
            $failed += $status ? 0 : 1;
        }

        return $failed ? 1 : 0;
    }
}
