<?php

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\CRM\Customer\StoreCustomer;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\CRM\Customer;
use App\Models\Helpers\Address;
use App\Models\HumanResources\Employee;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Facades\DB;

beforeAll(function () {
    loadDB();
});

test('seed demo data', function () {
    [$organisation, $user, $shop] = createShop();
    $group = $organisation->group;
    $shop->update(['name' => 'Northwind Home & Garden', 'code' => 'nhg']);
    $organisation->update(['name' => 'Acme Trading Ltd']);
    createWarehouse();
    createWebsite($shop);
    $user->update(['username' => 'demo', 'password' => 'demo1234']);

    [$orgStocks, $firstProduct] = createProduct($shop);
    $tradeUnits = createTradeUnits($group);

    $catalogue = [
        'Candles & Home Fragrance' => [
            'Scented Candles' => ['Lavender Soy Candle 200g', 'Vanilla Bean Candle 200g', 'Cedar & Sage Candle 350g', 'Citrus Grove Travel Tin'],
            'Diffusers' => ['Reed Diffuser Fresh Linen', 'Reed Diffuser Wild Fig', 'Oil Burner Ceramic White'],
        ],
        'Bath & Body' => [
            'Soap Bars' => ['Oatmeal Soap Bar 100g', 'Charcoal Soap Bar 100g', 'Rose Petal Soap Bar 100g', 'Goat Milk Soap Bar 100g'],
            'Bath Salts' => ['Himalayan Bath Salts 500g', 'Eucalyptus Bath Soak 400g'],
        ],
        'Garden' => [
            'Planters' => ['Terracotta Planter 15cm', 'Terracotta Planter 25cm', 'Hanging Planter Jute'],
            'Tools' => ['Hand Trowel Ash Handle', 'Pruning Shears Bypass', 'Garden Gloves Leather L'],
        ],
    ];

    $products = [];
    $code = 1000;
    foreach ($catalogue as $departmentName => $families) {
        $department = StoreProductCategory::make()->action($shop, array_merge(ProductCategory::factory()->definition(), [
            'type' => ProductCategoryTypeEnum::DEPARTMENT->value, 'name' => $departmentName, 'code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $departmentName), 0, 4)),
        ]));
        foreach ($families as $familyName => $items) {
            $family = StoreProductCategory::make()->action($department, array_merge(ProductCategory::factory()->definition(), [
                'type' => ProductCategoryTypeEnum::FAMILY->value, 'name' => $familyName, 'code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $familyName), 0, 4)).rand(10, 99),
            ]));
            foreach ($items as $itemName) {
                $code++;
                $product = StoreProduct::make()->action($family, array_merge(Product::factory()->definition(), [
                    'code' => 'NHG-'.$code,
                    'name' => $itemName,
                    'price' => rand(250, 3900) / 100,
                    'rrp' => rand(600, 8900) / 100,
                    'trade_units' => [['id' => $tradeUnits[array_rand($tradeUnits)]->id, 'quantity' => 1]],
                ]));
                $products[] = UpdateProduct::make()->action($product, ['state' => ProductStateEnum::ACTIVE]);
            }
        }
    }

    $customers = [];
    for ($i = 0; $i < 28; $i++) {
        $customers[] = StoreCustomer::make()->action($shop, array_merge(Customer::factory()->definition(), ['is_fulfilment' => false]));
    }

    foreach ($customers as $k => $customer) {
        $orderCount = rand(1, 6);
        for ($j = 0; $j < $orderCount; $j++) {
            $date = now()->subDays(rand(0, 365))->format('Y-m-d');
            $net = rand(1800, 84000) / 100;
            createDispatchedOrderFor($customer, $shop, $date, 'dispatched', $net);
            createInvoiceFor($customer, $shop, $date, $net);
        }
    }

    foreach (array_slice($customers, 0, 8) as $customer) {
        $order = StoreOrder::make()->action($customer, [
            'date' => now()->subDays(rand(0, 5)),
            'customer_id' => $customer->id,
            'delivery_address' => new Address(Address::factory()->definition()),
            'billing_address' => new Address(Address::factory()->definition()),
        ]);
        foreach (array_rand($products, rand(2, 5)) as $idx) {
            StoreTransaction::make()->action($order, $products[$idx]->historicAsset, array_merge(Transaction::factory()->definition(), ['quantity_ordered' => rand(1, 24)]));
        }
        if (rand(0, 1)) {
            SubmitOrder::make()->action($order->refresh());
        }
    }

    for ($i = 0; $i < 12; $i++) {
        StoreEmployee::make()->action($organisation, array_merge(Employee::factory()->definition(), [
            'worker_number' => 'W'.(1000 + $i),
            'job_title' => fake()->randomElement(['Warehouse Operative', 'Picker', 'Customer Service', 'Accounts', 'Buyer']),
        ]));
    }

    expect(DB::table('orders')->count())->toBeGreaterThan(50);
});
