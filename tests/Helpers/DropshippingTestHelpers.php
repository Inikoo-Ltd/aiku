<?php /** @noinspection PhpUnhandledExceptionInspection */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Apr 2025 19:21:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace Tests\Helpers;

use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\Config;

use function Pest\Laravel\actingAs;


function setupDropshippingTest($testClass): void
{
    $testClass->organisation = createOrganisation();
    $testClass->group = $testClass->organisation->group;
    $testClass->user = createAdminGuest($testClass->group)->getUser();

    $shop = Shop::first();
    if (!$shop) {
        $storeData = Shop::factory()->definition();
        data_set($storeData, 'type', ShopTypeEnum::DROPSHIPPING);

        $shop = StoreShop::make()->action(
            $testClass->organisation,
            $storeData
        );
    }
    $testClass->shop = $shop;

    $testClass->shop = UpdateShop::make()->action($testClass->shop, ['state' => ShopStateEnum::OPEN]);

    $testClass->customer = createCustomer($testClass->shop);

    list(
        $testClass->tradeUnit,
        $testClass->product
    ) = createProduct($testClass->shop);

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );

    actingAs($testClass->user);
}
