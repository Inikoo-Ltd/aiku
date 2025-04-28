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
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Goods\StockFamily;
use App\Models\SysAdmin\Group;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreStockFamily extends OrgAction
{
    use WithNoStrictRules;
    use WithGoodsEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Group $group, $modelData): StockFamily
    {
        $stockFamily = DB::transaction(function () use ($group, $modelData) {
            /** @var StockFamily $stockFamily */
            $stockFamily = $group->stockFamilies()->create($modelData);
            $stockFamily->stats()->create();
            $stockFamily->intervals()->create();
            $stockFamily->salesIntervals()->create();
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $stockFamily->timeSeries()->create(['frequency' => $frequency]);
            }

            return $stockFamily;
        });

        GroupHydrateStockFamilies::dispatch($this->group);
        StockFamilyRecordSearch::dispatch($stockFamily);
        $stockFamily->refresh();

        return $stockFamily;
    }

    public function rules(): array
    {
        $rules = [
            'code' => [
                'required',
                'alpha_dash',
                'max:32',
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'stock_families',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                    ]
                ),
            ],
            'name' => ['required', 'required', 'string', 'max:255'],
        ];
        if (!$this->strict) {
            $rules['source_slug'] = ['sometimes', 'nullable', 'string'];
            $rules                = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Group $group, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): StockFamily
    {
        if (!$audit) {
            StockFamily::disableAuditing();
        }

        $this->asAction = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($group, $modelData);

        return $this->handle($group, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): StockFamily
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group(), $this->validatedData);
    }


    public function htmlResponse(StockFamily $stockFamily): RedirectResponse
    {
        return Redirect::route('grp.goods.stock-families.show', [
            'stockFamily' => $stockFamily->slug
        ]);
    }
}
