<?php

use App\Models\SysAdmin\User;
use Pest\Browser\ServerManager;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    config(['session.driver' => 'file']);
    actingAs(User::where('username', 'demo')->firstOrFail());
});

test('capture readme screenshots', function () {
    $port = ServerManager::instance()->http()->port;
    $base = "http://app.aiku.test:{$port}";
    config(['app.url' => $base]);
    app('url')->useOrigin($base);
    app('url')->useAssetOrigin($base);

    $pages = [
        'group-dashboard' => '/dashboard',
        'shop-dashboard' => '/org/acme/shops/nhg/dashboard',
        'orders' => '/org/acme/shops/nhg/ordering/orders',
        'customers' => '/org/acme/shops/nhg/crm/customers',
        'order' => '/org/acme/shops/nhg/ordering/orders/000008',
        'families' => '/org/acme/shops/nhg/catalogue/families',
        'departments' => '/org/acme/shops/nhg/catalogue/departments',
        'invoices' => '/org/acme/shops/nhg/ordering/invoices',
        'delivery-notes' => '/org/acme/warehouses/main/dispatching/delivery-notes',
        'website' => '/org/acme/shops/nhg/web/websites',
        'marketing' => '/org/acme/shops/nhg/marketing',
        'masters' => '/masters',
        'warehouse-inventory' => '/org/acme/warehouses/main/inventory',
        'employees' => '/org/acme/hr/employees',
    ];

    foreach ($pages as $name => $path) {
        $page = visit($base.$path)->on()->macbookAir();
        $page->wait(2);
        $page->screenshot(false, 'readme-'.$name);
    }

    expect(true)->toBeTrue();
});
