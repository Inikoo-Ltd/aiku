<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock;

use App\Actions\Inventory\OrgStock\SyncOrgStockTradeUnits;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Http\Resources\Inventory\OrgStockResource;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStockFamily;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdateStock extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithGoodsEditAuthorisation;


    private Stock $stock;

    private ?int $actingUserId = null;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Stock $stock, array $modelData): Stock
    {
        $hasTradeUnits = Arr::has($modelData, 'trade_units');
        $tradeUnitData = collect(Arr::pull($modelData, 'trade_units'))
            ->mapWithKeys(fn ($tradeUnit) => [$tradeUnit['id'] => ['quantity' => $tradeUnit['quantity']]])
            ->toArray();
        $stockStrategy = Arr::pull($modelData, 'stock_strategy');

        if ($hasTradeUnits && !$this->asAction && !$stockStrategy) {
            $this->ensureStockedWarehousesAreDecided($stock, $tradeUnitData);
        }

        $stock   = $this->update($stock, $modelData, ['data', 'settings']);
        $changes = Arr::except($stock->getChanges(), ['updated_at', 'last_fetched_at']);

        if (Arr::hasAny($changes, ['code', 'name', 'stock_family_id']) && $stock->state != StockStateEnum::IN_PROCESS) {
            foreach ($stock->orgStocks as $orgStock) {
                $orgStock->update(
                    [
                        'code' => $stock->code,
                        'name' => $stock->name,
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

        if ($hasTradeUnits) {
            SyncStockTradeUnits::run($stock, $tradeUnitData, $stockStrategy, $this->actingUserId);
        }

        $stock->refresh();

        return $stock;
    }

    /**
     * The composition cascades to every warehouse, so a pack size change re-means the counts
     * stored in each one holding stock: the editor must say whether to keep or convert them.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function ensureStockedWarehousesAreDecided(Stock $stock, array $tradeUnitData): void
    {
        $stockedOrgStocks = $stock->orgStocks()
            ->whereHas('locationOrgStocks', fn ($query) => $query->where('quantity', '!=', 0))
            ->get()
            ->filter(fn ($orgStock) => SyncOrgStockTradeUnits::pivotChanges($orgStock, $tradeUnitData));

        if ($stockedOrgStocks->isNotEmpty()) {
            throw ValidationException::withMessages([
                'stock_recount_required' => __(
                    ':code holds stock in :count warehouses. Changing its packing re-means those counts: decide whether to keep them (locations get flagged for recount) or convert them to the new packing.',
                    ['code' => $stock->code, 'count' => $stockedOrgStocks->count()]
                ),
            ]);
        }
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
            'trade_units'              => ['sometimes', 'array'],
            'trade_units.*.id'         => ['required', 'exists:trade_units,id'],
            'trade_units.*.quantity'   => ['required', 'numeric', 'gt:0'],
            'stock_strategy'           => ['sometimes', 'nullable', 'in:keep,convert'],
        ];

        if (!$this->strict) {
            $rules                    = $this->noStrictUpdateRules($rules);
            $rules['code']            = ['sometimes', 'string'];
            $rules['activated_at']    = ['sometimes', 'nullable', 'date'];
            $rules['discontinued_at'] = ['sometimes', 'nullable', 'date'];
            $rules['state']           = ['sometimes', Rule::enum(StockStateEnum::class)];
            $rules['source_slug']     = ['sometimes', 'string', 'max:255'];
        }

        return $rules;
    }


    public function action(Stock $stock, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Stock
    {
        $this->strict = $strict;
        if (!$audit) {
            Stock::disableAuditing();
        }
        $this->asAction       = true;
        $this->stock          = $stock;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($stock->group, $modelData);

        return $this->handle($stock, $this->validatedData);
    }

    public function asController(Stock $stock, ActionRequest $request): Stock
    {
        $this->stock        = $stock;
        $this->actingUserId = $request->user()?->id;
        $this->initialisationFromGroup($stock->group, $request);

        return $this->handle($stock, $this->validatedData);
    }


    public function jsonResponse(Stock $stock): OrgStockResource
    {
        return new OrgStockResource($stock);
    }
}
