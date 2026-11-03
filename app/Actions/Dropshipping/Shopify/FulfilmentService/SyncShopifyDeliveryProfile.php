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
 * Shopify zones stop at country level, so a country some of our zones cover only by postcode takes the
 * zone naming it with the highest price, as postcode zones are the dearer ones. Shopify has
 * no "to be confirmed" rate, so a band or a rest of the world priced TBC gets no rate at all and checkout
 * offers no shipping there, as we can not promise a price. Overlapping bands are cut where the earlier one
 * ends, as CalculateOrderShipping takes the first band that matches. What we bill the merchant is
 * unchanged: the order is still priced by our own schema.
 * Each variant belongs to one profile, so the merchant's own locations do not ship these products.
 *
 * A store still on shipping profiles keeps the merchant's rates: the aiku- location joins the profiles the
 * previous aiku location was in, or the merchant's default profile, and nothing else is touched. The app
 * profile is created on the first sync after Shopify moves the store to market-driven shipping. Once
 * created, a profile is always kept in sync.
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

        if (!Arr::get($shopifyUser->data, 'delivery_profile.id') && !$this->isOnMarketDrivenShipping($shopifyUser)) {
            return $this->addLocationToMerchantProfiles($shopifyUser, $dryRun);
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
        if (!$profileId) {
            [$status, $profileId] = $this->findProfileIdByName($shopifyUser, self::profileName($customerSalesChannel));
            if (!$status) {
                return [false, $profileId];
            }
        }

        $profile = null;
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
     * null when the flag can not be read (an API version before 2026-07, or the call failed); the sync then
     * treats the store as on shipping profiles.
     */
    private function isOnMarketDrivenShipping(ShopifyUser $shopifyUser): ?bool
    {
        [$status, $res] = $this->doPost($shopifyUser, 'query shopFeatures { shop { features { marketDrivenShipping } } }', [], '2026-07');
        $flag = $status ? ($res['body']->toArray()['data']['shop']['features']['marketDrivenShipping'] ?? null) : null;

        return is_bool($flag) ? $flag : null;
    }

    /**
     * Undoes an app profile given to a store still on shipping profiles (INI-177). Variants leaving a profile go
     * to the merchant's default profile, so the aiku- location joins every group of that profile first, or the
     * products would go sold out; then the variants are dissociated and our profile removed. A store on
     * market-driven shipping, or one whose flag can not be read, keeps the app profile.
     *
     * @return array{0: bool, 1: string}
     */
    public function restoreMerchantRates(ShopifyUser $shopifyUser, bool $dryRun = false): array
    {
        if (!$shopifyUser->shopify_location_id) {
            return [false, 'No Shopify location on this channel'];
        }

        $profileId = Arr::get($shopifyUser->data, 'delivery_profile.id');
        if (!$profileId && $shopifyUser->customerSalesChannel) {
            [$status, $profileId] = $this->findProfileIdByName($shopifyUser, self::profileName($shopifyUser->customerSalesChannel));
            if (!$status) {
                return [false, $profileId];
            }
            if ($profileId && !$dryRun) {
                $this->rememberInProfileData($shopifyUser, ['id' => $profileId]);
            }
        }
        if (!$profileId) {
            return [true, 'No delivery profile'];
        }

        $marketDriven = $this->isOnMarketDrivenShipping($shopifyUser);
        if ($marketDriven === null) {
            return [false, 'Could not read whether the store is on market-driven shipping, left as it is'];
        }
        if ($marketDriven) {
            return [true, 'On market-driven shipping, the app profile stays'];
        }

        [$status, $message] = $this->addLocationToMerchantProfiles($shopifyUser, $dryRun, $profileId);
        if (!$status) {
            return [false, $message];
        }

        [$status, $profile] = $this->readProfile($shopifyUser, $profileId);
        if (!$status) {
            return [false, $profile];
        }
        $variantIds = $profile['variantIds'] ?? [];
        if ($dryRun) {
            return [true, $message.'; would move '.count($variantIds).' variant(s) back and remove the app profile'];
        }

        [$status, $error] = $this->moveVariants($shopifyUser, $profileId, [], $variantIds);
        if (!$status) {
            return [false, $message.'; '.$error];
        }

        [$status, $removed] = $this->removeProfile($shopifyUser);

        return [$status, $message.'; moved '.count($variantIds).' variant(s) back; '.$removed];
    }

    /**
     * Shopify only sells stock held at a location inside a shipping profile, and a fulfilment service location
     * is never added to one by itself.
     *
     * @return array{0: bool, 1: string}
     */
    private function addLocationToMerchantProfiles(ShopifyUser $shopifyUser, bool $dryRun, ?string $appProfileId = null): array
    {
        $query = <<<'QUERY'
            query merchantDeliveryProfileLocations {
                deliveryProfiles(first: 50) {
                    nodes {
                        id
                        default
                        profileLocationGroups {
                            locationGroup {
                                id
                                locations(first: 250, includeLegacy: true) {
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

        $profiles = array_filter($res['body']->toArray()['data']['deliveryProfiles']['nodes'] ?? [], fn (array $profile) => !$appProfileId || ($profile['id'] ?? null) !== $appProfileId);
        $groups   = $appProfileId ? self::defaultGroupsToJoin($profiles, $shopifyUser->shopify_location_id) : self::merchantGroupsToJoin($profiles, $shopifyUser->shopify_location_id);
        if ($groups === null) {
            return [true, 'On shipping profiles that already ship from the aiku- location, left alone'];
        }
        if ($groups === []) {
            return [false, 'On shipping profiles, with no default profile to add the aiku- location to'];
        }
        if ($dryRun) {
            return [true, 'Would add the aiku- location to '.count($groups).' of the merchant\'s shipping profile group(s)'];
        }

        foreach ($groups as $group) {
            [$status, $message] = $this->updateProfile($shopifyUser, $group['profileId'], [
                'locationGroupsToUpdate' => [[
                    'id'             => $group['groupId'],
                    'locationsToAdd' => [$shopifyUser->shopify_location_id],
                ]],
            ]);
            if (!$status) {
                return [false, $message];
            }
        }

        return [true, 'Added the aiku- location to '.count($groups).' of the merchant\'s shipping profile group(s)'];
    }

    /**
     * @param  array<array<string, mixed>>  $profiles
     * @return list<array{profileId: string, groupId: string}>|null  null when a default profile group already ships from the location
     */
    public static function defaultGroupsToJoin(array $profiles, string $locationId): ?array
    {
        $default = collect($profiles)->first(fn (array $profile) => $profile['default'] ?? false);
        $groups  = [];
        foreach ($default['profileLocationGroups'] ?? [] as $profileLocationGroup) {
            $group = $profileLocationGroup['locationGroup'];
            if (in_array($locationId, array_column($group['locations']['nodes'] ?? [], 'id'), true)) {
                return null;
            }
            $groups[] = ['profileId' => $default['id'], 'groupId' => $group['id']];
        }

        return $groups;
    }

    /**
     * The groups a reinstalled store's previous aiku- location was in, or else the default profile's.
     *
     * @param  array<array<string, mixed>>  $profiles
     * @return list<array{profileId: string, groupId: string}>|null  null when a profile already ships from the location
     */
    public static function merchantGroupsToJoin(array $profiles, string $locationId): ?array
    {
        $withPreviousAikuLocation = [];
        $defaultGroup             = null;

        foreach ($profiles as $profile) {
            foreach ($profile['profileLocationGroups'] ?? [] as $profileLocationGroup) {
                $group     = $profileLocationGroup['locationGroup'];
                $locations = $group['locations']['nodes'] ?? [];
                if (in_array($locationId, array_column($locations, 'id'), true)) {
                    return null;
                }

                $candidate = ['profileId' => $profile['id'], 'groupId' => $group['id']];
                if (array_filter($locations, fn (array $location) => str_starts_with($location['name'] ?? '', 'aiku-dse'))) {
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
        $methodsByZone   = array_map(fn (array $priceData) => self::methodDefinitions($priceData, $currencyCode, $price), $priceDataByZone);
        $highestByZone   = array_map(fn (array $priceData) => self::highestPrice($priceData), $priceDataByZone);

        $candidateZones = [];
        $byPostcode     = [];
        foreach ($shippingZones as $index => $shippingZone) {
            foreach ($methodsByZone[$index] === [] ? [] : ($shippingZone->territories ?? []) as $territory) {
                $countryCode = Arr::get($territory, 'country_code');
                if (!$countryCode) {
                    continue;
                }
                $candidateZones[$countryCode][] = $index;
                if (Arr::get($territory, 'included_postal_codes') || Arr::get($territory, 'excluded_postal_codes')) {
                    $byPostcode[$countryCode] = true;
                }
            }
        }

        $zoneOfCountry = [];
        foreach ($candidateZones as $countryCode => $indexes) {
            $zoneOfCountry[$countryCode] = isset($byPostcode[$countryCode])
                ? collect($indexes)->sortByDesc(fn (int $index) => $highestByZone[$index])->first()
                : $indexes[0];
        }

        $zones = [];
        foreach ($shippingZones as $index => $shippingZone) {
            $methods = $methodsByZone[$index];
            if ($methods === []) {
                continue;
            }

            if (empty($shippingZone->territories)) {
                $countries = [['restOfWorld' => true]];
            } else {
                $countries = collect(array_keys($zoneOfCountry, $index, true))
                    ->map(fn (string $countryCode) => ['code' => $countryCode, 'includeAllProvinces' => true])
                    ->all();
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
     * Our bands include both ends and the earlier band wins where two meet or overlap, so a band starts just
     * above the end of the one before it, and a band priced TBC still takes its range without a rate.
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

        $methods   = [];
        $coveredTo = null;
        foreach (Arr::get($priceData, 'steps', []) as $step) {
            $to = $step['to'] === 'INF' ? INF : (float) $step['to'];
            if ($coveredTo !== null && $to <= $coveredTo) {
                continue;
            }

            $from      = (float) $step['from'];
            $from      = $coveredTo !== null && $coveredTo >= $from ? $coveredTo + $nudge : $from;
            $coveredTo = $to;
            if (!is_numeric($step['price'] ?? null)) {
                continue;
            }

            $conditions = [];
            if ($from > 0) {
                $conditions[] = ['criteria' => $criteria($from), 'operator' => 'GREATER_THAN_OR_EQUAL_TO'];
            }
            if ($to !== INF) {
                $conditions[] = ['criteria' => $criteria($to), 'operator' => 'LESS_THAN_OR_EQUAL_TO'];
            }

            $methods[] = array_filter([
                'name'           => 'AW Standard',
                'active'         => true,
                'rateDefinition' => ['price' => ['amount' => $price($step['price']), 'currencyCode' => $currencyCode]],
                $conditionKey    => $conditions,
            ], fn ($value) => $value !== []);
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
    /**
     * A profile we created but whose id we lost (HELP-4477: shopify:check used to overwrite the whole data
     * column) is still in the store under our name, and Shopify refuses a second one with that name.
     *
     * @return array{0: bool, 1: string|null}  the id, or the error when the store could not be read
     */
    private function findProfileIdByName(ShopifyUser $shopifyUser, string $name): array
    {
        [$status, $res] = $this->doPost($shopifyUser, 'query appDeliveryProfileByName { deliveryProfiles(first: 50) { nodes { id name } } }', []);
        if (!$status) {
            return [false, $res];
        }

        $profile = collect($res['body']->toArray()['data']['deliveryProfiles']['nodes'] ?? [])->firstWhere('name', $name);

        return [true, $profile['id'] ?? null];
    }

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

    /**
     * A closed channel's variants would stay locked in a profile shipping only from the aiku- location. Removing the
     * profile gives its products back to the merchant's general profile. Run while the store is still reachable.
     *
     * @return array{0: bool, 1: string}
     */
    public function removeProfile(ShopifyUser $shopifyUser): array
    {
        $profileId = Arr::get($shopifyUser->data, 'delivery_profile.id');
        if (!$profileId) {
            return [true, 'No delivery profile'];
        }

        [$status, $profile] = $this->readProfile($shopifyUser, $profileId);
        if (!$status) {
            return [false, $profile];
        }

        if ($profile) {
            $mutation = <<<'MUTATION'
                mutation appDeliveryProfileRemove($id: ID!) {
                    deliveryProfileRemove(id: $id) {
                        userErrors {
                            field
                            message
                        }
                    }
                }
            MUTATION;

            [$status, $res] = $this->doPost($shopifyUser, $mutation, ['id' => $profileId]);
            if (!$status) {
                return [false, $res];
            }

            $userErrors = $res['body']->toArray()['data']['deliveryProfileRemove']['userErrors'] ?? [];
            if ($userErrors !== []) {
                return [false, 'User errors: '.json_encode($userErrors)];
            }
        }

        $data = $shopifyUser->data;
        unset($data['delivery_profile']);
        $shopifyUser->update(['data' => $data]);

        return [true, $profile ? 'Removed the delivery profile and '.count($profile['variantIds']).' variant(s)' : 'The merchant had already deleted the delivery profile'];
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
