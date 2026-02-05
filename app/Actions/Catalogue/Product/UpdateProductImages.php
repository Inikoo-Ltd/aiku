<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-15h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithImageUpdate;
use App\Models\Catalogue\Product;
use Lorisleiva\Actions\ActionRequest;

class UpdateProductImages extends OrgAction
{
    use WithActionUpdate;
    use WithImageUpdate;

    public function handle(Product $product, array $modelData): Product
    {
        $this->updateModelImages($product, $modelData);

        data_set($modelData, 'bucket_images', true);

        $this->update($product, $modelData);

        UpdateProductWebImages::run($product);


        return $product;
    }


    public function rules(): array
    {
        return $this->imageUpdateRules();
    }


    public function asController(Product $product, ActionRequest $request): void
    {
        $this->initialisationFromShop($product->shop, $request);

        $this->handle($product, $this->validatedData);
    }
}
