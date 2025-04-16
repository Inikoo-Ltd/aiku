<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Dec 2024 21:46:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\MasterProductCategory;

use App\Actions\GrpAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Goods\MasterProductCategory;
use App\Models\Goods\MasterShop;
use App\Models\Inventory\Location;
use Lorisleiva\Actions\ActionRequest;

class UploadImageMasterProductCategory extends GrpAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    private MasterProductCategory $masterProductCategory;

    private MasterShop $masterShop;

    public function handle(MasterProductCategory $masterProductCategory, array $modelData): MasterProductCategory
    {
        return $masterProductCategory;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return false;
    }


    public function rules(): array
    {
        $rules = [
            'name'        => ['sometimes', 'max:250', 'string'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(MasterProductCategory $masterProductCategory, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): MasterProductCategory
    {
        $this->strict          = $strict;
        if (!$audit) {
            Location::disableAuditing();
        }
        $this->asAction        = true;
        $this->masterProductCategory = $masterProductCategory;
        $this->masterShop = $masterProductCategory->masterShop;
        $this->hydratorsDelay  = $hydratorsDelay;

        $this->initialisation($masterProductCategory->group, $modelData);

        return $this->handle($masterProductCategory, $this->validatedData);
    }
}
