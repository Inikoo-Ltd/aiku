<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\StockFamily;

use App\Actions\Goods\StockFamily\Search\StockFamilyRecordSearch;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateStockFamilies;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Goods\StockFamily\StockFamilyStateEnum;
use App\Http\Resources\Inventory\OrgStockFamiliesResource;
use App\Models\Goods\StockFamily;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateStockFamily extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithGoodsEditAuthorisation;

    private StockFamily $stockFamily;


    public function handle(StockFamily $stockFamily, array $modelData): StockFamily
    {
        $stockFamily = $this->update($stockFamily, $modelData, ['data']);
        $changes     = $stockFamily->getChanges();
        if ($stockFamily->orgStockFamilies && Arr::hasAny($changes, ['code', 'name'])) {
            /** @var StockFamily $stockFamily */
            foreach ($stockFamily->orgStockFamilies as $orgStockFamily) {
                $orgStockFamily->update(
                    [
                        'code' => $stockFamily->code,
                        'name' => $stockFamily->name,
                    ]
                );
            }
        }

        if (Arr::hasAny($stockFamily->getChanges(), ['state'])) {
            GroupHydrateStockFamilies::dispatch($stockFamily->group)->delay($this->hydratorsDelay);
        }
        StockFamilyRecordSearch::dispatch($stockFamily);


        return $stockFamily;
    }

    public function rules(): array
    {
        $rules = [
            'code'  => [
                'sometimes',
                'required',
                'alpha_dash',
                'max:32',
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'stock_families',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->stockFamily->id
                        ],

                    ]
                ),
            ],
            'name'  => ['sometimes', 'required', 'string', 'max:255'],
            'state' => ['sometimes', 'required', Rule::enum(StockFamilyStateEnum::class)],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(StockFamily $stockFamily, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): StockFamily
    {
        $this->strict = $strict;
        if (!$audit) {
            StockFamily::disableAuditing();
        }
        $this->asAction       = true;
        $this->stockFamily    = $stockFamily;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($stockFamily->group, $modelData);

        return $this->handle($stockFamily, $this->validatedData);
    }

    public function asController(StockFamily $stockFamily, ActionRequest $request): StockFamily
    {
        $this->stockFamily = $stockFamily;
        $this->initialisationFromGroup($stockFamily->group, $request);

        return $this->handle($stockFamily, $this->validatedData);
    }

    public function jsonResponse(StockFamily $stockFamily): OrgStockFamiliesResource
    {
        return new OrgStockFamiliesResource($stockFamily);
    }
}
