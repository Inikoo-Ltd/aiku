<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 07:51:01 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterCollection\Search\MasterCollectionRecordSearch;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\UI\WithImageCatalogue;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\MasterCollection\MasterCollectionProductStatusEnum;
use App\Enums\Catalogue\MasterCollection\MasterCollectionStateEnum;
use App\Http\Resources\Masters\MasterCollectionResource;
use App\Models\Masters\MasterCollection;
use App\Models\SysAdmin\Group;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterCollection extends GrpAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithImageCatalogue;

    private MasterCollection $masterCollection;

    public function handle(MasterCollection $masterCollection, array $modelData): MasterCollection
    {
        $imageData = ['image' => Arr::pull($modelData, 'image')];
        if ($imageData['image']) {
            $this->processCatalogueImage($imageData, $masterCollection);
        }
        $masterCollection = $this->update($masterCollection, $modelData, ['data']);
        MasterCollectionRecordSearch::dispatch($masterCollection);

        return $masterCollection;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("sysadmin.grp.{$this->group->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'code'        => [
                'sometimes',
                'max:32',
                new AlphaDashDot(),
                new IUnique(
                    table: 'master_collections',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                        ['column' => 'id', 'value' => $this->masterCollection->id, 'operator' => '!=']

                    ]
                ),
            ],
            'name'        => ['sometimes', 'max:250', 'string'],
            'image'       => ['sometimes'],
            'description' => ['sometimes', 'required', 'max:1500'],
            'description_title' => ['sometimes', 'nullable', 'max:255'],
            'description_extra' => ['sometimes', 'nullable', 'max:65500'],
            'state'       => ['sometimes', Rule::enum(MasterCollectionStateEnum::class)],
            'products_status' => ['sometimes', Rule::enum(MasterCollectionProductStatusEnum::class)],
            'images'      => ['sometimes', 'array'],
        ];
        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;

    }

    public function action(MasterCollection $masterCollection, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): MasterCollection
    {
        $this->strict = $strict;
        if (!$audit) {
            MasterCollection::disableAuditing();
        }
        $this->asAction   = true;
        $this->masterCollection = $masterCollection;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($masterCollection->group, $modelData);

        return $this->handle($masterCollection, $this->validatedData);
    }

    public function asController(Group $group, MasterCollection $masterCollection, ActionRequest $request): MasterCollection
    {
        $this->masterCollection = $masterCollection;

        $this->initialisation($group, $request);

        return $this->handle($masterCollection, $this->validatedData);
    }

    public function jsonResponse(MasterCollection $masterCollection): MasterCollectionResource
    {
        return new MasterCollectionResource($masterCollection);
    }
}