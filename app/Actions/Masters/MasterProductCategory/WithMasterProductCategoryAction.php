<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Apr 2025 12:17:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Lorisleiva\Actions\ActionRequest;

trait WithMasterProductCategoryAction
{
    use WithMastersEditAuthorisation;
    use WithActionUpdate;
    use WithNoStrictRules;


    private MasterProductCategory $masterProductCategory;
    private MasterShop $masterShop;

    public function prepareForValidation(): void
    {
        if ($this->masterProductCategory->type == ProductCategoryTypeEnum::DEPARTMENT) {
            $this->set('master_department_id', null);
        }

        if($this->has('master_department_or_master_sub_department_id')) {
            $parent = MasterProductCategory::find($this->get('master_department_or_master_sub_department_id'));
            if($parent->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
                $this->set('master_department_id', $parent->id);
                $this->set('master_sub_department_id', null);
            } elseif ($parent->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $this->set('master_sub_department_id', $parent->id);
                $this->set('master_department_id', $parent->masterDepartment->id);
            }
        }
    }


    public function action(MasterProductCategory $masterProductCategory, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): MasterProductCategory
    {
        $this->strict          = $strict;
        if (!$audit) {
            MasterProductCategory::disableAuditing();
        }
        $this->asAction        = true;
        $this->masterProductCategory = $masterProductCategory;
        $this->masterShop = $masterProductCategory->masterShop;
        $this->hydratorsDelay  = $hydratorsDelay;

        $this->initialisationFromGroup($masterProductCategory->group, $modelData);

        return $this->handle($masterProductCategory, $this->validatedData);
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): MasterProductCategory
    {
        $this->asAction        = true;
        $this->masterProductCategory = $masterProductCategory;
        $this->masterShop = $masterProductCategory->masterShop;

        $this->initialisationFromGroup($masterProductCategory->group, $request);

        return $this->handle($masterProductCategory, $this->validatedData);
    }

}
