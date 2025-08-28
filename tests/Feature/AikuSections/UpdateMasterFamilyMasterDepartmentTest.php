<?php

namespace Tests\Feature\AikuSections;

use App\Actions\Masters\MasterProductCategory\Hydrators\MasterDepartmentHydrateMasterAssets;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterProductCategoryHydrateMasterFamilies;
use App\Actions\Masters\UpdateMasterFamilyMasterDepartment;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Helpers\Country;
use App\Models\Helpers\Language;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Group;
use App\Models\Sysadmin\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

test('it handles various department update scenarios', function () {
    loadDB();

    $countryForGroup = Country::where('code', 'ES')->firstOrFail();
    $defaultLanguage = Language::where('code', 'en')->firstOrFail();

    $groupId = DB::table('groups')->insertGetId([
        'name'        => 'Test Group',
        'code'        => 'TEST-GROUP',
        'country_id'  => $countryForGroup->id,
        'language_id' => $defaultLanguage->id,
        'timezone_id' => $countryForGroup->timezone_id,
        'currency_id' => $countryForGroup->currency_id,
        'ulid'        => Str::ulid(),
        'slug'        => 'test-group',
        'created_at'  => now(),
        'updated_at'  => now(),
        'limits'      => '{}',
        'data'        => '{}',
        'settings'    => '{}',
    ]);
    $group = Group::find($groupId);


    $user = User::create([
        'email'    => 'test@example.com',
        'username' => 'testuser',
        'slug'     => 'test-user',
        'password' => 'password',
        'group_id' => $group->id,
    ]);

    $masterShop = MasterShop::create([
        'group_id' => $group->id,
        'name'     => 'Test Shop',
        'code'     => 'TEST',
        'type'     => ShopTypeEnum::B2C,
    ]);

    $this->actingAs($user);

    Queue::fake();

    $oldDepartment = MasterProductCategory::create([
        'master_shop_id' => $masterShop->id,
        'group_id'       => $group->id,
        'code'           => 'DEPT-OLD',
        'name'           => 'Old Dept',
        'type'           => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);
    $newDepartment = MasterProductCategory::create([
        'master_shop_id' => $masterShop->id,
        'group_id'       => $group->id,
        'code'           => 'DEPT-NEW',
        'name'           => 'New Dept',
        'type'           => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);
    $familyForScenario1 = MasterProductCategory::create([
        'master_shop_id'       => $masterShop->id,
        'group_id'             => $group->id,
        'code'                 => 'FAM-1',
        'name'                 => 'Family One',
        'type'                 => MasterProductCategoryTypeEnum::FAMILY,
        'master_department_id' => $oldDepartment->id,
        'master_parent_id'     => $oldDepartment->id,
    ]);
    $asset = MasterAsset::create([
        'master_family_id'     => $familyForScenario1->id,
        'master_department_id' => $oldDepartment->id,
        'group_id'             => $group->id,
        'code'                 => 'ASSET-01',
        'name'                 => 'Asset One',
        'type'                 => MasterAssetTypeEnum::PRODUCT,
        'units'                => 1,
    ]);

    UpdateMasterFamilyMasterDepartment::run($familyForScenario1, ['master_department_id' => $newDepartment->id]);
    $familyForScenario1->refresh();

    expect($familyForScenario1->master_department_id)->toBe($newDepartment->id);
    $this->assertDatabaseHas('master_assets', ['id' => $asset->id, 'master_department_id' => null]);
    Queue::assertPushed(MasterDepartmentHydrateMasterAssets::class, 0);
    Queue::assertPushed(MasterProductCategoryHydrateMasterFamilies::class, 0);

    $familyForScenario2 = MasterProductCategory::create([
        'master_shop_id' => $masterShop->id,
        'group_id'       => $group->id,
        'code'           => 'FAM-2',
        'name'           => 'Family Two',
        'type'           => MasterProductCategoryTypeEnum::FAMILY,
    ]);
    $notADepartment = MasterProductCategory::create([
        'master_shop_id' => $masterShop->id,
        'group_id'       => $group->id,
        'code'           => 'NOT-DEPT',
        'name'           => 'Not A Dept',
        'type'           => MasterProductCategoryTypeEnum::FAMILY,
    ]);

    expect(
        fn() => UpdateMasterFamilyMasterDepartment::make()->asController(
            $familyForScenario2,
            new ActionRequest(['master_department_id' => $notADepartment->id])
        )
    )->toThrow(\Error::class);

    Queue::fake();

    $departmentForScenario3 = MasterProductCategory::create([
        'master_shop_id' => $masterShop->id,
        'group_id'       => $group->id,
        'code'           => 'DEPT-3',
        'name'           => 'Dept Three',
        'type'           => MasterProductCategoryTypeEnum::DEPARTMENT,
    ]);
    $oldSubDepartment = MasterProductCategory::create([
        'master_shop_id' => $masterShop->id,
        'group_id'       => $group->id,
        'code'           => 'SUB-DEPT-OLD',
        'name'           => 'Old Sub-Dept',
        'type'           => MasterProductCategoryTypeEnum::SUB_DEPARTMENT,
    ]);
    $familyForScenario3 = MasterProductCategory::create([
        'master_shop_id'           => $masterShop->id,
        'group_id'                 => $group->id,
        'code'                     => 'FAM-3',
        'name'                     => 'Family Three',
        'type'                     => MasterProductCategoryTypeEnum::FAMILY,
        'master_sub_department_id' => $oldSubDepartment->id,
    ]);

    UpdateMasterFamilyMasterDepartment::run($familyForScenario3, ['master_department_id' => $departmentForScenario3->id]);

    $this->assertDatabaseHas('master_product_categories', [
        'id'                       => $familyForScenario3->id,
        'master_department_id'     => $departmentForScenario3->id,
        'master_sub_department_id' => null,
    ]);
});
