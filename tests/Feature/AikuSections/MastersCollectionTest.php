<?php

use App\Actions\Masters\MasterProductCategory\StoreMasterProductCategory;
use App\Actions\Masters\MasterProductCategory\UpdateMasterProductCategory;
use App\Actions\Masters\MasterProductCategory\DeleteMasterProductCategory;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Validation\ValidationException;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $group = group();

    $this->masterShop = MasterShop::firstOrCreate(
        ['code' => 'TEST-SHOP'],
        [
            'name' => 'Test Master Shop',
            'group_id' => $group->id,
            'type' => ShopTypeEnum::B2C->value,
        ]
    );
});

test('can create a department category', function () {
    $data = [
        'code' => 'DEPT-01',
        'name' => 'Electronics',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT->value,
    ];

    $department = StoreMasterProductCategory::make()->action($this->masterShop, $data);

    expect($department)->toBeInstanceOf(MasterProductCategory::class)
        ->and($department->code)->toBe('DEPT-01')
        ->and($department->name)->toBe('Electronics')
        ->and($department->type)->toBe(MasterProductCategoryTypeEnum::DEPARTMENT)
        ->and($department->master_shop_id)->toBe($this->masterShop->id);

    $this->assertDatabaseHas('master_product_categories', [
        'id' => $department->id,
        'code' => 'DEPT-01',
    ]);
});

test('can create a sub-department under a department', function () {
    $department = MasterProductCategory::create([
        'code' => 'DEPT-01',
        'name' => 'Electronics',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
        'master_shop_id' => $this->masterShop->id,
        'group_id' => $this->masterShop->group_id,
    ]);

    $subDeptData = [
        'code' => 'SUB-DEPT-01',
        'name' => 'Smartphones',
        'type' => MasterProductCategoryTypeEnum::SUB_DEPARTMENT->value,
        'master_department_id' => $department->id,
    ];

    $subDepartment = StoreMasterProductCategory::make()->action($this->masterShop, $subDeptData);

    expect($subDepartment->type)->toBe(MasterProductCategoryTypeEnum::SUB_DEPARTMENT)
        ->and($subDepartment->parent->id)->toBe($department->id)
        ->and($subDepartment->masterDepartment->id)->toBe($department->id);
});

test('can update a product category', function () {
    $category = MasterProductCategory::create([
        'code' => 'CAT-TO-UPDATE',
        'name' => 'Original Name',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
        'master_shop_id' => $this->masterShop->id,
        'group_id' => $this->masterShop->group_id,
    ]);

    $updateData = [
        'name' => 'Updated Category Name',
        'description' => 'This is an updated description.',
    ];

    $updatedCategory = UpdateMasterProductCategory::make()->action($category, $updateData);

    expect($updatedCategory->name)->toBe('Updated Category Name')
        ->and($updatedCategory->description)->toBe('This is an updated description.');

    $this->assertDatabaseHas('master_product_categories', [
        'id' => $category->id,
        'name' => 'Updated Category Name',
    ]);
});

test('can delete a product category', function () {
    $category = MasterProductCategory::create([
        'code' => 'CAT-TO-DELETE',
        'name' => 'Category to Delete',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
        'master_shop_id' => $this->masterShop->id,
        'group_id' => $this->masterShop->group_id,
    ]);
    $categoryId = $category->id;

    $deletedCategory = DeleteMasterProductCategory::make()->action($category);
    expect($deletedCategory->trashed())->toBeTrue();

    $this->assertSoftDeleted('master_product_categories', [
        'id' => $categoryId,
    ]);
});

test('can store and retrieve translated attributes', function () {
    $category = MasterProductCategory::create([
        'code' => 'CAT-I8N',
        'name' => 'Default Name',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT,
        'master_shop_id' => $this->masterShop->id,
        'group_id' => $this->masterShop->group_id,
    ]);

    $category->setTranslation('name_i8n', 'en', 'English Name');
    $category->setTranslation('name_i8n', 'id', 'Nama Indonesia');
    $category->save();

    $freshCategory = MasterProductCategory::find($category->id);
    expect($freshCategory->getTranslation('name_i8n', 'en'))->toBe('English Name')
        ->and($freshCategory->getTranslation('name_i8n', 'id'))->toBe('Nama Indonesia');

    app()->setLocale('id');
    expect($freshCategory->name_i8n)->toBe('Nama Indonesia');
});

test('cannot create category without required fields', function () {
    expect(function () {
        StoreMasterProductCategory::make()->action($this->masterShop, ['name' => 'test']);
    })->toThrow(ValidationException::class);
});

test('cannot create category with duplicate code in the same shop', function () {
    $first = StoreMasterProductCategory::make()->action($this->masterShop, [
        'code' => 'DUPLICATE-CODE',
        'name' => 'First Category',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT->value,
    ]);

    // Optional: soft delete
    $first->delete();

    // Act
    $second = StoreMasterProductCategory::make()->action($this->masterShop, [
        'code' => 'DUPLICATE-CODE',
        'name' => 'Second Category',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT->value,
    ]);

    expect($second)->not()->toBeNull();

    // Act: try to create second with same code
    $initialCount = MasterProductCategory::where('code', 'DUPLICATE-CODE')->count();

    try {
        StoreMasterProductCategory::make()->action($this->masterShop, [
            'code' => 'DUPLICATE-CODE',
            'name' => 'Second Category',
            'type' => MasterProductCategoryTypeEnum::DEPARTMENT->value,
        ]);
    } catch (\Throwable $e) {
        // Optional: Log or assert the exception type here if needed
    }

    // Assert: no duplicate was created
    $finalCount = MasterProductCategory::where('code', 'DUPLICATE-CODE')->count();

    expect($finalCount)->toBe($initialCount);
});
