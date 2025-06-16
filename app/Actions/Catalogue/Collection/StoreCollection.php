<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection;

use App\Actions\Catalogue\Collection\Search\CollectionRecordSearch;
use App\Actions\Catalogue\ProductCategory\Hydrators\ProductCategoryHydrateCollections;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCollections;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateCollections;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateCollections;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\UI\WithImageCatalogue;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Inventory\Location;
use App\Models\SysAdmin\Organisation;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class StoreCollection extends OrgAction
{
    use WithImageCatalogue;
    use WithNoStrictRules;

    public function handle(Shop|ProductCategory $parent, array $modelData): Collection
    {
        $imageData = ['image' => Arr::pull($modelData, 'image')];
        if ($parent instanceof ProductCategory) {
            $shop = $parent->shop;
        } else {
            $shop = $parent;
        }

        data_set($modelData, 'shop_id', $shop->id);
        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);



        $collection = Collection::create($modelData);

        $collection->stats()->create();
        $collection->salesIntervals()->create();
        $collection->orderingStats()->create();

        if ($imageData['image']) {
            $this->processCatalogueImage($imageData, $collection);
        }

        AttachCollectionToModel::make()->action($parent, $collection);


        CollectionRecordSearch::dispatch($collection);
        OrganisationHydrateCollections::dispatch($collection->organisation)->delay($this->hydratorsDelay);
        GroupHydrateCollections::dispatch($collection->group)->delay($this->hydratorsDelay);
        ShopHydrateCollections::dispatch($collection->shop)->delay($this->hydratorsDelay);


        if ($parent instanceof ProductCategory) {
            ProductCategoryHydrateCollections::dispatch($parent)->delay($this->hydratorsDelay);
        }

        return $collection;
    }

    public function rules(): array
    {
        $rules = [
            'code'        => [
                'required',
                'max:32',
                new AlphaDashDot(),
                new IUnique(
                    table: 'collections',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'name'        => ['required', 'max:250', 'string'],
            'image_id'    => ['sometimes', 'required', Rule::exists('media', 'id')->where('group_id', $this->organisation->group_id)],
            'image'       => [
                'sometimes',
                'nullable',
                File::image()
                    ->max(12 * 1024)
            ],
            'description' => ['sometimes', 'required', 'max:1500'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function action(Shop|ProductCategory $parent, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Collection
    {
        if (!$audit) {
            Location::disableAuditing();
        }

        if ($parent instanceof ProductCategory) {
            $shop = $parent->shop;
        } else {
            $shop = $parent;
        }

        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($parent, $this->validatedData);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function inProductCategory(ProductCategory $productCategory, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle($productCategory, $this->validatedData);
    }

    public function htmlResponse(Collection $collection, ActionRequest $request): RedirectResponse
    {
        if ($collection->parent instanceof ProductCategory) {
            if ($collection->parent->type == ProductCategoryTypeEnum::DEPARTMENT) {
                return Redirect::route('grp.org.shops.show.catalogue.departments.show.collection.show', [
                    'organisation' => $collection->organisation->slug,
                    'shop'         => $collection->shop->slug,
                    'department'   => $collection->parent->slug,
                    'collection'   => $collection->slug,
                ]);
            } elseif ($collection->parent->type == ProductCategoryTypeEnum::SUB_DEPARTMENT) {
                return Redirect::route('grp.org.shops.show.catalogue.departments.show.sub_departments.show.collection.show', [
                    'organisation'  => $collection->organisation->slug,
                    'shop'          => $collection->shop->slug,
                    'department'    => $collection->parent->department->slug,
                    'subDepartment' => $collection->parent->slug,
                    'collection'    => $collection->slug,
                ]);
            } else {
                return Redirect::route('grp.org.shops.show.catalogue.families.show.collection.show', [
                    'organisation' => $collection->organisation->slug,
                    'shop'         => $collection->shop->slug,
                    'family'       => $collection->parent->slug,
                    'collection'   => $collection->slug,
                ]);
            }
        } else {
            return Redirect::route('grp.org.shops.show.catalogue.collections.show', [
                'organisation' => $collection->organisation->slug,
                'shop'         => $collection->shop->slug,
                'collection'   => $collection->slug,
            ]);
        }
    }


}
