<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\OrgAction;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class AttachCollectionToModels extends OrgAction
{
    public function handle(Collection $collection, array $modelData)
    {
        $modelTypes = [
            'products'    => Product::class,
            'families'    => ProductCategory::class,
            'departments' => ProductCategory::class,
            'sub_departments' => ProductCategory::class,
            'collections' => Collection::class,
        ];

        foreach ($modelTypes as $key => $modelClass) {
            $ids = Arr::get($modelData, $key, []);

            foreach ($ids as $id) {
                $model = $modelClass::find($id);
                if ($model) {
                    AttachCollectionToModel::make()->action($model, $collection);
                }
            }
        }

        return $collection;
    }

    public function rules(): array
    {
        return [
                'products'      => ['nullable', 'array'],
                'products.*'    => ['exists:products,id'],
                'families'      => ['nullable', 'array'],
                'families.*'    => ['exists:product_categories,id'],
                'departments'   => ['nullable', 'array'],
                'departments.*' => ['exists:product_categories,id'],
                'sub_departments'   => ['nullable', 'array'],
                'sub_departments.*' => ['exists:product_categories,id'],
                'collections'   => ['nullable', 'array'],
                'collections.*' => ['exists:collections,id'],
        ];
    }

    public function action(Collection $collection, $modelData)
    {
        $this->asAction       = true;
        $this->initialisationFromShop($collection->shop, $modelData);

        return $this->handle($collection, $modelData);
    }

    public function asController(Collection $collection, ActionRequest $request)
    {
        $this->initialisationFromShop($collection->shop, $request);
        $this->handle($collection, $this->validatedData);
    }
}
