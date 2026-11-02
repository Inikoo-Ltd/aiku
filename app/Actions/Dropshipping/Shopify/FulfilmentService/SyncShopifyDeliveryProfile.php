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
 *
 * A store still on shipping profiles whose profiles already ship from this channel's aiku- location is left
 * alone: its customers keep paying the merchant's rates until Shopify moves it to market-driven shipping,
 * and the profile is created on the first sync after that. Once created, a profile is always kept in sync.
 */
class SyncShopifyDeliveryProfile
{
    use AsAction;
    use WithShopifyApi;
    use WithShopifyStoreCurrency;

    public const string PROFILE_NAME = 'AW Connect shipping';

    public const int VARIANTS_PER_CALL = 250;

    /**
     * A channel is checked again once one of its portfolios changes after its last check, failed or not, so a
     * store whose token is gone is not called every run. A deleted portfolio leaves no row behind: its variant
     * leaves the profile at the nightly full run, its stock at the aiku- location is already deactivated.
     */
    private const string LINKS_CHANGED_SINCE_CHECKED = <<<'SQL'
        exists (
            select 1
            from portfolios
            join shopify_users on shopify_users.id = customer_sales_channels.platform_user_id
            where portfolios.customer_sales_channel_id = customer_sales_channels.id
              and portfolios.updated_at > coalesce((shopify_users.data->'delivery_profile'->>'checked_at')::timestamptz, '-infinity'::timestamptz)
        )
        SQL;

    public string $commandSignature = 'shopify:sync-delivery-profile {customerSalesChannel?} {--dry-run} {--changed : only channels with a product link changed since they were last checked}';

    /**
     * @return array{0: bool, 1: string}
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, bool $dryRun = false): array
    {
        $shopifyUser = $customerSalesChannel->user;
        if (!$shopifyUser instanceof ShopifyUser || !$shopifyUser->shopify_location_id) {
            return [false, 'No Shopify location on this channel'];
        }

        if (!Arr::get($shopifyUser->data, 'delivery_profile.id')) {
            [$status, $leftAlone] = $this->isLeftAlone($shopifyUser);
            if (!$status) {
                return [false, $leftAlone];
            }
            if ($leftAlone) {
                return [true, 'On shipping profiles that already ship from the aiku- location, left alone'];
            }
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

            return $this->createProfile($shopifyUser, self::profileName($customerSalesChannel), $zones, $variantIds, $ratesFingerprint);
        }

        $toAssociate    = array_values(array_diff($variantIds, $profile['variantIds']));
        $toDissociate = array_values(array_diff($profile['variantIds'], $variantIds));
        $ratesChanged   = Arr::get($shopifyUser->data, 'delivery_profile.rates_fingerprint') !== $ratesFingerprint;
        $renamed        = $profile['name'] !== self::profileName($customerSalesChannel);

        if ($dryRun) {
            return [true, 'Would '.($renamed ? 'rename the profile, ' : '').($ratesChanged ? 'replace the rates, ' : '').'add '.count($toAssociate).' and remove '.count($toDissociate).' variant(s)'];
        }

        if ($ratesChanged || $renamed) {
            $changes = $renamed ? ['name' => self::profileName($customerSalesChannel)] : [];
            if ($ratesChanged) {
                $changes['zonesToDelete']          = $profile['zoneIds'];
                $changes['locationGroupsToUpdate'] = [[
                    'id'            => $profile['locationGroupId'],
                    'zonesToCreate' => $zones,
                ]];
            }

            [$status, $message] = $this->updateProfile($shopifyUser, $profile['id'], $changes);
            if (!$status) {
                return [false, $message];
            }
        }

        [$status, $message] = $this->moveVariants($shopifyUser, $profile['id'], $toAssociate, $toDissociate);
        if (!$status) {
            return [false, $message];
        }

        $this->remember($shopifyUser, $profile['id'], $ratesFingerprint);

        return [true, ($renamed ? 'Renamed, ' : '').($ratesChanged ? 'Rates replaced, ' : '').'added '.count($toAssociate).' and removed '.count($toDissociate).' variant(s)'];
    }

    /**
     * Only reads: the merchant's profiles are never written to. A store whose market-driven flag can not be
     * read (an API version before 2026-07) is treated as on shipping profiles.
     *
     * @return array{0: bool, 1: bool|string}
     */
    private function isLeftAlone(ShopifyUser $shopifyUser): array
    {
        [$status, $res] = $this->doPost($shopifyUser, 'query shopFeatures { shop { features { marketDrivenShipping } } }', [], '2026-07');
        if ($status && ($res['body']->toArray()['data']['shop']['features']['marketDrivenShipping'] ?? false)) {
            return [true, false];
        }

        $query = <<<'QUERY'
            query merchantDeliveryProfileLocations {
                deliveryProfiles(first: 50) {
                    nodes {
                        profileLocationGroups {
                            locationGroup {
                                locations(first: 250, includeLegacy: true) {
                                    nodes {
                                        id
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

        $locationIds = Arr::pluck(
            Arr::flatten(Arr::pluck($res['body']->toArray()['data']['deliveryProfiles']['nodes'] ?? [], 'profileLocationGroups'), 1),
            'locationGroup.locations.nodes'
        );

        return [true, in_array($shopifyUser->shopify_location_id, Arr::pluck(Arr::flatten($locationIds, 1), 'id'), true)];
    }

    /**
     * A store connected to several of our shops gets one profile per channel, told apart like the aiku- locations.
     */
    public static function profileName(CustomerSalesChannel $customerSalesChannel): string
    {
        return self::PROFILE_NAME.' ('.$customerSalesChannel->slug.')';
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
                    'name'           => 'AW Standard',
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
     * @return array{0: bool, 1: array{id: string, name: string|null, locationGroupId: string, zoneIds: list<string>, variantIds: list<string>}|null|string}
     *         null when the merchant deleted the profile
     */
    private function readProfile(ShopifyUser $shopifyUser, string $profileId): array
    {
        $query = <<<'QUERY'
            query getAppDeliveryProfile($id: ID!, $after: String) {
                deliveryProfile(id: $id) {
                    id
                    name
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
                'name'            => $node['name'] ?? null,
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
    private function createProfile(ShopifyUser $shopifyUser, string $name, array $zones, array $variantIds, string $ratesFingerprint): array
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
                'name'                   => $name,
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
     * @param  list<string>  $toDissociate
     * @return array{0: bool, 1: string|null}
     */
    private function moveVariants(ShopifyUser $shopifyUser, string $profileId, array $toAssociate, array $toDissociate): array
    {
        foreach (array_chunk($toAssociate, self::VARIANTS_PER_CALL) as $chunk) {
            [$status, $message] = $this->updateProfile($shopifyUser, $profileId, ['variantsToAssociate' => $chunk]);
            if (!$status) {
                return [false, $message];
            }
        }

        foreach (array_chunk($toDissociate, self::VARIANTS_PER_CALL) as $chunk) {
            [$status, $message] = $this->updateProfile($shopifyUser, $profileId, ['variantsToDissociate' => $chunk]);
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
        $this->rememberInProfileData($shopifyUser, [
            'id'                => $profileId,
            'rates_fingerprint' => $ratesFingerprint,
            'synced_at'         => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function rememberInProfileData(ShopifyUser $shopifyUser, array $values): void
    {
        $data                     = $shopifyUser->data ?? [];
        $data['delivery_profile'] = [...($data['delivery_profile'] ?? []), ...$values];

        $shopifyUser->update(['data' => $data]);
    }

    public function asCommand(Command $command): int
    {
        $query = CustomerSalesChannel::where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->where('platform_status', true)
            ->whereIn('platform_id', Platform::where('type', PlatformTypeEnum::SHOPIFY)->pluck('id'));

        if ($command->argument('customerSalesChannel')) {
            $query->where('slug', $command->argument('customerSalesChannel'));
        }

        if ($command->option('changed')) {
            $query->whereRaw(self::LINKS_CHANGED_SINCE_CHECKED);
        }

        $dryRun = (bool) $command->option('dry-run');
        $failed = 0;
        foreach ($query->get() as $customerSalesChannel) {
            [$status, $message] = $this->handle($customerSalesChannel, $dryRun);
            $command->line(($status ? 'ok   ' : 'FAIL ').$customerSalesChannel->slug.' '.$message);
            $failed += $status ? 0 : 1;

            if (!$dryRun && $customerSalesChannel->user instanceof ShopifyUser) {
                $this->rememberInProfileData($customerSalesChannel->user->refresh(), ['checked_at' => now()->toIso8601String()]);
            }
        }

        return $failed ? 1 : 0;
    }
}
