<?php

/*
 * author Arya Permana - Kirin
 * created on 30-05-2025-13h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\ModelHasContent;

use App\Actions\OrgAction;
use App\Actions\Traits\UI\WithImageCatalogue;
use App\Enums\Web\ModelHasContent\ModelHasContentTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\ModelHasContent;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreModelHasContent extends OrgAction
{
    use WithImageCatalogue;

    public function handle(Product|ProductCategory $parent, array $modelData): ModelHasContent
    {
        $imageData = [];
        if (Arr::exists($modelData, 'image')) {
            $imageData = ['image' => Arr::pull($modelData, 'image')]; //TODO: image handling
        }

        $modelHasContent = $parent->contents()->create($modelData);

        if ($imageData['image']) {
            $this->processCatalogue($imageData, $modelHasContent);
        }
        return $modelHasContent;
    }

    public function rules(): array
    {
        return [
            'type'         => ['required', Rule::enum(ModelHasContentTypeEnum::class)],
            'title'        => ['required', 'string'],
            'text'         => ['required', 'string'],
            'image'        => ['nullable', 'image', 'mimes:jpg,png,jpeg', 'max:10240'],
            'position'     => ['required']
        ];
    }

    public function inProduct(Product $product, ActionRequest $request): void
    {
        $this->initialisationFromShop($product->shop, $request);
        $this->handle($product, $this->validatedData);
    }

    public function inProductCategory(ProductCategory $productCategory, ActionRequest $request): void
    {
        $this->initialisationFromShop($productCategory->shop, $request);
        $this->handle($productCategory, $this->validatedData);
    }
}
