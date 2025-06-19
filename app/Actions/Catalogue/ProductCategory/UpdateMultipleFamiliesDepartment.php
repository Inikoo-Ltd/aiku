<?php

/*
 * author Arya Permana - Kirin
 * created on 19-06-2025-16h-07m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateMultipleFamiliesDepartment extends OrgAction
{
    use WithActionUpdate;
    use WithCatalogueEditAuthorisation;

    public function handle(ProductCategory $department, array $modelData): void
    {
        foreach ($modelData['families'] as $familyId) {
            $family = ProductCategory::find($familyId);
            UpdateFamilyDepartment::make()->action($family, [
                'department_id' => $department->id
            ]);
        }
    }


    public function rules(): array
    {
        return [
            'families' => ['required', 'array'],
            'families.*' => [
                'required',
                Rule::exists('product_categories', 'id')->where(function ($query) {
                    $query->where('shop_id', $this->shop->id);
                }),
            ],
        ];
    }

    public function asController(ProductCategory $department, ActionRequest $request): void
    {
        $this->initialisationFromShop($department->shop, $request);
        $this->handle($department, $this->validatedData);
    }
}
