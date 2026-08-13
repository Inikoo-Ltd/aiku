<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Apr 2023 09:57:38 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Catalogue\Shop\StoreShop;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Actions\Goods\Stock\StoreStock;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Helpers\Avatars\GetDiceBearAvatar;
use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\Inventory\Warehouse\StoreWarehouse;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\SysAdmin\Group\StoreGroup;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Actions\Web\Website\StoreWebsite;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\TrafficSource;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Goods\Stock;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Address;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Foundation\Testing\TestCase;

/*
 * Faker is seeded per test from the test's own name, so every factory draws the same values on
 * every run, in any order, on any machine. Data still looks real, but a value combination that
 * breaks something breaks every run instead of one run in eleven - flaky-by-fixture is a class
 * of bug this line deletes. Edge cases (zero quantities, empty strings, …) must be explicit in
 * the test that wants them, never left to the dice.
 */
$seedFaker = function (): void {
    $seed = crc32(static::class.'::'.$this->name());
    fake()->seed($seed);
    fake('en_GB')->seed($seed);
};

uses(TestCase::class)->beforeEach($seedFaker)->in('Feature');
uses(TestCase::class)->beforeEach($seedFaker)->in('Unit');
uses(TestCase::class)->group('integration')->beforeEach($seedFaker)->in('Integration');
uses(TestCase::class)->group('browser')->beforeEach($seedFaker)->in('Browser');

function loadDB(): void
{
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../', '.env.testing');
    $dotenv->load();

    $databaseName = env('DB_DATABASE_TEST', 'aiku_test');
    $numberParallelRestoreJobs = 16;
    if (env('TEST_TOKEN')) {
        $databaseName .= '_'.env('TEST_TOKEN');
        $numberParallelRestoreJobs = 2;

        // The app boots and reads DB_DATABASE for its 'aiku'/'aiku_no_sticky'
        // connections after this runs, so the env var (not just the dump target)
        // must point at the token-suffixed database for TEST_TOKEN isolation to work.
        putenv("DB_DATABASE={$databaseName}");
        $_ENV['DB_DATABASE']    = $databaseName;
        $_SERVER['DB_DATABASE'] = $databaseName;
    }

    shell_exec(
        './devops/devel/reset_test_database.sh '.
        $databaseName.' '.
        env('DB_PORT').' '.
        env('DB_USERNAME').' '.
        env('DB_PASSWORD').' '.
        env('DB_HOST').
        ' tests/datasets/db_dumps/aiku.dump '.$numberParallelRestoreJobs
    );
}

function createGroup(): Group
{
    $group = Group::first();
    if (!$group) {
        $group = StoreGroup::make()->action(Group::factory()->definition());
    }

    return $group;
}

/**
 * @throws \Throwable
 */
function createOrganisation(): Organisation
{
    GetDiceBearAvatar::mock()
        ->shouldReceive('handle')
        ->andReturn(Storage::disk('art')->get('icons/shapes.svg'));

    $group = createGroup();

    $organisation = Organisation::first();
    if (!$organisation) {
        $modelData = Organisation::factory()->definition();
        data_set($modelData, 'code', 'acme');
        data_set($modelData, 'type', OrganisationTypeEnum::SHOP);

        $organisation = StoreOrganisation::make()->action($group, $modelData);
    }

    return $organisation;
}


function createAdminGuest(Group $group): Guest
{
    app()->instance('group', $group);
    setPermissionsTeamId($group->id);

    $guest = Guest::all()->first(fn (Guest $candidate) => $candidate->getUser()?->hasRole('group-admin'));
    if (!$guest) {
        try {
            $guest = StoreGuest::make()
                ->action(
                    $group,
                    array_merge(
                        Guest::factory()->definition(),
                        [
                            'positions' => [
                                [
                                    'slug'   => 'group-admin',
                                    'scopes' => []
                                ]
                            ]
                        ]
                    )
                );
        } catch (Exception|Throwable) {
            //
        }
    }

    return $guest;
}

/**
 * The calling test file's own shop, created on first use and reused by every test in that file.
 *
 * This used to hand out Shop::first(), which returns whatever shop happens to sort first in the
 * table - fine while a file only ever had one, but roulette as soon as any other fixture in the
 * file creates a shop of its own. Keying by caller file keeps the old continuity-within-a-file
 * semantics while guaranteeing the shop is always the one this helper created.
 *
 * @throws \Throwable
 */
function createShop(): array
{
    $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'] ?? 'createShop';

    [$organisation, , $shop] = createOwnShop($caller);

    $adminGuest = createAdminGuest($organisation->group);

    /* Fresh instances, not the cached ones: a relation lazy-loaded in an earlier test (crmStats,
       salesStats, …) stays loaded on the cached model and reads stale counts in the next test. */
    return [$organisation->fresh(), $adminGuest->getUser(), $shop->fresh()];
}

/**
 * A shop of one caller's own, created once per key and reused for every test under that key.
 *
 * createShop() hands everybody Shop::first(), which is safe while each test file restores the
 * database before it runs: the shop is pristine because everything else is gone. It stops being safe
 * once several subjects share one file and one database, because then each inherits whatever the
 * ones before it did to that shop.
 *
 * Keyed rather than fresh-per-call because a test file's tests always shared their shop; it is only
 * across files that it was clean. Passing the block name reproduces exactly that: isolation between
 * subjects, continuity within one.
 *
 * @return array{0: Organisation, 1: \App\Models\SysAdmin\User, 2: Shop}
 *
 * @throws \Throwable
 */
function createOwnShop(string $key): array
{
    static $shops = [];

    /* A test that resets the database mid-file (loadDB() in a test body) leaves the cached models
       pointing at rows that no longer exist; detect that and rebuild instead of handing them out. */
    if (isset($shops[$key]) && !$shops[$key][2]->fresh()) {
        unset($shops[$key]);
    }

    if (!isset($shops[$key])) {
        $organisation = createOrganisation();
        $adminGuest   = createAdminGuest($organisation->group);

        $shop = StoreShop::run($organisation, Shop::factory()->definition());
        $shop->refresh();

        $shops[$key] = [$organisation, $adminGuest->getUser(), $shop];
    }

    return $shops[$key];
}

/**
 * A customer of one caller's own, created once per key and reused for every test under that key.
 *
 * Most of what one subject leaves behind for the next hangs off the customer rather than the shop -
 * its touch history, its attribution shares, its invoices and orders - and a customer costs a
 * fraction of what a shop costs to create. Same keying as createOwnShop(): isolation between
 * subjects, continuity within one.
 *
 * @throws \Throwable
 */
function createOwnCustomer(Shop $shop, string $key): Customer
{
    static $customers = [];

    if (!isset($customers[$key])) {
        $customers[$key] = StoreCustomer::make()->action($shop, Customer::factory()->definition());
    }

    /* Refreshed, because the model outlives the rows it describes: a fixture reset that clears a
       column straight through the query builder leaves this instance still holding the old value,
       and the next ->update() with that same value writes nothing at all. The touch history then
       silently stays empty and every figure derived from it reads zero. */
    return $customers[$key]->refresh();
}

/**
 * @throws \Throwable
 */
function createFulfilment(Organisation $organisation): Fulfilment
{
    $group = $organisation->group;
    app()->instance('group', $group);
    setPermissionsTeamId($group->id);
    $organisation = createOrganisation();


    $fulfilment = Fulfilment::first();
    if (!$fulfilment) {
        $shop       = StoreShop::run(
            $organisation,
            array_merge(
                Shop::factory()->definition(),
                [
                    'type'       => ShopTypeEnum::FULFILMENT->value,
                    'warehouses' => [createWarehouse()->id]
                ]
            )
        );
        $fulfilment = $shop->fulfilment;
    }


    return $fulfilment;
}


/**
 * @throws \Throwable
 */
function createWarehouse(): Warehouse
{
    $organisation = createOrganisation();


    $warehouse = Warehouse::first();
    if (!$warehouse) {
        $warehouse = StoreWarehouse::run(
            $organisation,
            Warehouse::factory()->definition()
        );
        $warehouse->refresh();
    }


    return $warehouse;
}


/**
 * @throws \Throwable
 */
function createCustomer(Shop $shop): Customer
{
    $customer = $shop->customers()->first();
    if (!$customer) {
        $customer = StoreCustomer::make()->action(
            $shop,
            Customer::factory()->definition(),
        );
    }

    return $customer;
}

/**
 * Marketing revenue is measured from invoices, so tests that assert on attributed revenue need real
 * ones rather than a hand-set customer_stats rollup.
 */
function createInvoiceFor($customer, $shop, string $date, float $net, bool $inProcess = false): void
{
    \Illuminate\Support\Facades\DB::table('invoices')->insert([
        'group_id'        => $shop->group_id,
        'organisation_id' => $shop->organisation_id,
        'shop_id'         => $shop->id,
        'customer_id'     => $customer->id,
        'currency_id'     => $shop->currency_id,
        'tax_category_id' => \App\Models\Helpers\TaxCategory::firstOrFail()->id,
        'reference'       => 'INV-'.uniqid(),
        'slug'            => 'inv-'.uniqid(),
        'type'            => 'invoice',
        'net_amount'      => $net,
        'org_net_amount'  => $net,
        'total_amount'    => $net,
        'in_process'      => $inProcess,
        'payment_data'    => '{}',
        'data'            => '{}',
        'date'            => $date,
        'created_at'      => $date,
        'updated_at'      => $date,
    ]);
}

/**
 * A channel's order count is measured from the orders themselves, since only they carry the date that
 * says whether the order came after the touch claiming it.
 */
function createDispatchedOrderFor($customer, $shop, string $date, string $state = 'dispatched', float $net = 100): void
{
    \Illuminate\Support\Facades\DB::table('orders')->insert([
        'group_id'        => $shop->group_id,
        'organisation_id' => $shop->organisation_id,
        'shop_id'         => $shop->id,
        'customer_id'     => $customer->id,
        'currency_id'     => $shop->currency_id,
        'tax_category_id' => \App\Models\Helpers\TaxCategory::firstOrFail()->id,
        'slug'            => 'ord-'.uniqid(),
        'state'           => $state,
        'net_amount'      => $net,
        'org_net_amount'  => $net,
        'grp_net_amount'  => $net,
        'status'          => \App\Enums\Ordering\Order\OrderStatusEnum::SETTLED,
        'payment_data'    => '{}',
        'data'            => '{}',
        'date'            => $date,
        'created_at'      => $date,
        'updated_at'      => $date,
    ]);
}

function createTrafficSource(Shop $shop, string $type, string $name): TrafficSource
{
    return TrafficSource::firstOrCreate(
        [
            'shop_id' => $shop->id,
            'type'    => $type,
        ],
        [
            'group_id'        => $shop->group_id,
            'organisation_id' => $shop->organisation_id,
            'name'            => $name,
        ]
    );
}

function createTradeUnits(Group $group): array
{
    $tradeUnits = $group->tradeUnits()->get();
    if ($tradeUnits->isEmpty()) {
        $tradeUnit1 = StoreTradeUnit::make()->action($group, TradeUnit::factory()->definition());
        $tradeUnit2 = StoreTradeUnit::make()->action($group, TradeUnit::factory()->definition());
        $tradeUnit3 = StoreTradeUnit::make()->action($group, TradeUnit::factory()->definition());
    } else {
        $tradeUnit1 = $tradeUnits->first();
        $tradeUnit2 = $tradeUnits->skip(1)->first();
        $tradeUnit3 = $tradeUnits->skip(2)->first();
    }

    return [$tradeUnit1, $tradeUnit2, $tradeUnit3];
}

/**
 * @throws \Throwable
 */
function createStocks(Group $group): array
{
    $numberStocks = $group->stocks()->count();
    if ($numberStocks < 3) {
        $stock = StoreStock::make()->action(
            $group,
            array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE])
        );


        $stock2 = StoreStock::make()->action(
            $group,
            array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE])
        );


        $stock3 = StoreStock::make()->action(
            $group,
            array_merge(Stock::factory()->definition(), ['state' => StockStateEnum::ACTIVE])
        );
    } else {
        $stock  = $group->stocks()->first();
        $stock2 = $group->stocks()->skip(1)->first();
        $stock3 = $group->stocks()->skip(2)->first();
    }

    return [
        $stock,
        $stock2,
        $stock3
    ];
}

/**
 * @throws \Throwable
 */
function createOrgStocks(Organisation $organisation, array $stocks): array
{
    $orgStocks = [];
    foreach ($stocks as $stock) {
        $orgStock = $organisation->orgStocks()->where('stock_id', $stock->id)->first();
        if (!$orgStock) {
            $orgStock = StoreOrgStock::make()->action(
                $organisation,
                $stock,
                OrgStock::factory()->definition()
            );
        }
        $orgStocks[] = $orgStock;
    }

    return $orgStocks;
}

/**
 * @throws \Throwable
 */
function createProduct(Shop $shop): array
{
    $tradeUnits = createTradeUnits($shop->group);
    $stocks     = createStocks($shop->group);
    $orgStocks  = createOrgStocks($shop->organisation, $stocks);

    $department = $shop->productCategories()->where('type', ProductCategoryTypeEnum::DEPARTMENT)->first();
    if (!$department) {
        $departmentData = ProductCategory::factory()->definition();
        data_set($departmentData, 'type', ProductCategoryTypeEnum::DEPARTMENT->value);
        $department = StoreProductCategory::make()->action(
            $shop,
            $departmentData
        );
    }

    $family = $shop->productCategories()->where('type', ProductCategoryTypeEnum::FAMILY)->first();
    if (!$family) {
        $familyData = ProductCategory::factory()->definition();
        data_set($familyData, 'type', ProductCategoryTypeEnum::FAMILY->value);
        $family = StoreProductCategory::make()->action(
            $department,
            $familyData
        );
    }


    $product = $shop->products()->orderBy('id')->first();
    if (!$product) {
        $productData = array_merge(
            Product::factory()->definition(),
            [
                'trade_units' => [
                    [
                        'id'       => $tradeUnits[0]->id,
                        'quantity' => 1
                    ]
                ],
                'price'       => 100,
            ]
        );
        $product     = StoreProduct::make()->action(
            $family,
            $productData
        );

        $product     = UpdateProduct::make()->action(
            $product,
            [
                'state'  => ProductStateEnum::ACTIVE,
            ]
        );
    }

    return [
        $orgStocks,
        $product
    ];
}

/**
 * @throws \Throwable
 */
function createOrder(Customer $customer, Product $product): Order
{
    $order = $customer->organisation->orders()->first();
    if (!$order) {
        $arrayData = [
            'reference'        => '123456',
            'date'             => date('Y-m-d'),
            'customer_id'      => $customer->id,
            'delivery_address' => new Address(Address::factory()->definition()),
            'billing_address'  => new Address(Address::factory()->definition()),
        ];

        $order = StoreOrder::make()->action($customer, $arrayData);

        $transactionData = Transaction::factory()->definition();
        $item            = $product->historicAsset;
        StoreTransaction::make()->action($order, $item, $transactionData);
    }

    return $order;
}

function createWebsite(Shop $shop): Website
{
    if ($website = $shop->website) {
        return $website;
    }

    return StoreWebsite::make()->action(
        $shop,
        Website::factory()->definition()
    );
}

/**
 * @throws \Throwable
 */
function createWebUser(Customer $customer): WebUser
{
    $webUser = $customer->webUsers()->first();
    if (!$webUser) {
        data_set($storeData, 'username', 'test');
        data_set($storeData, 'email', 'test@testmail.com');
        data_set($storeData, 'password', 'test');

        $webUser = StoreWebUser::make()->action(
            $customer,
            $storeData
        );
    }

    return $webUser;
}
