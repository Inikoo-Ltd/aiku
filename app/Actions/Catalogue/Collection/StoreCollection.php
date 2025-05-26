<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\Catalogue\Collection\Search\CollectionRecordSearch;
use App\Actions\Catalogue\CollectionCategory\Hydrators\CollectionCategoryHydrateCollections;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCollections;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateCollections;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateCollections;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\CollectionCategory;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreCollection extends OrgAction
{
    public function handle(Shop|CollectionCategory|ProductCategory $parent, array $modelData): Collection
    {

        if ($parent instanceof CollectionCategory) {
            $shop = $parent->shop;
        } elseif ($parent instanceof ProductCategory) {
            $shop = $parent->shop;
        } else {
            $shop = $parent;
        }

        data_set($modelData, 'shop_id', $shop->id);
        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);
        data_set($modelData, 'parent_type', class_basename($parent));
        data_set($modelData, 'parent_id', $parent->id);

        /** @var Collection $collection */
        $collection = $parent->collections()->create($modelData);

        $collection->stats()->create();
        $collection->salesIntervals()->create();
        $collection->orderingStats()->create();

        CollectionRecordSearch::dispatch($collection);
        OrganisationHydrateCollections::dispatch($collection->organisation)->delay($this->hydratorsDelay);
        GroupHydrateCollections::dispatch($collection->group)->delay($this->hydratorsDelay);
        ShopHydrateCollections::dispatch($collection->shop)->delay($this->hydratorsDelay);


        if ($parent instanceof CollectionCategory) {
            CollectionCategoryHydrateCollections::dispatch($parent)->delay($this->hydratorsDelay);
        }

        return $collection;
    }

    public function rules(): array
    {
        return [
            'code'                 => [
                'required',
                'max:32',
                new AlphaDashDot(),
                new IUnique(
                    table: 'collection_categories',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'name'                 => ['required', 'max:250', 'string'],
            'image_id'             => ['sometimes', 'required', Rule::exists('media', 'id')->where('group_id', $this->organisation->group_id)],
            'description'          => ['sometimes', 'required', 'max:1500'],
            ];
    }

    public function action(Shop|CollectionCategory $parent, array $modelData, int $hydratorsDelay = 0): Collection
    {
        if ($parent instanceof CollectionCategory) {
            $shop = $parent->shop;
        } else {
            $shop = $parent;
        }

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($parent, $this->validatedData);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($shop, $this->validatedData);
    }

    public function htmlResponse(Collection $collection, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.catalogue.collections.show', [
            'organisation'       => $collection->organisation->slug,
            'shop'               => $collection->shop->slug,
            'collection'         => $collection->slug,
        ]);




    }


}
