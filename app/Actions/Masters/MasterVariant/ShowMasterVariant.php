<?php

/*
 * author Louis Perez
 * created on 23-12-2025-13h-27m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterVariant;

use App\Actions\OrgAction;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Models\Masters\MasterVariant;
use Lorisleiva\Actions\ActionRequest;

class ShowMasterVariant extends OrgAction
{
    private MasterProductCategory $parent;

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): MasterProductCategory
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterVariant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterDepartmentInMasterShop(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): MasterProductCategory
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterVariant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartmentInMasterDepartment(MasterShop $masterShop, MasterProductCategory $masterDepartment, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): MasterProductCategory
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterVariant);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inMasterSubDepartment(MasterShop $masterShop, MasterProductCategory $masterSubDepartment, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): MasterProductCategory
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterVariant);
    }

    public function inMasterFamily(MasterShop $masterShop, MasterProductCategory $masterFamily, MasterVariant $masterVariant, ActionRequest $request): MasterProductCategory
    {
        $this->parent = $masterFamily;
        $group        = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($masterVariant);
    }

    /**
     * @throws \Throwable
     */
    public function handle(MasterVariant $masterVariant)
    {
        $masterProductInVariant = MasterAsset::whereIn('id', data_get($masterVariant->data, 'products.*.product.id'))->get();
        dd($masterVariant, $masterProductInVariant);
    }

    /**
     * @throws \Throwable
     */
    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): MasterVariant
    {
        $this->initialisationFromGroup($masterProductCategory->group, $request);

        return $this->handle($masterProductCategory, $this->validatedData);
    }
}
