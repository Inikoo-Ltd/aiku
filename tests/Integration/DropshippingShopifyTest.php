<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jul 2024 12:16:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\CRM\Customer\AttachCustomerToPlatform;
use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\PlatformStats;
use App\Models\Dropshipping\Portfolio;

use function Pest\Laravel\actingAs;

uses()->group('integration');


beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    \Tests\Helpers\setupDropshippingTest($this);
});

test('test platform were seeded ', function () {
    expect($this->group->platforms()->count())->toBe(2);
    $platform = Platform::first();
    expect($platform)->toBeInstanceOf(Platform::class)
        ->and($platform->stats)->toBeInstanceOf(PlatformStats::class);

    $this->artisan('group:seed-platforms')->assertExitCode(0);
    expect($this->group->platforms()->count())->toBe(2);
});

test('create customer client', function () {
    $customerClient = StoreCustomerClient::make()->action($this->customer, CustomerClient::factory()->definition());
    expect($customerClient)->toBeInstanceOf(CustomerClient::class);

    return $customerClient;
});

test('update customer client', function ($customerClient) {
    $customerClient = UpdateCustomerClient::make()->action($customerClient, ['reference' => '001']);
    expect($customerClient->reference)->toBe('001');
})->depends('create customer client');

test('add product to customer portfolio', function () {
    $dropshippingCustomerPortfolio = StorePortfolio::make()->action(
        $this->customer,
        [
            'product_id' => $this->product->id
        ]
    );
    expect($dropshippingCustomerPortfolio)->toBeInstanceOf(Portfolio::class);

    return $dropshippingCustomerPortfolio;
});

test('associate customer shopify to customer', function () {
    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::SHOPIFY)->first();


    expect($this->customer->platforms->count())->toBe(0)
        ->and($this->customer->getMainPlatform())->toBeNull();
    $customer = AttachCustomerToPlatform::make()->action(
        $this->customer,
        $platform,
        [
            'reference' => 'test_shopify_reference' // todo add shopify id?? to .env.test
        ]
    );


    $customer->refresh();


    expect($customer->platforms->first())->toBeInstanceOf(Platform::class)
        ->and($customer->getMainPlatform())->toBeInstanceOf(Platform::class)
        ->and($customer->getMainPlatform()->type)->toBe(PlatformTypeEnum::SHOPIFY);




    return $customer;
});
