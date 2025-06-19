<?php
/*
 * author Arya Permana - Kirin
 * created on 19-06-2025-15h-02m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Actions\Traits\Authorisations\WithCatalogueEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\ActionRequest;

class UpdateMultipleProductsFamily extends OrgAction
{
    use WithActionUpdate;
    use WithCatalogueEditAuthorisation;

    public function handle(ProductCategory $family, array $modelData): void
    {
        foreach ($modelData['products'] as $productId) {
            $product = Product::find($productId);
            UpdateProductFamily::make()->action($product, [
                'family_id' => $family->id
            ]);
        }
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
