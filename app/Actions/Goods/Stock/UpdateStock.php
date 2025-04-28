<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock;

use App\Actions\Goods\Stock\Hydrators\StockHydrateUniversalSearch;
use App\Actions\Goods\StockFamily\Hydrators\StockFamilyHydrateStocks;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateStocks;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Http\Resources\Inventory\OrgStockResource;
use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\Inventory\OrgStockFamily;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateStock extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    private StockFamily $stockFamily;

    private Stock $stock;

    public function handle(Stock $stock, array $modelData): Stock
    {
        $stock   = $this->update($stock, $modelData, ['data', 'settings']);
        $changes = Arr::except($stock->getChanges(), ['updated_at', 'last_fetched_at']);

        if (Arr::hasAny($changes, ['code', 'name', 'stock_family_id', 'unit_value', 'state']) && $stock->state != StockStateEnum::IN_PROCESS) {

            foreach ($stock->orgStocks as $orgStock) {
                $orgStock->update(
                    [
                        'code'       => $stock->code,
                        'name'       => $stock->name,
                        'unit_value' => $stock->unit_value,
                        'state'      => match ($stock->state) {
                            StockStateEnum::ACTIVE        => OrgStockStateEnum::ACTIVE,
                            StockStateEnum::DISCONTINUING => OrgStockStateEnum::DISCONTINUING,
                            StockStateEnum::DISCONTINUED  => OrgStockStateEnum::DISCONTINUED,
                            StockStateEnum::SUSPENDED     => OrgStockStateEnum::SUSPENDED,
                        }
                    ]
                );
            }

        }

        if (Arr::has($changes, 'stock_family_id')) {
            foreach ($stock->orgStocks as $orgStock) {
                $orgStockFamily = OrgStockFamily::where('stock_family_id', $stock->stock_family_id)
                    ->where('organisation_id', $orgStock->organisation_id)
                    ->first();

                if ($orgStockFamily) {
                    $orgStock->update(
                        [
                            'org_stock_family_id' => $orgStockFamily->id

                        ]
                    );
                }
            }
        }

        if (Arr::has($changes, 'state')) {
            GroupHydrateStocks::dispatch($stock->group)->delay($this->hydratorsDelay);


            if ($stock->stockFamily) {
                StockFamilyHydrateStocks::dispatch($stock->stockFamily)->delay($this->hydratorsDelay);
            }
        }


        if (count($changes) > 0) {
            StockHydrateUniversalSearch::dispatch($stock);
        }


        $stock->refresh();

        return $stock;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("goods.{$this->group->id}.view");
    }

    public function rules(): array
    {
        $rules = [
            'code'            => [
                'sometimes',
                'required',
                new AlphaDashDot(),
                'max:64',
                Rule::notIn(['export', 'create', 'upload', 'in-process', 'active', 'discontinuing', 'discontinued']),
                new IUnique(
                    table: 'stocks',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->group->id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->stock->id
                        ],

                    ]
                ),
            ],
            'name'            => ['sometimes', 'required', 'string', 'max:255'],
            'stock_family_id' => ['sometimes', 'nullable', 'exists:stock_families,id'],
            'state'           => ['sometimes', 'required', Rule::enum(StockStateEnum::class)],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }


    public function action(Stock $stock, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Stock
    {
        $this->strict = $strict;
        if (!$audit) {
            Stock::disableAuditing();
        }
        $this->asAction = true;
        $this->stock    = $stock;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($stock->group, $modelData);

        return $this->handle($stock, $this->validatedData);
    }

    public function asController(Stock $stock, ActionRequest $request): Stock
    {
        $this->stock = $stock;
        $this->initialisationFromGroup($stock->group, $request);

        return $this->handle($stock, $this->validatedData);
    }


    public function jsonResponse(Stock $stock): OrgStockResource
    {
        return new OrgStockResource($stock);
    }
}
