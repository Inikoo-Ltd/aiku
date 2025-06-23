<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 26-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\OrgAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreMasterSubDepartment extends OrgAction
{
    public function handle(MasterProductCategory $masterDepartment, array $modelData): MasterProductCategory
    {
        data_set($modelData, 'type', MasterProductCategoryTypeEnum::SUB_DEPARTMENT);

        return StoreMasterProductCategory::run($masterDepartment, $modelData);
    }

    public function rules(): array
    {
        return [
            'code'        => [
                'required',
                'max:32',
                new AlphaDashDot(),
                new IUnique(
                    table: 'product_categories',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'name'        => ['required', 'max:250', 'string'],
            'description' => ['sometimes', 'required', 'max:1500'],

        ];
    }


    public function action(MasterProductCategory $masterDepartment, array $modelData): MasterProductCategory
    {
        $this->asAction = true;
        $this->initialisationFromGroup(group(), $modelData);

        return $this->handle($masterDepartment, $this->validatedData);
    }

    public function asController(MasterProductCategory $masterDepartment, ActionRequest $request): MasterProductCategory
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterDepartment, $this->validatedData);
    }


    public function htmlResponse(MasterProductCategory $masterSubDepartment, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.masters.master_departments.show.master_sub_departments.show', [
            'masterDepartment' => $masterSubDepartment->parent->slug,
            'masterSubDepartment' => $masterSubDepartment->slug,
        ]);
    }


}
