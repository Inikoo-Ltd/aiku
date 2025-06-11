<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class AttachModelsToCollection extends OrgAction
{
    public function handle(Collection $collection, array $modelData): Collection
    {
        foreach (Arr::get($modelData, 'families', []) as $modelID) {
            if (!DB::table('collection_has_models')->where('collection_id', $collection->id)->where('model_type', 'ProductCategory')->where('model_id', $modelID)->exists()) {
                $family = ProductCategory::find($modelID);
                AttachModelToCollection::make()->action($collection, $family);
            }
        }

        foreach (Arr::get($modelData, 'products', []) as $modelID) {
            if (!DB::table('collection_has_models')->where('collection_id', $collection->id)->where('model_type', 'Product')->where('model_id', $modelID)->exists()) {
                $product = Product::find($modelID);
                AttachModelToCollection::make()->action($collection, $product);
            }
        }

        return $collection;
    }

    public function rules(): array
    {
        return [
            'families'   => ['nullable', 'array'],
            'families.*' => [Rule::exists('product_categories', 'id')->where('type', ProductCategoryTypeEnum::FAMILY)->where('shop_id', $this->shop->id)],
            'products'   => ['nullable', 'array'],
            'products.*' => [Rule::exists('products', 'id')->where('shop_id', $this->shop->id)],
        ];
    }

    public function action(Collection $collection, $modelData): Collection
    {
        $this->asAction = true;
        $this->initialisationFromShop($collection->shop, $modelData);

        return $this->handle($collection, $modelData);
    }

    public function asController(Collection $collection, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($collection->shop, $request);

        return $this->handle($collection, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
