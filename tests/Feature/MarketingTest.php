<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 08:09:16 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Tests\Feature;

use App\Actions\Marketing\Department\StoreDepartment;
use App\Actions\Marketing\Department\UpdateDepartment;
use App\Actions\Marketing\Product\DeleteProduct;
use App\Actions\Marketing\Product\StoreProduct;
use App\Actions\Marketing\Product\UpdateProduct;
use App\Actions\Marketing\Shop\StoreShop;
use App\Actions\Marketing\Shop\UpdateShop;
use App\Models\Marketing\Department;
use App\Models\Marketing\Product;
use App\Models\Marketing\Shop;
use App\Models\Tenancy\Tenant;

beforeAll(fn () => loadDB('d3_with_tenants.dump'));

beforeEach(function () {
    $this->tenant = Tenant::where('slug', 'agb')->first();
    $this->tenant->makeCurrent();

});

test('create shop', function () {
    $shop = StoreShop::make()->action(Shop::factory()->definition());
    $this->assertModelExists($shop);
    return $shop;
});

test('update shop', function ($shop) {
    $department = UpdateShop::make()->action($shop, Shop::factory()->definition());

    $this->assertModelExists($department);
})->depends('create shop');

test('create department', function ($shop) {
    $department = StoreDepartment::make()->action($shop, Department::factory()->definition());
    $this->assertModelExists($department);

    return $department;
})->depends('create shop');


test('create sub department', function ($department) {
    $department = StoreDepartment::make()->action($department, Department::factory()->definition());
    $this->assertModelExists($department);

    return $department;
})->depends('create department');

test('update department', function ($department) {
    $department = UpdateDepartment::make()->action($department, Department::factory()->definition());

    $this->assertModelExists($department);
})->depends('create department');

test('create product', function ($shop) {
    $product = StoreProduct::make()->action($shop, Product::factory()->definition());
    $this->assertModelExists($product);

    return $product;
})->depends('create shop')->todo();

test('update product', function ($product) {
    $product = UpdateProduct::make()->action($product, Product::factory()->definition());

    $this->assertModelExists($product);
})->depends('create product')->todo();

test('delete product', function ($product) {
    $product = DeleteProduct::run($product);

    $this->assertModelExists($product);
})->depends('create product')->todo();
