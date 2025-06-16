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
use App\Http\Resources\Web\ModelHasContentsResource;
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

        $position = Arr::get($modelData, 'position', $parent->contents()->max('position') + 1);
        $contents = $parent->contents()->orderBy('position')->get();

        if (!$contents->isEmpty() && $position) {
            $positions = [];
            foreach ($contents as $content) {
                if ($content->position >= $position) {
                    $positions[$content->id] = ['position' => $content->position + 1];
                } else {
                    $positions[$content->id] = ['position' => $content->position];
                }
            }

            ReorderModelHasContent::make()->action($parent, ['positions' => $positions]);
        }

        $modelHasContent = $parent->contents()->create($modelData);

        if (Arr::exists($imageData, 'image')) {
            $this->processCatalogueImage($imageData, $modelHasContent);
        }
        $modelHasContent->refresh();

        return $modelHasContent;
    }

    public function rules(): array
    {
        return [
            'type'         => ['required', Rule::enum(ModelHasContentTypeEnum::class)],
            'title'        => ['required', 'string'],
            'text'         => ['required', 'string'],
            'image'        => ['nullable', 'image', 'mimes:jpg,png,jpeg', 'max:10240'],
            'position'     => ['sometimes']
        ];
    }

    public function inProduct(Product $product, ActionRequest $request): ModelHasContent
    {
        $this->initialisationFromShop($product->shop, $request);
        return $this->handle($product, $this->validatedData);
    }

    public function inProductCategory(ProductCategory $productCategory, ActionRequest $request): ModelHasContent
    {
        $this->initialisationFromShop($productCategory->shop, $request);
        return $this->handle($productCategory, $this->validatedData);
    }

    public function jsonResponse(ModelHasContent $modelHasContent)
    {
        return ModelHasContentsResource::make($modelHasContent);
    }
}
