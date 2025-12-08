<?php

/*
 * Author: Arya Permana <aryapermana02@gmail.com>
 * Created: Thu, 13 Jun 2024 13:27:40 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Billables\Service\StoreService;
use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\Fulfilment\Pallet\StorePallet;
use App\Actions\Fulfilment\PalletDelivery\Notifications\SendPalletDeliveryNotification;
use App\Actions\Fulfilment\PalletDelivery\StorePalletDelivery;
use App\Actions\Fulfilment\PalletReturn\Notifications\SendPalletReturnNotification;
use App\Actions\Fulfilment\PalletReturn\StorePalletReturn;
use App\Actions\Inventory\Location\StoreLocation;
use App\Enums\Billables\Rental\RentalUnitEnum;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Billables\Service;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Inventory\Location;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {

    SendPalletDeliveryNotification::shouldRun()
        ->andReturn();
    SendPalletReturnNotification::shouldRun()
        ->andReturn();

    $this->organisation = createOrganisation();
    $this->adminGuest = createAdminGuest($this->organisation->group);
    $this->warehouse = createWarehouse();
    $this->fulfilment = createFulfilment($this->organisation);
    $location = $this->warehouse->locations()->first();
    if (! $location) {
        StoreLocation::run(
            $this->warehouse,
            Location::factory()->definition()
        );
        StoreLocation::run(
            $this->warehouse,
            Location::factory()->definition()
        );
    }

    $shop = Shop::first();
    if (! $shop) {
        $storeData = Shop::factory()->definition();
        data_set($storeData, 'type', ShopTypeEnum::FULFILMENT);
        data_set($storeData, 'warehouses', [$this->warehouse->id]);

        $shop = StoreShop::make()->action(
            $this->organisation,
            $storeData
        );
    }
    $this->shop = $shop;

    $this->shop = UpdateShop::make()->action($this->shop, ['state' => ShopStateEnum::OPEN]);

    [
        $this->tradeUnit,
        $this->product
    ] = createProduct($this->shop);

    $this->customer = createCustomer($this->shop);

    $pallet = Pallet::first();
    if (! $pallet) {
        $storeData = Pallet::factory()->definition();
        data_set($storeData, 'state', PalletStateEnum::SUBMITTED);
        data_set($storeData, 'warehouse_id', $this->warehouse->id);

        $pallet = StorePallet::make()->action(
            $this->customer->fulfilmentCustomer,
            $storeData
        );
    }

    $this->pallet = $pallet;

    $palletDelivery = PalletDelivery::first();
    if (! $palletDelivery) {
        data_set($storeData, 'warehouse_id', $this->warehouse->id);
        data_set($storeData, 'state', PalletDeliveryStateEnum::IN_PROCESS);

        $palletDelivery = StorePalletDelivery::make()->action(
            $this->customer->fulfilmentCustomer,
            $storeData
        );
    }

    $this->palletDelivery = $palletDelivery;

    $palletReturn = PalletReturn::first();
    if (! $palletReturn) {
        data_set($storeData, 'warehouse_id', $this->warehouse->id);
        data_set($storeData, 'state', PalletReturnStateEnum::IN_PROCESS);

        $palletReturn = StorePalletReturn::make()->action(
            $this->customer->fulfilmentCustomer,
            $storeData
        );
    }

    $this->palletReturn = $palletReturn;

    $service = Service::first();
    if (! $service) {
        data_set($storeData, 'code', 'TEST1');
        data_set($storeData, 'state', ServiceStateEnum::ACTIVE);
        data_set($storeData, 'name', 'test1');
        data_set($storeData, 'price', 100);
        data_set($storeData, 'unit', RentalUnitEnum::DAY->value);

        $service = StoreService::make()->action(
            $this->shop,
            $storeData
        );
    }

    $this->service = $service;

    $physicalGoods = Product::first();
    if (! $physicalGoods) {
        data_set($storeData, 'code', 'TEST2');
        data_set($storeData, 'name', 'test2');
        data_set($storeData, 'state', ProductStateEnum::ACTIVE);

        data_set($storeData, 'price', 100);
        data_set($storeData, 'is_main', true);

        $physicalGoods = StoreProduct::make()->action(
            $this->shop,
            $storeData
        );
    }

    $this->physicalGoods = $physicalGoods;

    $user = $this->adminGuest->getUser();

    $user->refresh();
    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($user);
});

test('UI Index fulfilment services (delivery)', function () {
    $response = $this->get(route('grp.json.fulfilment.delivery.services.index', [$this->fulfilment->slug, $this->palletDelivery->slug]));
    $responseData = $response->json('data');
    $this->assertNotEmpty($responseData);
    $response->assertStatus(200);
});

test('UI Index fulfilment services (return)', function () {
    $response = $this->get(route('grp.json.fulfilment.return.services.index', [$this->fulfilment->slug, $this->palletReturn->slug]));
    $responseData = $response->json('data');
    $this->assertNotEmpty($responseData);
    $response->assertStatus(200);
});

test('UI Index fulfilment physical goods (delivery)', function () {
    $response = $this->get(route('grp.json.fulfilment.delivery.physical-goods.index', [$this->fulfilment->slug, $this->palletDelivery->slug]));
    $responseData = $response->json('data');
    $this->assertNotEmpty($responseData);
    $response->assertStatus(200);
});

test('UI Index fulfilment physical goods (return)', function () {
    $response = $this->get(route('grp.json.fulfilment.return.physical-goods.index', [$this->fulfilment->slug, $this->palletReturn->slug]));
    $responseData = $response->json('data');
    $this->assertNotEmpty($responseData);
    $response->assertStatus(200);
});
