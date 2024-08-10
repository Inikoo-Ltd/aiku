<?php
/*
 * Author: Arya Permana <aryapermana02@gmail.com>
 * Created: Thu, 13 Jun 2024 13:27:40 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\Catalogue\Shop\UpdateShop;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\Inventory\Location\StoreLocation;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Inventory\Location;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {

    $this->organisation = createOrganisation();
    $this->adminGuest   = createAdminGuest($this->organisation->group);
    $this->warehouse    = createWarehouse();
    $location           = $this->warehouse->locations()->first();
    if (!$location) {
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
    if (!$shop) {
        $storeData = Shop::factory()->definition();
        data_set($storeData, 'type', ShopTypeEnum::DROPSHIPPING);
        data_set($storeData, 'warehouses', [$this->warehouse->id]);

        $shop = StoreShop::make()->action(
            $this->organisation,
            $storeData
        );
    }
    $this->shop = $shop;

    $this->shop = UpdateShop::make()->action($this->shop, ['state' => ShopStateEnum::OPEN]);

    $customer = Customer::first();
    if (!$customer) {
        $storeData = Customer::factory()->definition();

        $customer = StoreCustomer::make()->action(
            $this->shop,
            $storeData
        );
    }
    $this->customer = $customer;

    $this->adminGuest->refresh();

    Config::set(
        'inertia.testing.page_paths',
        [resource_path('js/Pages/Grp')]
    );
    actingAs($this->adminGuest->user);
});

test('UI Index customers', function () {
    $response = $this->get(route('grp.org.shops.show.crm.customers.index', [$this->organisation->slug, $this->shop->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Shop/CRM/Customers')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                        ->where('title', 'customers')
                        ->etc()
            )
            ->has('data');
    });
});

test('UI create customer', function () {
    $response = get(route('grp.org.shops.show.crm.customers.create', [$this->organisation->slug, $this->shop->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 4);
    });
});

test('UI show customer', function () {
    $this->withoutExceptionHandling();
    $response = get(route('grp.org.shops.show.crm.customers.show', [$this->organisation->slug, $this->shop->slug, $this->customer->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Shop/CRM/Customer')
            ->has('title')
            ->has('breadcrumbs', 3)
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                        ->where('title', $this->customer->name)
                        ->etc()
            )
            ->has('tabs');

    });
});

test('UI edit employee', function () {
    $response = get('http://app.aiku.test/org/'.$this->organisation->slug.'/shops/'.$this->shop->slug.'/crm/customers/'.$this->customer->slug.'/edit?section=properties');
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('EditModel')
            ->has('title')
            ->has('pageHead')
            ->has('formData')
            ->has(
                'formData.args.updateRoute',
                fn (AssertableInertia $page) => $page
                        ->where('name', 'grp.models.customer.update')
                        ->where('parameters', $this->customer->id)
            )
            ->has('breadcrumbs', 3);
    });
})->skip();

test('UI Index customer clients', function () {
    $response = $this->get(route('grp.org.shops.show.crm.customers.show.customer-clients.index', [$this->organisation->slug, $this->shop->slug, $this->customer->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Shop/CRM/CustomerClients')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                        ->where('title', 'customer clients')
                        ->has('subNavigation')
                        ->etc()
            )
            ->has('data');
    });
});

test('UI create customer client', function () {
    $response = get(route('grp.org.shops.show.crm.customers.show.customer-clients.create', [$this->organisation->slug, $this->shop->slug, $this->customer->slug]));
    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('CreateModel')
            ->has('title')->has('formData')->has('pageHead')->has('breadcrumbs', 5);
    });
});

test('UI Index customer portfolios', function () {
    $response = $this->get(route('grp.org.shops.show.crm.customers.show.portfolios.index', [$this->organisation->slug, $this->shop->slug, $this->customer->slug]));

    $response->assertInertia(function (AssertableInertia $page) {
        $page
            ->component('Org/Shop/CRM/Portfolios')
            ->has('title')
            ->has('breadcrumbs', 4)
            ->has('pageHead')
            ->has(
                'pageHead',
                fn (AssertableInertia $page) => $page
                        ->where('title', 'portfolios')
                        ->has('subNavigation')
                        ->etc()
            )
            ->has('data');
    });
});
