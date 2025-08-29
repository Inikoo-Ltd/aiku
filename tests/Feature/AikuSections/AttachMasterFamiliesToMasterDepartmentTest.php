<?php

use App\Actions\Masters\MasterProductCategory\AttachMasterFamiliesToMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterProductCategory;
use App\Actions\Masters\UpdateMasterFamilyMasterDepartment;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    loadDB();

    $group = group();

    $this->masterShop = MasterShop::firstOrCreate(
        ['code' => 'TEST-SHOP'],
        [
            'name' => 'Test Master Shop',
            'group_id' => $group->id,
            'type' => ShopTypeEnum::B2C->value,
        ]
    );

    $this->department = StoreMasterProductCategory::make()->action($this->masterShop, [
        'code' => 'DEPT-01',
        'name' => 'Electronics',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT->value,
    ]);

    $this->family1 = StoreMasterProductCategory::make()->action($this->masterShop, [
        'code' => 'FAM-01',
        'name' => 'Smartphones',
        'type' => MasterProductCategoryTypeEnum::FAMILY->value,
    ]);

    $this->family2 = StoreMasterProductCategory::make()->action($this->masterShop, [
        'code' => 'FAM-02',
        'name' => 'Laptops',
        'type' => MasterProductCategoryTypeEnum::FAMILY->value,
    ]);
});

test('can attach multiple families to a department', function () {
    UpdateMasterFamilyMasterDepartment::mock()
        ->shouldReceive('action')
        ->andReturnUsing(function (MasterProductCategory $family, array $data) {
            $family->master_department_id = $data['master_department_id'];
            $family->save();
            return $family;
        });

    $familiesToAttach = [
        'master_families' => [
            $this->family1->id,
            $this->family2->id,
        ],
    ];

    AttachMasterFamiliesToMasterDepartment::make()->action($this->department, $familiesToAttach);

    $this->family1->refresh();
    $this->family2->refresh();

    expect($this->family1->master_department_id)->toBe($this->department->id)
        ->and($this->family2->master_department_id)->toBe($this->department->id);
});

test('it throws validation exception if master_families is empty', function () {
    $familiesToAttach = ['master_families' => []];

    expect(fn () => AttachMasterFamiliesToMasterDepartment::make()->action($this->department, $familiesToAttach))
        ->toThrow(ValidationException::class);
});

test('it throws validation exception if a family does not exist', function () {
    $nonExistentId = 99999;
    $familiesToAttach = [
        'master_families' => [
            $this->family1->id,
            $nonExistentId,
        ],
    ];

    expect(fn () => AttachMasterFamiliesToMasterDepartment::make()->action($this->department, $familiesToAttach))
        ->toThrow(ValidationException::class);
});

test('it throws validation exception if a family belongs to another shop', function () {
    $otherShop = MasterShop::create([
        'code' => 'OTHER-SHOP',
        'name' => 'Other Master Shop',
        'group_id' => $this->masterShop->group_id,
        'type' => ShopTypeEnum::B2C->value,
    ]);
    $otherFamily = StoreMasterProductCategory::make()->action($otherShop, [
        'code' => 'FAM-OTHER',
        'name' => 'Other Family',
        'type' => MasterProductCategoryTypeEnum::FAMILY->value,
    ]);

    $familiesToAttach = [
        'master_families' => [
            $this->family1->id,
            $otherFamily->id,
        ],
    ];

    expect(fn () => AttachMasterFamiliesToMasterDepartment::make()->action($this->department, $familiesToAttach))
        ->toThrow(ValidationException::class);
});

test('can re-attach a family to a new department', function () {
    UpdateMasterFamilyMasterDepartment::mock()
        ->shouldReceive('action')
        ->andReturnUsing(function (MasterProductCategory $family, array $data) {
            $family->master_department_id = $data['master_department_id'];
            $family->save();
            return $family;
        });

    $newDepartment = StoreMasterProductCategory::make()->action($this->masterShop, [
        'code' => 'DEPT-02',
        'name' => 'Home Appliances',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT->value,
    ]);


    AttachMasterFamiliesToMasterDepartment::make()->action($this->department, ['master_families' => [$this->family1->id]]);
    $this->family1->refresh();
    expect($this->family1->master_department_id)->toBe($this->department->id);


    AttachMasterFamiliesToMasterDepartment::make()->action($newDepartment, ['master_families' => [$this->family1->id]]);
    $this->family1->refresh();


    expect($this->family1->master_department_id)->toBe($newDepartment->id);
});
