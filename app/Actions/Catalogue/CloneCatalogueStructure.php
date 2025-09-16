<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 26 Jul 2025 16:55:04 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue;

use App\Actions\Catalogue\ProductCategory\AttachFamiliesToDepartment;
use App\Actions\Catalogue\ProductCategory\AttachFamiliesToSubDepartment;
use App\Actions\Catalogue\ProductCategory\DeleteProductCategory;
use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Catalogue\ProductCategory\StoreProductCategoryWebpage;
use App\Actions\Catalogue\ProductCategory\StoreSubDepartment;
use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Masters\MasterProductCategory\AttachMasterFamiliesToMasterDepartment;
use App\Actions\Masters\MasterProductCategory\AttachMasterFamiliesToMasterSubDepartment;
use App\Actions\Masters\MasterProductCategory\DeleteMasterProductCategory;
use App\Actions\Masters\MasterProductCategory\StoreMasterProductCategory;
use App\Actions\Masters\MasterProductCategory\StoreMasterSubDepartment;
use App\Actions\Masters\MasterProductCategory\UpdateMasterProductCategory;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneCatalogueStructure
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(MasterShop|Shop $fromShop, MasterShop|Shop $shop, $deleteMissing = false): void
    {
        $this->cloneDepartments($fromShop, $shop);
        $this->cloneSubDepartments($fromShop, $shop);

        if ($deleteMissing) {
            $this->deleteDepartmentsNotFoundInFromShop($fromShop, $shop);
            $this->deleteSubDepartmentsNotFoundInFromShop($fromShop, $shop);
        }

        $this->attachFamiliesToDepartments($fromShop, $shop);
        $this->attachFamiliesToSubDepartments($fromShop, $shop);
    }

    public function attachFamiliesToSubDepartments(MasterShop|Shop $fromShop, MasterShop|Shop $shop): void
    {
        $subDepartments = $this->getSubDepartments($shop);
        foreach ($subDepartments as $subDepartment) {
            $fromSubDepartment = $this->getEquivalentProductCategory($fromShop, $subDepartment->code, 'sub_department');
            if ($fromSubDepartment) {
                $fromFamilies = $this->getCategories($fromSubDepartment, 'family');
                foreach ($fromFamilies as $fromFamily) {
                    $family = $this->getEquivalentProductCategory($shop, $fromFamily->code, 'family');
                    if ($family) {
                        $this->attachFamily($subDepartment, $family);
                    }
                }
            }
        }
    }

    public function attachFamiliesToDepartments(MasterShop|Shop $fromShop, MasterShop|Shop $shop): void
    {
        $departments = $this->getDepartments($shop);
        foreach ($departments as $department) {
            $fromDepartment = $this->getEquivalentProductCategory($fromShop, $department->code, 'department');
            if ($fromDepartment) {
                $fromFamilies = $this->getCategories($fromDepartment, 'family');
                foreach ($fromFamilies as $fromFamily) {
                    $family = $this->getEquivalentProductCategory($shop, $fromFamily->code, 'family');
                    if ($family) {
                        $this->attachFamily($department, $family);
                    }
                }
            }
        }
    }

    public function getCategories(MasterProductCategory|ProductCategory $parent, string $type): array
    {
        $fromFamilies = [];
        if ($parent instanceof ProductCategory) {
            foreach (
                DB::table('product_categories')
                    ->where('type', $type)
                    ->where('parent_id', $parent->id)->get() as $familyData
            ) {
                $family = ProductCategory::find($familyData->id);
                if ($family) {
                    $fromFamilies[] = $family;
                }
            }
        } else {
            foreach (
                DB::table('master_product_categories')
                    ->where('type', $type)
                    ->where('parent_id', $parent->id)->get() as $familyData
            ) {
                $masterFamily = MasterProductCategory::find($familyData->id);
                if ($masterFamily) {
                    $fromFamilies[] = $masterFamily;
                }
            }
        }

        return $fromFamilies;
    }

    public function getEquivalentProductCategory(MasterShop|Shop $baseShop, string $needle, string $type): null|MasterProductCategory|ProductCategory
    {
        $family = null;
        if ($baseShop instanceof Shop) {
            $foundFamilyData = DB::table('product_categories')
                ->where('shop_id', $baseShop->id)
                ->where('type', $type)
                ->whereRaw("lower(code) = lower(?)", [$needle])->first();
            if ($foundFamilyData) {
                $family = ProductCategory::find($foundFamilyData->id);
            }
        } else {
            $foundFamilyData = DB::table('master_product_categories')
                ->where('master_shop_id', $baseShop->id)
                ->where('type', $type)
                ->whereRaw("lower(code) = lower(?)", [$needle])->first();

            if ($foundFamilyData) {
                $family = MasterProductCategory::find($foundFamilyData->id);
            }
        }

        return $family;
    }


    /**
     * @throws \Throwable
     */
    public function cloneDepartments(MasterShop|Shop $fromShop, MasterShop|Shop $shop): void
    {
        /** @var ProductCategory|MasterProductCategory $fromDepartment */
        foreach ($fromShop->departments() as $fromDepartment) {
            if ($shop instanceof Shop) {
                $this->upsertDepartment($shop, $fromDepartment);
            } else {
                $this->upsertMasterDepartment($shop, $fromDepartment);
            }
        }
    }

    public function deleteDepartmentsNotFoundInFromShop(MasterShop|Shop $fromShop, MasterShop|Shop $shop): void
    {
        if ($shop instanceof Shop) {
            $departments = $shop->departments();
        } else {
            $departments = $shop->getMasterDepartments();
        }


        if ($fromShop instanceof Shop) {
            $codes = $fromShop->departments()->pluck('code');
        } else {
            $codes = $fromShop->getMasterDepartments()->pluck('code');
        }


        /** @var ProductCategory|MasterProductCategory $fromDepartment */
        foreach ($departments as $department) {
            if (!in_array($department->code, $codes->toArray())) {
                if ($department instanceof MasterProductCategory) {
                    DeleteMasterProductCategory::run($department);
                } else {
                    DeleteProductCategory::run($department);
                }
            }
        }
    }

    public function deleteSubDepartmentsNotFoundInFromShop(MasterShop|Shop $fromShop, MasterShop|Shop $shop): void
    {
        if ($shop instanceof Shop) {
            $departments = $shop->subDepartments();
        } else {
            $departments = $shop->getMasterSubDepartments();
        }


        if ($fromShop instanceof Shop) {
            $codes = $fromShop->subDepartments()->pluck('code');
        } else {
            $codes = $fromShop->getMasterSubDepartments()->pluck('code');
        }


        /** @var ProductCategory|MasterProductCategory $fromDepartment */
        foreach ($departments as $department) {
            if (!in_array($department->code, $codes->toArray())) {
                if ($department instanceof MasterProductCategory) {
                    DeleteMasterProductCategory::run($department);
                } else {
                    DeleteProductCategory::run($department);
                }
            }
        }
    }


    /**
     * @throws \Throwable
     */
    public function upsertDepartment(Shop $shop, ProductCategory|MasterProductCategory $department): ?ProductCategory
    {
        $code = $department->code;

        $foundDepartmentData = DB::table('product_categories')
            ->where('shop_id', $shop->id)
            ->where('type', ProductCategoryTypeEnum::DEPARTMENT->value)
            ->whereRaw("lower(code) = lower(?)", [$code])->first();


        if (!$foundDepartmentData) {

            $foundDepartment = StoreProductCategory::make()->action(
                $shop,
                [
                    'code'        => $department->code,
                    'name'        => $department->name,
                    'description' => $department->description,
                    'type'        => ProductCategoryTypeEnum::DEPARTMENT
                ]
            );
        } else {
            $foundDepartment = ProductCategory::find($foundDepartmentData->id);
            if($foundDepartment) {
                $dataToUpdate = [
                    'code' => $department->code,
                    'name' => $department->name,
                ];
                if ($department->description) {
                    data_set($dataToUpdate, 'description', $department->description);
                }

                $foundDepartment = UpdateProductCategory::make()->action(
                    $foundDepartment,
                    $dataToUpdate
                );
            }
        }

        if ($foundDepartment && !$foundDepartment->webpage) {
            $webpage = StoreProductCategoryWebpage::make()->action($foundDepartment);
            PublishWebpage::make()->action(
                $webpage,
                [
                    'comment' => 'Published after cloning',
                ]
            );
        }


        return $foundDepartment;
    }


    /**
     * @throws \Throwable
     */
    public function upsertMasterDepartment(MasterShop $masterShop, ProductCategory|MasterProductCategory $department): MasterProductCategory
    {
        $code = $department->code;

        $foundMasterDepartmentData = DB::table('master_product_categories')
            ->where('master_shop_id', $masterShop->id)
            ->where('type', MasterProductCategoryTypeEnum::DEPARTMENT->value)
            ->whereRaw("lower(code) = lower(?)", [$code])->first();


        if (!$foundMasterDepartmentData) {
            $foundMasterDepartment = StoreMasterProductCategory::make()->action(
                $masterShop,
                [
                    'code'        => $department->code,
                    'name'        => $department->name,
                    'description' => $department->description,
                    'type'        => MasterProductCategoryTypeEnum::DEPARTMENT
                ]
            );
        } else {
            $foundMasterDepartment = MasterProductCategory::find($foundMasterDepartmentData->id);

            $dataToUpdate = [
                'code' => $department->code,
                'name' => $department->name,
            ];
            if ($department->description) {
                data_set($dataToUpdate, 'description', $department->description);
            }

            $foundMasterDepartment = UpdateMasterProductCategory::make()->action(
                $foundMasterDepartment,
                $dataToUpdate
            );
        }

        return $foundMasterDepartment;
    }


    /**
     * @throws \Throwable
     */
    public function upsertSubDepartment(ProductCategory $department, ProductCategory|MasterProductCategory $subDepartment): ProductCategory
    {
        $code                   = $subDepartment->code;
        $foundSubDepartmentData = DB::table('product_categories')
            ->where('shop_id', $department->shop->id)
            ->where('type', ProductCategoryTypeEnum::SUB_DEPARTMENT->value)
            ->whereRaw("lower(code) = lower(?)", [$code])->first();


        if (!$foundSubDepartmentData) {
            /** @var ProductCategory $department */
            $department = $this->upsertDepartment($department->shop, $subDepartment->parent);


            $subDepartment = StoreSubDepartment::make()->action(
                $department,
                [
                    'code'        => $subDepartment->code,
                    'name'        => $subDepartment->name,
                    'description' => $subDepartment->description,
                ]
            );
        } else {
            $foundSubDepartment = ProductCategory::find($foundSubDepartmentData->id);
            $dataToUpdate       = [
                'code' => $subDepartment->code,
                'name' => $subDepartment->name,
            ];
            if ($subDepartment->description) {
                data_set($dataToUpdate, 'description', $subDepartment->description);
            }
            $subDepartment = UpdateProductCategory::make()->action(
                $foundSubDepartment,
                $dataToUpdate
            );
        }

        if (!$subDepartment->webpage) {
            $webpage = StoreProductCategoryWebpage::make()->action($subDepartment);
            PublishWebpage::make()->action(
                $webpage,
                [
                    'comment' => 'Published after cloning',
                ]
            );
        }

        return $subDepartment;
    }

    /**
     * @throws \Throwable
     */
    public function upsertMasterSubDepartment(MasterProductCategory $masterDepartment, ProductCategory|MasterProductCategory $toSubDepartment): MasterProductCategory
    {
        $code                         = $toSubDepartment->code;
        $foundMasterSubDepartmentData = DB::table('master_product_categories')
            ->where('master_shop_id', $masterDepartment->master_shop_id)
            ->where('type', MasterProductCategoryTypeEnum::SUB_DEPARTMENT->value)
            ->whereRaw("lower(code) = lower(?)", [$code])->first();

        if (!$foundMasterSubDepartmentData) {
            /** @var MasterProductCategory $masterDepartment */
            $masterDepartment = $this->upsertMasterDepartment($masterDepartment->masterShop, $toSubDepartment->parent);


            $toSubDepartment = StoreMasterSubDepartment::make()->action(
                $masterDepartment,
                [
                    'code'        => $toSubDepartment->code,
                    'name'        => $toSubDepartment->name,
                    'description' => $toSubDepartment->description,
                ]
            );
        } else {
            $foundMasterSubDepartment = MasterProductCategory::find($foundMasterSubDepartmentData->id);
            $dataToUpdate             = [
                'code' => $toSubDepartment->code,
                'name' => $toSubDepartment->name,
            ];
            if ($toSubDepartment->description) {
                data_set($dataToUpdate, 'description', $toSubDepartment->description);
            }
            $toSubDepartment = UpdateMasterProductCategory::make()->action(
                $foundMasterSubDepartment,
                $dataToUpdate
            );
        }

        return $toSubDepartment;
    }


    /**
     * @throws \Throwable
     */
    public function cloneSubDepartments(MasterShop|Shop $fromShop, MasterShop|Shop $shop): void
    {
        if ($fromShop instanceof Shop) {
            $fromSubDepartments = $fromShop->subDepartments();
        } else {
            $fromSubDepartments = $fromShop->getMasterSubDepartments();
        }


        foreach ($fromSubDepartments as $fromSubDepartment) {
            if ($shop instanceof Shop) {
                $department = $this->upsertDepartment($shop, $fromSubDepartment->parent);
                $this->upsertSubDepartment($department, $fromSubDepartment);
            } else {
                $department = $this->upsertMasterDepartment($shop, $fromSubDepartment->parent);
                $this->upsertMasterSubDepartment($department, $fromSubDepartment);
            }
        }
    }

    public function attachFamily(MasterProductCategory|ProductCategory $parent, MasterProductCategory|ProductCategory $family): void
    {
        if ($parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
            AttachFamiliesToSubDepartment::make()->action(
                $parent,
                [
                    'families_id' => [
                        $family->id
                    ]
                ]
            );
        } elseif ($parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
            AttachFamiliesToDepartment::make()->action(
                $parent,
                [
                    'families' => [
                        $family->id
                    ]
                ]
            );
        } elseif ($parent->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
            AttachMasterFamiliesToMasterDepartment::make()->action(
                $parent,
                [
                    'master_families' => [
                        $family->id
                    ]
                ]
            );
        } elseif ($parent->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
            AttachMasterFamiliesToMasterSubDepartment::make()->action(
                $parent,
                [
                    'master_families' => [
                        $family->id
                    ]
                ]
            );
        }
    }


    public function getDepartments(MasterShop|Shop $shop): array|Collection
    {
        if ($shop instanceof Shop) {
            $departments = $shop->departments();
        } else {
            $departments = $shop->getMasterDepartments();
        }

        return $departments;
    }

    public function getSubDepartments(MasterShop|Shop $shop): array|Collection
    {
        if ($shop instanceof Shop) {
            $subDepartments = $shop->subDepartments();
        } else {
            $subDepartments = $shop->getMasterSubDepartments();
        }

        return $subDepartments;
    }

    public function getCommandSignature(): string
    {
        return 'catalogue:clone {from_type} {from} {to_type} {to} {--delete-missing : Delete categories not found in source shop}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        if ($command->argument('to_type') == 'shop') {
            $toShop = Shop::where('slug', $command->argument('to'))->firstOrFail();
        } else {
            $toShop = MasterShop::where('slug', $command->argument('to'))->firstOrFail();
        }


        if ($command->argument('from_type') == 'shop') {
            $fromShop = Shop::where('slug', $command->argument('from'))->firstOrFail();
        } else {
            $fromShop = MasterShop::where('slug', $command->argument('from'))->firstOrFail();
        }

        $deleteMissing = $command->option('delete-missing');
        $this->handle($fromShop, $toShop, $deleteMissing);

        return 0;
    }


}
