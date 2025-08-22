<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 06:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterCollection\Search\MasterCollectionRecordSearch;
use App\Actions\Masters\MasterProductCategory\Hydrators\MasterProductCategoryHydrateMasterCollections;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterCollections;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMasterCollections;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\UI\WithImageCatalogue;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class StoreMasterCollection extends GrpAction
{
    use WithImageCatalogue;
    use WithNoStrictRules;

    public function handle(MasterShop|MasterProductCategory $parent, array $modelData): MasterCollection
    {
        $imageData = ['image' => Arr::pull($modelData, 'image')];
        if ($parent instanceof MasterProductCategory) {
            $masterShop = $parent->masterShop;
        } else {
            $masterShop = $parent;
        }

        data_set($modelData, 'group_id', $masterShop->group_id);
        data_set($modelData, 'master_shop_id', $masterShop->id);

        $masterCollection = MasterCollection::create($modelData);

        $masterCollection->stats()->create();
        $masterCollection->salesIntervals()->create();
        $masterCollection->orderingStats()->create();

        if ($imageData['image']) {
            $this->processCatalogueImage($imageData, $masterCollection);
        }

        AttachMasterCollectionToModel::make()->action($parent, $masterCollection);

        MasterCollectionRecordSearch::dispatch($masterCollection);
        GroupHydrateMasterCollections::dispatch($masterCollection->group)->delay($this->hydratorsDelay);
        MasterShopHydrateMasterCollections::dispatch($masterShop)->delay($this->hydratorsDelay);

        if ($parent instanceof MasterProductCategory) {
            MasterProductCategoryHydrateMasterCollections::dispatch($parent)->delay($this->hydratorsDelay);
        }

        return $masterCollection;
    }

    public function rules(): array
    {
        $rules = [
            'code'        => [
                'required',
                'max:32',
                new AlphaDashDot(),
                new IUnique(
                    table: 'master_collections',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'name'        => ['required', 'max:250', 'string'],
            'image_id'    => ['sometimes', 'required', Rule::exists('media', 'id')->where('group_id', $this->group->id)],
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

    public function action(MasterShop|MasterProductCategory $parent, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): MasterCollection
    {
        if (!$audit) {
            MasterCollection::disableAuditing();
        }

        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($parent->group, $modelData);

        return $this->handle($parent, $this->validatedData);
    }

    public function asController(MasterShop $masterShop, ActionRequest $request): MasterCollection
    {
        $this->initialisation($masterShop->group, $request);

        return $this->handle($masterShop, $this->validatedData);
    }

    public function inMasterProductCategory(MasterProductCategory $masterProductCategory, ActionRequest $request): MasterCollection
    {
        $this->initialisation($masterProductCategory->group, $request);

        return $this->handle($masterProductCategory, $this->validatedData);
    }

    public function htmlResponse(MasterCollection $masterCollection, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.masters.master_shops.show.master_collections.index', [
            'masterShop' => $masterCollection->masterShop->slug,
            'masterCollection' => $masterCollection->slug
        ]);
    }
}
