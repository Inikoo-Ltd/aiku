<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Wix\Order\StoreWixOrder;
use App\Actions\Dropshipping\Wix\Order\ValidateIncomingWixOrder;
use App\Actions\Dropshipping\Wix\User\StoreWixUser;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\WixUser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->organisation = createOrganisation();

    $shop = Shop::first();
    if (!$shop) {
        $storeData = Shop::factory()->definition();
        data_set($storeData, 'type', ShopTypeEnum::DROPSHIPPING);

        $shop = StoreShop::make()->action($this->organisation, $storeData);
    }
    $this->shop = UpdateShop::make()->action($shop, ['state' => ShopStateEnum::OPEN]);

    $this->customer = StoreCustomer::make()->action($this->shop, Customer::factory()->definition());

    list(, $this->product) = createProduct($this->shop);

    Http::fake();
});

function wixChannel(Customer $customer): WixUser
{
    return StoreWixUser::make()->action($customer, [
        'wix_instance_id' => (string) Str::uuid(),
        'name'            => 'Wix site '.Str::random(4),
    ]);
}

function wixApprovedOrder(string $catalogItemId, string $appId = '215238eb-22a5-4c36-9e7b-e7c08025e04e'): array
{
    return [
        'id'        => (string) Str::uuid(),
        'status'    => 'APPROVED',
        'buyerInfo' => ['email' => 'buyer@example.com'],
        'lineItems' => [
            ['catalogReference' => ['catalogItemId' => $catalogItemId, 'appId' => $appId]],
        ],
    ];
}

test('a Wix order without our products, such as a booking, is not logged nor imported', function () {
    $wixUser = wixChannel($this->customer);
    StoreWixOrder::shouldRun()->never();

    ValidateIncomingWixOrder::run($wixUser, wixApprovedOrder((string) Str::uuid(), '13d21c63-b5ec-5912-8397-c3a5ddb27a97'));

    expect($wixUser->debugWebhooks()->count())->toBe(0);
});

test('a Wix order with our products is logged and imported', function () {
    $wixUser   = wixChannel($this->customer);
    $portfolio = StorePortfolio::make()->action($wixUser->customerSalesChannel, $this->product, []);
    $portfolio->update(['platform_product_id' => (string) Str::uuid()]);
    StoreWixOrder::shouldRun()->once();

    ValidateIncomingWixOrder::run($wixUser, wixApprovedOrder($portfolio->platform_product_id));

    expect($wixUser->debugWebhooks()->count())->toBe(1);
});
