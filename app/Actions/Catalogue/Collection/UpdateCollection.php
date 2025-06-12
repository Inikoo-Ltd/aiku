<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 07:51:01 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\Catalogue\Collection\Search\CollectionRecordSearch;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\UI\WithImageCatalogue;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Catalogue\CollectionResource;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Shop;
use App\Models\Inventory\Location;
use App\Models\SysAdmin\Organisation;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateCollection extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithImageCatalogue;

    private Collection $collection;

    public function handle(Collection $collection, array $modelData): Collection
    {
        $imageData = ['image' => Arr::pull($modelData, 'image')];
        if ($imageData['image']) {
            $this->processCatalogueImage($imageData, $collection);
        }
        $collection = $this->update($collection, $modelData, ['data']);
        CollectionRecordSearch::dispatch($collection);

        return $collection;
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
        $rules = [
            'code'        => [
                'sometimes',
                'max:32',
                new AlphaDashDot(),
                new IUnique(
                    table: 'product_categories',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                        ['column' => 'id', 'value' => $this->collection->id, 'operator' => '!=']

                    ]
                ),
            ],
            'name'        => ['sometimes', 'max:250', 'string'],
            'image'       => ['sometimes'],
            'description' => ['sometimes', 'required', 'max:1500'],
        ];
        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;

    }

    public function action(Collection $collection, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Collection
    {
        $this->strict = $strict;
        if (!$audit) {
            Location::disableAuditing();
        }
        $this->asAction   = true;
        $this->collection = $collection;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($collection->shop, $modelData);

        return $this->handle($collection, $this->validatedData);
    }

    public function asController(Organisation $organisation, Shop $shop, Collection $collection, ActionRequest $request): Collection
    {
        $this->collection = $collection;

        $this->initialisationFromShop($shop, $request);

        return $this->handle($collection, $this->validatedData);
    }

    public function jsonResponse(Collection $collection): CollectionResource
    {
        return new CollectionResource($collection);
    }
}
