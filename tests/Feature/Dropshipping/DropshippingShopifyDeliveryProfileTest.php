<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 30 Oct 2026 12:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Billables\ShippingZone\StoreShippingZone;
use App\Actions\Billables\ShippingZoneSchema\StoreShippingZoneSchema;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Shopify\FulfilmentService\SyncShopifyDeliveryProfile;
use App\Actions\Dropshipping\ShopifyUser\StoreShopifyUser;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\Support\ShopifyFake;

beforeAll(function () {
    loadDB();
});

afterEach(function () {
    expect(ShopifyFake::$stray)->toBe([]);
});

beforeEach(function () {
    $this->organisation = createOrganisation();

    $shop = Shop::where('type', ShopTypeEnum::DROPSHIPPING)->first();
    if (!$shop) {
        $shop = StoreShop::make()->action($this->organisation, [...Shop::factory()->definition(), 'type' => ShopTypeEnum::DROPSHIPPING]);
    }
    $this->shop = UpdateShop::make()->action($shop, ['state' => ShopStateEnum::OPEN]);

    [, $this->product] = createProduct($this->shop);
});

function deliveryProfileChannel($test): array
{
    $customer    = StoreCustomer::make()->action($test->shop, [...Customer::factory()->definition(), 'email' => Str::lower(Str::random(12)).'@shopify-test.com']);
    $shopifyUser = StoreShopifyUser::make()->handle($customer, ['name' => 'delivery-profile-'.Str::lower(Str::random(6))]);
    $shopifyUser->update([
        'shopify_location_id' => 'gid://shopify/Location/1001',
        'data'                => [...($shopifyUser->data ?? []), 'shop' => ['currencyCode' => $test->shop->currency->code]],
    ]);
    $channel = $shopifyUser->customerSalesChannel;

    $schema = StoreShippingZoneSchema::make()->action($test->shop, ['name' => 'Profile shipping '.Str::random(4)]);
    $zone   = StoreShippingZone::make()->action($schema, [
        'code'        => 'PZ1',
        'name'        => 'Zone 1',
        'status'      => true,
        'territories' => [['country_code' => 'GB']],
        'price'       => ['type' => 'Step Order Estimated Weight', 'steps' => [['from' => 0, 'to' => 2, 'price' => 2.99], ['from' => 2, 'to' => 'INF', 'price' => 5.99]]],
        'position'    => 1,
        'is_failover' => false,
    ]);
    $channel->shop->setRelation('currentShippingZoneSchema', $schema);

    $portfolio = StorePortfolio::make()->action($channel, $test->product, []);
    $portfolio->update(['platform_product_variant_id' => 'gid://shopify/ProductVariant/7001']);

    return [$channel, $shopifyUser, $zone, $portfolio];
}

function appDeliveryProfile(array $variantIds, ?string $name, ?string $id = 'gid://shopify/DeliveryProfile/900'): array
{
    return ShopifyFake::graphql(['deliveryProfile' => $id ? [
        'id'                    => $id,
        'name'                  => $name,
        'profileLocationGroups' => [[
            'locationGroup'      => ['id' => 'gid://shopify/DeliveryLocationGroup/40'],
            'locationGroupZones' => ['nodes' => [['zone' => ['id' => 'gid://shopify/DeliveryZone/50']]]],
        ]],
        'profileItems'          => [
            'pageInfo' => ['hasNextPage' => false, 'endCursor' => null],
            'nodes'    => [['variants' => ['nodes' => array_map(fn (string $variantId) => ['id' => $variantId], $variantIds)]]],
        ],
    ] : null]);
}

test('the app creates its own delivery profile with the aiku location, our rates and the channel variants', function () {
    [$channel, $shopifyUser] = deliveryProfileChannel($this);

    ShopifyFake::fake([
        'deliveryProfileCreate' => ShopifyFake::graphql(['deliveryProfileCreate' => ['profile' => ['id' => 'gid://shopify/DeliveryProfile/900'], 'userErrors' => []]]),
    ]);

    [$status, $message] = SyncShopifyDeliveryProfile::run($channel);
    $profile = ShopifyFake::calls('deliveryProfileCreate')[0]['variables']['profile'];

    expect($status)->toBeTrue()
        ->and($message)->toBe('Created the delivery profile with 1 zone(s) and 1 variant(s)')
        ->and($profile['name'])->toBe('AW Connect shipping ('.$channel->slug.')')
        ->and($profile['locationGroupsToCreate'][0]['locations'])->toBe(['gid://shopify/Location/1001'])
        ->and($profile['locationGroupsToCreate'][0]['zonesToCreate'][0]['countries'])->toBe([['code' => 'GB', 'includeAllProvinces' => true]])
        ->and($profile['locationGroupsToCreate'][0]['zonesToCreate'][0]['methodDefinitionsToCreate'])->toHaveCount(2)
        ->and($profile['variantsToAssociate'])->toBe(['gid://shopify/ProductVariant/7001'])
        ->and($shopifyUser->refresh()->data['delivery_profile']['id'])->toBe('gid://shopify/DeliveryProfile/900')
        ->and(ShopifyFake::calls('deliveryProfileUpdate'))->toBe([]);
});

test('the app profile follows the linked variants and our rates, and is created again when the merchant deleted it', function () {
    [$channel, $shopifyUser, $zone, $portfolio] = deliveryProfileChannel($this);

    ShopifyFake::fake([
        'deliveryProfileCreate' => ShopifyFake::graphql(['deliveryProfileCreate' => ['profile' => ['id' => 'gid://shopify/DeliveryProfile/900'], 'userErrors' => []]]),
    ]);
    SyncShopifyDeliveryProfile::run($channel);

    $portfolio->update(['platform_product_variant_id' => 'gid://shopify/ProductVariant/7003']);
    ShopifyFake::fake([
        'getAppDeliveryProfile'    => appDeliveryProfile(['gid://shopify/ProductVariant/7001', 'gid://shopify/ProductVariant/7002'], 'AW Connect shipping ('.$channel->slug.')'),
        'appDeliveryProfileUpdate' => ShopifyFake::graphql(['deliveryProfileUpdate' => ['userErrors' => []]]),
    ]);

    [$status, $message] = SyncShopifyDeliveryProfile::run($channel);
    $updates = array_column(ShopifyFake::calls('appDeliveryProfileUpdate'), 'variables');

    expect($status)->toBeTrue()
        ->and($message)->toBe('added 1 and removed 2 variant(s)')
        ->and($updates)->toBe([
            ['id' => 'gid://shopify/DeliveryProfile/900', 'profile' => ['variantsToAssociate' => ['gid://shopify/ProductVariant/7003']]],
            ['id' => 'gid://shopify/DeliveryProfile/900', 'profile' => ['variantsToDissociate' => ['gid://shopify/ProductVariant/7001', 'gid://shopify/ProductVariant/7002']]],
        ]);

    $zone->update(['price' => ['type' => 'Step Order Estimated Weight', 'steps' => [['from' => 0, 'to' => 'INF', 'price' => 3.49]]]]);
    $channel->shop->currentShippingZoneSchema->unsetRelation('shippingZones');
    ShopifyFake::fake([
        'getAppDeliveryProfile'    => appDeliveryProfile(['gid://shopify/ProductVariant/7003'], 'AW Connect shipping'),
        'appDeliveryProfileUpdate' => ShopifyFake::graphql(['deliveryProfileUpdate' => ['userErrors' => []]]),
    ]);

    [$status, $message] = SyncShopifyDeliveryProfile::run($channel);
    $update = ShopifyFake::calls('appDeliveryProfileUpdate')[0]['variables']['profile'];

    expect($status)->toBeTrue()
        ->and($message)->toBe('Renamed, Rates replaced, added 0 and removed 0 variant(s)')
        ->and($update['name'])->toBe('AW Connect shipping ('.$channel->slug.')')
        ->and($update['zonesToDelete'])->toBe(['gid://shopify/DeliveryZone/50'])
        ->and($update['locationGroupsToUpdate'][0]['id'])->toBe('gid://shopify/DeliveryLocationGroup/40')
        ->and($update['locationGroupsToUpdate'][0]['zonesToCreate'][0]['methodDefinitionsToCreate'])->toHaveCount(1);

    ShopifyFake::fake([
        'getAppDeliveryProfile' => appDeliveryProfile([], null, null),
    ]);
    [$status, $message] = SyncShopifyDeliveryProfile::run($channel, true);

    expect($status)->toBeTrue()
        ->and($message)->toBe('Would create the delivery profile with 1 zone(s) and 1 variant(s)')
        ->and($shopifyUser->refresh()->data['delivery_profile']['id'])->toBe('gid://shopify/DeliveryProfile/900');
});

test('a channel without a current shipping zone schema or a location is refused', function () {
    [$channel, $shopifyUser] = deliveryProfileChannel($this);
    ShopifyFake::fake([]);

    $channel->shop->setRelation('currentShippingZoneSchema', null);
    expect(SyncShopifyDeliveryProfile::run($channel))->toBe([false, 'The shop has no current shipping zone schema']);

    $shopifyUser->update(['shopify_location_id' => null]);
    $channel->setRelation('user', $shopifyUser);
    expect(SyncShopifyDeliveryProfile::run($channel))->toBe([false, 'No Shopify location on this channel']);
});

test('the frequent run only picks channels with a portfolio changed since they were last checked, failed or not', function () {
    [$channel, $shopifyUser, , $portfolio] = deliveryProfileChannel($this);
    $channel->update(['platform_status' => true]);
    $run = function () use ($channel): string {
        Artisan::call('shopify:sync-delivery-profile', ['customerSalesChannel' => $channel->slug, '--changed' => true]);

        return Artisan::output();
    };

    ShopifyFake::fake([
        'getAppDeliveryProfile'    => appDeliveryProfile(['gid://shopify/ProductVariant/7001'], 'AW Connect shipping ('.$channel->slug.')', 'gid://shopify/DeliveryProfile/1'),
        'appDeliveryProfileUpdate' => ShopifyFake::graphql(['deliveryProfileUpdate' => ['userErrors' => []]]),
    ]);

    expect($run())->toContain($channel->slug)
        ->and($shopifyUser->refresh()->data['delivery_profile']['checked_at'] ?? null)->not->toBeNull()
        ->and($run())->not->toContain($channel->slug);

    $portfolio->forceFill(['updated_at' => now()->addMinute()])->save();

    expect($run())->toContain($channel->slug);
});

test('a store on shipping profiles that already ships from the aiku location is left alone until it moves to market-driven shipping', function () {
    [$channel, $shopifyUser] = deliveryProfileChannel($this);
    $merchantProfiles = ShopifyFake::graphql(['deliveryProfiles' => ['nodes' => [[
        'profileLocationGroups' => [['locationGroup' => ['locations' => ['nodes' => [['id' => 'gid://shopify/Location/5'], ['id' => 'gid://shopify/Location/1001']]]]]],
    ]]]]);

    ShopifyFake::fake(['merchantDeliveryProfileLocations' => $merchantProfiles]);

    expect(SyncShopifyDeliveryProfile::run($channel))->toBe([true, 'On shipping profiles that already ship from the aiku- location, left alone'])
        ->and(ShopifyFake::calls('deliveryProfileCreate'))->toBe([])
        ->and(ShopifyFake::calls('deliveryProfileUpdate'))->toBe([])
        ->and(ShopifyFake::calls('merchantDeliveryProfileLocations')[0]['url'])->not->toContain('2026-07')
        ->and($shopifyUser->refresh()->data['delivery_profile']['id'] ?? null)->toBeNull();

    ShopifyFake::fake([
        'shopFeatures'                     => ShopifyFake::graphql(['shop' => ['features' => ['marketDrivenShipping' => true]]]),
        'merchantDeliveryProfileLocations' => $merchantProfiles,
    ]);

    [$status, $message] = SyncShopifyDeliveryProfile::run($channel);

    expect($status)->toBeTrue()
        ->and($message)->toStartWith('Created the delivery profile')
        ->and(ShopifyFake::calls('merchantDeliveryProfileLocations'))->toBe([])
        ->and(ShopifyFake::calls('deliveryProfileCreate'))->toHaveCount(1);
});

test('a store on shipping profiles without the aiku location, as on a new install, gets the app profile', function () {
    [$channel] = deliveryProfileChannel($this);

    ShopifyFake::fake([
        'merchantDeliveryProfileLocations' => ShopifyFake::graphql(['deliveryProfiles' => ['nodes' => [[
            'profileLocationGroups' => [['locationGroup' => ['locations' => ['nodes' => [['id' => 'gid://shopify/Location/5']]]]]],
        ]]]]),
    ]);

    [$status, $message] = SyncShopifyDeliveryProfile::run($channel);

    expect($status)->toBeTrue()
        ->and($message)->toStartWith('Created the delivery profile')
        ->and(ShopifyFake::calls('deliveryProfileCreate'))->toHaveCount(1);
});
