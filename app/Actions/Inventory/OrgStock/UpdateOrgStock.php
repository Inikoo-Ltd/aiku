<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 11:10:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateAvailableQuantity;
use App\Actions\Goods\Stock\Hydrators\StockHydrateStateFromOrgStocks;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitHydrateStatusFromOrgStocks;
use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitsHydrateOrgStocks;
use App\Actions\Inventory\OrgStock\Search\OrgStockRecordSearch;
use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateOrgStocks;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgStocks;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Inventory\OrgStock;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;

class UpdateOrgStock extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    private OrgStock $orgStock;

    public function handle(OrgStock $orgStock, array $modelData): OrgStock
    {
        $orgStock = $this->update($orgStock, $modelData, ['data', 'settings']);

        $changes = $orgStock->getChanges();

        if (Arr::has($changes, 'state')) {
            StockHydrateStateFromOrgStocks::dispatch($orgStock->id);
            OrganisationHydrateOrgStocks::dispatch($orgStock->organisation);

            foreach ($orgStock->tradeUnits as $tradeUnit) {
                TradeUnitHydrateStatusFromOrgStocks::dispatch($tradeUnit);
                TradeUnitsHydrateOrgStocks::dispatch($tradeUnit);
            }


            if ($orgStock->orgStockFamily) {
                OrgStockFamilyHydrateOrgStocks::dispatch($orgStock->orgStockFamily);
            }
        }

        if (Arr::hasAny($changes, ['code', 'name', 'state'])) {
            OrgStockRecordSearch::dispatch($orgStock);
        }

        if (Arr::hasAny($changes, ['is_on_demand'])) {
            foreach ($orgStock->products as $product) {
                ProductHydrateAvailableQuantity::run($product);
            }
        }


        return $orgStock;
    }

    public function rules(): array
    {
        $rules = [
            'state'        => ['sometimes', Rule::enum(OrgStockStateEnum::class)],
            'unit_cost'    => ['sometimes', 'numeric', 'min:0'],
            'is_on_demand' => ['sometimes', 'boolean'],
            'name'         => ['sometimes', 'string', 'max:255'],
            'packed_in'    => ['sometimes', 'nullable', 'numeric', 'min:0'],

        ];
        if (!$this->strict) {
            $rules['discontinued_in_organisation_at'] = ['sometimes', 'nullable', 'date'];
            $rules                                    = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }


    public function action(OrgStock $orgStock, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): OrgStock
    {
        if (!$audit) {
            OrgStock::disableAuditing();
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
