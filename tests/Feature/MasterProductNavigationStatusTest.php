<?php

/*
 * Author Louis Perez
 * Created on 18-09-2026
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->group      = createGroup();
    $this->adminGuest = createAdminGuest($this->group);
    actingAs($this->adminGuest->getUser());

    $this->masterShop = StoreMasterShop::make()->action($this->group, [
        'type' => ShopTypeEnum::B2B,
        'code' => 'NV'.substr(uniqid(), -6),
        'name' => 'Navigation Master Shop',
    ]);

    $masterDepartment = StoreMasterDepartment::make()->action($this->masterShop, [
        'code' => 'NVD-'.uniqid(),
        'name' => 'dep',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $masterFamily = StoreMasterFamily::make()->action($masterDepartment, [
        'code' => 'NVF-'.uniqid(),
        'name' => 'fam',
        'type' => MasterProductCategoryTypeEnum::FAMILY,
    ]);

    $this->assets = [];
    foreach (['NAV-A' => true, 'NAV-B' => false, 'NAV-C' => true, 'NAV-D' => false] as $code => $status) {
        $asset = StoreMasterAsset::make()->action($masterFamily, [
            'code'        => $code,
            'name'        => $code,
            'is_main'     => true,
            'type'        => MasterAssetTypeEnum::PRODUCT,
            'price'       => 10,
            'stocks'      => [],
            'trade_units' => [],
        ]);
        $asset->updateQuietly(['status' => $status]);
        $this->assets[$code] = $asset;
    }
});

test('next and previous skip master products with a different status', function () {
    $response = get(route('grp.masters.master_shops.show.master_products.show', [
        $this->masterShop->slug,
        $this->assets['NAV-A']->slug,
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous', null)
            ->where('navigation.next.route.parameters.masterProduct', $this->assets['NAV-C']->slug)
            ->etc()
    );

    $response = get(route('grp.masters.master_shops.show.master_products.show', [
        $this->masterShop->slug,
        $this->assets['NAV-D']->slug,
    ]));

    $response->assertOk();
    $response->assertInertia(
        fn (AssertableInertia $page) => $page
            ->where('navigation.previous.route.parameters.masterProduct', $this->assets['NAV-B']->slug)
            ->where('navigation.next', null)
            ->etc()
    );
});
