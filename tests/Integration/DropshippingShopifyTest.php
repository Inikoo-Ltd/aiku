<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jul 2024 12:16:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Dropshipping\CustomerClient\StoreCustomerClient;
use App\Actions\Dropshipping\CustomerClient\UpdateCustomerClient;
use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;

uses()->group('integration');


beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    \Tests\Helpers\setupDropshippingTest($this);
});


test('create shopify channel', function () {
    $platform = $this->group->platforms()->where('type', PlatformTypeEnum::SHOPIFY)->first();


    expect($this->customer->customerSalesChannels()->count())->toBe(0);
    $customerSalesChannel = StoreCustomerSalesChannel::make()->action(
        $this->customer,
        $platform,
        [
            'reference' => 'test_shopify_reference'
        ]
    );


    $customer = $customerSalesChannel->customer;
    expect($customer->customerSalesChannels()->first())->toBeInstanceOf(CustomerSalesChannel::class);


    return $customerSalesChannel;
});


test('create customer client', function (CustomerSalesChannel $customerSalesChannel) {
    $customerClient = StoreCustomerClient::make()->action($customerSalesChannel, CustomerClient::factory()->definition());
    expect($customerClient)->toBeInstanceOf(CustomerClient::class);

    return $customerClient;
})->depends('create shopify channel');

test('update customer client', function ($customerClient) {
    $customerClient = UpdateCustomerClient::make()->action($customerClient, ['reference' => '001']);
    expect($customerClient->reference)->toBe('001');
    return $customerClient;
})->depends('create customer client');

test('add product to customer portfolio', function (CustomerClient $customerClient) {
    $dropshippingCustomerPortfolio = StorePortfolio::make()->action(
        $customerClient->salesChannel,
        $this->product,
        [
        ]
    );
    expect($dropshippingCustomerPortfolio)->toBeInstanceOf(Portfolio::class);

    return $dropshippingCustomerPortfolio;
})->depends('update customer client');

