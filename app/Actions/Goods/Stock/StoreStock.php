<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock;

use App\Actions\Goods\Stock\Hydrators\StockHydrateUniversalSearch;
use App\Actions\Goods\StockFamily\Hydrators\StockFamilyHydrateStocks;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateStocks;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\UI\SupplyChain\StockFamilyTabsEnum;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\SysAdmin\Group;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Arr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreStock extends OrgAction
{
    use WithNoStrictRules;

    /**
     * @throws \Throwable
     */
    public function handle(Group|StockFamily $parent, $modelData): Stock
    {
        data_set($modelData, 'group_id', $this->group->id);

        $tradeUnitData = Arr::pull($modelData, 'trade_unit');
        data_set($tradeUnitData, 'code', $modelData['code']);
        data_set($tradeUnitData, 'name', $modelData['name']);
        $units = Arr::pull($modelData, 'units');

        $stock = DB::transaction(function () use ($parent, $modelData, $tradeUnitData, $units) {
            /** @var Stock $stock */
            $stock = $parent->stocks()->create($modelData);
            $stock->stats()->create();
            $stock->intervals()->create();
            $stock->salesIntervals()->create();
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $stock->timeSeries()->create(['frequency' => $frequency]);
            }


            if ($this->strict) {
                $tradeUnit = StoreTradeUnit::make()->action($this->group, $tradeUnitData);

                SyncStockTradeUnits::run($stock, [
                    $tradeUnit->id => [
                        'quantity' => $units
                    ]
                ]);
            }


            return $stock;
        });
        $stock->refresh();
        GroupHydrateStocks::dispatch($this->group)->delay($this->hydratorsDelay);
        if ($parent instanceof StockFamily) {
            StockFamilyHydrateStocks::dispatch($parent)->delay($this->hydratorsDelay);
        }

        StockHydrateUniversalSearch::dispatch($stock);

        return $stock;
    }

    public function rules(): array
    {
        $rules = [
            'code'  => [
                'required',
                'max:64',
                new AlphaDashDot(),
                Rule::notIn(['export', 'create', 'upload', 'in-process', 'active', 'discontinuing', 'discontinued']),
                new IUnique(
                    table: 'stocks',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                    ]
                ),
            ],
            'name'  => ['required', 'string', 'max:255'],
            'state' => ['sometimes', Rule::enum(StockStateEnum::class)->only(StockStateEnum::ACTIVE)],

            'units' => ['required', 'integer', 'min:1'],

            'trade_unit'                  => ['required', 'array'],
            'trade_unit.description'      => ['required', 'string', 'max:255'],
            'trade_unit.barcode'          => ['sometimes', 'nullable'],
            'trade_unit.gross_weight'     => ['sometimes', 'nullable', 'numeric'],
            'trade_unit.net_weight'       => ['sometimes', 'nullable', 'numeric'],
            'trade_unit.marketing_weight' => ['sometimes', 'nullable', 'numeric'],
            'trade_unit.dimensions'       => ['sometimes', 'nullable'],
            'trade_unit.type'             => ['sometimes', 'string'],
            'trade_unit.data'             => ['sometimes', 'array'],


        ];

        if (!$this->strict) {
            unset($rules['units']);
            unset($rules['trade_unit']);
            unset($rules['trade_unit.description']);
            unset($rules['trade_unit.barcode']);
            unset($rules['trade_unit.gross_weight']);
            unset($rules['trade_unit.net_weight']);
            unset($rules['trade_unit.marketing_weight']);
            unset($rules['trade_unit.dimensions']);
            unset($rules['trade_unit.type']);
            unset($rules['trade_unit.data']);


            $rules['state']       = ['sometimes', 'nullable', Rule::enum(StockStateEnum::class)];
            $rules['source_slug'] = ['sometimes', 'nullable', 'string'];
            $rules                = $this->noStrictStoreRules($rules);
        }


        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Group|StockFamily $parent, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Stock
    {
        if (!$audit) {
            Stock::disableAuditing();
        }

        if ($parent instanceof Group) {
            $group = $parent;
        } else {
            $group = $parent->group;
        }


        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisationFromGroup($group, $modelData);

        return $this->handle($parent, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function inStockFamily(StockFamily $stockFamily, ActionRequest $request): Stock
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($stockFamily, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): Stock
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group(), $this->validatedData);
    }

    public function htmlResponse(Stock $stock): RedirectResponse
    {
        if (!$stock->stock_family_id) {
            return Redirect::route('grp.goods.stocks.show', [
                $stock->slug
            ]);
        } else {
            return Redirect::route('grp.goods.stock-families.show', [
                $stock->stockFamily->slug,
                'tab' => StockFamilyTabsEnum::STOCKS
            ]);
        }
    }
}
