<?php

use App\Actions\Masters\MasterProductCategory\AttachMasterFamiliesToMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreMasterProductCategory;
use App\Actions\Masters\UpdateMasterFamilyMasterDepartment;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Validation\ValidationException;

// Menggunakan `beforeEach` dengan `loadDB()` untuk me-reset database sebelum setiap tes.
beforeEach(function () {
    loadDB();

    $group = group();

    // Membuat MasterShop sebagai dasar untuk semua kategori
    $this->masterShop = MasterShop::firstOrCreate(
        ['code' => 'TEST-SHOP'],
        [
            'name' => 'Test Master Shop',
            'group_id' => $group->id,
            'type' => ShopTypeEnum::B2C->value,
        ]
    );

    // Membuat MasterDepartment yang akan menjadi target penautan
    $this->department = StoreMasterProductCategory::make()->action($this->masterShop, [
        'code' => 'DEPT-01',
        'name' => 'Electronics',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT->value,
    ]);

    // Membuat beberapa MasterFamily yang akan ditautkan
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
    // FIX: Mengubah mock untuk meniru perilaku action yang asli.
    // Ini akan memperbarui database dan mengembalikan objek yang benar untuk menghindari TypeError.
    UpdateMasterFamilyMasterDepartment::mock()
        ->shouldReceive('action')
        ->andReturnUsing(function (MasterProductCategory $family, array $data) {
            // Secara manual meniru pembaruan database yang seharusnya dilakukan oleh action
            $family->master_department_id = $data['master_department_id'];
            $family->save();
            return $family; // Mengembalikan objek yang benar
        });

    $familiesToAttach = [
        'master_families' => [
            $this->family1->id,
            $this->family2->id,
        ],
    ];

    // Menjalankan action
    AttachMasterFamiliesToMasterDepartment::make()->action($this->department, $familiesToAttach);

    // Memuat ulang data dari database untuk memastikan perubahan tersimpan
    $this->family1->refresh();
    $this->family2->refresh();

    // Memastikan department_id pada kedua family sudah benar
    expect($this->family1->master_department_id)->toBe($this->department->id)
        ->and($this->family2->master_department_id)->toBe($this->department->id);
});

test('it throws validation exception if master_families is empty', function () {
    $familiesToAttach = ['master_families' => []];

    // Mengharapkan ValidationException karena array kosong
    expect(fn() => AttachMasterFamiliesToMasterDepartment::make()->action($this->department, $familiesToAttach))
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

    // Mengharapkan ValidationException karena ada ID yang tidak valid
    expect(fn() => AttachMasterFamiliesToMasterDepartment::make()->action($this->department, $familiesToAttach))
        ->toThrow(ValidationException::class);
});

test('it throws validation exception if a family belongs to another shop', function () {
    // Membuat shop dan family baru yang berbeda
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
            $otherFamily->id, // Family ini milik shop lain
        ],
    ];

    // Mengharapkan ValidationException karena family tidak berada di shop yang sama
    expect(fn() => AttachMasterFamiliesToMasterDepartment::make()->action($this->department, $familiesToAttach))
        ->toThrow(ValidationException::class);
});

test('can re-attach a family to a new department', function () {
    // FIX: Mengubah mock untuk meniru perilaku action yang asli.
    UpdateMasterFamilyMasterDepartment::mock()
        ->shouldReceive('action')
        ->andReturnUsing(function (MasterProductCategory $family, array $data) {
            // Secara manual meniru pembaruan database
            $family->master_department_id = $data['master_department_id'];
            $family->save();
            return $family; // Mengembalikan objek yang benar
        });

    // Membuat department kedua
    $newDepartment = StoreMasterProductCategory::make()->action($this->masterShop, [
        'code' => 'DEPT-02',
        'name' => 'Home Appliances',
        'type' => MasterProductCategoryTypeEnum::DEPARTMENT->value,
    ]);

    // Menautkan family1 ke department pertama
    AttachMasterFamiliesToMasterDepartment::make()->action($this->department, ['master_families' => [$this->family1->id]]);
    $this->family1->refresh();
    expect($this->family1->master_department_id)->toBe($this->department->id);

    // Menautkan kembali family1 ke department yang baru
    AttachMasterFamiliesToMasterDepartment::make()->action($newDepartment, ['master_families' => [$this->family1->id]]);
    $this->family1->refresh();

    // Memastikan family1 sekarang tertaut ke department yang baru
    expect($this->family1->master_department_id)->toBe($newDepartment->id);
});
