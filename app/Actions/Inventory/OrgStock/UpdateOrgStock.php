<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 11:10:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity;
use App\Actions\Goods\Stock\Hydrators\StockHydrateStateFromOrgStocks;
use App\Actions\Goods\Stock\RepairStocksSkoBarcodes;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateOrgStocks;
use App\Actions\Goods\TradeUnit\SetTradeUnitStatus;
use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateOrgStocks;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateLowStockAudits;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateOrgStocksWithoutProducts;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydrateReplenishments;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrgStocks;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgStocks;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrgStock extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithOrgStockConsumables;

    private OrgStock $orgStock;

    public function handle(OrgStock $orgStock, array $modelData): OrgStock
    {
        if (Arr::exists($modelData, 'consumables') && is_string($modelData['consumables'])) {
            $modelData['consumables'] = $this->parseConsumables($modelData['consumables']) ?: null;
        }

        if (Arr::exists($modelData, 'barcode')) {
            $modelData['barcode']             = blank($modelData['barcode']) ? null : trim($modelData['barcode']);
            $modelData['independent_barcode'] = $modelData['barcode'] !== null;

            if ($stock = $orgStock->stock) {
                $stock->update(['barcode' => $modelData['barcode']]);

                /*
                 * Saved one by one rather than in a single mass update: a mass update writes no
                 * model events, so the organisations that had their scanning barcode changed for
                 * them would carry no history of it. There is one sibling per organisation, so
                 * the loop is a handful of rows.
                 *
                 * The organisation being edited is left out of the cascade: its own copy is the
                 * row this action is already writing, and org_stocks is unique on (organisation,
                 * barcode), so handing the same code to a duplicate org stock sitting in that
                 * same organisation would be rejected by the database.
                 */
                $siblings = RepairStocksSkoBarcodes::orgStocksToCarryBarcode($stock, $modelData['barcode'])
                    ->reject(fn (OrgStock $sibling) => $sibling->organisation_id == $orgStock->organisation_id);

                foreach ($siblings as $sibling) {
                    $sibling->update(Arr::only($modelData, ['barcode', 'independent_barcode']));
                }
            }
        }

        if (Arr::exists($modelData, 'unit_barcode')) {
            $modelData['unit_barcode'] = blank($modelData['unit_barcode']) ? null : trim($modelData['unit_barcode']);
        }

        $orgStock = $this->update($orgStock, $modelData, ['data', 'settings']);

        $changes = $orgStock->getChanges();

        if (Arr::has($changes, 'state')) {
            StockHydrateStateFromOrgStocks::dispatch($orgStock->id);
            OrganisationHydrateOrgStocks::dispatch($orgStock->organisation);

            foreach ($orgStock->organisation->warehouses as $warehouse) {
                WarehouseHydrateLowStockAudits::dispatch($warehouse);
                WarehouseHydrateReplenishments::dispatch($warehouse);
                WarehouseHydrateOrgStocksWithoutProducts::dispatch($warehouse);
            }

            foreach ($orgStock->tradeUnits as $tradeUnit) {
                SetTradeUnitStatus::dispatch($tradeUnit);
                TradeUnitsHydrateOrgStocks::dispatch($tradeUnit);
            }


            if ($orgStock->orgStockFamily) {
                OrgStockFamilyHydrateOrgStocks::dispatch($orgStock->orgStockFamily);
            }
        }

        if (Arr::has($changes, 'is_excluded_from_auto_ordering')) {
            OrganisationHydrateOrgStocks::dispatch($orgStock->organisation);
            GroupHydrateOrgStocks::dispatch($orgStock->group);
        }

        if (Arr::hasAny($changes, ['is_on_demand'])) {
            foreach ($orgStock->products as $product) {
                ProductHydrateAvailableQuantity::run($product);
            }
        }


        return $orgStock;
    }

    /**
     * The SKO barcode lives on the stock and cascades to every org stock of that stock, so
     * uniqueness runs group-wide: against other stocks, and against org stocks that do not follow
     * this stock (orphan org stocks with no stock included). Siblings of the same stock are no
     * conflict, they are about to receive the same barcode.
     */
    protected function orgStockBarcodeUniqueRule(): \Illuminate\Validation\Rules\Unique
    {
        $rule = Rule::unique('org_stocks', 'barcode')
            ->where('group_id', $this->orgStock->group_id)
            ->whereNull('deleted_at');

        if ($this->orgStock->stock_id) {
            return $rule->where(
                fn ($query) => $query->whereNull('stock_id')->orWhere('stock_id', '!=', $this->orgStock->stock_id)
            );
        }

        return $rule->ignore($this->orgStock->id);
    }

    public function rules(): array
    {
        $rules = [
            'state'        => ['sometimes', Rule::enum(OrgStockStateEnum::class)],
            'is_on_demand' => ['sometimes', 'boolean'],
            'is_excluded_from_auto_ordering' => ['sometimes', 'boolean'],
            'estimated_lead_time_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
            'name'         => ['sometimes', 'string', 'max:255'],
            'packed_in'    => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'barcode'      => [
                'sometimes',
                'nullable',
                'string',
                'max:54',
                'regex:/^[\x20-\x7E]+$/',
                Rule::unique('stocks', 'barcode')
                    ->where('group_id', $this->orgStock->group_id)
                    ->whereNull('deleted_at')
                    ->ignore($this->orgStock->stock_id),
                $this->orgStockBarcodeUniqueRule(),
            ],
            'unit_barcode' => ['sometimes', 'nullable', 'string', 'max:64', 'regex:/^[\x20-\x7E]+$/'],
            'note_to_pickers' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'note_to_packers' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'consumables'     => [
                'sometimes',
                'nullable',
                function ($attribute, $value, $fail) {
                    if (is_string($value) && $this->parseConsumables($value) === null) {
                        $fail(__('Each line must read like "IAL01 x 1".'));
                    }
                },
            ],

        ];
        if (!$this->strict) {
            $rules['discontinued_in_organisation_at'] = ['sometimes', 'nullable', 'date'];
            $rules['code']                            = ['sometimes', 'string'];
            $rules                                    = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }


    /**
     * disableAuditing() sets a static that nothing here ever cleared, and under Octane the worker
     * outlives the request: one caller asking for no audit switched org stock history off for
     * every request that worker went on to serve. withoutAuditing() puts back whatever the flag
     * was, so a nested caller that had already turned it off still gets what it asked for.
     */
    public function action(OrgStock $orgStock, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): OrgStock
    {
        if (!$audit) {
            return OrgStock::withoutAuditing(fn () => $this->action($orgStock, $modelData, $hydratorsDelay, $strict));
        }

        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->orgStock       = $orgStock;
        $this->strict         = $strict;
        $this->initialisation($orgStock->organisation, $modelData);

        return $this->handle($orgStock, $this->validatedData);
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->orgStock = $orgStock;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($orgStock, $this->validatedData);
    }


}
