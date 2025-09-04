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
use App\Models\Masters\MasterShop;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class StoreMasterFamily extends OrgAction
{
    /**
     * @var \App\Models\Masters\MasterProductCategory|\App\Models\Masters\MasterShop
     */
    private MasterShop|MasterProductCategory $parent;

    public function handle(MasterProductCategory|MasterShop $parent, array $modelData): MasterProductCategory
    {
        $shops = Arr::pull($modelData, 'shops', []);

        data_set($modelData, 'type', MasterProductCategoryTypeEnum::FAMILY);

        $masterFamily = StoreMasterProductCategory::run($parent, $modelData);
        StoreFamilyFromMasterFamily::make()->action($masterFamily, [
            'shops' => $shops
        ]);
        
        return $masterFamily;
    }

    public function rules(): array
    {
        return [
            'code'        => [
                'required',
                'max:32',
                new AlphaDashDot(),
                new IUnique(
                    table: 'master_product_categories',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'name'        => ['required', 'max:250', 'string'],
            'description' => ['sometimes', 'required', 'max:1500'],
            'image'       => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
            'shops' => ['sometimes', 'array']
        ];
    }


    public function action(MasterProductCategory $masterDepartment, array $modelData): MasterProductCategory
    {
        $this->asAction = true;
        $this->initialisationFromGroup(group(), $modelData);

        return $this->handle($masterDepartment, $this->validatedData);
    }

    public function asController(MasterShop $masterShop, ActionRequest $request): MasterProductCategory
    {
        $this->parent = $masterShop;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterShop, $this->validatedData);
    }

    public function inMasterDepartment(MasterProductCategory $masterDepartment, ActionRequest $request): MasterProductCategory
    {
        $this->parent = $masterDepartment;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterDepartment, $this->validatedData);
    }

    public function inMasterSubDepartment(MasterProductCategory $masterSubDepartment, ActionRequest $request): MasterProductCategory
    {
        $this->parent = $masterSubDepartment;
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterSubDepartment, $this->validatedData);
    }


    public function htmlResponse(MasterProductCategory $masterProductCategory, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.masters.master_shops.show.master_families.show', [
            'masterShop' => $masterProductCategory->masterShop->slug,
            'masterFamily' => $masterProductCategory->slug,
        ]);

    }


}
