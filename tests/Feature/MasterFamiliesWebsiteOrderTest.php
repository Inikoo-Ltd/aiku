<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Catalogue\ProductCategory\ReorderFamiliesInDepartment;
use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Masters\MasterProductCategory\ReorderMasterFamiliesInMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterFamily;
use App\Actions\Masters\MasterProductCategory\StoreMasterSubDepartment;
use App\Actions\Masters\MasterProductCategory\UI\GetMasterDepartmentFamilies;
use App\Actions\Masters\MasterShop\StoreMasterShop;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Language;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $this->group      = createGroup();
    $this->adminGuest = createAdminGuest($this->group);
    list($this->organisation, $this->user, $this->shop) = createShop();
    actingAs($this->adminGuest->getUser());

    $this->masterShop = StoreMasterShop::make()->action($this->group, [
        'type' => ShopTypeEnum::B2B,
        'code' => 'WP'.substr(uniqid(), -6),
        'name' => 'Website Order Master Shop',
    ]);

    $this->shop->updateQuietly([
        'master_shop_id' => $this->masterShop->id,
        'language_id'    => Language::where('code', 'en')->first()->id,
    ]);

    $this->masterDepartment = StoreMasterDepartment::make()->action($this->masterShop, [
        'code' => 'WPD-'.uniqid(),
        'name' => 'website order department',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $this->masterSubDepartment = StoreMasterSubDepartment::make()->action($this->masterDepartment, [
        'code' => 'WPS-'.uniqid(),
        'name' => 'website order sub department',
        'type' => MasterProductCategoryTypeEnum::SUB_DEPARTMENT,
    ]);

    $this->storeMasterFamily = function (MasterProductCategory $parent, string $name) {
        return StoreMasterFamily::make()->action($parent, [
            'code' => 'WPF-'.uniqid(),
            'name' => $name,
            'type' => MasterProductCategoryTypeEnum::FAMILY,
        ]);
    };
});

test('the families of a department include the ones under its sub departments', function () {
    $underDepartment    = ($this->storeMasterFamily)($this->masterDepartment, 'under department');
    $underSubDepartment = ($this->storeMasterFamily)($this->masterSubDepartment, 'under sub department');

    $families = GetMasterDepartmentFamilies::run($this->masterDepartment);

    expect($families->pluck('id')->sort()->values()->all())
        ->toBe(collect([$underDepartment->id, $underSubDepartment->id])->sort()->values()->all());
});

test('reordering master families writes contiguous positions and drops the ones left out', function () {
    $first  = ($this->storeMasterFamily)($this->masterDepartment, 'first');
    $second = ($this->storeMasterFamily)($this->masterSubDepartment, 'second');
    $third  = ($this->storeMasterFamily)($this->masterDepartment, 'third');

    ReorderMasterFamiliesInMasterDepartment::make()->action($this->masterDepartment, [
        'master_families' => [$third->id, $first->id, $second->id],
    ]);

    expect($third->fresh()->website_position)->toBe(1)
        ->and($first->fresh()->website_position)->toBe(2)
        ->and($second->fresh()->website_position)->toBe(3);

    ReorderMasterFamiliesInMasterDepartment::make()->action($this->masterDepartment, [
        'master_families' => [$second->id, $first->id],
    ]);

    expect($second->fresh()->website_position)->toBe(1)
        ->and($first->fresh()->website_position)->toBe(2)
        ->and($third->fresh()->website_position)->toBeNull();
});

test('reordering refuses master families that do not belong to the department', function () {
    $otherDepartment = StoreMasterDepartment::make()->action($this->masterShop, [
        'code' => 'WPD-'.uniqid(),
        'name' => 'other department',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);

    $stranger = ($this->storeMasterFamily)($otherDepartment, 'stranger');

    expect(fn () => ReorderMasterFamiliesInMasterDepartment::make()->action($this->masterDepartment, [
        'master_families' => [$stranger->id],
    ]))->toThrow(ValidationException::class);
});

test('the hand picked order cascades to the shop families following the master', function () {
    $first  = ($this->storeMasterFamily)($this->masterDepartment, 'first');
    $second = ($this->storeMasterFamily)($this->masterDepartment, 'second');

    $shopFamilies = [];
    foreach ([$first, $second] as $masterFamily) {
        $familyData = ProductCategory::factory()->definition();
        data_set($familyData, 'type', ProductCategoryTypeEnum::FAMILY->value);
        data_set($familyData, 'master_product_category_id', $masterFamily->id);

        $shopFamilies[$masterFamily->id] = StoreProductCategory::make()->action($this->shop, $familyData);
    }

    ReorderMasterFamiliesInMasterDepartment::make()->action($this->masterDepartment, [
        'master_families' => [$second->id, $first->id],
    ]);

    expect($shopFamilies[$second->id]->fresh()->website_position)->toBe(1)
        ->and($shopFamilies[$first->id]->fresh()->website_position)->toBe(2);
});

test('a shop that does not follow the master order keeps its own positions', function () {
    $masterFamily = ($this->storeMasterFamily)($this->masterDepartment, 'not followed');

    $familyData = ProductCategory::factory()->definition();
    data_set($familyData, 'type', ProductCategoryTypeEnum::FAMILY->value);
    data_set($familyData, 'master_product_category_id', $masterFamily->id);
    $shopFamily = StoreProductCategory::make()->action($this->shop, $familyData);

    $settings = $this->shop->settings;
    data_set($settings, 'catalog.family_order_follow_master', false);
    $this->shop->updateQuietly(['settings' => $settings]);

    ReorderMasterFamiliesInMasterDepartment::make()->action($this->masterDepartment, [
        'master_families' => [$masterFamily->id],
    ]);

    expect($masterFamily->fresh()->website_position)->toBe(1)
        ->and($shopFamily->fresh()->website_position)->toBeNull();
});

test('reordering a shop department is refused while it follows the master order', function () {
    [, $product] = createProduct($this->shop);
    $department = $product->department;

    $familyData = ProductCategory::factory()->definition();
    data_set($familyData, 'type', ProductCategoryTypeEnum::FAMILY->value);
    $family = StoreProductCategory::make()->action($department, $familyData);

    expect(fn () => ReorderFamiliesInDepartment::make()->action($department->refresh(), [
        'families' => [$family->id],
    ]))->toThrow(HttpException::class);
});

test('a shop department that has opted out orders its own families', function () {
    [, $product] = createProduct($this->shop);
    $department = $product->department;

    $families = [];
    foreach (['first', 'second'] as $label) {
        $familyData = ProductCategory::factory()->definition();
        data_set($familyData, 'type', ProductCategoryTypeEnum::FAMILY->value);
        $families[$label] = StoreProductCategory::make()->action($department, $familyData);
    }

    $settings = $this->shop->settings;
    data_set($settings, 'catalog.family_order_follow_master', false);
    $this->shop->updateQuietly(['settings' => $settings]);

    ReorderFamiliesInDepartment::make()->action($department->refresh(), [
        'families' => [$families['second']->id, $families['first']->id],
    ]);

    expect($families['second']->fresh()->website_position)->toBe(1)
        ->and($families['first']->fresh()->website_position)->toBe(2);
});
