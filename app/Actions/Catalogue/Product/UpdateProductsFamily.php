<?php
/*
 * author Arya Permana - Kirin
 * created on 19-06-2025-15h-02m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\ProductCategory\Hydrators\DepartmentHydrateProducts;
use App\Actions\Catalogue\ProductCategory\Hydrators\FamilyHydrateProducts;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateFamilies;
use App\Actions\Catalogue\ProductCategory\Hydrators\SubDepartmentHydrateProducts;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use Google\Service\Keep\Family;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\Rule;

class UpdateProductsFamily extends OrgAction
{
    use WithActionUpdate;

    public function handle(ProductCategory $family, array $modelData): void
    {
        foreach ($modelData['products'] as $productId) {
            $product = Product::find($productId);
            UpdateProductFamily::make()->action($product, [
                'family_id' => $family->id
            ]);

            if (!$product) {
                continue;
            }
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("products.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        return [
            'products' => ['required', 'array'],
        ];
    }

    public function asController(ProductCategory $family, ActionRequest $request): void
    {
        $this->initialisationFromShop($family->shop, $request);
        $this->handle($family, $this->validatedData);
    }
}
